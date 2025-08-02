// Initialiser le lazy loading des images
function initializeLazyLoading() {
    // Vérifier la compatibilité avec IntersectionObserver
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    loadLazyImage(img);
                    observer.unobserve(img);
                }
            });
        }, {
            // Commencer à charger l'image 100px avant qu'elle soit visible
            rootMargin: '100px 0px',
            threshold: 0.01
        });

        // Observer toutes les images lazy
        document.querySelectorAll('img.lazy-image').forEach(img => {
            imageObserver.observe(img);
        });
    } else {
        // Fallback pour les navigateurs anciens
        document.querySelectorAll('img.lazy-image').forEach(img => {
            loadLazyImage(img);
        });
    }
}

// Charger une image lazy
function loadLazyImage(img) {
    const placeholder = img.parentElement.querySelector('.image-loading-placeholder');
    
    // Afficher le spinner de chargement
    if (placeholder) {
        placeholder.style.display = 'flex';
    }
    
    // Créer une nouvelle image pour pré-charger
    const imageLoader = new Image();
    
    imageLoader.onload = function() {
        // L'image est chargée, l'afficher
        img.src = img.dataset.src;
        img.classList.remove('lazy-image');
        img.classList.add('loaded');
        
        // Masquer le placeholder
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    };
    
    imageLoader.onerror = function() {
        // Erreur de chargement, afficher une image par défaut
        img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjVmNWY1Ii8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSI+SW1hZ2Ugbm9uIHRyb3V2w6llPC90ZXh0Pjwvc3ZnPg==';
        img.classList.add('error');
        
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    };
    
    // Commencer le chargement
    imageLoader.src = img.dataset.src;
}

// Lazy loading pour les images de galerie
function initializeGalleryLazyLoading() {
    if ('IntersectionObserver' in window) {
        const galleryObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    loadGalleryLazyImage(img);
                    observer.unobserve(img);
                }
            });
        }, {
            rootMargin: '50px 0px',
            threshold: 0.1
        });

        // Observer toutes les images lazy de galerie
        document.querySelectorAll('.gallery-lazy-image').forEach(img => {
            galleryObserver.observe(img);
        });
    } else {
        // Fallback pour navigateurs anciens
        document.querySelectorAll('.gallery-lazy-image').forEach(img => {
            loadGalleryLazyImage(img);
        });
    }
}

// Charger une image lazy de galerie
function loadGalleryLazyImage(img) {
    const container = img.closest('.gallery-image-container');
    const placeholder = container.querySelector('.gallery-loading-placeholder');
    
    // Afficher le spinner
    if (placeholder) {
        placeholder.style.display = 'flex';
    }
    
    // Pré-charger l'image
    const imageLoader = new Image();
    
    imageLoader.onload = function() {
        // Image chargée avec succès
        img.src = img.dataset.src;
        img.classList.remove('gallery-lazy-image');
        img.classList.add('gallery-loaded');
        
        // Masquer le placeholder avec animation
        if (placeholder) {
            placeholder.style.opacity = '0';
            setTimeout(() => {
                placeholder.style.display = 'none';
            }, 200);
        }
        
        // Animation de fade-in pour l'image
        img.style.opacity = '0';
        setTimeout(() => {
            img.style.transition = 'opacity 0.3s ease';
            img.style.opacity = '1';
        }, 50);
    };
    
    imageLoader.onerror = function() {
        // Erreur de chargement
        img.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjVmNWY1IiBzdHJva2U9IiNkZGQiLz48dGV4dCB4PSI1MCUiIHk9IjUwJSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjEyIiBmaWxsPSIjOTk5Ij5JbWFnZSBub24gZGlzcG9uaWJsZTwvdGV4dD48L3N2Zz4=';
        img.classList.add('gallery-error');
        
        if (placeholder) {
            placeholder.style.display = 'none';
        }
    };
    
    // Démarrer le chargement
    imageLoader.src = img.dataset.src;
}