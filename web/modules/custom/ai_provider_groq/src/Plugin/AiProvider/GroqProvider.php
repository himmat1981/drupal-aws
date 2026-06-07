<?php

declare(strict_types=1);

namespace Drupal\ai_provider_groq\Plugin\AiProvider;

use Drupal\Core\StringTranslation\TranslatableMarkup;
use Drupal\ai\Attribute\AiProvider;
use Drupal\ai\Base\OpenAiBasedProviderClientBase;
use Drupal\ai\Enum\AiModelCapability;
use Drupal\ai\Exception\AiSetupFailureException;
use Drupal\ai\OperationType\Chat\ChatInput;
use Drupal\ai\OperationType\Chat\ChatOutput;
use Drupal\ai\Traits\OperationType\ChatTrait;

/**
 * Plugin implementation of the 'groq' AI provider.
 *
 * Groq exposes an OpenAI-compatible REST API at:
 *   https://api.groq.com/openai/v1
 *
 * This provider extends OpenAiBasedProviderClientBase so the existing
 * openai-php/client handles all HTTP transport — we only override the
 * endpoint and the model catalogue.
 *
 * Configuration is read automatically from ai_provider_groq.settings
 * (the base class resolves <provider_module>.settings via getConfig()).
 */
#[AiProvider(
  id: 'groq',
  label: new TranslatableMarkup('Groq (LLaMA)'),
)]
class GroqProvider extends OpenAiBasedProviderClientBase {

  use ChatTrait;

  /**
   * Groq's OpenAI-compatible base URL.
   */
  const GROQ_API_BASE = 'https://api.groq.com/openai/v1';

  /**
   * Model catalogue — Chat operation only.
   *
   * Keep in sync with https://console.groq.com/docs/models
   */
  const GROQ_CHAT_MODELS = [
    'llama-3.3-70b-versatile'  => 'LLaMA 3.3 70B Versatile',
    'llama-3.1-8b-instant'     => 'LLaMA 3.1 8B Instant',
    'llama3-8b-8192'           => 'LLaMA 3 8B (8192 ctx)',
    'llama3-70b-8192'          => 'LLaMA 3 70B (8192 ctx)',
    'mixtral-8x7b-32768'       => 'Mixtral 8x7B (32768 ctx)',
    'gemma2-9b-it'             => 'Gemma 2 9B IT',
  ];

  // -------------------------------------------------------------------------
  // AiProviderInterface
  // -------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function getSupportedOperationTypes(): array {
    return ['chat'];
  }

  /**
   * {@inheritdoc}
   */
  public function getSupportedCapabilities(): array {
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function getConfiguredModels(?string $operation_type = NULL, array $capabilities = []): array {
    if ($operation_type === NULL || $operation_type === 'chat') {
      return self::GROQ_CHAT_MODELS;
    }
    return [];
  }

  /**
   * {@inheritdoc}
   */
  public function isUsable(?string $operation_type = NULL, array $capabilities = []): bool {
    // Accept a configured Key entity OR the GROQ_API_KEY environment variable.
    if (empty($this->getConfig()->get('api_key')) && empty(getenv('GROQ_API_KEY'))) {
      return FALSE;
    }
    if ($operation_type !== NULL && !in_array($operation_type, $this->getSupportedOperationTypes(), TRUE)) {
      return FALSE;
    }
    return TRUE;
  }

  /**
   * {@inheritdoc}
   *
   * Falls back to the GROQ_API_KEY environment variable when no Key entity
   * is configured in ai_provider_groq.settings — typical for Docker/K8s
   * deployments where secrets are injected as env vars.
   */
  protected function loadApiKey(): string {
    // If a Key entity ID is configured, use the parent (Key module) path.
    if (!empty($this->getConfig()->get('api_key'))) {
      return parent::loadApiKey();
    }

    // Fall back to the environment variable.
    $envKey = getenv('GROQ_API_KEY');
    if (!empty($envKey)) {
      return $envKey;
    }

    throw new AiSetupFailureException(
      'Groq API key is not configured. Set GROQ_API_KEY as an environment variable or configure a Key entity at /admin/config/ai/providers/groq.'
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getModelSettings(string $model_id, array $generalConfig = []): array {
    return $generalConfig;
  }

  // -------------------------------------------------------------------------
  // Client bootstrap — force Groq endpoint before the parent builds the client
  // -------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  protected function loadClient(): void {
    // Always point to Groq's OpenAI-compatible endpoint.
    $this->setEndpoint(self::GROQ_API_BASE);

    // The parent will call loadApiKey() which reads getConfig()->get('api_key')
    // and resolves the Key entity — our config is ai_provider_groq.settings.
    try {
      parent::loadClient();
    }
    catch (AiSetupFailureException $e) {
      throw new AiSetupFailureException(
        'Groq provider not configured. Visit /admin/config/ai/providers/groq — ' . $e->getMessage(),
        $e->getCode(),
        $e,
      );
    }
  }

  // -------------------------------------------------------------------------
  // Chat operation
  // -------------------------------------------------------------------------

  /**
   * {@inheritdoc}
   */
  public function chat(array|string|ChatInput $input, string $model_id, array $tags = []): ChatOutput {
    $this->loadClient();
    return parent::chat($input, $model_id, $tags);
  }

}
