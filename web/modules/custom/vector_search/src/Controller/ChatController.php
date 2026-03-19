<?php

namespace Drupal\vector_search\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;

class ChatController extends ControllerBase {

  public function chat(Request $request) {

    $data = json_decode($request->getContent(), TRUE);

    $question = $data['question'] ?? '';

    if (!$question) {
      return new JsonResponse(['error' => 'Question required'], 400);
    }

    $client = \Drupal::httpClient();

    $response = $client->post('http://vector-api:8000/chat', [
      'json' => [
        'question' => $question
      ],
      'timeout' => 30
    ]);

    $result = json_decode($response->getBody(), TRUE);

    return new JsonResponse($result);

  }

  public function chatPage() {

    return [
      '#markup' => '
        <div id="chatbox">
          <div id="messages"></div>
          <input type="text" id="question" placeholder="Ask something..." />
          <button id="send-btn">Send</button>
        </div>
      ',
      '#attached' => [
        'library' => [
          'vector_search/chatbot'
        ]
      ]
    ];

  }

}