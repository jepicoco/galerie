// Variables globales
let currentZoom = 1;
let currentImageX = 0;
let currentImageY = 0;
let isDragging = false;
let dragStartX = 0;
let dragStartY = 0;
// Variables pour la navigation des photos
let currentGalleryPhotos = [];
let currentPhotoIndex = 0;
let currentActivityKey = '';
let mouseX = 0;
let mouseY = 0;
// Variables pour les commandes
let currentOrder = null;
let pendingPhoto = null; // Photo en attente d'ajout
let allOrders = []; // Cache des commandes
let isAdmin = false; // Statut administrateur
// Variables pour tracker l'image actuelle
let currentModalPhoto = {
    activityKey: '',
    photoName: '',
    photoPath: ''
};
// Variables pour l'administration
let isAdminLoggedIn = false;

document.addEventListener('DOMContentLoaded', function() {
    // Initialisation
    initializeModals();
    initializeSearch();
    initializeActivityCards();
    initializeImageViewer();

    // Appliquer le watermark
    applyWatermark();

    // Initialiser le système de commandes
    initializeOrderSystem();
    
    // Animation d'entrée des cartes
    animateCards();

    // Gestion du contenu du panier
    updateCartButtonVisibility();
    updateCartDisplay();

    // Initialiser le lazy loading
    initializeLazyLoading();

    // Vérifier les données de tarification
    ensurePricingData();
    
    // Réinitialiser le lazy loading après ajout de nouvelles cartes
    const observer = new MutationObserver(function(mutations) {
        mutations.forEach(function(mutation) {
            if (mutation.addedNodes.length) {
                initializeLazyLoading();
            }
        });
    });
    
    observer.observe(document.body, {
        childList: true,
        subtree: true
    });

});

/**
 * Vérifier la disponibilité des données de tarification
 * Doit être identique à la version dans config.php
 * Version: 1.0
 */
function ensurePricingData() {
    // Vérifier que ACTIVITY_PRICING est disponible
    if (typeof ACTIVITY_PRICING === 'undefined') {
        console.warn('ACTIVITY_PRICING non défini - utilisation des valeurs par défaut');
        window.ACTIVITY_PRICING = {
            'PHOTO': { display_name: 'Photo standard', price:2,description: 'Tirage photo' },
            'USB': { display_name: 'Clé USB', price:15,description: 'Support USB avec toutes les films du gala'}
        };
    }
    
    // Vérifier que activities est disponible
    if (typeof activities === 'undefined') {
        console.warn('activities non défini - chargement depuis le serveur nécessaire');
        window.activities = {};
    }
}

// Gestion des modals
function initializeModals() {
    // Modal de connexion admin
    const adminModal = document.getElementById('admin-modal');
    const adminBtn = document.getElementById('admin-login-btn');
    const closeBtns = document.querySelectorAll('.close');
    
    if (adminBtn && adminModal) {
        adminBtn.addEventListener('click', function(e) {
            e.preventDefault();
            adminModal.style.display = 'block';
            document.body.style.overflow = 'hidden';
        });
    }
    
    // Fermeture des modals
    closeBtns.forEach(closeBtn => {
        closeBtn.addEventListener('click', function() {
            closeAllModals();
        });
    });
    
    // Fermeture en cliquant à l'extérieur
    /*
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeAllModals();
        }
    });
    */
    
    // Fermeture avec la touche Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

// Fermer toutes les modals
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
    document.body.style.overflow = 'auto';
    resetImageViewer();
}

// Gestion de la recherche
function initializeSearch() {
    const searchInput = document.getElementById('search-input');
    const searchResults = document.getElementById('search-results');
    const activitiesGrid = document.getElementById('activities-list');
    
    if (!searchInput) return;
    
    let searchTimeout;
    
    searchInput.addEventListener('input', function() {
        clearTimeout(searchTimeout);
        const query = this.value.toLowerCase().trim();
        
        searchTimeout = setTimeout(() => {
            if (query === '') {
                showAllActivities();
                searchResults.innerHTML = '';
            } else {
                performSearch(query);
            }
        }, 300);
    });
}

// Effectuer la recherche
function performSearch(query) {
    const activityCards = document.querySelectorAll('.activity-card');
    const searchResults = document.getElementById('search-results');
    let foundActivities = [];
    let visibleCount = 0;
    
    activityCards.forEach(card => {
        const activityName = card.dataset.activity.toLowerCase();
        const activityTags = card.dataset.tags.toLowerCase();
        const activityText = card.textContent.toLowerCase();
        
        const isMatch = activityName.includes(query) || 
                       activityTags.includes(query) || 
                       activityText.includes(query);
        
        if (isMatch) {
            card.style.display = 'block';
            foundActivities.push(card.dataset.activity);
            visibleCount++;
            
            // Animation d'apparition
            card.style.animation = 'none';
            setTimeout(() => {
                card.style.animation = 'slideUp 0.4s ease forwards';
            }, 10);
        } else {
            card.style.display = 'none';
        }
    });
    
    // Afficher les résultats
    if (foundActivities.length > 0) {
        searchResults.innerHTML = `
            <div class="search-summary">
                <strong>${foundActivities.length}</strong> activité(s) trouvée(s)
            </div>
        `;
    } else {
        searchResults.innerHTML = `
            <div class="search-summary no-results">
                Aucune activité trouvée pour "<strong>${query}</strong>"
            </div>
        `;
    }
}

// Afficher toutes les activités
function showAllActivities() {
    const activityCards = document.querySelectorAll('.activity-card');
    activityCards.forEach((card, index) => {
        card.style.display = 'block';
        card.style.animation = 'none';
        setTimeout(() => {
            card.style.animation = `slideUp 0.4s ease forwards`;
            card.style.animationDelay = `${index * 0.1}s`;
        }, 10);
    });
}

// Initialiser les cartes d'activités
function initializeActivityCards() {
    const activityCards = document.querySelectorAll('.activity-card');
    
    activityCards.forEach(card => {
        card.addEventListener('click', function() {
            const activityKey = this.dataset.activity;
            openGallery(activityKey);
        });
        
        // Effet hover sur les images
        const img = card.querySelector('.activity-image img');
        if (img) {
            img.addEventListener('load', function() {
                this.style.opacity = '1';
            });
        }
    });
}

// Ouvrir la galerie d'une activité
function openGallery(activityKey) {
    const activity = activities[activityKey];
    if (!activity) return;
    
    const galleryModal = document.getElementById('gallery-modal');
    const galleryTitle = document.getElementById('gallery-title');
    const galleryImages = document.getElementById('gallery-images');
    
    // Titre
    galleryTitle.textContent = activity.name;
    
    // Vider les images précédentes
    galleryImages.innerHTML = '';
    
    // Créer les images de galerie
    activity.photos.forEach((photo, index) => {
        const imageDiv = document.createElement('div');
        imageDiv.className = 'gallery-item';
        
        // Structure HTML avec lazy loading
        imageDiv.innerHTML = `
            <div class="gallery-image-container">
                <img class="gallery-lazy-image" 
                     data-src="${getPhotoUrl(photo, 'thumbnail')}" 
                     data-fullsize="${getPhotoUrl(photo, 'original')}"
                     alt="${getPhotoName(photo)}" 
                     data-activity="${activityKey}"
                     data-photo="${getPhotoName(photo)}"
                     src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjE1MCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxMiIgZmlsbD0iIzk5OSI+Q2hhcmdlbWVudC4uLjwvdGV4dD48L3N2Zz4=">
                <div class="gallery-loading-placeholder">
                    <div class="loading-spinner"></div>
                </div>
            </div>
            <button class="add-to-cart-btn gallery-cart-btn" onclick="event.stopPropagation(); addToCart('${activityKey}', '${getPhotoName(photo)}', '${getPhotoUrl(photo, 'thumbnail')}')">
                🛒 Ajouter
            </button>
        `;
        
        // Animation d'entrée
        imageDiv.style.opacity = '0';
        imageDiv.style.transform = 'scale(0.8)';
        
        // Clic pour ouvrir l'image en grand
        imageDiv.addEventListener('click', function() {
            openImageViewer(activityKey, getPhotoName(photo));
        });
        
        galleryImages.appendChild(imageDiv);
        
        // Animation différée pour chaque image
        setTimeout(() => {
            imageDiv.style.transition = 'all 0.3s ease';
            imageDiv.style.opacity = '1';
            imageDiv.style.transform = 'scale(1)';
        }, index * 50);
    });
    
    // Afficher la modal
    galleryModal.style.display = 'block';
    document.body.style.overflow = 'hidden';

    // Initialiser le lazy loading pour les images de galerie
    setTimeout(() => {
        initializeGalleryLazyLoading();
    }, 100);

    // Appliquer le watermark après chargement des images
    setTimeout(() => {
        applyWatermark();
    }, 300);
}

// Initialiser le visualiseur d'images
function initializeImageViewer() {
    const zoomInBtn = document.getElementById('zoom-in');
    const zoomOutBtn = document.getElementById('zoom-out');
    const zoomResetBtn = document.getElementById('zoom-reset');
    const prevBtn = document.getElementById('prev-photo');
    const nextBtn = document.getElementById('next-photo');
    const modalImage = document.getElementById('modal-image');
    const modalAddCartBtn = document.getElementById('modal-add-cart');
    
    if (!zoomInBtn || !modalImage) return;
    
    // Boutons de zoom
    zoomInBtn.addEventListener('click', () => zoomImage(1.2));
    zoomOutBtn.addEventListener('click', () => zoomImage(0.8));
    zoomResetBtn.addEventListener('click', resetImageViewer);
    
    // Boutons de navigation
    if (prevBtn && nextBtn) {
        prevBtn.addEventListener('click', () => {
            navigateImage('previous');
        });
        nextBtn.addEventListener('click', () => {
            navigateImage('next');
        });
    }
    
    // Bouton d'ajout au panier dans la modal
    if (modalAddCartBtn) {
        modalAddCartBtn.addEventListener('click', async function() {
            if (currentModalPhoto.activityKey && currentModalPhoto.photoName) {
                try {
                    // Animation du bouton
                    this.classList.add('success');
                    const originalText = this.textContent;
                    this.textContent = '✅ Ajouté!';
                    
                    // Ajouter au panier
                    await addToCart(
                        currentModalPhoto.activityKey,
                        currentModalPhoto.photoName,
                        currentModalPhoto.photoPath
                    );
                    
                    // Restaurer le bouton après 2 secondes
                    setTimeout(() => {
                        this.classList.remove('success');
                        this.textContent = originalText;
                    }, 2000);
                    
                } catch (error) {
                    console.error('Erreur ajout au panier depuis modal:', error);
                    this.textContent = '❌ Erreur';
                    setTimeout(() => {
                        this.textContent = '🛒 Ajouter au panier';
                    }, 2000);
                }
            } else {
                console.error('Informations de photo manquantes:', currentModalPhoto);
                alert('Erreur: informations de photo manquantes');
            }
        });
    }
    
    // Zoom avec la molette de la souris
    modalImage.addEventListener('wheel', function(e) {
        e.preventDefault();
        const zoomFactor = e.deltaY > 0 ? 0.9 : 1.1;
        zoomAtMousePosition(zoomFactor);
    });
    
    // Glisser-déposer pour déplacer l'image
    modalImage.addEventListener('mousedown', startDrag);
    document.addEventListener('mousemove', drag);
    document.addEventListener('mouseup', endDrag);
    
    // Support tactile
    modalImage.addEventListener('touchstart', startTouchDrag);
    modalImage.addEventListener('touchmove', touchDrag);
    modalImage.addEventListener('touchend', endDrag);
}

// Fonction openImageViewer
function openImageViewer(activityKey, photoName) {
    const imageModal = document.getElementById('image-modal');
    const modalImage = document.getElementById('modal-image');
    
    // Récupérer l'activité
    const activity = activities[activityKey];
    if (!activity || !activity.photos) return;
    
    // Trouver l'objet photo correspondant
    const photoObject = activity.photos.find(p => getPhotoName(p) === photoName);
    if (!photoObject) return;
    
    // Stocker les informations de navigation
    currentActivityKey = activityKey;
    currentGalleryPhotos = activity.photos;
    currentPhotoIndex = currentGalleryPhotos.findIndex(p => getPhotoName(p) === photoName);
    
    // Stocker les informations de la photo actuelle pour le panier
    currentModalPhoto = {
        activityKey: activityKey,
        photoName: getPhotoName(photoObject),
        photoPath: getPhotoUrl(photoObject, 'resized'), // Utiliser l'image redimensionnée
        originalPath: getPhotoUrl(photoObject, 'original'), // Garder l'original disponible
        thumbPath: getPhotoUrl(photoObject, 'thumbnail')
    };
    
    // Afficher un placeholder pendant le chargement
    modalImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjYwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjMjIyIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIyMCIgZmlsbD0iI2ZmZiI+Q2hhcmdlbWVudCBlbiBjb3Vycy4uLjwvdGV4dD48L3N2Zz4=';
    modalImage.alt = currentModalPhoto.photoName;
    
    // Afficher la modal
    imageModal.style.display = 'block';
    resetImageViewer();
    
    // Pré-charger l'image redimensionnée
    loadModalImage(currentModalPhoto.photoPath);
}

// Fonction pour charger l'image dans la modal avec gestion des erreurs
function loadModalImage(imageSrc, isRetry = false) {
    const modalImage = document.getElementById('modal-image');
    const loadingIndicator = createLoadingIndicator();
    
    // Ajouter l'indicateur de chargement
    const imageModal = document.getElementById('image-modal');
    imageModal.appendChild(loadingIndicator);
    
    // Pré-charger l'image
    const imageLoader = new Image();
    
    imageLoader.onload = function() {
        // Image chargée avec succès
        modalImage.src = imageSrc;
        
        // Supprimer l'indicateur de chargement
        if (loadingIndicator.parentNode) {
            loadingIndicator.parentNode.removeChild(loadingIndicator);
        }
        
        // Animation de fade-in
        modalImage.style.opacity = '0';
        setTimeout(() => {
            modalImage.style.transition = 'opacity 0.3s ease';
            modalImage.style.opacity = '1';
        }, 50);
    };
    
    imageLoader.onerror = function() {
        // Erreur de chargement
        if (!isRetry && currentModalPhoto.originalPath) {
            // Essayer avec l'image originale si l'image redimensionnée échoue
            console.warn('Échec chargement image redimensionnée, essai avec originale');
            loadModalImage(currentModalPhoto.originalPath, true);
        } else if (!isRetry && currentModalPhoto.thumbPath) {
            // Essayer avec le thumbnail en dernier recours
            console.warn('Échec chargement image originale, essai avec thumbnail');
            loadModalImage(currentModalPhoto.thumbPath, true);
        } else {
            // Afficher une image d'erreur
            modalImage.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iODAwIiBoZWlnaHQ9IjYwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjVmNWY1IiBzdHJva2U9IiNkZGQiLz48dGV4dCB4PSI1MCUiIHk9IjQ1JSIgZG9taW5hbnQtYmFzZWxpbmU9Im1pZGRsZSIgdGV4dC1hbmNob3I9Im1pZGRsZSIgZm9udC1mYW1pbHk9IkFyaWFsIiBmb250LXNpemU9IjI0IiBmaWxsPSIjOTk5Ij7imaDvuI8gSW1hZ2Ugbm9uIGRpc3BvbmlibGU8L3RleHQ+PHRleHQgeD0iNTAlIiB5PSI1NSUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNiIgZmlsbD0iI2JiYiI+VsOpcmlmaWV6IGxhIGNvbm5leGlvbiBvdSBjb250YWN0ZXogbCdhZG1pbmlzdHJhdGV1cjwvdGV4dD48L3N2Zz4=';
            console.error('Impossible de charger l\'image:', imageSrc);
        }
        
        // Supprimer l'indicateur de chargement
        if (loadingIndicator.parentNode) {
            loadingIndicator.parentNode.removeChild(loadingIndicator);
        }
    };
    
    // Commencer le chargement
    imageLoader.src = imageSrc;
}

// Ajouter un bouton pour voir l'image en haute qualité
function addHighQualityButton() {
    const imageModal = document.getElementById('image-modal');
    
    // Vérifier si le bouton existe déjà
    if (imageModal.querySelector('.hq-button')) return;
    
    const hqButton = document.createElement('button');
    hqButton.className = 'hq-button';
    hqButton.innerHTML = '🔍 Haute qualité';
    hqButton.onclick = switchToOriginalImage;
    
    imageModal.appendChild(hqButton);
}

// CSS pour le bouton HQ
const hqButtonCSS = `
.hq-button {
    position: absolute;
    top: 20px;
    right: 60px;
    background: rgba(0, 0, 0, 0.7);
    color: white;
    border: none;
    padding: 10px 15px;
    border-radius: 5px;
    cursor: pointer;
    font-size: 12px;
    z-index: 1001;
    transition: background 0.3s ease;
}

.hq-button:hover {
    background: rgba(0, 0, 0, 0.9);
}
`;

// Créer un indicateur de chargement pour la modal
function createLoadingIndicator() {
    const indicator = document.createElement('div');
    indicator.className = 'modal-loading-indicator';
    indicator.innerHTML = `
        <div class="modal-loading-spinner"></div>
        <div class="modal-loading-text">Chargement de l'image...</div>
    `;
    return indicator;
}

// Fonction de navigation mise à jour pour les photos suivantes/précédentes
function navigateImage(direction) {
    if (!currentGalleryPhotos || currentGalleryPhotos.length === 0) return;
    
    // Calculer le nouvel index
    if (direction === 'next') {
        currentPhotoIndex = (currentPhotoIndex + 1) % currentGalleryPhotos.length;
    } else {
        currentPhotoIndex = (currentPhotoIndex - 1 + currentGalleryPhotos.length) % currentGalleryPhotos.length;
    }
    
    // Récupérer la nouvelle photo
    const newPhoto = currentGalleryPhotos[currentPhotoIndex];
    const newPhotoName = getPhotoName(newPhoto);
    
    // Mettre à jour currentModalPhoto
    currentModalPhoto = {
        activityKey: currentActivityKey,
        photoName: newPhotoName,
        photoPath: getPhotoUrl(newPhoto, 'resized'),
        originalPath: getPhotoUrl(newPhoto, 'original'),
        thumbPath: getPhotoUrl(newPhoto, 'thumbnail')
    };
    
    // Charger la nouvelle image
    loadModalImage(currentModalPhoto.photoPath);
    
    // Mettre à jour l'alt text
    const modalImage = document.getElementById('modal-image');
    modalImage.alt = newPhotoName;
}

// Fonction pour obtenir l'image en haute qualité (pour téléchargement ou zoom)
function getHighQualityImage() {
    if (currentModalPhoto && currentModalPhoto.originalPath) {
        return currentModalPhoto.originalPath;
    }
    return null;
}

// Fonction pour basculer vers l'image originale (zoom maximum)
function switchToOriginalImage() {
    if (currentModalPhoto && currentModalPhoto.originalPath) {
        const modalImage = document.getElementById('modal-image');
        
        // Afficher un indicateur pendant le changement
        modalImage.style.opacity = '0.5';
        
        const originalLoader = new Image();
        originalLoader.onload = function() {
            modalImage.src = currentModalPhoto.originalPath;
            modalImage.style.opacity = '1';
            
            // Mettre à jour le chemin principal
            currentModalPhoto.photoPath = currentModalPhoto.originalPath;
        };
        
        originalLoader.onerror = function() {
            // Revenir à l'image redimensionnée si l'originale échoue
            modalImage.style.opacity = '1';
            console.warn('Impossible de charger l\'image originale');
        };
        
        originalLoader.src = currentModalPhoto.originalPath;
    }
}

// Suivre la position de la souris pour le zoom
document.addEventListener('mousemove', function(e) {
    mouseX = e.clientX;
    mouseY = e.clientY;
});

// Fonctions de zoom
function zoomImage(factor) {
    currentZoom *= factor;
    currentZoom = Math.max(0.1, Math.min(5, currentZoom)); // Limiter le zoom
    updateImageTransform();
}

function resetImageViewer() {
    currentZoom = 1;
    currentImageX = 0;
    currentImageY = 0;
    updateImageTransform();
}

function updateImageTransform() {
    const modalImage = document.getElementById('modal-image');
    if (modalImage) {
        modalImage.style.transform = `translate(${currentImageX}px, ${currentImageY}px) scale(${currentZoom})`;
    }
}

// Fonctions de glisser-déposer
function startDrag(e) {
    if (currentZoom <= 1) return;
    isDragging = true;
    dragStartX = e.clientX - currentImageX;
    dragStartY = e.clientY - currentImageY;
    document.body.style.cursor = 'grabbing';
}

function drag(e) {
    if (!isDragging) return;
    e.preventDefault();
    currentImageX = e.clientX - dragStartX;
    currentImageY = e.clientY - dragStartY;
    updateImageTransform();
}

function startTouchDrag(e) {
    if (currentZoom <= 1) return;
    isDragging = true;
    const touch = e.touches[0];
    dragStartX = touch.clientX - currentImageX;
    dragStartY = touch.clientY - currentImageY;
}

function touchDrag(e) {
    if (!isDragging) return;
    e.preventDefault();
    const touch = e.touches[0];
    currentImageX = touch.clientX - dragStartX;
    currentImageY = touch.clientY - dragStartY;
    updateImageTransform();
}

function endDrag() {
    isDragging = false;
    document.body.style.cursor = 'default';
}

// Animation des cartes au chargement
function animateCards() {
    const cards = document.querySelectorAll('.activity-card');
    cards.forEach((card, index) => {
        card.style.animationDelay = `${index * 0.1}s`;
    });
}

// Fonctions utilitaires
function debounce(func, wait) {
    let timeout;
    return function executedFunction(...args) {
        const later = () => {
            clearTimeout(timeout);
            func(...args);
        };
        clearTimeout(timeout);
        timeout = setTimeout(later, wait);
    };
}

// Gestion des erreurs d'images
document.addEventListener('error', function(e) {
    if (e.target.tagName === 'IMG') {
        e.target.src = 'data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj4KICA8cmVjdCB3aWR0aD0iMjAwIiBoZWlnaHQ9IjIwMCIgZmlsbD0iI2Y1ZjVmNSIvPgogIDx0ZXh0IHg9IjUwJSIgeT0iNTAlIiBkb21pbmFudC1iYXNlbGluZT0ibWlkZGxlIiB0ZXh0LWFuY2hvcj0ibWlkZGxlIiBmb250LWZhbWlseT0iQXJpYWwiIGZvbnQtc2l6ZT0iMTQiIGZpbGw9IiM5OTkiPkltYWdlIG5vbiB0cm91dsOpZTwvdGV4dD4KPC9zdmc+';
        e.target.style.opacity = '0.5';
    }
}, true);


// Performance: Lazy loading des images
function setupLazyLoading() {
    if ('IntersectionObserver' in window) {
        const imageObserver = new IntersectionObserver((entries, observer) => {
            entries.forEach(entry => {
                if (entry.isIntersecting) {
                    const img = entry.target;
                    img.src = img.dataset.src;
                    img.classList.remove('lazy');
                    imageObserver.unobserve(img);
                }
            });
        });

        document.querySelectorAll('img[data-src]').forEach(img => {
            imageObserver.observe(img);
        });
    }
}

// Notification système
function showNotification2(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.innerHTML = `
        <div class="notification-content">
            <span>${message}</span>
            <button class="notification-close">&times;</button>
        </div>
    `;
    
    document.body.appendChild(notification);
    
    // Animation d'entrée
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Fermeture automatique
    setTimeout(() => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }, 5000);
    
    // Fermeture manuelle
    notification.querySelector('.notification-close').addEventListener('click', () => {
        notification.classList.remove('show');
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    });
}

// Initialisation finale
window.addEventListener('load', function() {
    setupLazyLoading();
    
    // Masquer le loader si présent
    const loader = document.querySelector('.loader');
    if (loader) {
        loader.style.display = 'none';
    }
});

// Zoomer à la position de la souris
function zoomAtMousePosition(factor) {
    const modalImage = document.getElementById('modal-image');
    const rect = modalImage.getBoundingClientRect();
    
    // Calculer la position relative de la souris sur l'image
    const relativeX = (mouseX - rect.left) / rect.width;
    const relativeY = (mouseY - rect.top) / rect.height;
    
    const oldZoom = currentZoom;
    currentZoom *= factor;
    currentZoom = Math.max(0.1, Math.min(5, currentZoom));
    
    // Ajuster la position pour centrer le zoom sur la souris
    if (currentZoom !== oldZoom) {
        const zoomDiff = currentZoom - oldZoom;
        currentImageX -= (relativeX - 0.5) * rect.width * zoomDiff / oldZoom;
        currentImageY -= (relativeY - 0.5) * rect.height * zoomDiff / oldZoom;
    }
    
    updateImageTransform();
}

// Raccourcis clavier
document.addEventListener('keydown', function(e) {
    const imageModal = document.getElementById('image-modal');
    if (imageModal && imageModal.style.display === 'block') {
        switch(e.key) {
            case '+':
            case '=':
                e.preventDefault();
                zoomAtMousePosition(1.2);
                break;
            case '-':
                e.preventDefault();
                zoomAtMousePosition(0.8);
                break;
            case '0':
                e.preventDefault();
                resetImageViewer();
                break;
            case 'r':
            case 'R':
                e.preventDefault();
                resetImageViewer();
                break;
            case 'p':
            case 'P':
                e.preventDefault();
                document.getElementById('modal-add-cart')?.click();
            case 'ArrowLeft':
                e.preventDefault();
                navigateImage('previous');
                break;
            case 'ArrowRight':
                e.preventDefault();
                navigateImage('next');
                break;
            case 'ArrowUp':
                e.preventDefault();
                zoomAtMousePosition(1.2);
                break;
            case 'ArrowDown':
                e.preventDefault();
                zoomAtMousePosition(0.8);
                break;
        }
    }
});

// Appliquer le watermark aux images
function applyWatermark() {
    if (!watermarkConfig.enabled || !watermarkConfig.text) return;
    
    const images = document.querySelectorAll('.activity-image img, .gallery-item img, #modal-image');
    
    images.forEach(img => {
        if (!img.parentElement.classList.contains('watermark-container')) {
            // Envelopper l'image dans un conteneur watermark
            const container = document.createElement('div');
            container.className = 'watermark-container';
            container.setAttribute('data-watermark', watermarkConfig.text);
            
            img.parentElement.insertBefore(container, img);
            container.appendChild(img);
            
            // Créer le pattern répétitif
            createWatermarkPattern(container, watermarkConfig.text);
        }
    });
}

// Créer le pattern de watermark répétitif
function createWatermarkPattern(container, text) {
    const pattern = document.createElement('div');
    pattern.className = 'watermark-pattern';
    pattern.style.opacity = watermarkConfig.opacity;
    
    // Créer plusieurs instances du texte pour couvrir toute l'image
    for (let row = 0; row < 20; row++) {
        for (let col = 0; col < 10; col++) {
            const textElement = document.createElement('div');
            textElement.className = 'watermark-text';
            textElement.textContent = text;
            textElement.style.left = (col * 300) + 'px';
            textElement.style.top = (row * 150) + 'px';
            textElement.style.fontSize = watermarkConfig.size;
            textElement.style.color = watermarkConfig.color;
            pattern.appendChild(textElement);
        }
    }
    
    container.appendChild(pattern);
}

document.addEventListener('contextmenu', event => event.preventDefault());

// Initialiser le système de commandes
function initializeOrderSystem() {
    // Événements des boutons principaux
    document.getElementById('new-order-btn')?.addEventListener('click', showNewOrderModal);
    document.getElementById('resume-order-btn')?.addEventListener('click', showResumeOrderModal);
    
    // Toggle du panier - Header entier cliquable pour toggle
    document.querySelector('.cart-header')?.addEventListener('click', function(e) {
        // Éviter le double clic si on clique directement sur le bouton toggle
        if (!e.target.closest('.cart-toggle')) {
            toggleCart();
        }
    });
    
    // Le bouton toggle reste fonctionnel aussi
    document.getElementById('toggle-cart')?.addEventListener('click', function(e) {
        e.stopPropagation(); // Éviter le double toggle
        toggleCart();
    });
    
    // Formulaires
    document.getElementById('new-order-form')?.addEventListener('submit', createNewOrder);
    document.getElementById('resume-order-form')?.addEventListener('submit', resumeOrder);
    
    // Validation commande
    document.getElementById('validate-order')?.addEventListener('click', validateOrder);
    
    // Charger commande en cours si elle existe
    loadCurrentOrder();
    
    // Ajouter boutons sur les photos
    //addCartButtonsToImages();
}

// Toggle panier amélioré
function toggleCart() {
    const cart = document.getElementById('order-cart');
    const toggle = document.getElementById('toggle-cart');
    
    cart.classList.toggle('collapsed');
    
    if (cart.classList.contains('collapsed')) {
        toggle.textContent = '▶';
        toggle.setAttribute('title', 'Ouvrir le panier');
    } else {
        toggle.textContent = '◀';
        toggle.setAttribute('title', 'Fermer le panier');
    }
}

// Reprendre une commande existante
async function resumeOrder(e) {
    e.preventDefault();
    
    const reference = document.getElementById('order_reference').value.trim();
    
    if (!reference) {
        showNotification('Veuillez saisir une référence de commande');
        return;
    }
    
    const formData = new FormData();
    formData.append('action', 'load_order');
    formData.append('reference', reference);
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            currentOrder = result.order;
            updateCartDisplay();
            document.getElementById('resume-order-modal').style.display = 'none';
            document.getElementById('order-cart').classList.remove('collapsed');
            
            showNotification('Commande rechargée: ' + result.order.reference, 'success');
            
            // Vider le formulaire
            e.target.reset();
        } else {
            showNotification('Erreur: ' + result.error);
        }
    } catch (error) {
        alert('Erreur de communication: ' + error.message);
    }
}

// Afficher modal nouvelle commande
function showNewOrderModal() {
    const modal = document.getElementById('new-order-modal');
    const title = modal.querySelector('h2');
    
    if (pendingPhoto) {
        title.innerHTML = `📋 Nouvelle commande<br><small style="font-size: 0.7em; color: #666;">Photo "${pendingPhoto.photoName}" en attente d'ajout</small>`;
    } else {
        title.textContent = '📋 Nouvelle commande';
    }
    
    modal.style.display = 'block';
}

// Afficher modal reprendre commande
async function showResumeOrderModal() {
    document.getElementById('resume-order-modal').style.display = 'block';
    
    // Vérifier le statut admin
    await checkAdminStatus();

    const adminSection = document.getElementById('admin-only-section');

    if(typeof adminSection !== 'undefined'){
        if (isAdmin) {
            adminSection.style.display = 'block';
            loadRecentOrdersList();
            setupOrdersSearch();
        } else {
            adminSection.style.display = 'none';
        }
    }
}

// Formater la date de commande
function formatOrderDate(dateString) {
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (error) {
        return dateString;
    }
}

// Charger une commande par référence (depuis la liste)
function loadOrderByReference(reference) {
    document.getElementById('order_reference').value = reference;
    document.getElementById('resume-order-form').dispatchEvent(new Event('submit'));
}

// Charger une commande par référence (depuis la liste)
function loadOrderByReference(reference) {
    document.getElementById('order_reference').value = reference;
    document.getElementById('resume-order-form').dispatchEvent(new Event('submit'));
}

// Toggle panier
function toggleCart() {
    const cart = document.getElementById('order-cart');
    const toggle = document.getElementById('toggle-cart');
    
    cart.classList.toggle('collapsed');
    toggle.textContent = cart.classList.contains('collapsed') ? '▶' : '◀';
}

// Créer nouvelle commande
async function createNewOrder(e) {
    e.preventDefault();
    
    const formData = new FormData(e.target);
    formData.append('action', 'create_order');
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('Réponse brute:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Erreur parsing JSON:', e);
            console.error('Réponse reçue:', responseText);
            showNotification('Erreur de communication avec le serveur');
            return;
        }
        
        if (result.success) {
            currentOrder = {
                reference: result.reference,
                customer: result.customer,
                items: {}
            };
            
            updateCartDisplay();
            document.getElementById('new-order-modal').style.display = 'none';
            document.getElementById('order-cart').classList.remove('collapsed');
            
            //showNotification('Commande créée: ' + result.reference, 'success');
            
            // Ajouter la photo en attente si elle existe
            if (pendingPhoto) {
                await addPhotoToCart(pendingPhoto.activityKey, pendingPhoto.photoName, pendingPhoto.photoPath);
                pendingPhoto = null;
            }
            
            // Vider le formulaire
            e.target.reset();
        } else {
            showNotification('Erreur: ' + result.error);
        }
    } catch (error) {
        console.error('Erreur complète:', error);
        alert('Erreur de communication: ' + error.message);
    }
}

// Ajouter boutons aux images
function addCartButtonsToImages() {
    // Boutons sur les cartes d'activités (image principale)
    document.querySelectorAll('.activity-card').forEach(card => {
        if (!card.querySelector('.add-to-cart-btn')) {
            const button = document.createElement('button');
            button.className = 'add-to-cart-btn';
            button.innerHTML = '🛒 Ajouter';
            button.onclick = (e) => {
                e.stopPropagation();
                const activityKey = card.dataset.activity;
                const img = card.querySelector('.activity-image img');
                if (img && activities[activityKey] && activities[activityKey].photos[0]) {
                    addToCart(activityKey, activities[activityKey].photos[0], img.src);
                }
            };
            card.querySelector('.activity-image').appendChild(button);
        }
    });
}

// Ajouter à la commande
async function addToCart(activityKey, photoName, photoPath) {
    if (!currentOrder) {
        // Stocker la photo en attente
        pendingPhoto = {
            activityKey: activityKey,
            photoName: photoName,
            photoPath: photoPath
        };
        
        // Ouvrir la modal de création de commande
        showNewOrderModal();
        return;
    }

    await addPhotoToCart(activityKey, photoName, photoPath);

}

/**
 * Ajouter une photo au panier
 * Version: 2.1
 * Correction 2.0: suppression mise à jour locale pour éviter triple comptage
 * Correction 2.1: mise à jour visuelle basée sur la réponse serveur
 */
async function addPhotoToCart1(activityKey, photoName, photoPath) {
    const formData = new FormData();
    formData.append('action', 'add_item');
    formData.append('activity_key', activityKey);
    formData.append('photo_name', photoName);
    formData.append('photo_path', photoPath);
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const itemKey = activityKey + '/' + photoName;
            
            // Recharger l'état complet depuis le serveur pour synchronisation
            const orderResponse = await fetch('order_handler.php', {
                method: 'POST',
                body: (() => {
                    const fd = new FormData();
                    fd.append('action', 'get_current_order');
                    return fd;
                })()
            });
            
            const orderResult = await orderResponse.json();
            
            if (orderResult.success && orderResult.order) {
                // Mettre à jour l'état local avec les données serveur
                currentOrder = orderResult.order;
                updateCartDisplay();
            } else {
                // Fallback : mise à jour manuelle si échec rechargement
                if (!currentOrder.items[itemKey]) {
                    currentOrder.items[itemKey] = {
                        photo_path: photoPath,
                        activity_key: activityKey,
                        photo_name: photoName,
                        quantity: 1
                    };
                } else {
                    currentOrder.items[itemKey].quantity++;
                }
                updateCartDisplay();
            }
            
            // Ouvrir le panier si ce n'est pas déjà fait
            document.getElementById('order-cart').classList.remove('collapsed');
        }
    } catch (error) {
        console.error('Erreur ajout photo:', error);
    }
}

/**
 * Ajouter une photo au panier avec scroll automatique vers l'item
 * @param {string} activityKey - Clé de l'activité
 * @param {string} photoName - Nom de la photo  
 * @param {string} photoPath - Chemin de la photo
 * @version 1.1.0
 */
async function addPhotoToCart(activityKey, photoName, photoPath) {
    const formData = new FormData();
    formData.append('action', 'add_item');
    formData.append('activity_key', activityKey);
    formData.append('photo_name', photoName);
    formData.append('photo_path', photoPath);
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            const itemKey = activityKey + '/' + photoName;
            const wasExisting = !!currentOrder.items[itemKey];
            
            if (!currentOrder.items[itemKey]) {
                currentOrder.items[itemKey] = {
                    photo_path: photoPath,
                    activity_key: activityKey,
                    photo_name: photoName,
                    quantity: 1
                };
            } else {
                currentOrder.items[itemKey].quantity++;
            }
            
            updateCartDisplay();
            
            // Ouvrir le panier si ce n'est pas déjà fait
            document.getElementById('order-cart').classList.remove('collapsed');
            
            // Attendre que le DOM soit mis à jour, puis scroller vers l'item
            setTimeout(() => {
                scrollToCartItem(itemKey, wasExisting);
            }, 100);
        }
    } catch (error) {
        console.error('Erreur ajout au panier:', error);
        alert('Erreur lors de l\'ajout au panier');
    }
}

// Mettre à jour quantité
async function updateQuantity(itemKey, quantity) {
    const formData = new FormData();
    formData.append('action', 'update_quantity');
    formData.append('item_key', itemKey);
    formData.append('quantity', quantity);
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success && currentOrder.items[itemKey]) {
            currentOrder.items[itemKey].quantity = parseInt(quantity);
            updateCartDisplay();
            
        }
    } catch (error) {
        console.error('Erreur mise à jour quantité:', error);
    }
}

// Supprimer item
async function removeItem(itemKey) {
    const formData = new FormData();
    formData.append('action', 'remove_item');
    formData.append('item_key', itemKey);
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            delete currentOrder.items[itemKey];
            updateCartDisplay();
        }
    } catch (error) {
        console.error('Erreur suppression item:', error);
    }
}

// Valider commande
// Variables pour la validation
let validationInProgress = false;

// Afficher la modal de validation
function showValidateOrderModal() {
    if (!currentOrder) return;
    
    const modal = document.getElementById('validate-order-modal');
    
    // Remplir les informations de confirmation
    document.getElementById('confirm-reference').textContent = currentOrder.reference;
    document.getElementById('confirm-customer').textContent = 
        currentOrder.customer.firstname + ' ' + currentOrder.customer.lastname;
    
    const totalPhotos = Object.values(currentOrder.items).reduce((sum, item) => sum + item.quantity, 0);
    document.getElementById('confirm-total').textContent = totalPhotos;
    
    // Réinitialiser l'état de la modal
    showValidationStep('validation-confirm');
    
    modal.style.display = 'block';
}

// Afficher une étape de validation
function showValidationStep(stepId) {
    // Masquer toutes les étapes
    document.querySelectorAll('.validation-step').forEach(step => {
        step.classList.remove('active');
    });

    // Masquer toutes les étapes
    document.querySelectorAll('.progress-step').forEach(step => {
        step.classList.remove('processing', 'completed', 'error');
    });
    
    // Afficher l'étape demandée
    document.getElementById(stepId).classList.add('active');
}

// Mettre à jour le statut d'une étape
function updateProgressStep(stepId, status) {
    const step = document.getElementById(stepId);
    const statusElement = step.querySelector('.step-status');
    
    // Nettoyer les classes précédentes
    step.classList.remove('processing', 'completed', 'error');
    
    switch(status) {
        case 'processing':
            step.classList.add('processing');
            statusElement.textContent = '🔄';
            break;
        case 'completed':
            step.classList.add('completed');
            statusElement.textContent = '✅';
            break;
        case 'error':
            step.classList.add('error');
            statusElement.textContent = '❌';
            break;
        default:
            statusElement.textContent = '⏳';
    }
}

// Fonction validateOrder
async function validateOrder() {
    showValidateOrderModal();
}

// Fonction de validation effective
async function performValidation() {
    if (validationInProgress) return;
    
    validationInProgress = true;
    
    try {
        // Passer à l'écran de progression
        showValidationStep('validation-progress');
        
        // Étape 1 : Sauvegarde
        updateProgressStep('step-save', 'processing');
        await new Promise(resolve => setTimeout(resolve, 500)); // Délai visuel
        
        const formData = new FormData();
        formData.append('action', 'validate_order');
        
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (!result.success) {
            throw new Error(result.error);
        }
        
        updateProgressStep('step-save', 'completed');
        
        // Étape 2 : Excel
        updateProgressStep('step-excel', 'processing');
        await new Promise(resolve => setTimeout(resolve, 300)); // Délai visuel
        updateProgressStep('step-excel', 'completed');
        
        // Étape 3 : Email
        updateProgressStep('step-email', 'processing');
        await new Promise(resolve => setTimeout(resolve, 800)); // Délai pour l'email
        updateProgressStep('step-email', 'completed');
        
        // Succès
        showValidationSuccess(result);
        
    } catch (error) {
        console.error('Erreur validation:', error);
        showValidationError(error.message);
    }
    
    validationInProgress = false;
}

// Afficher le succès
function showValidationSuccess(result) {
    document.getElementById('success-reference').textContent = result.reference;
    
    // Stocker les données pour l'impression
    printOrderData = {
        reference: result.reference,
        customer: currentOrder.customer,
        items: currentOrder.items,
        created_at: currentOrder.created_at,
        is_update: result.is_update
    };

    // Afficher info mise à jour si nécessaire
    const updateInfo = document.getElementById('success-update-info');
    if (result.is_update) {
        updateInfo.style.display = 'block';
    } else {
        updateInfo.style.display = 'none';
    }
    
    // Statut email
    const emailStatus = document.getElementById('success-email-status');
    if (result.email_sent) {
        emailStatus.innerHTML = '<span style="color: #28a745;">✅ Envoyé avec succès</span>';
    } else {
        emailStatus.innerHTML = '<span style="color: #ffc107;">⚠️ Échec d\'envoi (commande validée)</span>';
    }
    
    showValidationStep('validation-success');
    
    // Nettoyer le panier
    currentOrder = null;
    updateCartDisplay();
}

// Afficher l'erreur
function showValidationError(errorMessage) {
    document.getElementById('error-details').textContent = errorMessage;
    showValidationStep('validation-error');
}

// Fermer la modal de validation
function closeValidationModal() {
    document.getElementById('validate-order-modal').style.display = 'none';
    validationInProgress = false;
    
    // Fermer le panier si succès
    if (!currentOrder) {
        document.getElementById('order-cart').classList.add('collapsed');
    }
}

/**
 * Recharger l'état de la commande depuis le serveur
 * Version: 1.0
 * Fonction de synchronisation pour éviter désynchronisation client/serveur
 */
async function loadCurrentOrder() {
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: new FormData().append('action', 'get_current_order')
        });
        
        const result = await response.json();
        
        if (result.success && result.order) {
            currentOrder = result.order;
        }
    } catch (error) {
        console.error('Erreur rechargement commande:', error);
    }
}

// Détecter si le scroll est nécessaire dans le panier
function checkCartScroll() {
    const cartItems = document.getElementById('cart-items');
    if (cartItems) {
        const hasScroll = cartItems.scrollHeight > cartItems.clientHeight;
        
        if (hasScroll) {
            cartItems.classList.add('has-scroll');
        } else {
            cartItems.classList.remove('has-scroll');
        }
    }
}

/**
 * Mettre à jour l'affichage du panier avec identifiants uniques pour scroll
 * @version 1.2.0
 */
function updateCartDisplay() {
    const cartItems = document.getElementById('cart-items');
    const cartCount = document.getElementById('cart-count');
    const cartTotal = document.getElementById('cart-total');
    const cartIcon = document.getElementById('cart-icon');
    const customerInfo = document.getElementById('customer-info');
    const orderInfo = document.getElementById('order-info');
    const orderReference = document.getElementById('order-reference');
    const validateBtn = document.getElementById('validate-order');
    
    // Réinitialisation par défaut
    if (cartItems) {
        cartItems.innerHTML = '<p class="empty-cart">Panier vide</p>';
    }
    
    if (cartCount) {
        cartCount.textContent = '0';
    }
    
    if (cartTotal) {
        cartTotal.textContent = '0.00';
    }
    
    if (validateBtn) {
        validateBtn.disabled = true;
    }

    if (!currentOrder) {
        if (customerInfo) customerInfo.style.display = 'none';
        if (orderInfo) orderInfo.style.display = 'none';
        
        if (cartIcon) {
            cartIcon.classList.remove('has-items');
            cartIcon.removeAttribute('data-count');
        }
        return;
    }

    if (!orderInfo) return;
    
    orderInfo.style.display = 'block';
    orderReference.textContent = currentOrder.reference;
    
    customerInfo.style.display = 'block';
    document.getElementById('customer-name').textContent = 
        currentOrder.customer.firstname + ' ' + currentOrder.customer.lastname;
    document.getElementById('customer-email').textContent = currentOrder.customer.email;
    document.getElementById('customer-phone').textContent = currentOrder.customer.phone;
    
    const totalAmount = GetTotalAmount(currentOrder);
    const items = Object.entries(currentOrder.items);
    const totalQty = items.reduce((sum, [key, item]) => sum + item.quantity, 0);
    
    cartCount.textContent = totalQty;
    cartTotal.textContent = totalAmount.toFixed(2);

    if (items.length === 0) {
        cartItems.innerHTML = '<p class="empty-cart">Panier vide</p>';
        validateBtn.disabled = true;
        cartIcon.classList.remove('has-items');
        cartIcon.removeAttribute('data-count');
    } else {
        cartItems.innerHTML = items.map(([key, item]) => {
            const unitPrice = item.unit_price || getActivityPrice(item.activity_key) || 2;
            const subtotal = item.quantity * unitPrice;
            const pricingType = item.pricing_type || getActivityTypeInfo(item.activity_key)?.display_name || 'Photo standard';
            
            return `
                <div class="cart-item" data-item-key="${key}">
                    <img src="${item.photo_path}" alt="${item.photo_name}">
                    <div class="cart-item-info">
                        <div class="cart-item-activity">${item.activity_key}</div>
                        <small class="cart-item-photo">${item.photo_name}</small>
                        <div class="cart-item-pricing">
                            <span class="pricing-type">${pricingType}</span>
                            <span class="unit-price">${unitPrice.toFixed(2)}€ l'unité</span>
                        </div>
                    </div>
                    <div class="cart-item-quantity">
                        <input type="number" class="qty-input" value="${item.quantity}" 
                               min="1" onchange="updateQuantity('${key}', this.value)">
                        <div class="item-subtotal">${subtotal.toFixed(2)}€</div>
                    </div>
                    <div class="cart-item-actions">
                        <button class="remove-item" onclick="removeItem('${key}')" title="Supprimer">🗑️</button>
                    </div>
                </div>
            `;
        }).join('');
        
        validateBtn.disabled = false;
        
        if (totalQty > 0) {
            cartIcon.classList.add('has-items');
            cartIcon.setAttribute('data-count', totalQty);
        }
    }

    setTimeout(checkCartScroll, 100);
    updateCartButtonVisibility();
}

/**
 * Mettre à jour l'affichage du panier avec gestion des tarifs différenciés
 */
function updateCartDisplay1() {
    const cartItems = document.getElementById('cart-items');
    const cartCount = document.getElementById('cart-count');
    const cartTotal = document.getElementById('cart-total');
    const cartIcon = document.getElementById('cart-icon');
    const customerInfo = document.getElementById('customer-info');
    const orderInfo = document.getElementById('order-info');
    const orderReference = document.getElementById('order-reference');
    const validateBtn = document.getElementById('validate-order');
    
    // Réinitialisation par défaut
    if (cartItems) {
        cartItems.innerHTML = '<p class="empty-cart">Panier vide</p>';
    }
    
    if (cartCount) {
        cartCount.textContent = '0';
    }
    
    if (cartTotal) {
        cartTotal.textContent = '0.00';
    }
    
    if (validateBtn) {
        validateBtn.disabled = true;
    }

    if (!currentOrder) {
        if (customerInfo) customerInfo.style.display = 'none';
        if (orderInfo) orderInfo.style.display = 'none';
        
        // Masquer le badge
        if (cartIcon) {
            cartIcon.classList.remove('has-items');
            cartIcon.removeAttribute('data-count');
        }
        return;
    }

    if (!orderInfo) return;
    
    // Afficher la référence de commande
    orderInfo.style.display = 'block';
    orderReference.textContent = currentOrder.reference;
    
    // Afficher infos client
    customerInfo.style.display = 'block';
    document.getElementById('customer-name').textContent = 
        currentOrder.customer.firstname + ' ' + currentOrder.customer.lastname;
    document.getElementById('customer-email').textContent = currentOrder.customer.email;
    document.getElementById('customer-phone').textContent = currentOrder.customer.phone;
    
    // Calculer le total avec la fonction dédiée
    const totalAmount = GetTotalAmount(currentOrder);
    
    // Afficher items avec calcul des prix différenciés
    const items = Object.entries(currentOrder.items);
    const totalQty = items.reduce((sum, [key, item]) => sum + item.quantity, 0);
    
    // Mettre à jour les compteurs
    cartCount.textContent = totalQty;
    cartTotal.textContent = totalAmount.toFixed(2);

    if (items.length === 0) {
        cartItems.innerHTML = '<p class="empty-cart">Panier vide</p>';
        validateBtn.disabled = true;
        cartIcon.classList.remove('has-items');
        cartIcon.removeAttribute('data-count');
    } else {
        cartItems.innerHTML = items.map(([key, item]) => {
            // Récupérer le prix unitaire
            const unitPrice = item.unit_price || getActivityPrice(item.activity_key) || 2;
            const subtotal = item.quantity * unitPrice;
            
            // Récupérer le type de tarification
            const pricingType = item.pricing_type || getActivityTypeInfo(item.activity_key)?.display_name || 'Photo standard';
            
            return `
                <div class="cart-item">
                    <img src="${item.photo_path}" alt="${item.photo_name}">
                    <div class="cart-item-info">
                        <div class="cart-item-activity">${item.activity_key}</div>
                        <small class="cart-item-photo">${item.photo_name}</small>
                        <div class="cart-item-pricing">
                            <span class="pricing-type">${pricingType}</span>
                            <span class="unit-price">${unitPrice.toFixed(2)}€ l'unité</span>
                        </div>
                    </div>
                    <div class="cart-item-quantity">
                        <input type="number" class="qty-input" value="${item.quantity}" 
                               min="1" onchange="updateQuantity('${key}', this.value)">
                        <div class="item-subtotal">${subtotal.toFixed(2)}€</div>
                    </div>
                    <div class="cart-item-actions">
                        <button class="remove-item" onclick="removeItem('${key}')" title="Supprimer">🗑️</button>
                    </div>
                </div>
            `;
        }).join('');
        
        validateBtn.disabled = false;
        
        // Afficher le badge avec le nombre d'articles
        if (totalQty > 0) {
            cartIcon.classList.add('has-items');
            cartIcon.setAttribute('data-count', totalQty);
        }
    }

    // Vérifier si le scroll est nécessaire après mise à jour
    setTimeout(checkCartScroll, 100);
    updateCartButtonVisibility();
}

/**
 * Fonction helper pour récupérer le prix d'une activité côté client
 */
function getActivityPrice(activityKey) {
    if (typeof ACTIVITY_PRICING !== 'undefined' && activities[activityKey]) {
        const pricingType = activities[activityKey].pricing_type || DEFAULT_ACTIVITY_TYPE || 'PHOTO';
        return ACTIVITY_PRICING[pricingType]?.price || 2;
    }
    return 2; // Prix par défaut si les données ne sont pas disponibles
}

/**
 * Fonction helper pour récupérer les infos du type de tarification côté client
 */
function getActivityTypeInfo(activityKey) {
    if (typeof ACTIVITY_PRICING !== 'undefined' && activities[activityKey]) {
        const pricingType = activities[activityKey].pricing_type || DEFAULT_ACTIVITY_TYPE || 'PHOTO';
        return ACTIVITY_PRICING[pricingType] || ACTIVITY_PRICING['PHOTO'];
    }
    return { display_name: 'Photo standard', price: 2 };
}

// Vérifier le statut administrateur
async function checkAdminStatus() {
    try {
        const response = await fetch('admin.php', {
            method: 'HEAD',
            credentials: 'same-origin'
        });
        isAdmin = response.ok;
    } catch (error) {
        isAdmin = false;
    }
}

/**
 * Charger la liste des commandes récentes
 * Version: 2.0
 * Correction: gestion fusion commandes normales et temporaires
 */
async function loadRecentOrdersList() {
    const listDiv = document.getElementById('recent-orders-list');
    const countSpan = document.getElementById('orders-count');
    
    listDiv.innerHTML = '<p class="loading">Chargement...</p>';
    
    try {
        // Étape 1 : Charger les commandes normales
        const response = await fetch('order_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=list_recent_orders&limit=50'
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Initialiser avec les commandes normales
            allOrders = result.orders || [];
            
            // Étape 2 : Charger les commandes temporaires si admin
            if (isAdmin) {
                try {
                    const tempOrders = await loadTempOrders();
                    if (tempOrders && tempOrders.length > 0) {
                        // Fusionner : temporaires en premier
                        allOrders = [...tempOrders, ...allOrders];
                    }
                } catch (tempError) {
                    console.warn('Erreur chargement commandes temporaires:', tempError);
                    // Continuer avec les commandes normales seulement
                }
            }
            
            // Étape 3 : Affichage final
            displayOrders(allOrders);
            updateOrdersCount();
            
        } else {
            listDiv.innerHTML = `<div class="order-card no-results">Erreur: ${result.error}</div>`;
            countSpan.textContent = '0 commande';
        }
    } catch (error) {
        console.error('Erreur chargement commandes:', error);
        listDiv.innerHTML = '<div class="order-card no-results">Erreur de chargement</div>';
        countSpan.textContent = '0 commande';
    }
}

/**
 * Mettre à jour le compteur de commandes
 * Version: 1.0
 * Gestion séparée du comptage pour plus de clarté
 */
function updateOrdersCount() {
    const countSpan = document.getElementById('orders-count');
    
    if (!allOrders || allOrders.length === 0) {
        countSpan.textContent = '0 commande';
        return;
    }
    
    const tempCount = allOrders.filter(o => o.is_temp === true).length;
    const normalCount = allOrders.length - tempCount;
    
    let countText = `${allOrders.length} commande(s)`;
    
    if (tempCount > 0 && normalCount > 0) {
        countText += ` (${tempCount} temp., ${normalCount} valid.)`;
    } else if (tempCount > 0) {
        countText += ` (temporaires)`;
    } else if (normalCount > 0) {
        countText += ` (validées)`;
    }
    
    countSpan.textContent = countText;
}

/**
 * Charger les commandes temporaires (retourne les données)
 * Version: 1.0
 * Fonction séparée pour éviter les conflits d'affichage
 */
async function loadTempOrders() {
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=list_temp_orders'
        });
        
        const result = await response.json();
        
        if (result.success && result.orders && result.orders.length > 0) {
            // Marquer comme temporaires et retourner
            return result.orders.map(order => ({
                ...order,
                is_temp: true
            }));
        }
        
        return [];
    } catch (error) {
        console.error('Erreur chargement commandes temporaires:', error);
        return [];
    }
}

/**
 * Afficher la liste des commandes avec gestion des deux types et calcul du total
 */
function displayOrders1(orders) {
    const listDiv = document.getElementById('recent-orders-list');
    
    if (orders.length === 0) {
        listDiv.innerHTML = '<div class="order-card no-results">Aucune commande trouvée</div>';
        return;
    }
    
    listDiv.innerHTML = orders.map(order => {
        const isTemp = order.is_temp === true;
        const cardClass = isTemp ? 'order-card temp-order' : 'order-card';
        const action = isTemp ? 'loadTempOrderByReference' : 'loadOrderByReference';
        const statusBadge = isTemp ? '<span class="temp-badge">⏳ TEMPORAIRE</span>' : '';
        
        // Calculer le montant total de la commande
        let totalAmount = GetTotalAmount(order).toFixed(2);
        
        return `
            <div class="${cardClass}" onclick="${action}('${order.reference}')">
                <div class="order-header">
                    <div class="order-reference">${order.reference}</div>
                    ${statusBadge}
                </div>
                <div class="order-customer">${order.customer_name}</div>
                <div class="order-meta">
                    <div class="order-date">${formatOrderDate(order.created_at)}</div>
                    <div class="order-count">${order.items_count} photo(s)</div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Afficher la liste des commandes avec détail par type d'activité
 * Version: 2.0
 * Correction: affichage multiple par ACTIVITY_PRICING
 */
function displayOrders(orders) {
    const listDiv = document.getElementById('recent-orders-list');
    
    if (orders.length === 0) {
        listDiv.innerHTML = '<div class="order-card no-results">Aucune commande trouvée</div>';
        return;
    }
    
    listDiv.innerHTML = orders.map(order => {
        const isTemp = order.is_temp === true;
        const cardClass = isTemp ? 'order-card temp-order' : 'order-card';
        const action = isTemp ? 'loadTempOrderByReference' : 'loadOrderByReference';
        const statusBadge = isTemp ? '<span class="temp-badge">⏳ TEMPORAIRE</span>' : '';
        
        // Calculer le montant total de la commande
        let totalAmount = GetTotalAmount(order);
        
        // Regrouper les items par type de tarification
        const groupedItems = groupItemsByPricingType(order);
        
        // Créer les lignes de détail par type
        const itemsDetailLines = Object.values(groupedItems).map(group => {
            const pricingClass = `pricing-${group.pricing_type.toLowerCase()}`;
            return `<div class="order-count ${pricingClass}">
                <span class="pricing-badge">${group.display_name}&nbsp;</span>
                <span class="quantity">${group.quantity}</span>
            </div>`;
        }).join('');
        
        // Ligne de total si plusieurs types
        const totalLine = Object.keys(groupedItems).length > 1 
            ? `<div class="order-count total-line">
                <span class="total-label">Total</span>
                <span class="quantity">${order.items_count || Object.values(groupedItems).reduce((sum, g) => sum + g.quantity, 0)}</span>
               </div>` 
            : '';
        
        return `
            <div class="${cardClass}" onclick="${action}('${order.reference}')">
                <div class="order-header">
                    <div class="order-reference">${order.reference}</div>
                    ${statusBadge}
                </div>
                <div class="order-customer">${order.customer_name}</div>
                <div class="order-meta">
                    <div class="order-date">${formatOrderDate(order.created_at)}</div>
                    <div class="order-items-detail">
                        ${itemsDetailLines}
                        ${totalLine}
                    </div>
                </div>
            </div>
        `;
    }).join('');
}

/**
 * Calculer le montant total d'une commande
 */
function GetTotalAmount(order) {
    let totalAmount = 0;
    
    if (!order || !order.items) {
        console.log('Commande vide ou sans items');
        return 0;
    }
    
    // currentOrder.items est un objet, pas un array
    if (typeof order.items === 'object' && !Array.isArray(order.items)) {
        // Convertir l'objet en array pour le traitement
        const itemsArray = Object.values(order.items);
        totalAmount = itemsArray.reduce((sum, item) => {
            const unitPrice = item.unit_price || getActivityPrice(item.activity_key) || 2;
            const subtotal = item.quantity * unitPrice;
            return sum + subtotal;
        }, 0);
    } else if (Array.isArray(order.items)) {
        // Si c'est déjà un array
        totalAmount = order.items.reduce((sum, item) => {
            const unitPrice = item.unit_price || getActivityPrice(item.activity_key) || 2;
            return sum + (item.quantity * unitPrice);
        }, 0);
    } else if (order.items_count) {
        // Fallback : estimation basée sur le nombre de photos et prix moyen
        totalAmount = order.items_count * 2;
    }
    
    return totalAmount;
}

// Charger une commande temporaire
async function loadTempOrderByReference(reference) {
    console.log('Chargement commande temporaire:', reference);
    
    const formData = new FormData();
    formData.append('action', 'load_temp_order');
    formData.append('reference', reference);
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const responseText = await response.text();
        console.log('Réponse brute:', responseText);
        
        let result;
        try {
            result = JSON.parse(responseText);
        } catch (e) {
            console.error('Erreur parsing JSON:', e);
            alert('Erreur de communication avec le serveur');
            return;
        }
        
        if (result.success) {
            currentOrder = result.order;
            updateCartDisplay();
            document.getElementById('resume-order-modal').style.display = 'none';
            document.getElementById('order-cart').classList.remove('collapsed');
            
            showNotification('Commande temporaire rechargée: ' + result.order.reference, 'success');
        } else {
            console.error('Erreur serveur:', result.error);
            showNotification('Erreur: ' + result.error);
        }
    } catch (error) {
        console.error('Erreur complète:', error);
        alert('Erreur de communication: ' + error.message);
    }
}

// setupOrdersSearch pour gérer les deux types
function setupOrdersSearch() {
    const searchInput = document.getElementById('search-orders');
    const clearBtn = document.getElementById('clear-search');
    
    // Recherche en temps réel
    searchInput.addEventListener('input', function() {
        const query = this.value.toLowerCase().trim();
        
        if (query === '') {
            displayOrders(allOrders);
            document.getElementById('orders-count').textContent = `${allOrders.length} commande(s)`;
        } else {
            const filtered = allOrders.filter(order => 
                order.customer_name.toLowerCase().includes(query) ||
                order.reference.toLowerCase().includes(query) ||
                (order.customer_email && order.customer_email.toLowerCase().includes(query))
            );
            
            displayOrders(filtered);
            
            // Compter séparément les temporaires et normales
            const tempCount = filtered.filter(o => o.is_temp).length;
            const normalCount = filtered.length - tempCount;
            let countText = `${filtered.length} trouvée(s)`;
            if (tempCount > 0 && normalCount > 0) {
                countText += ` (${tempCount} temp., ${normalCount} valid.)`;
            } else if (tempCount > 0) {
                countText += ` (temporaires)`;
            } else if (normalCount > 0) {
                countText += ` (validées)`;
            }
            
            document.getElementById('orders-count').textContent = countText;
        }
    });
    
    // Bouton effacer
    clearBtn.addEventListener('click', function() {
        searchInput.value = '';
        displayOrders(allOrders);
        
        const tempCount = allOrders.filter(o => o.is_temp).length;
        const normalCount = allOrders.length - tempCount;
        let countText = `${allOrders.length} commande(s)`;
        if (tempCount > 0 && normalCount > 0) {
            countText += ` (${tempCount} temp., ${normalCount} valid.)`;
        }
        
        document.getElementById('orders-count').textContent = countText;
        searchInput.focus();
    });
}

// Afficher les activités avec gestion des activités à la une
function displayActivities(activitiesToShow = null) {
    const activitiesGrid = document.getElementById('activities-grid');
    const featuredGrid = document.getElementById('featured-activities');
    const featuredSection = document.getElementById('featured-section');
    const activitiesTitle = document.getElementById('activities-title');
    
    const dataToShow = activitiesToShow || activities;
    
    // Séparer les activités à la une des autres
    const featuredActivities = {};
    const regularActivities = {};
    
    Object.entries(dataToShow).forEach(([key, activity]) => {
        if (activity.featured) {
            featuredActivities[key] = activity;
        } else {
            regularActivities[key] = activity;
        }
    });
    
    // Afficher les activités à la une
    if (Object.keys(featuredActivities).length > 0) {
        featuredSection.style.display = 'block';
        featuredGrid.innerHTML = '';
        
        Object.entries(featuredActivities).forEach(([activityKey, activity]) => {
            const card = createActivityCard(activityKey, activity, true);
            featuredGrid.appendChild(card);
        });
        
        // Mettre à jour le titre des autres activités
        if (Object.keys(regularActivities).length > 0) {
            activitiesTitle.textContent = '🎭 Autres activités';
        } else {
            activitiesTitle.textContent = '🎭 Toutes les activités';
        }
    } else {
        featuredSection.style.display = 'none';
        activitiesTitle.textContent = '🎭 Toutes les activités';
    }
    
    // Afficher les autres activités
    if (Object.keys(regularActivities).length > 0) {
        activitiesGrid.innerHTML = '';
        
        Object.entries(regularActivities).forEach(([activityKey, activity]) => {
            const card = createActivityCard(activityKey, activity, false);
            activitiesGrid.appendChild(card);
        });
    } else if (Object.keys(featuredActivities).length === 0) {
        // Aucune activité du tout
        activitiesGrid.innerHTML = '<div class="no-activities">Aucune activité trouvée</div>';
    } else {
        // Seulement des activités à la une
        activitiesGrid.innerHTML = '';
    }
}

// Créer une carte d'activité
function createActivityCard(activityKey, activity, isFeatured = false) {
    const card = document.createElement('div');
    card.className = `activity-card${isFeatured ? ' featured' : ''}`;
    card.dataset.activity = activityKey;
    
    // Vérifier qu'il y a des photos
    if (!activity.photos || activity.photos.length === 0) {
        return card;
    }
    
    // Récupérer la première photo (photo de couverture)
    const coverPhoto = activity.photos[0];
    
    // Badge à la une pour les cartes normales
    const featuredBadge = activity.featured && !isFeatured ? 
        '<div class="featured-badge">⭐ À la une</div>' : '';
    
    // Structure HTML de la carte
    card.innerHTML = `
        ${featuredBadge}
        <div class="activity-image">
            <img class="lazy-image" 
                 data-src="${getPhotoUrl(coverPhoto, 'thumbnail')}" 
                 data-fullsize="${getPhotoUrl(coverPhoto, 'original')}"
                 data-activity-key="${activityKey}"
                 data-photo-name="${getPhotoName(coverPhoto)}"
                 alt="${activity.name}" 
                 src="data:image/svg+xml;base64,PHN2ZyB3aWR0aD0iMzAwIiBoZWlnaHQ9IjIwMCIgeG1sbnM9Imh0dHA6Ly93d3cudzMub3JnLzIwMDAvc3ZnIj48cmVjdCB3aWR0aD0iMTAwJSIgaGVpZ2h0PSIxMDAlIiBmaWxsPSIjZjBmMGYwIi8+PHRleHQgeD0iNTAlIiB5PSI1MCUiIGRvbWluYW50LWJhc2VsaW5lPSJtaWRkbGUiIHRleHQtYW5jaG9yPSJtaWRkbGUiIGZvbnQtZmFtaWx5PSJBcmlhbCIgZm9udC1zaXplPSIxNCIgZmlsbD0iIzk5OSI+Q2hhcmdlbWVudC4uLjwvdGV4dD48L3N2Zz4=">
            <div class="image-loading-placeholder">
                <div class="loading-spinner"></div>
            </div>
            <button class="add-to-cart-btn" onclick="event.stopPropagation(); addToCart('${activityKey}', '${getPhotoName(coverPhoto)}', '${getPhotoUrl(coverPhoto, 'thumbnail')}')">
                🛒 Ajouter
            </button>
        </div>
        <div class="activity-info">
            <h3 class="activity-title">${activity.name}</h3>
            <p class="activity-description">${activity.description || 'Découvrez cette collection de photos'}</p>
            <div class="activity-meta">
                <span class="photo-count">${activity.photos.length} photo${activity.photos.length > 1 ? 's' : ''}</span>
                ${activity.tags && activity.tags.length > 0 ? 
                    `<div class="activity-tags">${activity.tags.slice(0, 3).map(tag => 
                        `<span class="tag">${tag}</span>`
                    ).join('')}</div>` : ''}
            </div>
        </div>
    `;
    
    // Événement de clic
    card.addEventListener('click', function() {
        openGallery(activityKey);
    });
    
    return card;
}

// Modifier la fonction de recherche pour prendre en compte les activités à la une
function searchActivities() {
    const searchTerm = document.getElementById('search-input').value.toLowerCase().trim();
    
    if (searchTerm === '') {
        displayActivities();
        return;
    }
    
    const filteredActivities = {};
    
    Object.entries(activities).forEach(([key, activity]) => {
        const matchesName = activity.name.toLowerCase().includes(searchTerm);
        const matchesDescription = activity.description && activity.description.toLowerCase().includes(searchTerm);
        const matchesTags = activity.tags && activity.tags.some(tag => 
            tag.toLowerCase().includes(searchTerm)
        );
        const matchesKey = key.toLowerCase().includes(searchTerm);
        
        if (matchesName || matchesDescription || matchesTags || matchesKey) {
            filteredActivities[key] = activity;
        }
    });
    
    displayActivities(filteredActivities);
}

// Fonction pour fermer toutes les modales et réinitialiser la recherche
function closeAllModalsAndResetSearch() {
    // Restaurer l'ascenseur du navigateur
    document.body.style.overflow = 'auto';

    // Fermer toutes les modales
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
    
    // Vider le champ de recherche
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.value = '';
    }
    
    // Réafficher toutes les activités (supprimer les filtres)
    const activityCards = document.querySelectorAll('.activity-card');
    activityCards.forEach(card => {
        card.style.display = 'block';
    });
    
    // Supprimer les classes de filtrage actives s'il y en a
    const activeFilters = document.querySelectorAll('.filter-active, .search-active');
    activeFilters.forEach(element => {
        element.classList.remove('filter-active', 'search-active');
    });
    
    // Réinitialiser le compteur de résultats s'il existe
    const resultsCounter = document.querySelector('.search-results-count');
    if (resultsCounter) {
        resultsCounter.style.display = 'none';
    }
}

/**
 * Obtenir le nom d'une photo (version unifiée)
 * @param {Object|string} photo - Objet photo ou nom de fichier
 * @returns {string} Nom de la photo
 * @version 1.2
 */
function getPhotoName(photo) {
    // Si c'est null ou undefined
    if (!photo) {
        console.warn('getPhotoName: photo non définie');
        return 'photo_inconnue';
    }
    
    // Si c'est un objet, essayer différentes propriétés
    if (typeof photo === 'object') {
        return photo.photo_name || photo.name || photo.filename || 'photo_sans_nom';
    }
    
    // Si c'est une string, la retourner directement
    if (typeof photo === 'string') {
        return photo;
    }
    
    // Cas d'erreur
    console.warn('getPhotoName: format de photo non reconnu:', photo);
    return 'photo_erreur';
}

/**
 * Construire le chemin complet d'une image
 */
function buildImagePath(photo) {
    const photosDir = 'photos/';
    const photoName = getPhotoName(photo);
    return `${photosDir}${photo.activity_key}/${photoName}`;
}

// Gestion du bouton "Annuler le panier"
document.addEventListener('DOMContentLoaded', function() {
    const clearCartBtn = document.getElementById('clear-cart');
    const clearCartModal = document.getElementById('clear-cart-modal');
    const cancelClearBtn = document.getElementById('cancel-clear-cart');
    const confirmClearBtn = document.getElementById('confirm-clear-cart');
    const closeValidationBtn = document.getElementById('close-validation');

    
    // Événements de validation
    document.getElementById('cancel-validation')?.addEventListener('click', closeValidationModal);
    document.getElementById('confirm-validation')?.addEventListener('click', performValidation);
    document.getElementById('close-validation')?.addEventListener('click', closeValidationModal);
    document.getElementById('close-error')?.addEventListener('click', closeValidationModal);
    document.getElementById('retry-validation')?.addEventListener('click', performValidation);

    // Bouton d'impression
    document.getElementById('print-order')?.addEventListener('click', printOrder);
    
    // Fermeture avec Escape
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            const modal = document.getElementById('validate-order-modal');
            if (modal && modal.style.display === 'block' && !validationInProgress) {
                closeValidationModal();
            }
        }
    });
    
    // Ouvrir la modal de confirmation
    if (clearCartBtn) {
        clearCartBtn.addEventListener('click', function() {
            clearCartModal.style.display = 'block';
        });
    }
    
    // Fermer la modal (annuler)
    if (cancelClearBtn) {
        cancelClearBtn.addEventListener('click', function() {
            clearCartModal.style.display = 'none';
        });
    }
    
    // Confirmer la suppression
    if (confirmClearBtn) {
        confirmClearBtn.addEventListener('click', function() {
            clearCart();
        });
    }
    
    // Fermer la modal en cliquant à l'extérieur
    if (clearCartModal) {
        clearCartModal.addEventListener('click', function(e) {
            if (e.target === clearCartModal) {
                clearCartModal.style.display = 'none';
            }
        });
    }

    if (closeValidationBtn) {
        closeValidationBtn.addEventListener('click', function() {
            closeAllModalsAndResetSearch();
        });
    } 
});

// Fonction pour vider le panier
async function clearCart() {
    const clearCartModal = document.getElementById('clear-cart-modal');
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: 'action=clear_cart'
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Fermer la modal
            clearCartModal.style.display = 'none';
            
            // ✅ SOLUTION : Réinitialiser complètement l'affichage
            currentOrder = null; // Supprimer la commande côté client pour l'affichage
            
            // Mettre à jour l'affichage (maintenant vide)
            updateCartDisplay();
            
            // Afficher un message de succès
            showNotification('Panier vidé avec succès', 'success');

            closeAllModalsAndResetSearch();
            
        } else {
            console.error('Erreur lors du vidage du panier:', result.error);
            alert('Erreur: ' + result.error);
        }
        
    } catch (error) {
        console.error('Erreur de communication:', error);
        alert('Erreur de communication avec le serveur');
    }
}

// Fonction pour afficher les notifications (optionnelle)
function showNotification(message, type = 'info') {
    const notification = document.createElement('div');
    notification.className = `notification ${type}`;
    notification.textContent = message;
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 15px 20px;
        background: ${type === 'success' ? '#28a745' : type === 'error' ? '#dc3545' : '#17a2b8'};
        color: white;
        border-radius: 5px;
        z-index: 9999;
        box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        transform: translateX(100%);
        transition: transform 0.3s ease;
    `;
    
    document.body.appendChild(notification);
    
    // Animation d'apparition
    setTimeout(() => {
        notification.style.transform = 'translateX(0)';
    }, 100);
    
    // Suppression automatique
    setTimeout(() => {
        notification.style.transform = 'translateX(100%)';
        setTimeout(() => {
            notification.remove();
        }, 300);
    }, 3000);
}

// Modifier votre fonction existante d'ajout au panier pour afficher/cacher le bouton
function updateCartButtonVisibility() {
    // Contrôle de l'existence de l'élément order-reference
    const orderReference = document.getElementById('order-reference');
    const clearCartBtn = document.getElementById('clear-cart');
    const cartCountElem = document.getElementById('cart-count');

    if (!orderReference || !clearCartBtn || !cartCountElem) {
        // Si un des éléments n'existe pas, ne rien faire
        return;
    }

    const cartRef = cartCountElem.textContent;

    if (cartRef && parseInt(cartRef) > 0) {
        clearCartBtn.style.display = 'block';
    } else {
        clearCartBtn.style.display = 'none';
    }
}

/**
 * Obtenir l'URL d'une photo selon le type
 */
function getPhotoUrl(photo, type = 'thumbnail') {
    // Si photo est un objet (nouveau format)
    if (typeof photo === 'object' && photo !== null) {
        switch (type) {
            case 'thumbnail': return photo.thumbPath;
            case 'resized': return photo.resizedUrl;
            case 'original': return photo.originalUrl;
            default: return photo.thumbPath;
        }
    }
    
    // Si photo est une string (ancien format - fallback)
    if (typeof photo === 'string') {
        console.warn('Format de photo obsolète détecté:', photo);
        return `photos/${photo}`; // Fallback vers l'ancien système
    }
    
    return '';
}

/**
 * Regrouper les items d'une commande par type de tarification
 * Version: 1.0
 * Analyse et regroupe les photos par ACTIVITY_PRICING
 */
function groupItemsByPricingType(order) {

    if (!order || !order.items) {
        return {};
    }
    
    const groupedItems = {};
    
    // Fonction pour traiter un item
    const processItem = (item) => {
        let pricingType = 'PHOTO'; // Type par défaut
        let displayName = 'Photo standard';
        
        // Récupérer le type de tarification depuis les données d'activité
        if (typeof activities !== 'undefined' && activities[item.activity_key]) {
            pricingType = activities[item.activity_key].pricing_type || 'PHOTO';
        }
        
        // Récupérer le display_name depuis ACTIVITY_PRICING
        if (typeof ACTIVITY_PRICING !== 'undefined' && ACTIVITY_PRICING[pricingType]) {
            displayName = ACTIVITY_PRICING[pricingType].display_name;
        }
        
        // Initialiser le groupe si nécessaire
        if (!groupedItems[pricingType]) {
            groupedItems[pricingType] = {
                display_name: displayName,
                quantity: 0,
                pricing_type: pricingType
            };
        }
        
        // Ajouter la quantité
        groupedItems[pricingType].quantity += parseInt(item.quantity) || 1;
    };
    
    // Traiter selon la structure des items
    if (Array.isArray(order.items)) {
        // Structure array
        order.items.forEach(processItem);
    } else if (typeof order.items === 'object') {
        // Structure objet (clé/valeur)
        Object.values(order.items).forEach(processItem);
    } else if (order.items_count) {
        // Fallback : estimation générale
        groupedItems['PHOTO'] = {
            display_name: 'Photo standard',
            quantity: order.items_count,
            pricing_type: 'PHOTO'
        };
    }
    
    return groupedItems;
}

/**
 * Faire défiler le panier vers l'item spécifié avec animation
 * @param {string} itemKey - Clé unique de l'item (activityKey/photoName)
 * @param {boolean} wasExisting - Si l'item existait déjà (quantité augmentée)
 * @version 1.0.0
 */
function scrollToCartItem(itemKey, wasExisting = false) {
    const cartItemsContainer = document.getElementById('cart-items');
    const targetItem = cartItemsContainer.querySelector(`[data-item-key="${itemKey}"]`);
    
    if (!targetItem || !cartItemsContainer) {
        return;
    }
    
    // Calculer la position de scroll nécessaire
    const containerRect = cartItemsContainer.getBoundingClientRect();
    const itemRect = targetItem.getBoundingClientRect();
    const currentScrollTop = cartItemsContainer.scrollTop;
    
    // Position de l'item relative au container
    const itemOffsetTop = itemRect.top - containerRect.top + currentScrollTop;
    
    // Centrer l'item dans la zone visible
    const containerHeight = cartItemsContainer.clientHeight;
    const targetScrollTop = itemOffsetTop - (containerHeight / 2) + (itemRect.height / 2);
    
    // Assurer que le scroll reste dans les limites
    const maxScrollTop = cartItemsContainer.scrollHeight - containerHeight;
    const finalScrollTop = Math.max(0, Math.min(targetScrollTop, maxScrollTop));
    
    // Animation de scroll fluide
    cartItemsContainer.scrollTo({
        top: finalScrollTop,
        behavior: 'smooth'
    });
    
    // Effet visuel pour indiquer l'item ajouté/modifié
    highlightCartItem(targetItem, wasExisting);
}

/**
 * Mettre en évidence un item du panier avec animation
 * @param {HTMLElement} itemElement - Élément DOM de l'item
 * @param {boolean} wasExisting - Si l'item existait déjà
 * @version 1.0.0
 */
function highlightCartItem(itemElement, wasExisting = false) {
    if (!itemElement) return;
    
    // Classe CSS pour l'animation
    const highlightClass = wasExisting ? 'cart-item-updated' : 'cart-item-added';
    
    // Ajouter la classe d'animation
    itemElement.classList.add(highlightClass);
    
    // Retirer la classe après l'animation
    setTimeout(() => {
        itemElement.classList.remove(highlightClass);
    }, 2000);
}