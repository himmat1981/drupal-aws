(function (Drupal, once) {

  Drupal.behaviors.vectorChatbot = {
    attach: function (context) {

      once('vectorChatbot', context.querySelectorAll('#chatbot-button')).forEach(function (button) {

        const box      = document.getElementById('chatbot-box');
        const close    = document.getElementById('chatbot-close');
        const send     = document.getElementById('chatbot-send');
        const input    = document.getElementById('chatbot-input');
        const messages = document.getElementById('chatbot-messages');

        // ── Create file input dynamically (avoids ALL Drupal Form API interference) ──
        // Do NOT put <input type="file"> in PHP/Twig — Drupal wraps it in extra
        // markup and strips or rewrites IDs. Creating it here guarantees we own it.
        const fileInput = document.createElement('input');
        fileInput.type    = 'file';
        fileInput.accept  = '.pdf,.docx,.txt,.md';
        fileInput.style.display = 'none';
        document.body.appendChild(fileInput);

        const uploadLabel = document.getElementById('chatbot-upload-label');
        const fileName    = document.getElementById('chatbot-file-name');
        const fileClear   = document.getElementById('chatbot-file-clear');

        // Tracks the document_id returned after a successful upload.
        let activeDocumentId = null;

        // ── Open / close ─────────────────────────────────────
        button.addEventListener('click', function () {
          box.style.display = 'flex';
        });

        if (close) {
          close.addEventListener('click', function () {
            box.style.display = 'none';
          });
        }

        // ── Paperclip click → open file picker ───────────────
        if (uploadLabel) {
          uploadLabel.addEventListener('click', function (e) {
            e.preventDefault();
            e.stopPropagation();
            fileInput.click();
          });

          uploadLabel.addEventListener('keydown', function (e) {
            if (e.key === ' ' || e.key === 'Enter') {
              e.preventDefault();
              fileInput.click();
            }
          });
        }

        // ── File selected → upload immediately ───────────────
        fileInput.addEventListener('change', async function () {
          var file = fileInput.files[0];
          if (!file) return;

          if (fileName) fileName.textContent = file.name;
          if (fileClear) fileClear.style.display = 'inline';

          appendMessage('System', '⏳ Uploading and indexing document…');

          var formData = new FormData();
          formData.append('file', file);

          try {
            var res = await fetch('/chatbot-upload', {
              method: 'POST',
              body: formData,
            });

            var data = await res.json();

            if (!res.ok) {
              var errMsg = (data.detail) || (data.error) || 'Upload failed.';
              appendMessage('System', '❌ ' + escapeHtml(String(errMsg)));
              resetFile();
              return;
            }

            activeDocumentId = data.document_id;
            appendMessage(
              'System',
              '✅ <strong>' + escapeHtml(data.filename) + '</strong> indexed (' +
              data.chunk_count + ' chunks). You can now ask questions about it.'
            );

          } catch (err) {
            console.error('Upload error:', err);
            appendMessage('System', '❌ Error uploading document.');
            resetFile();
          }
        });

        // ── Clear attached file ───────────────────────────────
        if (fileClear) {
          fileClear.addEventListener('click', function () {
            resetFile();
            appendMessage('System', 'Document removed. Answers will come from the site knowledge base.');
          });
        }

        // ── Send on button click or Enter ─────────────────────
        if (send) {
          send.addEventListener('click', sendMessage);
        }

        if (input) {
          input.addEventListener('keypress', function (e) {
            if (e.key === 'Enter') sendMessage();
          });
        }

        // ── Helpers ───────────────────────────────────────────
        function escapeHtml(str) {
          return String(str)
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;');
        }

        function appendMessage(speaker, htmlContent) {
          var p = document.createElement('p');
          p.innerHTML = '<b>' + escapeHtml(speaker) + ':</b> ' + htmlContent;
          messages.appendChild(p);
          messages.scrollTop = messages.scrollHeight;
        }

        function resetFile() {
          activeDocumentId = null;
          fileInput.value  = '';
          if (fileName)  fileName.textContent    = '';
          if (fileClear) fileClear.style.display = 'none';
        }

        // ── Main send ─────────────────────────────────────────
        async function sendMessage() {
          var message = input.value.trim();
          if (!message) return;

          appendMessage('You', escapeHtml(message));
          input.value = '';

          var body = { question: message };
          if (activeDocumentId) {
            body.document_id = activeDocumentId;
          }

          try {
            var response = await fetch('/chatbot-api', {
              method:  'POST',
              headers: { 'Content-Type': 'application/json' },
              body:    JSON.stringify(body),
            });

            var data = await response.json();
            console.log('API response:', data);

            if (!response.ok) {
              if (data.detail && data.detail.error === 'spam_detected') {
                appendMessage(
                  'AI',
                  '⚠️ ' + escapeHtml(data.detail.message) +
                  ' <br><small>(Reason: ' + escapeHtml(data.detail.reason) + ')</small>'
                );
              } else {
                appendMessage('AI', 'Something went wrong. Please try again.');
              }
            } else {
              appendMessage('AI', escapeHtml(data.answer));

              if (data.sources && data.sources.some(function (s) {
                return s.source_type === 'document';
              })) {
                appendMessage('System', '📄 Answer includes content from your uploaded document.');
              }
            }

          } catch (err) {
            console.error('Error:', err);
            appendMessage('AI', 'Error connecting to server.');
          }
        }

      });

    }
  };

})(Drupal, once);