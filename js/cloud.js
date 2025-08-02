// Variables globales pour les filtres par tags
let allTags = new Set();
let selectedTags = new Set();
let isFilterExpanded = false;

// Initialisation du système de filtres par tags
function initializeTagsFilter() {
    // Collecter tous les tags des activités
    document.querySelectorAll('.activity-card').forEach(card => {
        // Récupération des tags depuis l'attribut data-tags (séparés par espaces)
        const tagsString = card.dataset.tags;
        if (tagsString && tagsString.trim()) {
            const tags = tagsString.split(' ').map(tag => tag.trim()).filter(tag => tag);
            tags.forEach(tag => allTags.add(tag));
        }
    });

    renderTagsCloud();
    setupTagsEventListeners();
}

// Configurer les event listeners pour les filtres
function setupTagsEventListeners() {
    // Toggle pour afficher/masquer les filtres
    const toggleBtn = document.getElementById('tags-filter-toggle');
    if (toggleBtn) {
        toggleBtn.addEventListener('click', toggleFiltersSection);
    }

    // Recherche textuelle avec debounce pour optimiser les performances
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        let searchTimeout;
        searchInput.addEventListener('input', function() {
            clearTimeout(searchTimeout);
            searchTimeout = setTimeout(() => {
                updateActivitiesDisplay();
                updateClearButton();
                updateActiveFiltersInfo();
            }, 150); // Délai de 150ms pour éviter trop d'appels
        });
    }

    // Bouton pour effacer les filtres
    const clearBtn = document.getElementById('clear-tags-btn');
    if (clearBtn) {
        clearBtn.addEventListener('click', clearAllFilters);
    }
}

// Afficher/masquer la section des filtres
function toggleFiltersSection() {
    const section = document.getElementById('tags-filter-section');
    isFilterExpanded = !isFilterExpanded;
    
    if (isFilterExpanded) {
        section.classList.add('expanded');
    } else {
        section.classList.remove('expanded');
    }
}

// Générer le nuage de tags
function renderTagsCloud() {
    const container = document.getElementById('tags-cloud');
    if (!container) return;
    
    container.innerHTML = '';

    // Tri alphabétique des tags
    const sortedTags = Array.from(allTags).sort((a, b) => a.localeCompare(b));

    sortedTags.forEach(tag => {
        const tagElement = document.createElement('span');
        tagElement.className = `tag-filter ${selectedTags.has(tag) ? 'active' : ''}`;
        tagElement.textContent = tag;
        tagElement.onclick = () => toggleTag(tag);
        container.appendChild(tagElement);
    });
}

// Toggle d'un tag
function toggleTag(tag) {
    if (selectedTags.has(tag)) {
        selectedTags.delete(tag);
    } else {
        selectedTags.add(tag);
    }

    renderTagsCloud();
    updateSelectedTagsBadge();
    updateActivitiesDisplay();
    updateClearButton();
    updateActiveFiltersInfo();
}

// Mettre à jour le badge du nombre de tags sélectionnés
function updateSelectedTagsBadge() {
    const badge = document.getElementById('selected-tags-badge');
    if (!badge) return;
    
    if (selectedTags.size > 0) {
        badge.textContent = selectedTags.size;
        badge.style.display = 'inline-flex';
    } else {
        badge.style.display = 'none';
    }
}

// Mettre à jour l'affichage des activités (optimisé)
function updateActivitiesDisplay() {
    const searchInput = document.getElementById('search-input');
    const searchTerm = searchInput ? searchInput.value.toLowerCase().trim() : '';
    
    const activityCards = document.querySelectorAll('.activity-card');
    let visibleCount = 0;

    // Utilisation d'un fragment pour optimiser les performances
    const fragment = document.createDocumentFragment();

    activityCards.forEach(card => {
        let show = true;

        // Filtre par recherche textuelle
        if (searchTerm) {
            const activityName = card.querySelector('h3')?.textContent.toLowerCase() || '';
            const description = card.querySelector('.description')?.textContent.toLowerCase() || '';
            const searchableText = `${activityName} ${description}`;
            
            if (!searchableText.includes(searchTerm)) {
                show = false;
            }
        }

        // Filtre par tags sélectionnés
        if (show && selectedTags.size > 0) {
            const cardTagsString = card.dataset.tags || '';
            const cardTags = cardTagsString.split(' ').map(tag => tag.trim()).filter(tag => tag);
            
            // Logique AND: afficher si TOUS les tags sélectionnés sont présents
            const hasAllSelectedTags = Array.from(selectedTags).every(selectedTag => 
                cardTags.includes(selectedTag)
            );
            
            if (!hasAllSelectedTags) {
                show = false;
            }
        }

        // Appliquer l'affichage avec animation optimisée
        if (show) {
            card.style.display = 'block';
            // Utilisation de requestAnimationFrame pour des animations plus fluides
            requestAnimationFrame(() => {
                card.style.opacity = '1';
                card.style.transform = 'scale(1)';
            });
            visibleCount++;
        } else {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            // Délai optimisé pour l'animation
            setTimeout(() => {
                if (card.style.opacity === '0') {
                    card.style.display = 'none';
                }
            }, 200);
        }
    });

    // Mettre à jour le compteur
    updateActivityCount(visibleCount);
}

// Mettre à jour le compteur d'activités (fonction séparée pour réutilisabilité)
function updateActivityCount(visibleCount) {
    // Chercher différents types de compteurs possibles
    const counters = [
        document.getElementById('activities-count'),
        document.getElementById('photos-count'),
        document.querySelector('.activities-count'),
        document.querySelector('.photos-count')
    ].filter(el => el !== null);

    counters.forEach(counter => {
        counter.textContent = visibleCount;
    });
}

// Mettre à jour le bouton "Effacer les filtres"
function updateClearButton() {
    const clearBtn = document.getElementById('clear-tags-btn');
    const searchInput = document.getElementById('search-input');
    
    if (!clearBtn) return;
    
    const hasSearchFilter = searchInput && searchInput.value.trim() !== '';
    const hasTagFilters = selectedTags.size > 0;
    const hasFilters = hasSearchFilter || hasTagFilters;
    
    clearBtn.disabled = !hasFilters;
}

// Mettre à jour l'info des filtres actifs
function updateActiveFiltersInfo() {
    const info = document.getElementById('active-filters-info');
    const searchInput = document.getElementById('search-input');
    
    if (!info) return;
    
    const activeInfo = [];
    const searchTerm = searchInput ? searchInput.value.trim() : '';

    if (searchTerm) {
        activeInfo.push(`Recherche: "${searchTerm}"`);
    }

    if (selectedTags.size > 0) {
        const tagsText = Array.from(selectedTags).join(', ');
        activeInfo.push(`Tags: ${tagsText}`);
    }

    info.textContent = activeInfo.join(' • ');
}

// Effacer tous les filtres
function clearAllFilters() {
    // Reset des filtres
    selectedTags.clear();
    
    const searchInput = document.getElementById('search-input');
    if (searchInput) {
        searchInput.value = '';
    }

    // Mise à jour de l'interface
    renderTagsCloud();
    updateSelectedTagsBadge();
    updateActivitiesDisplay();
    updateClearButton();
    updateActiveFiltersInfo();
}

// Fonction utilitaire pour déboguer (à supprimer en production)
function debugTagsSystem() {
    console.log('Tags disponibles:', Array.from(allTags));
    console.log('Tags sélectionnés:', Array.from(selectedTags));
    console.log('Activités trouvées:', document.querySelectorAll('.activity-card').length);
}

// Initialiser au chargement de la page avec gestion d'erreur
document.addEventListener('DOMContentLoaded', function() {
    try {
        initializeTagsFilter();
        
        // Optionnel: activer le debug en développement
        // debugTagsSystem();
        
    } catch (error) {
        console.error('Erreur lors de l\'initialisation des filtres par tags:', error);
    }
});

// Optimisation: nettoyer les timeouts si la page est fermée
window.addEventListener('beforeunload', function() {
    // Nettoyer les éventuels timeouts en cours
    clearTimeout(window.searchTimeout);
});