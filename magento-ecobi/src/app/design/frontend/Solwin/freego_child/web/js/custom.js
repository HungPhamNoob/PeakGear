define(['jquery'], function ($) {
  'use strict';

  return function () {
    $(document).ready(function () {
      let animationTimeout;

      const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
          const cards = document.querySelectorAll('.product-item');
          const btns = document.querySelectorAll('.product-home-btn');

          if (entry.isIntersecting) {
            // Clear any existing timeouts
            clearTimeout(animationTimeout);

            // Show cards sequentially
            cards.forEach((card, index) => {
              setTimeout(() => {
                card.classList.add('show');
              }, index * 300);
            });

            btns.forEach((btn, index) => {
              setTimeout(() => {
                btn.classList.add('show');
              }, index * 300);
            });
          }
        });
      }, {
        threshold: 0.3 // Trigger when 30% of the section is visible
      });
      const productGrid = document.querySelector('.products-grid.grid')

      // Observe the products section
      if (productGrid) observer.observe(productGrid);
    });
  }
});