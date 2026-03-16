/**
 * PeakGear Climbing Theme - RequireJS Configuration
 *
 * @category  PeakGear
 * @package   PeakGear_Climbing
 */

var config = {
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
