<?php

namespace Drupal\ecommerce\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Displays AI-powered product recommendations for the logged-in user.
 *
 * Recommendations are pre-fetched on login (hook_user_login) and stored in
 * private tempstore. This block reads them without any HTTP call, so page
 * load is never blocked.
 *
 * For anonymous users the block renders nothing.
 *
 * @Block(
 *   id = "recommended_products_block",
 *   admin_label = @Translation("Recommended Products"),
 *   category = @Translation("Ecommerce")
 * )
 */
class RecommendedProductsBlock extends BlockBase {

  /**
   * {@inheritdoc}
   */
  public function build(): array {
    $account = \Drupal::currentUser();

    if ($account->isAnonymous()) {
      return [];
    }

    $uid   = (int) $account->id();
    $store = \Drupal::service('tempstore.private')->get('ecommerce');
    $data  = $store->get('recommendations_' . $uid);

    if (empty($data['products'])) {
      // Tempstore miss (first visit after module enable, or session expired).
      // Fetch live from Python and populate tempstore for subsequent pages.
      $base_url = \Drupal::service('settings')->get('ai_api_base_url', 'http://python-api:8000');
      try {
        $response = \Drupal::httpClient()->post(
          $base_url . '/recommendations/login',
          [
            'json'        => ['user_id' => $uid, 'limit' => 10],
            'timeout'     => 5,
            'http_errors' => FALSE,
          ]
        );
        if ($response->getStatusCode() === 200) {
          $fetched = json_decode($response->getBody()->getContents(), TRUE);
          if (!empty($fetched['products'])) {
            $data = [
              'products'  => $fetched['products'],
              'strategy'  => $fetched['strategy'] ?? 'popular',
              'timestamp' => time(),
            ];
            $store->set('recommendations_' . $uid, $data);
          }
        }
      }
      catch (\Exception $e) {
        // Non-critical — fall through to empty state
      }
    }

    if (empty($data['products'])) {
      return [
        '#markup' => '<p class="ec-no-recommendations">'
          . t('No recommendations available yet.')
          . '</p>',
        '#cache'   => ['max-age' => 0],
      ];
    }

    return [
      '#theme'    => 'ecommerce_recommendations',
      '#products' => $data['products'],
      '#strategy' => $data['strategy'] ?? 'popular',
      '#uid'      => $uid,
      '#attached' => ['library' => ['ecommerce/recommendations']],
      '#cache'    => ['max-age' => 0],
    ];
  }

  /**
   * {@inheritdoc}
   */
  public function getCacheMaxAge(): int {
    // Disable block cache — tempstore already handles staleness.
    return 0;
  }

}
