<?php

namespace Drupal\ecommerce\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;

/**
 * Handles recommendation AJAX calls and product interaction tracking.
 */
class RecommendationController extends ControllerBase {

  /**
   * Return current user's recommendations as JSON.
   *
   * First checks tempstore (populated on login). If stale or missing,
   * falls back to a live call to the Python API.
   *
   * GET /ecommerce/recommendations
   */
  public function ajaxRecommendations(Request $request): JsonResponse {
    $uid   = (int) \Drupal::currentUser()->id();
    $store = \Drupal::service('tempstore.private')->get('ecommerce');
    $data  = $store->get('recommendations_' . $uid);

    // Tempstore hit — return immediately
    if (!empty($data['products'])) {
      return new JsonResponse([
        'products' => $data['products'],
        'strategy' => $data['strategy'] ?? 'popular',
        'source'   => 'cache',
      ]);
    }

    // Tempstore miss — fetch live from Python API
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
        $result = json_decode($response->getBody()->getContents(), TRUE);

        // Cache for next request
        if (!empty($result['products'])) {
          $store->set('recommendations_' . $uid, [
            'products'  => $result['products'],
            'strategy'  => $result['strategy'] ?? 'popular',
            'timestamp' => time(),
          ]);
        }

        return new JsonResponse([
          'products' => $result['products'] ?? [],
          'strategy' => $result['strategy'] ?? 'popular',
          'source'   => 'live',
        ]);
      }
    }
    catch (\Exception $e) {
      \Drupal::logger('ecommerce')->warning(
        'AJAX recommendation fetch failed for user @uid: @error',
        ['@uid' => $uid, '@error' => $e->getMessage()]
      );
    }

    return new JsonResponse(['products' => [], 'strategy' => 'popular', 'source' => 'error']);
  }

  /**
   * Record a user-product interaction (view, click, add_to_cart, purchase).
   *
   * POST /ecommerce/product/{product_id}/interact?type=view
   */
  public function recordInteraction(int $product_id, Request $request): JsonResponse {
    $uid              = (int) \Drupal::currentUser()->id();
    $interaction_type = $request->query->get('type', 'view');
    $base_url         = \Drupal::service('settings')->get('ai_api_base_url', 'http://python-api:8000');

    $allowed = ['view', 'click', 'add_to_cart', 'purchase'];
    if (!in_array($interaction_type, $allowed, TRUE)) {
      return new JsonResponse(['error' => 'Invalid interaction type'], 400);
    }

    try {
      \Drupal::httpClient()->post(
        $base_url . '/recommendations/interact',
        [
          'query'       => [
            'user_id'          => $uid,
            'product_id'       => $product_id,
            'interaction_type' => $interaction_type,
          ],
          'timeout'     => 3,
          'http_errors' => FALSE,
        ]
      );

      // Invalidate cached recommendations so next page load gets fresh data
      \Drupal::service('tempstore.private')
        ->get('ecommerce')
        ->delete('recommendations_' . $uid);
    }
    catch (\Exception $e) {
      // Non-critical — don't surface errors for interaction tracking
      \Drupal::logger('ecommerce')->warning(
        'Interaction tracking failed for user @uid / product @pid: @error',
        ['@uid' => $uid, '@pid' => $product_id, '@error' => $e->getMessage()]
      );
    }

    return new JsonResponse(['status' => 'ok']);
  }

}
