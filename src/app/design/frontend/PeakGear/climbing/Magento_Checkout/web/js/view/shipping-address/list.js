/**
 * PeakGear shipping address list behavior.
 */
define([
    'underscore',
    'ko',
    'mageUtils',
    'uiComponent',
    'uiLayout',
    'uiRegistry',
    'Magento_Customer/js/model/address-list',
    'Magento_Checkout/js/action/select-shipping-address',
    'Magento_Checkout/js/model/quote',
    'Magento_Checkout/js/model/shipping-address/form-popup-state',
    'Magento_Checkout/js/checkout-data'
], function (
    _,
    ko,
    utils,
    Component,
    layout,
    registry,
    addressList,
    selectShippingAddress,
    quote,
    formPopUpState,
    checkoutData
) {
    'use strict';

    var defaultRendererTemplate = {
        parent: '${ $.$data.parentName }',
        name: '${ $.$data.name }',
        component: 'Magento_Checkout/js/view/shipping-address/address-renderer/default',
        provider: 'checkoutProvider'
    };

    function toAddressSignature(address) {
        var fields;

        if (!address) {
            return '';
        }

        fields = [
            address.firstname,
            address.middlename,
            address.lastname,
            Array.isArray(address.street) ? address.street.join(' ') : (address.street || ''),
            address.city,
            address.region,
            address.postcode,
            address.telephone,
            address.countryId
        ];

        return fields
            .map(function (value) {
                return (value || '').toString().trim().toLowerCase();
            })
            .join('|');
    }

    return Component.extend({
        defaults: {
            template: 'Magento_Checkout/shipping-address/list',
            visible: true,
            rendererTemplates: []
        },

        initialize: function () {
            this._super()
                .initChildren();

            this.deletedAddressKeys = ko.observableArray([]);
            this.hiddenAddressKeys = ko.observableArray([]);
            this.actionAddress = ko.observable(quote.shippingAddress() || addressList()[0] || null);
            this.canAddAddress = ko.pureComputed(function () {
                return this.getActiveAddressCount() < 2 || this.deletedAddressKeys().length > 0;
            }, this);
            this.canShipHere = ko.pureComputed(function () {
                var address = this.actionAddress(),
                    shippingAddress = quote.shippingAddress(),
                    actionKey = this.getAddressKey(address),
                    shippingKey = this.getAddressKey(shippingAddress);

                return !!address && !this.isAddressDeleted(address) &&
                    (!shippingAddress || shippingKey !== actionKey);
            }, this);
            this.canEditAddress = ko.pureComputed(function () {
                var address = this.actionAddress();

                return !!address && !this.isAddressDeleted(address);
            }, this);
            this.canDeleteAddress = ko.pureComputed(function () {
                return !!this.actionAddress() && !this.isActionAddressDeleted() && this.getActiveAddressCount() > 1;
            }, this);

            this.enforceInitialCardLimit();
            this.normalizeActionAddress();

            addressList.subscribe(function (changes) {
                var self = this;

                changes.forEach(function (change) {
                    var changedAddressKey = self.getAddressKey(change.value),
                        currentActionKey = self.getAddressKey(self.actionAddress());

                    if (change.status === 'added') {
                        if (changedAddressKey) {
                            self.deletedAddressKeys.remove(changedAddressKey);
                            self.hiddenAddressKeys.remove(changedAddressKey);
                        }

                        if (!self.tryReuseDeletedCard(change.value, change.index)) {
                            self.createRendererComponent(change.value, change.index);
                        }

                        self.enforceInitialCardLimit();
                        self.actionAddress(change.value);
                        self.normalizeActionAddress();
                    }

                    if (change.status === 'deleted') {
                        if (changedAddressKey) {
                            self.deletedAddressKeys.remove(changedAddressKey);
                            self.hiddenAddressKeys.remove(changedAddressKey);
                            self.removeRendererComponentByAddressKey(changedAddressKey);
                        }

                        if (currentActionKey && changedAddressKey && currentActionKey === changedAddressKey) {
                            self.actionAddress(quote.shippingAddress() || addressList()[0] || null);
                        }

                        self.enforceInitialCardLimit();
                        self.normalizeActionAddress();
                    }
                });
            }, this, 'arrayChange');

            quote.shippingAddress.subscribe(function (address) {
                if (!this.actionAddress() || this.isAddressHidden(this.actionAddress())) {
                    this.actionAddress(address || addressList()[0] || null);
                }

                this.normalizeActionAddress();
            }, this);

            return this;
        },

        initConfig: function () {
            this._super();
            this.rendererComponents = [];
            return this;
        },

        initChildren: function () {
            _.each(addressList(), this.createRendererComponent, this);
            return this;
        },

        createRendererComponent: function (address, index) {
            var rendererTemplate,
                templateData,
                rendererComponent;

            if (index in this.rendererComponents) {
                this.rendererComponents[index].address(address);
            } else {
                rendererTemplate = address.getType() != undefined && this.rendererTemplates[address.getType()] != undefined ?
                    utils.extend({}, defaultRendererTemplate, this.rendererTemplates[address.getType()]) :
                    defaultRendererTemplate;
                templateData = {
                    parentName: this.name,
                    name: index
                };
                rendererComponent = utils.template(rendererTemplate, templateData);
                utils.extend(rendererComponent, {
                    address: ko.observable(address)
                });
                layout([rendererComponent]);
                this.rendererComponents[index] = rendererComponent;
            }
        },

        setActionAddress: function (address) {
            if (!address || this.isAddressHidden(address)) {
                return;
            }

            this.actionAddress(address);
        },

        isActionAddress: function (address) {
            var current = this.actionAddress(),
                currentKey,
                incomingKey;

            if (!current || !address) {
                return false;
            }

            if (current === address) {
                return true;
            }

            currentKey = this.getAddressKey(current);
            incomingKey = this.getAddressKey(address);

            return currentKey !== '' && currentKey === incomingKey;
        },

        isActionAddressDeleted: function () {
            return this.isAddressDeleted(this.actionAddress());
        },

        normalizeActionAddress: function () {
            var activeAddress;

            if (this.actionAddress() && !this.isAddressHidden(this.actionAddress())) {
                return;
            }

            activeAddress = this.getActiveAddresses()[0] || null;
            this.actionAddress(activeAddress);
        },

        getAddressKey: function (address) {
            var baseKey,
                signature;

            if (!address || typeof address.getKey !== 'function') {
                return '';
            }

            baseKey = (address.getKey() || '').toString();
            signature = toAddressSignature(address);

            return baseKey + '::' + signature;
        },

        isAddressDeleted: function (address) {
            var key = this.getAddressKey(address);

            return key !== '' && this.deletedAddressKeys.indexOf(key) !== -1;
        },
 
        isAddressHidden: function (address) {
            var key = this.getAddressKey(address);

            return key !== '' && this.hiddenAddressKeys.indexOf(key) !== -1;
        },

        isDeletedAddress: function (address) {
            return this.isAddressDeleted(address);
        },

        enforceInitialCardLimit: function () {
            var rendererKeys = this.getRendererAddressKeys(),
                hiddenKeys = rendererKeys.slice(2),
                existingKeys = {};

            rendererKeys.forEach(function (key) {
                existingKeys[key] = true;
            });

            this.hiddenAddressKeys(hiddenKeys);
            this.deletedAddressKeys(this.deletedAddressKeys().filter(function (key) {
                return !!existingKeys[key];
            }));
        },

        getRendererAddressKeys: function () {
            var keys = [];

            _.each(this.rendererComponents, function (rendererComponent) {
                var rendererAddress,
                    key;

                if (!rendererComponent || !rendererComponent.address || typeof rendererComponent.address !== 'function') {
                    return;
                }

                rendererAddress = rendererComponent.address();
                key = this.getAddressKey(rendererAddress);

                if (!key || keys.indexOf(key) !== -1) {
                    return;
                }

                keys.push(key);
            }, this);

            return keys;
        },

        getActiveAddresses: function () {
            var self = this;

            return addressList().filter(function (address) {
                return !self.isAddressDeleted(address) && !self.isAddressHidden(address);
            });
        },

        getActiveAddressCount: function () {
            return this.getActiveAddresses().length;
        },

        getFirstDeletedAddressKey: function () {
            var deletedKeys = this.deletedAddressKeys(),
                i,
                key;

            for (i = 0; i < deletedKeys.length; i += 1) {
                key = deletedKeys[i];

                if (this.findRendererComponentByAddressKey(key)) {
                    return key;
                }

                this.deletedAddressKeys.remove(key);
                this.hiddenAddressKeys.remove(key);
            }

            return '';
        },

        findRendererComponentByAddressKey: function (addressKey) {
            var found = null;

            _.each(this.rendererComponents, function (rendererComponent) {
                var rendererAddress;

                if (found || !rendererComponent || !rendererComponent.address || typeof rendererComponent.address !== 'function') {
                    return;
                }

                rendererAddress = rendererComponent.address();
                if (rendererAddress && this.getAddressKey(rendererAddress) === addressKey) {
                    found = rendererComponent;
                }
            }, this);

            return found;
        },

        tryReuseDeletedCard: function (newAddress, newIndex) {
            var deletedKey,
                deletedRenderer,
                newAddressKey;

            if (!newAddress || typeof newAddress.getKey !== 'function') {
                return false;
            }

            deletedKey = this.getFirstDeletedAddressKey();
            if (!deletedKey) {
                return false;
            }

            deletedRenderer = this.findRendererComponentByAddressKey(deletedKey);
            if (!deletedRenderer || typeof deletedRenderer.address !== 'function') {
                return false;
            }

            newAddressKey = this.getAddressKey(newAddress);
            this.removeRendererComponentByAddressKey(newAddressKey, deletedRenderer);
            deletedRenderer.address(newAddress);
            this.deletedAddressKeys.remove(deletedKey);
            this.hiddenAddressKeys.remove(deletedKey);
            this.hiddenAddressKeys.remove(newAddressKey);

            if (newIndex in this.rendererComponents && this.rendererComponents[newIndex] !== deletedRenderer) {
                this.removeRendererComponent(this.rendererComponents[newIndex], newIndex);
            }

            this.enforceInitialCardLimit();

            return true;
        },

        openAddAddressPopup: function () {
            if (!this.canAddAddress()) {
                return;
            }

            registry.async('checkout.steps.shipping-step.shippingAddress')(function (shippingComponent) {
                if (shippingComponent && shippingComponent.showFormPopUp) {
                    shippingComponent.showFormPopUp();
                }
            });
        },

        shipHereFromToolbar: function () {
            var address = this.actionAddress();

            if (!address || this.isAddressDeleted(address)) {
                return;
            }

            selectShippingAddress(address);
            checkoutData.setSelectedShippingAddress(address.getKey());
        },

        editAddressFromToolbar: function () {
            var address = this.actionAddress();

            if (!address || this.isAddressDeleted(address)) {
                return;
            }

            selectShippingAddress(address);
            checkoutData.setSelectedShippingAddress(address.getKey());
            registry.async('checkout.steps.shipping-step.shippingAddress')(function (shippingComponent) {
                if (shippingComponent && shippingComponent.showFormPopUp) {
                    shippingComponent.showFormPopUp();
                } else {
                    formPopUpState.isVisible(true);
                }
            });
        },

        deleteAddressFromToolbar: function () {
            var address = this.actionAddress(),
                removedAddressKey,
                currentShippingAddress,
                fallbackAddress;

            if (!address || this.isAddressDeleted(address) || this.getActiveAddressCount() <= 1) {
                return;
            }

            removedAddressKey = this.getAddressKey(address);
            currentShippingAddress = quote.shippingAddress();

            if (this.deletedAddressKeys.indexOf(removedAddressKey) === -1) {
                this.deletedAddressKeys.push(removedAddressKey);
            }

            if (currentShippingAddress && this.getAddressKey(currentShippingAddress) === removedAddressKey) {
                fallbackAddress = this.getActiveAddresses()[0] || null;
            } else {
                fallbackAddress = currentShippingAddress || this.getActiveAddresses()[0] || null;
            }

            this.actionAddress(address);

            if (fallbackAddress) {
                selectShippingAddress(fallbackAddress);
                checkoutData.setSelectedShippingAddress(fallbackAddress.getKey());
            } else {
                quote.shippingAddress(null);
                checkoutData.setSelectedShippingAddress(null);
            }

            this.normalizeActionAddress();
        },

        removeRendererComponent: function (rendererComponent, index) {
            if (!rendererComponent) {
                return;
            }

            if (this.elems && typeof this.elems.remove === 'function') {
                this.elems.remove(rendererComponent);
            }

            if (typeof this.removeChild === 'function') {
                this.removeChild(rendererComponent);
            }

            if (typeof rendererComponent.destroy === 'function') {
                rendererComponent.destroy();
            }

            delete this.rendererComponents[index];
        },

        removeRendererComponentByAddressKey: function (addressKey, skipRendererComponent) {
            _.each(this.rendererComponents, function (rendererComponent, index) {
                var rendererAddress;

                if (!rendererComponent || !rendererComponent.address || typeof rendererComponent.address !== 'function') {
                    return;
                }

                if (skipRendererComponent && rendererComponent === skipRendererComponent) {
                    return;
                }

                rendererAddress = rendererComponent.address();
                if (!rendererAddress || this.getAddressKey(rendererAddress) !== addressKey) {
                    return;
                }

                this.removeRendererComponent(rendererComponent, index);
            }, this);
        }
    });
});
