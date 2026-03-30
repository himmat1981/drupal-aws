<?php

namespace Drupal\vector_search\Plugin\QueueWorker;

use Drupal\Core\Queue\QueueWorkerBase;

/**
 * @QueueWorker(
 *   id = "ai_governance_queue",
 *   title = @Translation("AI Governance Queue"),
 *   cron = {"time" = 60}
 * )
 */
class AIGovernanceQueue extends QueueWorkerBase {

    public function processItem($data) {

        $client = \Drupal::httpClient();
      
        try {
          $response = $client->post('http://ec2-52-66-65-95.ap-south-1.compute.amazonaws.com:8000/ai/governance', [
            'json' => [
              'title' => $data['title'],
              'body'  => $data['content'],
            ],
            'timeout' => 60,
          ]);
      
          $result = json_decode($response->getBody(), TRUE);
      
          $node = \Drupal\node\Entity\Node::load($data['nid']);
          if (!$node) {
            return;
          }
      
          // 🔹 Extract structured data
          $quality = $result['quality'] ?? [];
          $fact    = $result['fact_check'] ?? [];
          $comp    = $result['compliance'] ?? [];
      
          // 🟢 Quality (Score + Issues)
          if (isset($quality['score']) || !empty($quality['issues'])) {
            $quality_text = 'Score: ' . ($quality['score'] ?? '-') . "\n";
      
            if (!empty($quality['issues'])) {
              $quality_text .= implode("\n", $quality['issues']);
            }
      
            $node->set('field_ai_quality', $quality_text);
          }
      
          // 🟢 Fact Check
          if (!empty($fact['incorrect_points'])) {
            $node->set('field_ai_fact_check', implode("\n", $fact['incorrect_points']));
          }
      
          // 🟢 Improved Content
          if (!empty($result['improved_content'])) {
            $node->set('field_ai_improved', $result['improved_content']);
          }
      
          // 🟢 Compliance
          if (isset($comp['compliant'])) {
            $compliance_text = strtoupper($comp['compliant']);
      
            if (!empty($comp['issues'])) {
              $compliance_text .= "\n" . implode("\n", $comp['issues']);
            }
      
            $node->set('field_ai_compliance', $compliance_text);
          }
      
          // ✅ Prevent infinite loop
          $node->setSyncing(TRUE);
          $node->save();
          $node->setSyncing(FALSE);
      
        } catch (\Exception $e) {
          \Drupal::logger('ai_governance')->error(
            'AI Governance failed for node @nid: @error',
            ['@nid' => $data['nid'], '@error' => $e->getMessage()]
          );
        }
      }
}