define([
    'uiRegistry'
], function (registry) {
    'use strict';

    var CITY_ALIASES = {
        'tp hcm': 'ho chi minh',
        'hcm': 'ho chi minh',
        'sai gon': 'ho chi minh',
        'tphcm': 'ho chi minh',
        'hanoi': 'ha noi',
        'thua thien hue': 'hue',
        'ba ria vung tau': 'vung tau'
    };

    function normalizeVietnamName(value) {
        var normalized = (value || '').toString().trim().toLowerCase();

        if (typeof normalized.normalize === 'function') {
            normalized = normalized.normalize('NFD').replace(/[\u0300-\u036f]/g, '');
        }

        normalized = normalized
            .replace(/đ/g, 'd')
            .replace(/^(thanh\s*pho|tp\.?|tinh)\s+/g, '')
            .replace(/[^a-z0-9\s]/g, ' ')
            .replace(/\s+/g, ' ')
            .trim();

        return CITY_ALIASES[normalized] || normalized;
    }

    function toRegionId(value) {
        var parsed = parseInt(value, 10);

        return !isNaN(parsed) && parsed > 0 ? parsed : null;
    }

    function getCheckoutProviderRegionOptions() {
        var checkoutProvider = registry.get('checkoutProvider');

        if (!checkoutProvider || typeof checkoutProvider.get !== 'function') {
            return [];
        }

        return checkoutProvider.get('dictionaries.region_id') || [];
    }

    function extractVietnamRegions() {
        var checkoutConfig = window.checkoutConfig || {},
            directoryData = checkoutConfig.directoryData || {},
            countryData = directoryData.VN || {},
            rawRegions = countryData.regions || {},
            providerRegions = getCheckoutProviderRegionOptions(),
            regions = [];

        if (Array.isArray(providerRegions) && providerRegions.length) {
            providerRegions.forEach(function (region) {
                if (!region || region.country_id !== 'VN') {
                    return;
                }

                regions.push({
                    id: toRegionId(region.value || region.id || region.region_id),
                    name: region.label || region.title || region.name || '',
                    code: region.code || region.region_code || ''
                });
            });

            regions = regions.filter(function (region) {
                return region.id && region.name;
            });

            if (regions.length) {
                return regions;
            }
        }

        if (Array.isArray(rawRegions)) {
            rawRegions.forEach(function (region) {
                if (!region) {
                    return;
                }

                regions.push({
                    id: toRegionId(region.id || region.region_id || region.value),
                    name: region.name || region.label || '',
                    code: region.code || region.region_code || ''
                });
            });

            return regions.filter(function (region) {
                return region.id && region.name;
            });
        }

        Object.keys(rawRegions).forEach(function (key) {
            var region = rawRegions[key] || {};

            regions.push({
                id: toRegionId(region.id || region.region_id || key),
                name: region.name || region.label || '',
                code: region.code || region.region_code || ''
            });
        });

        return regions.filter(function (region) {
            return region.id && region.name;
        });
    }

    function buildIndex(regions) {
        var byId = {},
            byName = {},
            fallback = null;

        (regions || []).forEach(function (region) {
            var normalizedName,
                normalizedCode;

            if (!region || !region.id || !region.name) {
                return;
            }

            byId[String(region.id)] = region;

            normalizedName = normalizeVietnamName(region.name);
            normalizedCode = normalizeVietnamName(region.code || '');

            if (normalizedName && !byName[normalizedName]) {
                byName[normalizedName] = region;
            }

            if (normalizedCode && !byName[normalizedCode]) {
                byName[normalizedCode] = region;
            }

            if (!fallback || normalizeVietnamName(region.name) === 'ha noi') {
                fallback = region;
            }
        });

        return {
            byId: byId,
            byName: byName,
            fallback: fallback
        };
    }

    function getRegionData() {
        var regions = extractVietnamRegions();

        return {
            regions: regions,
            index: buildIndex(regions)
        };
    }

    function resolveByName(value, index) {
        var normalized = normalizeVietnamName(value);

        return normalized && index.byName[normalized] ? index.byName[normalized] : null;
    }

    function readRegionName(address) {
        if (!address) {
            return '';
        }

        if (address.region && typeof address.region === 'object') {
            return address.region.region || address.region.name || '';
        }

        return address.region || '';
    }

    function readRegionId(address) {
        if (!address) {
            return null;
        }

        return toRegionId(address.regionId || address.region_id ||
            (address.region && address.region.region_id ? address.region.region_id : null));
    }

    function resolveAddressRegion(address) {
        var data = getRegionData(),
            index = data.index,
            regionId = readRegionId(address),
            region;

        if (!address) {
            return null;
        }

        region = resolveByName(address.city || '', index);
        if (region) {
            return region;
        }

        region = resolveByName(readRegionName(address), index);
        if (region) {
            return region;
        }

        if (regionId && index.byId[String(regionId)]) {
            return index.byId[String(regionId)];
        }

        return index.fallback;
    }

    function applyRegionToAddress(address, region) {
        if (!address || !region || !region.id) {
            return;
        }

        address.countryId = 'VN';
        address.country_id = 'VN';
        address.regionId = region.id;
        address.region_id = region.id;

        if (address.region && typeof address.region === 'object') {
            address.region.region = region.name;
            address.region.region_id = region.id;
        } else {
            address.region = region.name;
        }
    }

    function ensureAddressRegion(address) {
        var region = resolveAddressRegion(address);

        applyRegionToAddress(address, region);

        return region;
    }

    return {
        normalizeVietnamName: normalizeVietnamName,
        extractVietnamRegions: extractVietnamRegions,
        getRegionData: getRegionData,
        resolveByName: function (value) {
            return resolveByName(value, getRegionData().index);
        },
        resolveAddressRegion: resolveAddressRegion,
        applyRegionToAddress: applyRegionToAddress,
        ensureAddressRegion: ensureAddressRegion
    };
});
