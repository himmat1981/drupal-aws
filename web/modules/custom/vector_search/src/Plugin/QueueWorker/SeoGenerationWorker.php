<?php

namespace Drupal\vector_search\Plugin\QueueWorker;

use Drupal\Core\Plugin\ContainerFactoryPluginInterface;
use Drupal\Core\Queue\QueueWorkerBase;
use Drupal\Core\Entity\EntityTypeManagerInterface;
use Drupal\Core\Database\Connection;
use GuzzleHttp\ClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Processes SEO generation queue items.
 *
 * @QueueWorker(
 *   id = "vector_seo_generation",
 *   title = @Translation("Vector SEO Generation"),
 *   cron = {"time" = 30}
 * )
 */
class SeoGenerationWorker extends QueueWorkerBase implements ContainerFactoryPluginInterface {

  const API_URL = 'http://vector-api:8000';

  protected $entityTypeManager;
  protected $httpClient;
  protected $logger;
  protected $database;

  public function __construct(
    array $configuration,
    $plugin_id,
    $plugin_definition,
    EntityTypeManagerInterface $entity_type_manager,
    ClientInterface $http_client,
    LoggerInterface $logger,
    Connection $database
  ) {
    parent::__construct($configuration, $plugin_id, $plugin_definition);
    $this->entityTypeManager = $entity_type_manager;
    $this->httpClient        = $http_client;
    $this->logger            = $logger;
    $this->database          = $database;
  }

  public static function create(
    ContainerInterface $container,
    array $configuration,
    $plugin_id,
    $plugin_definition
  ) {
    return new static(
      $configuration,
      $plugin_id,
      $plugin_definition,
      $container->get('entity_type.manager'),
      $container->get('http_client'),
      $container->get('logger.factory')->get('vector_search'),
      $container->get('database')
    );
  }

  /**
   * Process one queue item.
   *
   * Uses direct DB update instead of $node->save()
   * to avoid lock wait timeout errors.
   */
  public function processItem($data) {
    $node_id = $data['node_id'];
    $title   = $data['title'];
    $content = $data['content'];

    $this->logger->notice(
      'Processing SEO for node @id',
      ['@id' => $node_id]
    );

    // ── Step 1: Call Python API ───────────────────────────────
    $response = $this->httpClient->post(self::API_URL . '/seo/generate', [
      'headers' => [
        'Content-Type' => 'application/json',
        'Accept'       => 'application/json',
      ],
      'json' => [
        'node_id' => $node_id,
        'title'   => $title,
        'content' => $content,
      ],
      'timeout'     => 30,
      'http_errors' => FALSE,
    ]);

    // ── Step 2: Validate response ─────────────────────────────
    $status = $response->getStatusCode();
    if ($status !== 200) {
      // Throw = item stays in queue = retried next cron
      throw new \Exception("SEO API returned HTTP $status for node $node_id");
    }

    $seo = json_decode($response->getBody()->getContents(), TRUE);
    if (empty($seo)) {
      throw new \Exception("Empty SEO response for node $node_id");
    }

    // ── Step 3: Load node to verify it exists ─────────────────
    $node = $this->entityTypeManager
      ->getStorage('node')
      ->load($node_id);

    if (!$node) {
      $this->logger->warning(
        'Node @id not found — skipping SEO save',
        ['@id' => $node_id]
      );
      return;
    }

    $vid      = $node->getRevisionId();
    $bundle   = $node->bundle();
    $langcode = $node->language()->getId();

    // ── Step 4: Direct DB upsert — bypasses entity API ────────
    // This avoids $node->save() which causes lock conflicts
    // because the original node save transaction may still be open

    if (!empty($seo['meta_title']) && $node->hasField('field_seo_title')) {
      $this->upsertField(
        'node__field_seo_title',
        'field_seo_title_value',
        $node_id, $vid, $bundle, $langcode,
        $seo['meta_title']
      );
    }

    if (!empty($seo['meta_desc']) && $node->hasField('field_seo_description')) {
      $this->upsertField(
        'node__field_seo_description',
        'field_seo_description_value',
        $node_id, $vid, $bundle, $langcode,
        $seo['meta_desc']
      );
    }

    if (!empty($seo['keywords']) && $node->hasField('field_seo_keywords')) {
      $this->upsertField(
        'node__field_seo_keywords',
        'field_seo_keywords_value',
        $node_id, $vid, $bundle, $langcode,
        $seo['keywords']
      );
    }

    // Invalidate node cache so fresh SEO data shows immediately
    \Drupal::service('cache_tags.invalidator')->invalidateTags(
      ["node:$node_id"]
    );

    $this->logger->notice(
      'SEO tags saved for node @id — title: @title',
      ['@id' => $node_id, '@title' => $seo['meta_title']]
    );
  }

  /**
   * Insert or update a single field value directly in DB.
   *
   * Why direct DB instead of $node->save():
   * - $node->save() opens a new transaction
   * - Original node save transaction may still be open
   * - Two transactions on same table = lock wait timeout
   * - Direct DB update has no transaction conflict
   */
  private function upsertField(
    string $table,
    string $value_column,
    int $node_id,
    int $vid,
    string $bundle,
    string $langcode,
    string $value
  ) {
    // Check if row already exists for this node
    $exists = $this->database->select($table, 'f')
      ->fields('f', ['entity_id'])
      ->condition('entity_id', $node_id)
      ->condition('langcode', $langcode)
      ->execute()
      ->fetchField();

    if ($exists) {
      // Row exists — just update the value
      $this->database->update($table)
        ->fields([$value_column => $value])
        ->condition('entity_id', $node_id)
        ->condition('langcode', $langcode)
        ->execute();
    }
    else {
      // Row does not exist — insert new row
      $this->database->insert($table)
        ->fields([
          'entity_id'   => $node_id,
          'revision_id' => $vid,
          'bundle'      => $bundle,
          'delta'       => 0,
          'langcode'    => $langcode,
          $value_column => $value,
        ])
        ->execute();
    }
  }

}