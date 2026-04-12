/**
 * PeakGear Climbing Theme - RequireJS Configuration
 *
 * @category  PeakGear
 * @package   PeakGear_Climbing
 */

var config = {
    config: {
        mixins: {
            'Magento_Checkout/js/view/shipping': {
                'Magento_Checkout/js/view/shipping-mixin': true
            },
            'Magento_Checkout/js/view/summary': {
                'Magento_Checkout/js/view/summary-mixin': true
            },
            'Magento_Checkout/js/view/payment': {
                'Magento_Checkout/js/view/payment-mixin': true
            },
            'Magento_Checkout/js/view/payment/default': {
                'Magento_Checkout/js/view/payment/default-mixin': true
            },
            'Magento_Checkout/js/action/place-order': {
                'Magento_Checkout/js/action/place-order-mixin': true
            },
            'Magento_Checkout/js/action/set-payment-information-extended': {
                'Magento_Checkout/js/action/set-payment-information-extended-mixin': true
            },
            'Magento_Checkout/js/model/resource-url-manager': {
                'Magento_Checkout/js/model/resource-url-manager-mixin': true
            },
            'Magento_Checkout/js/action/redirect-on-success': {
                'Magento_Checkout/js/action/redirect-on-success-mixin': true
            },
            'Magento_SalesRule/js/view/payment/discount': {
                'Magento_SalesRule/js/view/payment/discount-mixin': true
            }
        }
    },
    map: {
        '*': {
            'peakgearHeader': 'PeakGear_Climbing/js/header',
            'peakgearHero': 'PeakGear_Climbing/js/hero-section',
            'peakgearProductList': 'PeakGear_Climbing/js/product-list'
        }
    },
    shim: {
        'PeakGear_Climbing/js/header': {
            deps: ['jquery', 'ko', 'Magento_Customer/js/customer-data']
        }
    }
};
