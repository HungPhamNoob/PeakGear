/**
 * PeakGear Climbing Theme - Hero Section Component
 *
 * @category  PeakGear
 * @package   PeakGear_Climbing
 */

define([
    'uiComponent',
    'ko'
], function (Component, ko) {
    'use strict';

    return Component.extend({

        /** @inheritdoc */
        initialize: function () {
            this._super();

            // Weather observables – start empty so the PHP-rendered SSR content
            // shows undisturbed until real values are set below.
            this.temperature = ko.observable('');
            this.cityName = ko.observable('');
            this.condition = ko.observable('');

            // Date observable (no time) – also starts empty; _updateDate fills it.
            this.currentDate = ko.observable('');

            this.timezone = this.timezone || 'Asia/Ho_Chi_Minh';
            this.weatherRefreshIntervalMs = parseInt(this.weatherRefreshIntervalMs, 10) || 300000;
            this.weatherData = Array.isArray(this.weatherData) ? this.weatherData : [];
            this.weatherRefreshUrl = this.weatherRefreshUrl || '';

            this._findHanoiWeather();
            this._updateDate();
            this._startDateUpdate();
            this._startWeatherRefresh();

            return this;
        },

        /**
         * Find Hà Nội weather from the initial data payload.
         * @private
         */
        _findHanoiWeather: function () {
            if (!this.weatherData.length) {
                return;
            }

            var hanoi = null;

            for (var i = 0; i < this.weatherData.length; i++) {
                var item = this.weatherData[i];
                if (!item || typeof item !== 'object') {
                    continue;
                }
                var name = (item.city_name || '').toString().toLowerCase();
                if (name === 'hanoi' || name === 'hà nội' || name === 'ha noi') {
                    hanoi = item;
                    break;
                }
            }

            // Fallback to first city if Hà Nội not found
            if (!hanoi) {
                hanoi = this.weatherData[0];
            }

            if (hanoi) {
                var temp = parseFloat(hanoi.temperature);
                if (Number.isFinite(temp)) {
                    this.temperature(this._formatTemperature(temp));
                }
                // If temp is invalid, leave the observable as-is so
                // the PHP-rendered SSR value stays visible.
                var displayName = hanoi.city_name || 'Hà Nội';
                var normalizedDisplay = displayName.toString().toLowerCase();
                if (normalizedDisplay === 'hanoi' || normalizedDisplay === 'ha noi') {
                    displayName = 'Hà Nội';
                }
                this.cityName(displayName);
                this.condition((hanoi.description || '').toString() || '');
            }
        },

        /**
         * Format temperature for UI.
         * @param {number} value
         * @returns {string}
         * @private
         */
        _formatTemperature: function (value) {
            var rounded = Math.round(value * 10) / 10;
            var text = rounded.toFixed(1).replace(/\.0$/, '');
            return text + '°C';
        },

        /**
         * Refresh weather payload periodically from backend endpoint.
         * @private
         */
        _startWeatherRefresh: function () {
            var self = this;
            if (!self.weatherRefreshUrl || typeof window.fetch !== 'function') {
                return;
            }

            var refreshWeather = function () {
                window.fetch(self.weatherRefreshUrl, {
                    credentials: 'same-origin',
                    headers: {
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                }).then(function (response) {
                    if (!response.ok) {
                        throw new Error('Weather endpoint failed');
                    }
                    return response.json();
                }).then(function (payload) {
                    if (!payload || !Array.isArray(payload.cities) || !payload.cities.length) {
                        return;
                    }

                    self.weatherData = payload.cities;
                    self._findHanoiWeather();
                }).catch(function () {
                    // Keep current UI data on refresh error.
                });
            };

            refreshWeather();

            setInterval(function () {
                refreshWeather();
            }, self.weatherRefreshIntervalMs);
        },

        /**
         * Start the date update interval (once per minute).
         * @private
         */
        _startDateUpdate: function () {
            var self = this;
            setInterval(function () {
                self._updateDate();
            }, 60000);
        },

        /**
         * Update Vietnam date observable (dd/mm/yyyy only, no day name).
         * @private
         */
        _updateDate: function () {
            var now = new Date();

            try {
                var dateParts = new Intl.DateTimeFormat('vi-VN', {
                    timeZone: this.timezone,
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                }).format(now);

                if (dateParts.indexOf(',') !== -1) {
                    dateParts = dateParts.split(',').pop().trim();
                }
                var dateMatch = dateParts.match(/\d{1,2}\/\d{1,2}\/\d{4}/);
                if (dateMatch) {
                    dateParts = dateMatch[0];
                }

                this.currentDate(dateParts);
            } catch (e) {
                var dayLocal = now.getDate().toString().padStart(2, '0');
                var monthLocal = (now.getMonth() + 1).toString().padStart(2, '0');
                var yearLocal = now.getFullYear();

                this.currentDate(dayLocal + '/' + monthLocal + '/' + yearLocal);
            }
        }
    });
});
