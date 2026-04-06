(function (Drupal, once) {

  Drupal.behaviors.vectorChatbot = {
    attach: function (context) {

      once('vectorChatbot', context.querySelectorAll('#chatbot-button')).forEach(function (button) {

        const box = document.getElementById("chatbot-box");
        const close = document.getElementById("chatbot-close");
        const send = document.getElementById("chatbot-send");
        const input = document.getElementById("chatbot-input");
        const messages = document.getElementById("chatbot-messages");

        // Open chatbot
        button.addEventListener("click", function () {
          box.style.display = "flex";
        });

        // Close chatbot
        if (close) {
          close.addEventListener("click", function () {
            box.style.display = "none";
          });
        }

        // Send button
        if (send) {
          send.addEventListener("click", sendMessage);
        }

        // Enter key
        if (input) {
          input.addEventListener("keypress", function (e) {
            if (e.key === "Enter") {
              sendMessage();
            }
          });
        }

        function escapeHtml(str) {
          return str
            .replace(/&/g, "&amp;")
            .replace(/</g, "&lt;")
            .replace(/>/g, "&gt;")
            .replace(/"/g, "&quot;");
        }

        // Async function
        async function sendMessage() {

          let message = input.value.trim();

          if (!message) return;

          messages.innerHTML += `<p><b>You:</b> ${escapeHtml(message)}</p>`;

          try {

            const response = await fetch("/chatbot-api", {
              method: "POST",
              headers: {
                "Content-Type": "application/json"
              },
              body: JSON.stringify({
                question: message
              })
            });

            const data = await response.json();
            console.log("API response:", data);

            // Handle spam detection (400 status)
            // data.detail is an object: { error, reason, message }
            if (!response.ok) {
              if (data.detail && data.detail.error === "spam_detected") {
                messages.innerHTML += `<p><b>AI:</b> ⚠️ ${escapeHtml(data.detail.message)} <br><small>(Reason: ${escapeHtml(data.detail.reason)})</small></p>`;
              } else {
                messages.innerHTML += `<p><b>AI:</b> Something went wrong. Please try again.</p>`;
              }
            } else {
              // Normal successful response
              messages.innerHTML += `<p><b>AI:</b> ${escapeHtml(data.answer)}</p>`;
            }

            messages.scrollTop = messages.scrollHeight;

          } catch (error) {
            console.error("Error:", error);
            messages.innerHTML += `<p><b>AI:</b> Error connecting to server</p>`;
          }

          input.value = "";
        }

      });

    }
  };

})(Drupal, once);