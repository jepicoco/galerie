 // Variables globales pour les tags
let selectedTagsForFilter = new Set();
let selectedTagsForActivity = new Set();

// Variables pour les filtres
let activeFilters = {
    visibility: '',
    featured: '',
    tags: '',
    search: ''
};


// Initialiser les compteurs et les event listeners
function initializeFilters() {
    updateAllCounts();
    
    // Event listeners pour les boutons de filtre
    document.querySelectorAll('.filter-toggle').forEach(button => {
        button.addEventListener('click', function() {
            const filterType = this.dataset.filter;
            const filterValue = this.dataset.value;
            
            // Désactiver les autres boutons du même groupe
            document.querySelectorAll(`[data-filter="${filterType}"]`).forEach(btn => {
                btn.classList.remove('active');
            });
            
            // Activer le bouton cliqué
            this.classList.add('active');
            
            // Mettre à jour le filtre actif
            activeFilters[filterType] = filterValue;
            
            applyAllFilters();
            updateActiveFiltersSummary();
        });
    });

    // Event listener pour la recherche
    const searchInput = document.getElementById('search-activities');
    searchInput.addEventListener('input', function() {
        activeFilters.search = this.value.toLowerCase();
        applyAllFilters();
        
        // Afficher/cacher le bouton de clear
        const clearBtn = document.getElementById('clear-search');
        clearBtn.style.display = this.value ? 'flex' : 'none';
    });
    
    // Clear recherche
    document.getElementById('clear-search').addEventListener('click', function() {
        searchInput.value = '';
        activeFilters.search = '';
        this.style.display = 'none';
        applyAllFilters();
    });
    
    // Clear tous les filtres
    document.getElementById('clear-all-filters').addEventListener('click', clearAllFilters);
}

// Compter les activités pour chaque filtre
function updateAllCounts() {
    const allCards = document.querySelectorAll('.activity-card');
    
    // Compter par visibilité
    const publicCount = Array.from(allCards).filter(card => 
        card.querySelector('.badge.visibility.public')).length;
    const privateCount = allCards.length - publicCount;
    
    document.getElementById('count-visibility-all').textContent = allCards.length;
    document.getElementById('count-visibility-public').textContent = publicCount;
    document.getElementById('count-visibility-private').textContent = privateCount;
    
    // Compter par featured
    const featuredCount = Array.from(allCards).filter(card => 
        card.querySelector('.badge.featured')).length;
    
    document.getElementById('count-featured-all').textContent = allCards.length;
    document.getElementById('count-featured-true').textContent = featuredCount;
    document.getElementById('count-featured-false').textContent = allCards.length - featuredCount;
    
    // Compter par tags
    const withTagsCount = Array.from(allCards).filter(card => 
        card.querySelectorAll('.tag').length > 0).length;
    const withoutTagsCount = allCards.length - withTagsCount;
    
    document.getElementById('count-tags-all').textContent = allCards.length;
    document.getElementById('count-tags-with').textContent = withTagsCount;
    document.getElementById('count-tags-without').textContent = withoutTagsCount;
}

// Appliquer tous les filtres
function applyAllFilters() {
    document.querySelectorAll('.activity-card').forEach(card => {
        let show = true;
        
        // Filtre de recherche
        if (activeFilters.search) {
            const activityName = card.querySelector('.activity-title').textContent.toLowerCase();
            const tags = Array.from(card.querySelectorAll('.tag')).map(tag => tag.textContent.toLowerCase());
            const searchableText = [activityName, ...tags].join(' ');
            
            if (!searchableText.includes(activeFilters.search)) {
                show = false;
            }
        }
        
        // Filtre visibilité
        if (activeFilters.visibility) {
            const isPublic = card.querySelector('.badge.visibility.public') !== null;
            if ((activeFilters.visibility === 'public' && !isPublic) || 
                (activeFilters.visibility === 'private' && isPublic)) {
                show = false;
            }
        }
        
        // Filtre featured
        if (activeFilters.featured) {
            const isFeatured = card.querySelector('.badge.featured') !== null;
            if ((activeFilters.featured === 'true' && !isFeatured) || 
                (activeFilters.featured === 'false' && isFeatured)) {
                show = false;
            }
        }
        
        // Filtre tags
        if (activeFilters.tags) {
            const hasTags = card.querySelectorAll('.tag').length > 0;
            if ((activeFilters.tags === 'has-tags' && !hasTags) || 
                (activeFilters.tags === 'no-tags' && hasTags)) {
                show = false;
            }
        }
        
        // Filtre par tags sélectionnés
        if (selectedTagsForFilter.size > 0) {
            const cardTags = Array.from(card.querySelectorAll('.tag')).map(tag => tag.textContent.trim());
            const hasAnySelectedTag = Array.from(selectedTagsForFilter).some(selectedTag => 
                cardTags.includes(selectedTag)
            );
            if (!hasAnySelectedTag) {
                show = false;
            }
        }
        
        // Animation d'affichage/masquage
        if (show) {
            card.style.display = 'block';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        } else {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                if (card.style.opacity === '0') {
                    card.style.display = 'none';
                }
            }, 200);
        }
    });
    
    setTimeout(updateVisibleActivitiesCount, 250);
}

// Mettre à jour le résumé des filtres actifs
function updateActiveFiltersSummary() {
    const summary = document.getElementById('active-filters-summary');
    const active = [];
    
    if (activeFilters.search) active.push(`Recherche: "${activeFilters.search}"`);
    if (activeFilters.visibility) active.push(`Visibilité: ${activeFilters.visibility}`);
    if (activeFilters.featured) active.push(`Featured: ${activeFilters.featured === 'true' ? 'Oui' : 'Non'}`);
    if (activeFilters.tags) active.push(`Tags: ${activeFilters.tags === 'has-tags' ? 'Avec' : 'Sans'}`);
    if (selectedTagsForFilter.size > 0) active.push(`Tags spécifiques: ${Array.from(selectedTagsForFilter).join(', ')}`);
    
    summary.textContent = active.join(' • ');
}

// Clear tous les filtres
function clearAllFilters() {
    // Reset des filtres
    activeFilters = { visibility: '', featured: '', tags: '', search: '' };
    selectedTagsForFilter.clear();
    
    // Reset de l'interface
    document.getElementById('search-activities').value = '';
    document.getElementById('clear-search').style.display = 'none';
    
    document.querySelectorAll('.filter-toggle').forEach(btn => {
        btn.classList.remove('active');
    });
    
    // Activer les boutons "Toutes"
    document.querySelectorAll('[data-value=""]').forEach(btn => {
        btn.classList.add('active');
    });
    
    renderSearchTagsCloud();
    
    // Réafficher toutes les cartes
    document.querySelectorAll('.activity-card').forEach(card => {
        card.style.display = 'block';
        card.style.opacity = '1';
        card.style.transform = 'scale(1)';
    });
    
    updateVisibleActivitiesCount();
    updateActiveFiltersSummary();
}
 
function openEditModal(activityKey) {
    const modal = document.getElementById('editModal');
    const activity = activities[activityKey];
    
    if (activity) {
        // Remplir le formulaire avec les données
        document.getElementById('edit_activity_key').value = activityKey;
        document.getElementById('edit_activity_name').value = activity.name;
        document.getElementById('edit_activity_description').value = activity.description || '';
        document.getElementById('edit_activity_tags').value = activity.tags.join(', ');
        document.getElementById('edit_activity_visibility').value = activity.visibility;
        document.getElementById('edit_activity_featured').checked = activity.featured;
        
        // Afficher la modal
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

function closeEditModal() {
    const modal = document.getElementById('editModal');
    modal.classList.remove('show');
    document.body.style.overflow = 'auto';
}

function deleteActivity(activityKey) {
    if (confirm('Êtes-vous sûr de vouloir supprimer cette activité ?')) {
        // Ici, vous ajouteriez la logique de suppression PHP
        console.log('Suppression de l\'activité:', activityKey);
    }
}

// Fermer la modal en cliquant à l'extérieur
document.getElementById('editModal').addEventListener('click', function(e) {
    if (e.target === this) {
        closeEditModal();
    }
});

// Fermer la modal avec la touche Échap
document.addEventListener('keydown', function(e) {
    if (e.key === 'Escape') {
        closeEditModal();
    }
});

function filterActivities() {
    const searchTerm = document.getElementById('search-activities').value.toLowerCase();
    const visibilityValue = document.getElementById('filter-visibility').value;
    const featuredValue = document.getElementById('filter-featured').value;

    document.querySelectorAll('.activity-card').forEach(card => {
        const activityKey = card.getAttribute('onclick').match(/'([^']+)'/)[1];
        const activityName = card.querySelector('.activity-title').textContent.toLowerCase();
        const isFeatured = card.querySelector('.badge.featured') !== null;
        const visibility = card.querySelector('.badge.visibility.public') ? 'public' : 'private';
        const cardTags = Array.from(card.querySelectorAll('.tag')).map(tag => tag.textContent.toLowerCase());

        let show = true;

        // Filtre de recherche
        if (searchTerm) {
            const matchesName = activityName.includes(searchTerm);
            const matchesKey = activityKey.toLowerCase().includes(searchTerm);
            const matchesTags = cardTags.some(tag => tag.includes(searchTerm));
            
            if (!matchesName && !matchesKey && !matchesTags) {
                show = false;
            }
        }

        // Filtre de visibilité
        if (visibilityValue && visibility !== visibilityValue) {
            show = false;
        }

        // Filtre featured
        if (featuredValue === 'true' && !isFeatured) {
            show = false;
        } else if (featuredValue === 'false' && isFeatured) {
            show = false;
        }

        // Filtre par tags sélectionnés
        if (selectedTagsForFilter.size > 0) {
            const cardTagsOriginal = Array.from(card.querySelectorAll('.tag')).map(tag => tag.textContent.trim());
            const hasAnySelectedTag = Array.from(selectedTagsForFilter).some(selectedTag => 
                cardTagsOriginal.includes(selectedTag)
            );
            if (!hasAnySelectedTag) {
                show = false;
            }
        }

        // Animation fluide pour masquer/afficher
        if (show) {
            card.style.display = 'block';
            card.style.opacity = '1';
            card.style.transform = 'scale(1)';
        } else {
            card.style.opacity = '0';
            card.style.transform = 'scale(0.95)';
            setTimeout(() => {
                if (card.style.opacity === '0') {
                    card.style.display = 'none';
                }
            }, 200);
        }
    });

    // Petite temporisation pour que l'animation se termine avant de compter
    setTimeout(updateVisibleActivitiesCount, 250);
}

// Version alternative plus propre si vous voulez extraire la clé d'activité autrement
function filterActivitiesAlt() {
    const searchTerm = searchInput.value.toLowerCase();
    const visibilityValue = visibilityFilter.value;
    const featuredValue = featuredFilter.value;

    document.querySelectorAll('.activity-card').forEach(card => {
        // Ajouter un data-activity-key aux cartes pour une meilleure sélection
        const activityKey = card.dataset.activityKey; // Nécessite d'ajouter data-activity-key="<?php echo $activityKey; ?>"
        const activityName = card.querySelector('.activity-title').textContent.toLowerCase();
        const isFeatured = card.querySelector('.badge.featured') !== null;
        const visibility = card.querySelector('.badge.visibility.public') ? 'public' : 'private';
        
        // Récupérer les tags
        const tags = Array.from(card.querySelectorAll('.tag')).map(tag => tag.textContent.toLowerCase());

        let show = true;

        // Filtre de recherche étendu
        if (searchTerm) {
            const searchableText = [
                activityName,
                activityKey?.toLowerCase() || '',
                ...tags
            ].join(' ');
            
            if (!searchableText.includes(searchTerm)) {
                show = false;
            }
        }

        // Filtre de visibilité
        if (visibilityValue && visibility !== visibilityValue) {
            show = false;
        }

        // Filtre featured
        if (featuredValue === 'true' && !isFeatured) {
            show = false;
        } else if (featuredValue === 'false' && isFeatured) {
            show = false;
        }

        // Animation de filtrage
        card.style.transition = 'all 0.3s ease';
        card.style.display = show ? 'block' : 'none';
    });
}

// Initialiser les nuages de tags
function initializeTagsClouds() {
    // Collecter tous les tags des activités
    document.querySelectorAll('.activity-card .tag').forEach(tagElement => {
        allTags.add(tagElement.textContent.trim());
    });
    
    renderSearchTagsCloud();
    renderModalTagsCloud();
}

// Ajouter un badge avec le nombre de tags sélectionnés
function renderSearchTagsCloud() {
    const container = document.getElementById('search-tags-cloud');
    const section = container.parentElement;
    
    // Supprimer l'ancien badge s'il existe
    const oldBadge = section.querySelector('.active-filters-badge');
    if (oldBadge) oldBadge.remove();
    
    container.innerHTML = '';
    
    Array.from(allTags).sort().forEach(tag => {
        const tagElement = document.createElement('span');
        tagElement.className = `tag-filter ${selectedTagsForFilter.has(tag) ? 'active' : ''}`;
        tagElement.textContent = tag;
        tagElement.onclick = () => toggleTagFilter(tag);
        container.appendChild(tagElement);
    });
    
    // Ajouter le badge si des tags sont sélectionnés
    if (selectedTagsForFilter.size > 0) {
        const badge = document.createElement('div');
        badge.className = 'active-filters-badge';
        badge.textContent = selectedTagsForFilter.size;
        badge.title = `${selectedTagsForFilter.size} tag(s) sélectionné(s)`;
        section.appendChild(badge);
    }
}

// Afficher le nuage de tags pour la modal
function renderModalTagsCloud() {
    const container = document.getElementById('modal-tags-cloud');
    container.innerHTML = '';
    
    Array.from(allTags).sort().forEach(tag => {
        const tagElement = document.createElement('span');
        tagElement.className = 'tag-filter small';
        tagElement.textContent = tag;
        tagElement.onclick = () => addTagToActivity(tag);
        container.appendChild(tagElement);
    });
}

// Fonction pour compter les activités visibles
function updateVisibleActivitiesCount() {
    const visibleCards = document.querySelectorAll('.activity-card').length - document.querySelectorAll('.activity-card[style*="none"]').length;
    const totalCards = document.querySelectorAll('.activity-card').length;
    
    // Mettre à jour le titre
    const filteredCountElement = document.getElementById('filtered-count');
    if (filteredCountElement) {
        filteredCountElement.textContent = visibleCards;
    }
    
    // Mettre à jour le compteur détaillé (optionnel)
    let counter = document.getElementById('activities-counter');
    if (!counter) {
        counter = document.createElement('div');
        counter.id = 'activities-counter';
        counter.className = 'activities-counter';
        document.querySelector('.search-section').appendChild(counter);
    }
    
    // Afficher des détails sur les filtres actifs
    let filterDetails = '';
    if (selectedTagsForFilter.size > 0) {
        filterDetails += ` • Tags: ${Array.from(selectedTagsForFilter).join(', ')}`;
    }
    
    const searchTerm = document.getElementById('search-activities').value;
    if (searchTerm) {
        filterDetails += ` • Recherche: "${searchTerm}"`;
    }
     
    counter.innerHTML = `
        <span class="counter-text">
            ${visibleCards === totalCards ? 'Toutes les activités affichées' : `${visibleCards} sur ${totalCards} activité(s) affichée(s)`}
            ${filterDetails}
        </span>
    `;
    
    // Cacher le compteur si toutes les activités sont affichées et aucun filtre n'est actif
    if (visibleCards === totalCards && filterDetails === '') {
        counter.style.display = 'none';
    } else {
        counter.style.display = 'block';
    }
}

// Gérer le clic sur un tag de filtre
function toggleTagFilter(tag) {
    if (selectedTagsForFilter.has(tag)) {
        selectedTagsForFilter.delete(tag);
    } else {
        selectedTagsForFilter.add(tag);
    }
    
    renderSearchTagsCloud();
    filterActivitiesByTags();
    
    // Feedback visuel
    if (selectedTagsForFilter.size > 0) {
        document.getElementById('search-tags-cloud').classList.add('has-active-filters');
    } else {
        document.getElementById('search-tags-cloud').classList.remove('has-active-filters');
    }
}

// Filtrer les activités par tags sélectionnés
function filterActivitiesByTags() {
    document.querySelectorAll('.activity-card').forEach(card => {
        const cardTags = Array.from(card.querySelectorAll('.tag')).map(tag => tag.textContent.trim());
        
        let show = true;
        
        // Si des tags sont sélectionnés pour le filtre
        if (selectedTagsForFilter.size > 0) {
            // Option 1: OR logique - afficher si l'activité a AU MOINS UN des tags sélectionnés
            //const hasAnySelectedTag = Array.from(selectedTagsForFilter).some(selectedTag => 
            //    cardTags.includes(selectedTag)
            //);
            //show = hasAnySelectedTag;
            
            // Option 2: AND logique - afficher si l'activité a TOUS les tags sélectionnés
            const hasAllSelectedTags = Array.from(selectedTagsForFilter).every(selectedTag => 
                cardTags.includes(selectedTag)
            );
            show = hasAllSelectedTags;
        }
        
        card.style.display = show ? 'block' : 'none';
    });
    
    // Mettre à jour le compteur d'activités visibles
    updateVisibleActivitiesCount();
}

// Ajouter un tag à l'activité en cours d'édition
function addTagToActivity(tag) {
    if (!selectedTagsForActivity.has(tag)) {
        selectedTagsForActivity.add(tag);
        renderSelectedTags();
        updateHiddenTagsInput();
    }
}

// Supprimer un tag de l'activité
function removeTagFromActivity(tag) {
    selectedTagsForActivity.delete(tag);
    renderSelectedTags();
    updateHiddenTagsInput();
}

// Afficher les tags sélectionnés comme des blocs
function renderSelectedTags() {
    const container = document.getElementById('selected-tags');
    container.innerHTML = '';
    
    Array.from(selectedTagsForActivity).forEach(tag => {
        const tagBlock = document.createElement('div');
        tagBlock.className = 'tag-block';
        tagBlock.innerHTML = `
            <span class="tag-text">${tag}</span>
            <button type="button" class="tag-remove" onclick="removeTagFromActivity('${tag}')">&times;</button>
        `;
        container.appendChild(tagBlock);
    });
}

// Mettre à jour l'input caché avec la liste des tags
function updateHiddenTagsInput() {
    document.getElementById('edit_activity_tags_hidden').value = Array.from(selectedTagsForActivity).join(', ');
}

// Gérer l'ajout de tags via l'input
document.getElementById('edit_activity_tags_input').addEventListener('keypress', function(e) {
    if (e.key === 'Enter') {
        e.preventDefault();
        const tag = this.value.trim();
        if (tag && !selectedTagsForActivity.has(tag)) {
            addTagToActivity(tag);
            allTags.add(tag); // Ajouter à la liste globale
            renderModalTagsCloud(); // Mettre à jour le nuage
        }
        this.value = '';
    }
});

// Modifier la fonction openEditModal
function openEditModal(activityKey) {
    const modal = document.getElementById('editModal');
    const activity = activities[activityKey];
    
    if (activity) {
        // Réinitialiser les tags sélectionnés
        selectedTagsForActivity.clear();
        activity.tags.forEach(tag => selectedTagsForActivity.add(tag));
        
        // Remplir le formulaire
        document.getElementById('edit_activity_key').value = activityKey;
        document.getElementById('edit_activity_name').value = activity.name;
        document.getElementById('edit_activity_description').value = activity.description || '';
        document.getElementById('edit_activity_visibility').value = activity.visibility;
        document.getElementById('edit_activity_featured').checked = activity.featured;
        const pricingSelect = document.getElementById('edit_activity_pricing_type');
        if (pricingSelect) {
            pricingSelect.value = activity.pricing_type || 'PHOTO'; // Valeur par défaut
        }
        
        // Afficher les tags
        renderSelectedTags();
        updateHiddenTagsInput();
        
        modal.classList.add('show');
        document.body.style.overflow = 'hidden';
    }
}

// Initialiser le compteur au chargement
document.addEventListener('DOMContentLoaded', function() {
    initializeTagsClouds();
    initializeFilters();
    
    // Activer les filtres "Toutes" par défaut
    document.querySelectorAll('[data-value=""]').forEach(btn => {
        btn.classList.add('active');
    });
});