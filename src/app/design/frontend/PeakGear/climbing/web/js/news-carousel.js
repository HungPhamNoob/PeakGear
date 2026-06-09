define([], function () {
    'use strict';

    return function (config, section) {
        var track = section.querySelector('.home-news-track');
        var prev = section.querySelector('.home-news-nav-prev');
        var next = section.querySelector('.home-news-nav-next');

        if (!track || !prev || !next) {
            return;
        }

        var cards = Array.prototype.slice.call(track.querySelectorAll('.home-news-card'));
        var currentIndex = 0;

        if (!cards.length) {
            return;
        }

        function normalizeIndex(index) {
            return ((index % cards.length) + cards.length) % cards.length;
        }

        function scrollToIndex(index, behavior) {
            var target;

            currentIndex = normalizeIndex(index);
            target = cards[currentIndex];
            if (!target) {
                return;
            }

            track.scrollTo({
                left: target.offsetLeft,
                behavior: behavior || 'smooth'
            });
        }

        function detectCurrentIndex() {
            var scrollLeft = track.scrollLeft;
            var closest = 0;
            var minDiff = Number.POSITIVE_INFINITY;

            cards.forEach(function (card, index) {
                var diff = Math.abs(card.offsetLeft - scrollLeft);

                if (diff < minDiff) {
                    minDiff = diff;
                    closest = index;
                }
            });

            currentIndex = closest;
        }

        prev.addEventListener('click', function () {
            scrollToIndex(currentIndex - 1, 'smooth');
        });
        next.addEventListener('click', function () {
            scrollToIndex(currentIndex + 1, 'smooth');
        });
        track.addEventListener('scroll', function () {
            window.requestAnimationFrame(detectCurrentIndex);
        });
        window.addEventListener('resize', function () {
            scrollToIndex(currentIndex, 'auto');
        });
    };
});
