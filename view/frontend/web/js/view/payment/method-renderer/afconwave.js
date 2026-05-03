define(
    [
        'Magento_Checkout/js/view/payment/default',
        'mage/url',
        'Magento_Checkout/js/model/full-screen-loader'
    ],
    function (Component, url, fullScreenLoader) {
        'use strict';

        return Component.extend({
            defaults: {
                template: 'AfconWave_Payment/payment/afconwave'
            },

            /**
             * Get payment method title
             */
            getTitle: function () {
                return window.checkoutConfig.payment.afconwave_gateway.title;
            },

            /**
             * Get payment method logo
             */
            getLogoUrl: function () {
                return window.checkoutConfig.payment.afconwave_gateway.logoUrl;
            },

            /**
             * Redirect to AfconWave hosted checkout
             */
            afterPlaceOrder: function () {
                fullScreenLoader.startLoader();
                window.location.replace(url.build('afconwave/payment/redirect'));
            }
        });
    }
);
