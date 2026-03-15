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

            // Weather observables
            this.temperature = ko.observable('--°C');
            this.cityName = ko.observable('--');
            this.condition = ko.observable('--');

            // Time observables (VN timezone)
            this.currentTime = ko.observable('--:--');
            this.currentDate = ko.observable('--/--/----');

            this.timezone = this.timezone || 'Asia/Ho_Chi_Minh';
            this.switchIntervalMs = parseInt(this.switchIntervalMs, 10) || 600000; // 10 minutes
            this.weatherRefreshIntervalMs = parseInt(this.weatherRefreshIntervalMs, 10) || 60000; // 1 minute
            this.weatherData = Array.isArray(this.weatherData) ? this.weatherData : [];
            this.weatherRefreshUrl = this.weatherRefreshUrl || '';
            this.currentCityIndex = 0;

            this._normalizeWeatherData();
            this._applyCurrentCityWeather();
            this._startClock();
            this._startCityRotation();
            this._startWeatherRefresh();

            return this;
        },

        /**
         * Normalize weather payload for safer rendering.
         * @private
         */
        _normalizeWeatherData: function () {
            var normalized = [];

            this.weatherData.forEach(function (item) {
                if (!item || typeof item !== 'object') {
                    return;
                }

                normalized.push({
                    city_name: (item.city_name || '').toString() || 'Unknown',
                    temperature: parseFloat(item.temperature) || 0,
                    description: (item.description || '').toString() || 'Không có dữ liệu',
                    icon_code: (item.icon_code || '').toString(),
                    humidity: parseInt(item.humidity, 10) || 0,
                    wind_speed: parseFloat(item.wind_speed) || 0
                });
            });

            this.weatherData = normalized;
        },

        /**
         * Render weather for current rotating city.
         * @private
         */
        _applyCurrentCityWeather: function () {
            if (!this.weatherData.length) {
                this.temperature('--°C');
                this.cityName('--');
                this.condition('Không có dữ liệu thời tiết');
                return;
            }

            if (this.currentCityIndex >= this.weatherData.length) {
                this.currentCityIndex = 0;
            }

            var current = this.weatherData[this.currentCityIndex];
            this.temperature(this._formatTemperature(current.temperature));
            this.cityName(current.city_name);
            this.condition(current.description);
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
         * Rotate city every configured interval.
         * @private
         */
        _startCityRotation: function () {
            var self = this;
            if (!self.weatherData.length) {
                return;
            }

            setInterval(function () {
                self.currentCityIndex = (self.currentCityIndex + 1) % self.weatherData.length;
                self._applyCurrentCityWeather();
            }, self.switchIntervalMs);
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
                    self._normalizeWeatherData();
                    if (self.currentCityIndex >= self.weatherData.length) {
                        self.currentCityIndex = 0;
                    }
                    self._applyCurrentCityWeather();
                }).catch(function () {
                    // Keep current UI data on refresh error.
                });
            };

            // Run once immediately so localhost reflects latest weather without waiting for interval.
            refreshWeather();

            setInterval(function () {
                refreshWeather();
            }, self.weatherRefreshIntervalMs);
        },

        /**
         * Start the clock update interval.
         * @private
         */
        _startClock: function () {
            var self = this;

            self._updateTime();
            setInterval(function () {
                self._updateTime();
            }, 1000);
        },

        /**
         * Update Vietnam date/time observables.
         * @private
         */
        _updateTime: function () {
            var now = new Date();

            try {
                var timeParts = new Intl.DateTimeFormat('vi-VN', {
                    timeZone: this.timezone,
                    hour: '2-digit',
                    minute: '2-digit',
                    second: '2-digit',
                    hour12: false
                }).formatToParts(now);

                var dateParts = new Intl.DateTimeFormat('vi-VN', {
                    timeZone: this.timezone,
                    day: '2-digit',
                    month: '2-digit',
                    year: 'numeric'
                }).formatToParts(now);

                var hour = this._partValue(timeParts, 'hour');
                var minute = this._partValue(timeParts, 'minute');
                var second = this._partValue(timeParts, 'second');
                var day = this._partValue(dateParts, 'day');
                var month = this._partValue(dateParts, 'month');
                var year = this._partValue(dateParts, 'year');

                this.currentTime(hour + ':' + minute + ':' + second);
                this.currentDate(day + '/' + month + '/' + year + ' (GMT+7)');
            } catch (e) {
                var hours = now.getHours().toString().padStart(2, '0');
                var minutes = now.getMinutes().toString().padStart(2, '0');
                var seconds = now.getSeconds().toString().padStart(2, '0');
                var dayLocal = now.getDate().toString().padStart(2, '0');
                var monthLocal = (now.getMonth() + 1).toString().padStart(2, '0');
                var yearLocal = now.getFullYear();

                this.currentTime(hours + ':' + minutes + ':' + seconds);
                this.currentDate(dayLocal + '/' + monthLocal + '/' + yearLocal);
            }
        },

        /**
         * Extract one Intl format part by type.
         * @param {Array} parts
         * @param {string} type
         * @returns {string}
         * @private
         */
        _partValue: function (parts, type) {
            var match = parts.filter(function (item) {
                return item.type === type;
            });

            return match.length ? match[0].value : '';
        }
    });
});
