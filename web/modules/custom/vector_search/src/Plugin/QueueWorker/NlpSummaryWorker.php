<?php

namespace Drupal\vector_search\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;
use GuzzleHttp\Client;

/**
 * @QueueWorker(
 *   id = "nlp_summary_queue",
 *   title = @Translation("NLP Summary Queue"),
 *   cron = {"time" = 60}
 * )
 */
class NlpSummaryWorker extends QueueWorkerBase {

  public function processItem($data) {
    $nid = $data['nid'] ?? 'unknown';
  
    try {
      \Drupal::logger('vector_search')->notice(
        'processItem started for node @nid',
        ['@nid' => $nid]
      );
      $base_url = \Drupal::config('system.site')->get('ai_api_base_url') 
      ?? \Drupal::service('settings')->get('ai_api_base_url');
      $client   = \Drupal::httpClient();
      $response = $client->post($base_url . '/nlp/summarize', [
        'json' => [
          'node_id' => (int) $nid,        // ← required by your API
          'text'    => $data['content'],
        ],
        'timeout'     => 30,
        'http_errors' => FALSE,
      ]);
  
      $status = $response->getStatusCode();
      \Drupal::logger('vector_search')->notice(
        'API status @status for node @nid',
        ['@status' => $status, '@nid' => $nid]
      );
  
      $body    = json_decode($response->getBody(), TRUE);
      $summary = $body['summary'] ?? '';
  
      \Drupal::logger('vector_search')->notice(
        'Summary received for node @nid: @summary',
        ['@nid' => $nid, '@summary' => substr($summary, 0, 100)]
      );
  
      if (empty($summary)) {
        \Drupal::logger('vector_search')->warning(
          'Empty summary returned for node @nid — full response: @resp',
          ['@nid' => $nid, '@resp' => json_encode($body)]
        );
        return;
      }
  
      // Load node
      $node = \Drupal\node\Entity\Node::load($nid);
      if (!$node) {
        \Drupal::logger('vector_search')->warning(
          'Node @nid not found',
          ['@nid' => $nid]
        );
        return;
      }
  
      // ── Save to Drupal core body summary field ────────────────
      // Drupal core Article has body field with summary sub-field
      // No custom field needed!
      if ($node->hasField('body')) {
        $body_value = $node->get('body')->value;      // keep original body
        $body_format = $node->get('body')->format;    // keep original format
  
        $node->set('body', [
          'value'   => $body_value,    // original content unchanged
          'summary' => $summary,       // ← AI generated summary
          'format'  => $body_format,
        ]);
  
        $node->setSyncing(TRUE);
        $node->save();
        $node->setSyncing(FALSE);
  
        \Drupal::logger('vector_search')->notice(
          'Body summary saved for node @nid: @summary',
          ['@nid' => $nid, '@summary' => substr($summary, 0, 100)]
        );
      }
  
    }
    catch (\Exception $e) {
      \Drupal::logger('vector_search')->error(
        'processItem failed for node @nid: @error',
        ['@nid' => $nid, '@error' => $e->getMessage()]
      );
      throw $e;
    }
  }
}