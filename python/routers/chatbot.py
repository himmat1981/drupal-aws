from fastapi import APIRouter, HTTPException
from langgraph.graph import StateGraph, END
from typing import TypedDict, List, Optional

from models.schemas import ChatRequest, ChatResponse
from services.spam import detect_spam
from services.embeddings import encode
from services.llm import chat
from db.vectors import search_similar, log_spam

router = APIRouter(prefix="/chatbot", tags=["chatbot"])


# ── LangGraph State ───────────────────────────────────────────
class ChatState(TypedDict):
    question:    str
    context:     List[dict]
    answer:      str
    spam_reason: Optional[str]


# ── LangGraph Nodes ───────────────────────────────────────────
def spam_check_node(state: ChatState) -> ChatState:
    reason = detect_spam(state["question"])
    if reason:
        log_spam(state["question"], reason)
    return {**state, "spam_reason": reason}


def retrieve_node(state: ChatState) -> ChatState:
    vec  = encode(state["question"])
    docs = search_similar(vec, k=3)
    return {**state, "context": docs}


def generate_node(state: ChatState) -> ChatState:
    context_text = "\n\n".join(
        f"Title: {d['title']}\n{d['content']}"
        for d in state["context"]
    ) if state["context"] else "No relevant content found."

    messages = [
        {
            "role": "system",
            "content": (
                "You are a helpful assistant for a Drupal website. "
                "Answer questions based on the provided context. "
                "If the context doesn't contain relevant information, say so."
            )
        },
        {
            "role": "user",
            "content": f"Context:\n{context_text}\n\nQuestion: {state['question']}"
        }
    ]
    answer = chat(messages)
    return {**state, "answer": answer}


def route_after_spam_check(state: ChatState) -> str:
    return "end" if state["spam_reason"] else "retrieve"


# ── Build Graph ───────────────────────────────────────────────
def build_graph():
    graph = StateGraph(ChatState)
    graph.add_node("spam_check", spam_check_node)
    graph.add_node("retrieve",   retrieve_node)
    graph.add_node("generate",   generate_node)
    graph.set_entry_point("spam_check")
    graph.add_conditional_edges(
        "spam_check",
        route_after_spam_check,
        {"end": END, "retrieve": "retrieve"}
    )
    graph.add_edge("retrieve", "generate")
    graph.add_edge("generate", END)
    return graph.compile()


rag_graph = build_graph()


# ── Endpoint ──────────────────────────────────────────────────
@router.post("/ask")
async def ask(data: ChatRequest):
    """Answer a question using LangGraph RAG pipeline."""
    try:
        result = rag_graph.invoke({
            "question":    data.question,
            "context":     [],
            "answer":      "",
            "spam_reason": None,
        })

        if result["spam_reason"]:
            raise HTTPException(
                status_code=400,
                detail={
                    "error":   "spam_detected",
                    "reason":  result["spam_reason"],
                    "message": "Your message was flagged as spam. Please rephrase your question."
                }
            )

        return {
            "question": data.question,
            "answer":   result["answer"],
            "sources": [
                {"node_id": d["node_id"], "title": d["title"]}
                for d in result["context"]
            ],
        }
    except HTTPException:
        raise
    except Exception as e:
        raise HTTPException(status_code=500, detail=f"LLM error: {str(e)}")


@router.get("/spam-logs")
async def get_spam_logs():
    """View all blocked spam messages."""
    from db_vectors import get_spam_logs
    try:
        logs = get_spam_logs(limit=100)
        return {"total": len(logs), "logs": logs}
    except Exception as e:
        raise HTTPException(status_code=500, detail=str(e))