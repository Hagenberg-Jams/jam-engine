/*
* This is the main JavaScript used by Vite to build the app.js file.
*/

// Import Glide.js for sliders
import Glide from '@glidejs/glide';
import '@glidejs/glide/dist/css/glide.core.min.css';
import '@glidejs/glide/dist/css/glide.theme.min.css';

// Import GLightbox for lightbox functionality
import GLightbox from 'glightbox';
import 'glightbox/dist/css/glightbox.css';

// Initialize Glide sliders when DOM is ready
document.addEventListener('DOMContentLoaded', function() {
    // Hero slider
    const heroSlider = document.querySelector('.hero-slider');
    if (heroSlider) {
        // Ensure the slider container is visible
        heroSlider.style.display = 'block';
        const glide = new Glide('.hero-slider', {
            type: 'carousel',
            autoplay: 5000,
            hoverpause: true,
            perView: 1,
            animationDuration: 1000,
            startAt: 0,
        });
        glide.mount();
        // Force update after mount
        setTimeout(() => {
            glide.update();
        }, 100);
    }

    // Sponsors slider
    const sponsorsSlider = document.querySelector('.sponsors-slider');
    if (sponsorsSlider) {
        // Ensure the slider container is visible
        sponsorsSlider.style.display = 'block';
        const glide = new Glide('.sponsors-slider', {
            type: 'carousel',
            autoplay: 3000,
            perView: 3,
            gap: 40,
            breakpoints: {
                1024: {
                    perView: 2
                },
                768: {
                    perView: 1
                }
            },
            startAt: 0,
        });
        glide.mount();
        // Force update after mount
        setTimeout(() => {
            glide.update();
        }, 100);
    }
});

// Initialize GLightbox
document.addEventListener('DOMContentLoaded', function() {
    const lightbox = GLightbox({
        selector: '.glightbox',
        touchNavigation: true,
        loop: true,
        autoplayVideos: true,
        openEffect: 'fade',
        closeEffect: 'fade',
    });
});

// Smooth scroll to top
document.addEventListener('DOMContentLoaded', function() {
    const scrollTopBtn = document.getElementById('scroll-top');
    if (scrollTopBtn) {
        window.addEventListener('scroll', function() {
            if (window.pageYOffset > 300) {
                scrollTopBtn.classList.remove('hidden');
            } else {
                scrollTopBtn.classList.add('hidden');
            }
        });

        scrollTopBtn.addEventListener('click', function() {
            window.scrollTo({
                top: 0,
                behavior: 'smooth'
            });
        });
    }
});

// Mobile menu toggle (Alpine.js is already included in Hyde)
// No additional code needed as Alpine.js handles it

// Note: Alpine.js Collapse is handled via CDN in the scripts layout
// The x-collapse directive will work automatically
