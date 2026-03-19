<?php

namespace Drupal\vector_search\Controller;

use Drupal\Core\Controller\ControllerBase;
use Symfony\Component\HttpFoundation\JsonResponse;

class TestController extends ControllerBase {

  public function test() {

    $client = \Drupal::httpClient();

    $response = $client->post('http://python-api:8000/search', [
      'json' => [
        'question' => 'How to migrate Drupal?'
      ]
    ]);

    $data = json_decode($response->getBody(), TRUE);

    return new JsonResponse($data);
  }
}