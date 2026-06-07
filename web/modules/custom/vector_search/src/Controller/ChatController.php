<?php

namespace Drupal\vector_search\Controller;

use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;
use Drupal\Core\Controller\ControllerBase;

class ChatController extends ControllerBase {

  /**
   * Read the Python API base URL from config — same source as all other workers.
   * Falls back to the hardcoded docker hostname if nothing is configured.
   */
  private function getBaseUrl(): string {
    $url = \Drupal::config('system.site')->get('ai_api_base_url')
      ?? \Drupal::service('settings')->get('ai_api_base_url')
      ?? 'http://python-api:8000';

    return rtrim($url, '/');
  }

  /**
   * Proxy a chat question (with optional document_id) to the Python API.
   */
  public function chat(Request $request) {
    try {
      $data = json_decode($request->getContent(), TRUE);

      $question    = $data['question']    ?? '';
      $document_id = $data['document_id'] ?? NULL;

      if (!$question) {
        return new JsonResponse(['error' => 'Question required'], 400);
      }

      $payload = ['question' => $question];
      if ($document_id) {
        $payload['document_id'] = $document_id;
      }

      $client = \Drupal::httpClient();

      $response = $client->post($this->getBaseUrl() . '/chatbot/ask', [
        'json'        => $payload,
        'timeout'     => 30,
        'http_errors' => FALSE,
      ]);

      $result = json_decode($response->getBody(), TRUE);

      return new JsonResponse($result, $response->getStatusCode());
    }
    catch (\Exception $e) {
      \Drupal::logger('vector_search')->error('chat() error: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
  }

  /**
   * Proxy a file upload to the Python document-upload endpoint.
   */
  public function uploadDocument(Request $request) {
    try {
      $file = $request->files->get('file');

      if (!$file || !$file->isValid()) {
        return new JsonResponse(['error' => 'No valid file uploaded'], 400);
      }

      $bytes    = file_get_contents($file->getPathname());
      $filename = $file->getClientOriginalName();
      $mime     = $file->getClientMimeType() ?: 'application/octet-stream';

      if ($bytes === FALSE || strlen($bytes) === 0) {
        return new JsonResponse(['error' => 'Could not read uploaded file'], 400);
      }

      $client = \Drupal::httpClient();

      $response = $client->post($this->getBaseUrl() . '/documents/upload', [
        'multipart' => [
          [
            'name'     => 'file',
            'contents' => $bytes,
            'filename' => $filename,
            'headers'  => ['Content-Type' => $mime],
          ],
        ],
        'timeout'     => 60,
        'http_errors' => FALSE,
      ]);

      $body   = (string) $response->getBody();
      $result = json_decode($body, TRUE);

      if ($result === NULL) {
        \Drupal::logger('vector_search')->error('uploadDocument: non-JSON from Python: @body', ['@body' => $body]);
        return new JsonResponse(['error' => 'Python API returned an unexpected response', 'raw' => substr($body, 0, 300)], 502);
      }

      return new JsonResponse($result, $response->getStatusCode());
    }
    catch (\Exception $e) {
      \Drupal::logger('vector_search')->error('uploadDocument() error: @msg', ['@msg' => $e->getMessage()]);
      return new JsonResponse(['error' => $e->getMessage()], 500);
    }
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
        'library' => ['vector_search/chatbot'],
      ],
    ];
  }

}