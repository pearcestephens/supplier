/**
 * Lazy Loading System
 * Defers loading of images until they're in viewport
 *
 * Usage:
 * <img data-src="image.jpg"
 *      src="placeholder.jpg"
 *      class="lazy-load"
 *      alt="Description">
 */

class LazyLoader {
    constructor() {
        this.images = document.querySelectorAll('img.lazy-load, [data-lazy]');
        this.observer = null;
        this.init();
    }

    init() {
        // Check for Intersection Observer support
        if ('IntersectionObserver' in window) {
            this.observer = new IntersectionObserver((entries, observer) => {
                entries.forEach(entry => {
                    if (entry.isIntersecting) {
                        this.loadImage(entry.target);
                        observer.unobserve(entry.target);
                    }
                });
            }, {
                rootMargin: '50px 0px', // Start loading 50px before entering viewport
                threshold: 0.01
            });

            this.images.forEach(img => {
                this.observer.observe(img);
            });
        } else {
            // Fallback for older browsers
            this.images.forEach(img => {
                this.loadImage(img);
            });
        }
    }

    loadImage(img) {
        const src = img.getAttribute('data-src');
        if (!src) return;

        // Add loading class
        img.classList.add('lazy-loading');

        // Create temporary image to preload
        const tempImg = new Image();

        tempImg.onload = () => {
            img.src = src;
            img.classList.remove('lazy-loading', 'lazy-load');
            img.classList.add('lazy-loaded');

            // Fade in effect
            img.style.opacity = '0';
            img.style.transition = 'opacity 0.3s ease-in-out';
            setTimeout(() => {
                img.style.opacity = '1';
            }, 10);

            // Remove data-src to prevent reloading
            img.removeAttribute('data-src');
        };

        tempImg.onerror = () => {
            img.classList.remove('lazy-loading');
            img.classList.add('lazy-error');
            console.error('Failed to load image:', src);
        };

        tempImg.src = src;
    }

    refresh() {
        // Re-scan for new lazy images
        const newImages = document.querySelectorAll('img.lazy-load:not(.lazy-loaded), [data-lazy]:not(.lazy-loaded)');
        if (this.observer) {
            newImages.forEach(img => {
                this.observer.observe(img);
            });
        }
    }
}

// Auto-initialize
let lazyLoader;
document.addEventListener('DOMContentLoaded', function() {
    lazyLoader = new LazyLoader();
});

// Helper function to convert regular images to lazy-loaded
function makeLazyLoadable(selector, placeholder = '/supplier/assets/images/placeholder.png') {
    document.querySelectorAll(selector).forEach(img => {
        if (!img.classList.contains('lazy-load') && img.src && !img.src.includes('data:')) {
            const originalSrc = img.src;
            img.setAttribute('data-src', originalSrc);
            img.src = placeholder;
            img.classList.add('lazy-load');

            if (lazyLoader) {
                lazyLoader.refresh();
            }
        }
    });
}

// CSS for loading states (inject into document)
const lazyLoadStyles = document.createElement('style');
lazyLoadStyles.textContent = `
    .lazy-load {
        background: linear-gradient(90deg, #f0f0f0 25%, #e0e0e0 50%, #f0f0f0 75%);
        background-size: 200% 100%;
        animation: lazy-shimmer 1.5s infinite;
        min-height: 100px;
    }

    @keyframes lazy-shimmer {
        0% { background-position: 200% 0; }
        100% { background-position: -200% 0; }
    }

    .lazy-loading {
        opacity: 0.6;
    }

    .lazy-loaded {
        animation: none;
        background: none;
    }

    .lazy-error {
        background: #fee;
        border: 1px dashed #f88;
        display: flex;
        align-items: center;
        justify-content: center;
        min-height: 100px;
    }

    .lazy-error::after {
        content: '⚠️ Failed to load image';
        color: #c44;
        font-size: 0.875rem;
    }
`;
document.head.appendChild(lazyLoadStyles);
