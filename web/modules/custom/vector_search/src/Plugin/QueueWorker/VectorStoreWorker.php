<?php

namespace Drupal\vector_search\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * @QueueWorker(
 *   id = "vector_store_queue",
 *   title = @Translation("Vector Store Queue"),
 *   cron = {"time" = 60}
 * )
 */
class VectorStoreWorker extends QueueWorkerBase {

  public function processItem($data) {
    $nid = $data['nid'] ?? 'unknown';

    $base_url = \Drupal::config('system.site')->get('ai_api_base_url')
      ?? \Drupal::service('settings')->get('ai_api_base_url');

    $client = \Drupal::httpClient();

    try {
      $response = $client->post($base_url . '/nodes/store', [
        'headers' => [
          'Content-Type' => 'application/json',
          'Accept'       => 'application/json',
        ],
        'json' => [
          'node_id' => (int) $data['nid'],
          'title'   => (string) $data['title'],
          'content' => $data['content'],
        ],
        'timeout'     => 30,
        'http_errors' => FALSE,
      ]);

      \Drupal::logger('vector_search')->notice(
        'Vector stored for node @nid: @status',
        ['@nid' => $nid, '@status' => $response->getStatusCode()]
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('vector_search')->error(
        'Vector store failed for node @nid: @error',
        ['@nid' => $nid, '@error' => $e->getMessage()]
      );
      throw $e;
    }
  }

}
