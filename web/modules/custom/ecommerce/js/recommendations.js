/**
 * @file
 * Ecommerce recommendations — interaction tracking.
 *
 * Tracks product views (visible cards) and add-to-cart/view-product clicks,
 * then POSTs to /ecommerce/product/{id}/interact so the Python recommendation
 * engine can improve future personalization.
 */

(function ($, Drupal, once) {
  'use strict';

  Drupal.behaviors.ecRecommendations = {
    attach(context) {

      // ── Track product views ──────────────────────────────────────────────
      // Any product card rendered in the recommendations block counts as a view.
      once('ec-view-track', '.ec-product-card[data-product-id]', context)
        .forEach((el) => {
          const productId = el.dataset.productId;
          if (productId) {
            navigator.sendBeacon(
              `/ecommerce/product/${productId}/interact?type=view`
            );
          }
        });

      // ── Track "View Product" / add-to-cart clicks ────────────────────────
      once('ec-click-track', '.ec-add-to-cart[data-product-id]', context)
        .forEach((btn) => {
          btn.addEventListener('click', () => {
            const productId = btn.dataset.productId;
            if (productId) {
              navigator.sendBeacon(
                `/ecommerce/product/${productId}/interact?type=click`
              );
            }
          });
        });

    }
  };

})(jQuery, Drupal, once);
