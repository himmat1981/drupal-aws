<?php

namespace Drupal\ecommerce\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * Syncs a Drupal product node to the Python embeddings API.
 *
 * Runs on cron — same pattern as VectorStoreWorker in vector_search module.
 * Non-blocking: the product node save queues the item instantly; this worker
 * handles the actual HTTP call to POST /products/store in the background.
 *
 * @QueueWorker(
 *   id = "product_sync_queue",
 *   title = @Translation("Product Sync Queue"),
 *   cron = {"time" = 30}
 * )
 */
class ProductSyncWorker extends QueueWorkerBase {

  /**
   * {@inheritdoc}
   */
  public function processItem($data): void {
    $base_url = \Drupal::service('settings')->get('ai_api_base_url', 'http://python-api:8000');

    try {
      $response = \Drupal::httpClient()->post($base_url . '/products/store', [
        'json'        => $data,
        'timeout'     => 15,
        'http_errors' => FALSE,
      ]);

      $status = $response->getStatusCode();
      if ($status !== 200) {
        throw new \RuntimeException("Python API returned HTTP $status for product {$data['product_id']}");
      }

      \Drupal::logger('ecommerce')->info(
        'Product @id synced to Python API.',
        ['@id' => $data['product_id']]
      );
    }
    catch (\Exception $e) {
      \Drupal::logger('ecommerce')->error(
        'Product sync failed for product @id: @error',
        ['@id' => $data['product_id'], '@error' => $e->getMessage()]
      );
      // Re-throw so Drupal requeues the item for retry.
      throw $e;
    }
  }

}
