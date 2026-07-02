/**
 * HeroSlider Library
 * An industry-standard, dynamic, and flexible slider wrapper using Swiper.js
 */
class HeroSlider {
    constructor(selector, options = {}) {
        this.selector = selector;
        this.element = document.querySelector(this.selector);
        
        // Parse dynamic nav attributes
        if (this.element) {
            const navStyle = this.element.getAttribute('data-nav-style') || 'default';
            const navPos = this.element.getAttribute('data-nav-position') || 'sides';
            const navTheme = this.element.getAttribute('data-nav-theme') || 'light';
            const navShow = this.element.getAttribute('data-nav-show');
            const paginationShow = this.element.getAttribute('data-pagination-show');
            
            this.element.classList.add(`nav-style-${navStyle}`);
            this.element.classList.add(`nav-pos-${navPos}`);
            this.element.classList.add(`nav-${navTheme}`);
            
            if (navShow === 'false') {
                this.element.classList.add('nav-hidden');
            }
            if (paginationShow === 'false') {
                this.element.classList.add('pagination-hidden');
            }
            
            // Set SVG stroke color dynamically based on theme (handled in CSS via class)
        }

        this.defaultOptions = {
            loop: true,
            effect: 'fade',
            fadeEffect: {
                crossFade: true
            },
            autoplay: {
                delay: 6000,
                disableOnInteraction: false,
            },
            pagination: this.element && this.element.getAttribute('data-pagination-show') === 'false' ? false : {
                el: '.swiper-pagination',
                clickable: true,
            },
            navigation: this.element && this.element.getAttribute('data-nav-show') === 'false' ? false : {
                nextEl: '.swiper-button-next',
                prevEl: '.swiper-button-prev',
            },
            speed: 1000,
            grabCursor: true,
            on: {
                init: function () {
                    // Trigger custom animations on init
                    this.slides.forEach(slide => slide.classList.remove('is-active'));
                    this.slides[this.activeIndex].classList.add('is-active');
                },
                slideChangeTransitionStart: function () {
                    this.slides.forEach(slide => slide.classList.remove('is-active'));
                    this.slides[this.activeIndex].classList.add('is-active');
                }
            }
        };

        this.options = { ...this.defaultOptions, ...options };
        this.init();
    }

    init() {
        if (typeof Swiper === 'undefined') {
            console.error('Swiper.js is not loaded. Please include it before initializing HeroSlider.');
            return;
        }
        
        const element = document.querySelector(this.selector);
        if (!element) {
            console.warn(`HeroSlider: Element ${this.selector} not found.`);
            return;
        }

        // Initialize Swiper
        this.swiper = new Swiper(this.selector, this.options);
    }

    getInstance() {
        return this.swiper;
    }

    static injectStyles() {
        if (document.getElementById('hero-slider-styles')) return;

        const css = `
            /* Slider Navigation Styles */
            .nav-style-minimal .swiper-button-next::after,
            .nav-style-minimal .swiper-button-prev::after,
            .nav-style-rounded .swiper-button-next::after,
            .nav-style-rounded .swiper-button-prev::after,
            .nav-style-square .swiper-button-next::after,
            .nav-style-square .swiper-button-prev::after {
                display: none;
            }

            .nav-style-minimal .swiper-button-next, .nav-style-minimal .swiper-button-prev,
            .nav-style-rounded .swiper-button-next, .nav-style-rounded .swiper-button-prev,
            .nav-style-square .swiper-button-next, .nav-style-square .swiper-button-prev {
                width: 50px;
                height: 50px;
                display: flex;
                align-items: center;
                justify-content: center;
                background-color: transparent;
                transition: all 0.3s ease;
            }

            .nav-style-minimal .swiper-button-next,
            .nav-style-rounded .swiper-button-next,
            .nav-style-square .swiper-button-next {
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M8.25 4.5l7.5 7.5-7.5 7.5' /%3E%3C/svg%3E");
                background-size: 24px;
                background-repeat: no-repeat;
                background-position: center;
            }
            .nav-style-minimal .swiper-button-prev,
            .nav-style-rounded .swiper-button-prev,
            .nav-style-square .swiper-button-prev {
                background-image: url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' fill='none' viewBox='0 0 24 24' stroke-width='2' stroke='currentColor'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M15.75 19.5L8.25 12l7.5-7.5' /%3E%3C/svg%3E");
                background-size: 24px;
                background-repeat: no-repeat;
                background-position: center;
            }

            .nav-style-rounded .swiper-button-next,
            .nav-style-rounded .swiper-button-prev {
                background-color: var(--color-background);
                border-radius: 50%;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
                stroke: var(--color-primary);
            }
            .nav-style-rounded.nav-dark .swiper-button-next,
            .nav-style-rounded.nav-dark .swiper-button-prev {
                background-color: rgba(255,255,255,0.1);
                backdrop-filter: blur(10px);
            }
            .nav-style-rounded.nav-dark .swiper-button-next:hover,
            .nav-style-rounded.nav-dark .swiper-button-prev:hover {
                background-color: var(--color-primary);
            }

            .nav-style-square .swiper-button-next,
            .nav-style-square .swiper-button-prev {
                background-color: var(--color-background);
                border-radius: 12px;
                box-shadow: 0 10px 25px rgba(0,0,0,0.1);
            }
            .nav-style-square.nav-dark .swiper-button-next,
            .nav-style-square.nav-dark .swiper-button-prev {
                background-color: rgba(255,255,255,0.1);
                backdrop-filter: blur(10px);
            }
            .nav-style-square.nav-dark .swiper-button-next:hover,
            .nav-style-square.nav-dark .swiper-button-prev:hover {
                background-color: var(--color-primary);
            }

            .nav-pos-bottom-right .swiper-button-next { top: auto; bottom: 40px; right: 40px; left: auto; }
            .nav-pos-bottom-right .swiper-button-prev { top: auto; bottom: 40px; right: 100px; left: auto; }
            .nav-pos-bottom-left .swiper-button-next { top: auto; bottom: 40px; left: 100px; right: auto; }
            .nav-pos-bottom-left .swiper-button-prev { top: auto; bottom: 40px; left: 40px; right: auto; }

            .nav-hidden .swiper-button-next,
            .nav-hidden .swiper-button-prev { display: none !important; }
            .pagination-hidden .swiper-pagination { display: none !important; }
        `;

        const style = document.createElement('style');
        style.id = 'hero-slider-styles';
        style.textContent = css;
        document.head.appendChild(style);
    }
}

// Inject styles as soon as script loads
HeroSlider.injectStyles();

// Auto-initialize if data-hero-slider is present
document.addEventListener("DOMContentLoaded", () => {
    const sliders = document.querySelectorAll('[data-hero-slider]');
    sliders.forEach((slider, index) => {
        // Add a unique ID if it doesn't have one
        if (!slider.id) slider.id = `hero-slider-${index}`;
        
        // Parse custom options from data-slider-options
        let customOptions = {};
        if (slider.hasAttribute('data-slider-options')) {
            try {
                customOptions = JSON.parse(slider.getAttribute('data-slider-options'));
            } catch (e) {
                console.error('HeroSlider: Invalid JSON in data-slider-options', e);
            }
        }
        
        new HeroSlider(`#${slider.id}`, customOptions);
    });
});
