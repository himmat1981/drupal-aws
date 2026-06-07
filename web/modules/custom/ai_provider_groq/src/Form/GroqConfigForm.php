<?php

declare(strict_types=1);

namespace Drupal\ai_provider_groq\Form;

use Drupal\Core\Form\ConfigFormBase;
use Drupal\Core\Form\FormStateInterface;
use Drupal\ai\AiProviderPluginManager;
use Drupal\key\KeyRepositoryInterface;
use Symfony\Component\DependencyInjection\ContainerInterface;

/**
 * Settings form for the Groq AI provider.
 *
 * Stores:
 *   - api_key  : reference to a Key entity (via the Key module)
 *   - model    : default Groq model to use when none is specified
 */
class GroqConfigForm extends ConfigFormBase {

  const CONFIG_NAME  = 'ai_provider_groq.settings';
  const PROVIDER_ID  = 'groq';

  /**
   * Available Groq models (label => model ID).
   */
  const MODEL_OPTIONS = [
    'llama-3.3-70b-versatile'    => 'LLaMA 3.3 70B Versatile (recommended)',
    'llama-3.1-8b-instant'       => 'LLaMA 3.1 8B Instant (fastest)',
    'llama3-8b-8192'             => 'LLaMA 3 8B – 8192 ctx',
    'llama3-70b-8192'            => 'LLaMA 3 70B – 8192 ctx',
    'mixtral-8x7b-32768'         => 'Mixtral 8x7B – 32768 ctx',
    'gemma2-9b-it'               => 'Gemma 2 9B IT',
  ];

  public function __construct(
    private readonly AiProviderPluginManager $aiProviderManager,
    private readonly KeyRepositoryInterface $keyRepository,
  ) {}

  /**
   * {@inheritdoc}
   */
  public static function create(ContainerInterface $container): static {
    return new static(
      $container->get('ai.provider'),
      $container->get('key.repository'),
    );
  }

  /**
   * {@inheritdoc}
   */
  public function getFormId(): string {
    return 'ai_provider_groq_settings';
  }

  /**
   * {@inheritdoc}
   */
  protected function getEditableConfigNames(): array {
    return [static::CONFIG_NAME];
  }

  /**
   * {@inheritdoc}
   */
  public function buildForm(array $form, FormStateInterface $form_state): array {
    $config = $this->config(static::CONFIG_NAME);

    $form['api_key'] = [
      '#type'          => 'key_select',
      '#title'         => $this->t('Groq API Key'),
      '#description'   => $this->t(
        'Select a Key entity that holds your Groq API key. '
        . 'Create one at <a href="/admin/config/system/keys">admin/config/system/keys</a>. '
        . 'Get your key at <a href="https://console.groq.com/keys" target="_blank">console.groq.com/keys</a>.'
      ),
      '#default_value' => $config->get('api_key'),
      '#required'      => TRUE,
    ];

    $form['model'] = [
      '#type'          => 'select',
      '#title'         => $this->t('Default model'),
      '#description'   => $this->t('The Groq model used when no model is specified explicitly by an operation.'),
      '#options'       => static::MODEL_OPTIONS,
      '#default_value' => $config->get('model') ?: 'llama-3.3-70b-versatile',
      '#required'      => TRUE,
    ];

    return parent::buildForm($form, $form_state);
  }

  /**
   * {@inheritdoc}
   */
  public function validateForm(array &$form, FormStateInterface $form_state): void {
    $key_id = $form_state->getValue('api_key');
    if (empty($key_id)) {
      $form_state->setErrorByName('api_key', $this->t('Please select an API key.'));
      return;
    }

    $key = $this->keyRepository->getKey($key_id);
    $api_key_value = $key ? $key->getKeyValue() : '';

    if (empty($api_key_value)) {
      $form_state->setErrorByName('api_key', $this->t('The selected Key has no value. Make sure the key entity contains the Groq API key string.'));
      return;
    }

    // Quick connectivity check: ask the provider to list models.
    try {
      /** @var \Drupal\ai_provider_groq\Plugin\AiProvider\GroqProvider $provider */
      $provider = $this->aiProviderManager->createInstance(static::PROVIDER_ID);
      $provider->setAuthentication($api_key_value);
      $provider->getConfiguredModels('chat');
    }
    catch (\Exception $e) {
      $form_state->setErrorByName(
        'api_key',
        $this->t('Could not connect to Groq: @msg', ['@msg' => $e->getMessage()])
      );
    }
  }

  /**
   * {@inheritdoc}
   */
  public function submitForm(array &$form, FormStateInterface $form_state): void {
    $this->config(static::CONFIG_NAME)
      ->set('api_key', $form_state->getValue('api_key'))
      ->set('model', $form_state->getValue('model'))
      ->save();

    parent::submitForm($form, $form_state);
  }

}
