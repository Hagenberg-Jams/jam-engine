/*
* This is the main JavaScript used by Vite to build the app.js file.
*/

// Import Swiper.js for sliders
import Swiper from 'swiper';
import { Autoplay } from 'swiper/modules';
// CSS is imported in app.css

// Import GLightbox for lightbox functionality
import GLightbox from 'glightbox';
// CSS is imported in app.css

// Initialize Hero Slider (Vanilla JS)
document.addEventListener('DOMContentLoaded', function() {
    const heroSlides = document.querySelectorAll('.hero-slide');
    if (heroSlides.length > 0) {
        let currentSlide = 0;
        const totalSlides = heroSlides.length;
        
        // Function to show a specific slide
        function showSlide(index) {
            heroSlides.forEach((slide, i) => {
                if (i === index) {
                    slide.classList.add('active');
                } else {
                    slide.classList.remove('active');
                }
            });
        }
        
        // Function to go to next slide
        function nextSlide() {
            currentSlide = (currentSlide + 1) % totalSlides;
            showSlide(currentSlide);
        }
        
        // Initialize: show first slide
        showSlide(0);
        
        // Auto-advance slides every 5 seconds
        setInterval(nextSlide, 5000);
    }

    // Sponsors slider with Swiper
    const sponsorsSlider = document.querySelector('.sponsors-slider');
    if (sponsorsSlider) {
        new Swiper('.sponsors-slider', {
            modules: [Autoplay],
            slidesPerView: 3,
            spaceBetween: 40,
            loop: true,
            centeredSlides: false,
            autoplay: {
                delay: 3000,
                disableOnInteraction: false,
            },
            breakpoints: {
                1024: {
                    slidesPerView: 3,
                    spaceBetween: 40,
                },
                768: {
                    slidesPerView: 2,
                    spaceBetween: 30,
                },
                640: {
                    slidesPerView: 1,
                    spaceBetween: 20,
                },
            },
        });
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

// Smooth scroll to top - show button when navigation is not visible
document.addEventListener('DOMContentLoaded', function() {
    const scrollTopBtn = document.getElementById('scroll-top');
    const mainNavigation = document.getElementById('main-navigation');
    
    if (scrollTopBtn && mainNavigation) {
        function checkNavigationVisibility() {
            const navRect = mainNavigation.getBoundingClientRect();
            // Show button when navigation has scrolled out of view (top of nav is above viewport)
            if (navRect.bottom < 0) {
                scrollTopBtn.classList.remove('hidden');
            } else {
                scrollTopBtn.classList.add('hidden');
            }
        }

        window.addEventListener('scroll', checkNavigationVisibility);
        // Check on initial load
        checkNavigationVisibility();

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
