<?php

namespace Drupal\vector_search\Plugin\Block;

use Drupal\Core\Block\BlockBase;

/**
 * Provides AI Chatbot block.
 *
 * @Block(
 *   id = "vector_chatbot_block",
 *   admin_label = @Translation("Vector Search Chatbot")
 * )
 */
class ChatbotBlock extends BlockBase {

  public function build() {

    return [

      'chatbot_container' => [
        '#type' => 'container',
        '#attributes' => ['id' => 'chatbot-container'],

        'chatbot_box' => [
          '#type' => 'container',
          '#attributes' => ['id' => 'chatbot-box'],

          'header' => [
            '#markup' => '<div id="chatbot-header">AI Assistant <span id="chatbot-close">×</span></div>',
          ],

          'messages' => [
            '#type' => 'container',
            '#attributes' => ['id' => 'chatbot-messages'],
          ],

          'input_area' => [
            '#type' => 'container',
            '#attributes' => ['id' => 'chatbot-input-area'],

            'input' => [
              '#type' => 'textfield',
              '#attributes' => [
                'id' => 'chatbot-input',
                'placeholder' => 'Ask something...',
              ],
            ],

            'send' => [
              '#type' => 'button',
              '#value' => 'Send',
              '#attributes' => [
                'id' => 'chatbot-send',
              ],
            ],
          ],
        ],

        'button' => [
          '#markup' => '<div id="chatbot-button">💬</div>',
        ],

      ],

      '#attached' => [
        'library' => [
          'vector_search/chatbot'
        ]
      ]

    ];

  }

}