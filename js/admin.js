// JavaScript pour l'interface d'administration

document.addEventListener('DOMContentLoaded', function() {
    initializeAdminInterface();
    setupFormValidation();
    setupAutoSave();
    initializePhotoPreview();
});

// Initialiser l'interface d'administration
function initializeAdminInterface() {
    // Gestion des formulaires d'action
    const actionForms = document.querySelectorAll('.action-form form');
    actionForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                showLoadingState(button);
            }
        });
    });
    
    // Gestion des formulaires d'activité
    const activityForms = document.querySelectorAll('.activity-form');
    activityForms.forEach(form => {
        form.addEventListener('submit', function(e) {
            const button = this.querySelector('button[type="submit"]');
            if (button) {
                showLoadingState(button);
            }
        });
    });
    
    // Confirmation pour les actions importantes
    const dangerousActions = document.querySelectorAll('[data-confirm]');
    dangerousActions.forEach(element => {
        element.addEventListener('click', function(e) {
            const message = this.dataset.confirm || 'Êtes-vous sûr de vouloir effectuer cette action ?';
            if (!confirm(message)) {
                e.preventDefault();
            }
        });
    });
}

// Afficher l'état de chargement
function showLoadingState(button) {
    const originalText = button.textContent;
    button.textContent = 'Traitement...';
    button.disabled = true;
    button.classList.add('loading');
    
    // Ajouter un spinner
    const spinner = document.createElement('span');
    spinner.className = 'spinner';
    button.appendChild(spinner);
    
    // Restaurer l'état après un timeout (au cas où)
    setTimeout(() => {
        restoreButtonState(button, originalText);
    }, 30000);
}

// Restaurer l'état du bouton
function restoreButtonState(button, originalText) {
    button.textContent = originalText;
    button.disabled = false;
    button.classList.remove('loading');
    const spinner = button.querySelector('.spinner');
    if (spinner) {
        spinner.remove();
    }
}

// Validation des formulaires
function setupFormValidation() {
    const tagInputs = document.querySelectorAll('input[name="tags"]');
    tagInputs.forEach(input => {
        input.addEventListener('input', function() {
            validateTags(this);
        });
        
        input.addEventListener('blur', function() {
            formatTags(this);
        });
    });
}

// Valider les tags
function validateTags(input) {
    const value = input.value;
    const tags = value.split(',').map(tag => tag.trim()).filter(tag => tag.length > 0);
    
    // Supprimer les caractères spéciaux
    const cleanTags = tags.map(tag => tag.replace(/[^a-zA-Z0-9\sÀ-ÿ-]/g, ''));
    
    // Limiter la longueur
    const validTags = cleanTags.filter(tag => tag.length <= 50);
    
    if (validTags.length !== tags.length) {
        showFieldError(input, 'Certains tags contiennent des caractères non autorisés ou sont trop longs');
    } else {
        clearFieldError(input);
    }
}

// Formater les tags
function formatTags(input) {
    const value = input.value;
    const tags = value.split(',')
        .map(tag => tag.trim())
        .filter(tag => tag.length > 0)
        .map(tag => tag.toLowerCase());
    
    // Supprimer les doublons
    const uniqueTags = [...new Set(tags)];
    
    input.value = uniqueTags.join(', ');
}

// Afficher une erreur de champ
function showFieldError(field, message) {
    clearFieldError(field);
    
    const errorDiv = document.createElement('div');
    errorDiv.className = 'field-error';
    errorDiv.textContent = message;
    
    field.parentNode.appendChild(errorDiv);
    field.classList.add('error');
}

// Effacer l'erreur de champ
function clearFieldError(field) {
    const existingError = field.parentNode.querySelector('.field-error');
    if (existingError) {
        existingError.remove();
    }
    field.classList.remove('error');
}

// Auto-sauvegarde
function setupAutoSave() {
    const autoSaveInputs = document.querySelectorAll('.activity-form input, .activity-form textarea');
    const autoSaveData = {};
    
    autoSaveInputs.forEach(input => {
        const key = `autosave_${input.name}_${input.closest('form').querySelector('[name="activity_key"]').value}`;
        
        // Charger les données sauvegardées
        const savedValue = localStorage.getItem(key);
        if (savedValue && input.value === '') {
            input.value = savedValue;
            showNotification('Données auto-sauvegardées restaurées', 'info');
        }
        
        // Sauvegarder lors des modifications
        input.addEventListener('input', debounce(() => {
            localStorage.setItem(key, input.value);
            autoSaveData[key] = input.value;
        }, 1000));
        
        // Nettoyer la sauvegarde lors de la soumission
        input.closest('form').addEventListener('submit', () => {
            localStorage.removeItem(key);
        });
    });
}

// Aperçu des photos
function initializePhotoPreview() {
    const photoThumbnails = document.querySelectorAll('.photo-thumbnail');
    
    photoThumbnails.forEach(thumbnail => {
        thumbnail.addEventListener('click', function() {
            showPhotoPreview(this.src, this.alt);
        });
        
        // Effet de survol
        thumbnail.addEventListener('mouseenter', function() {
            this.style.transform = 'scale(1.1)';
        });
        
        thumbnail.addEventListener('mouseleave', function() {
            this.style.transform = 'scale(1)';
        });
    });
}

// Afficher l'aperçu d'une photo
function showPhotoPreview(src, alt) {
    const modal = createPhotoPreviewModal(src, alt);
    document.body.appendChild(modal);
    
    setTimeout(() => {
        modal.classList.add('show');
    }, 10);
    
    // Fermeture
    modal.addEventListener('click', function(e) {
        if (e.target === this || e.target.classList.contains('close')) {
            closePhotoPreview(modal);
        }
    });
    
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closePhotoPreview(modal);
        }
    });
}

// Créer la modal d'aperçu photo
function createPhotoPreviewModal(src, alt) {
    const modal = document.createElement('div');
    modal.className = 'photo-preview-modal';
    modal.innerHTML = `
        <div class="photo-preview-content">
            <span class="close">&times;</span>
            <img src="${src}" alt="${alt}">
            <div class="photo-info">
                <h3>${alt}</h3>
            </div>
        </div>
    `;
    return modal;
}

// Fermer l'aperçu photo
function closePhotoPreview(modal) {
    modal.classList.remove('show');
    setTimeout(() => {
        if (modal.parentNode) {
            modal.parentNode.removeChild(modal);
        }
    }, 300);
}

// Statistiques en temps réel
function updateStatistics() {
    const activityItems = document.querySelectorAll('.activity-item');
    let totalPhotos = 0;
    let totalTags = new Set();
    
    activityItems.forEach(item => {
        const photosCount = parseInt(item.querySelector('.photo-count').textContent);
        totalPhotos += photosCount;
        
        const tagsInput = item.querySelector('input[name="tags"]');
        if (tagsInput.value) {
            const tags = tagsInput.value.split(',').map(tag => tag.trim());
            tags.forEach(tag => totalTags.add(tag));
        }
    });
    
    // Mettre à jour les cartes de statistiques
    const photosStat = document.querySelector('.stat-card:nth-child(2) h3');
    const tagsStat = document.querySelector('.stat-card:nth-child(3) h3');
    
    if (photosStat) photosStat.textContent = totalPhotos;
    if (tagsStat) tagsStat.textContent = totalTags.size;
}

// Recherche dans l'administration
function setupAdminSearch() {
    const searchInput = document.createElement('input');
    searchInput.type = 'text';
    searchInput.className = 'admin-search';
    searchInput.placeholder = 'Rechercher une activité...';
    
    const activitiesList = document.querySelector('.activities-list');
    if (activitiesList) {
        activitiesList.parentNode.insertBefore(searchInput, activitiesList);
        
        searchInput.addEventListener('input', function() {
            const query = this.value.toLowerCase();
            const activityItems = document.querySelectorAll('.activity-item');
            
            activityItems.forEach(item => {
                const activityName = item.querySelector('.activity-header h3').textContent.toLowerCase();
                const isMatch = activityName.includes(query);
                
                item.style.display = isMatch ? 'block' : 'none';
            });
        });
    }
}

// Export de données
function exportData(type) {
    let data;
    let filename;
    
    switch(type) {
        case 'activities':
            data = collectActivitiesData();
            filename = 'activities_export.json';
            break;
        case 'photos':
            data = collectPhotosData();
            filename = 'photos_export.json';
            break;
        default:
            return;
    }
    
    downloadJSON(data, filename);
}

// Collecter les données des activités
function collectActivitiesData() {
    const activities = [];
    const activityItems = document.querySelectorAll('.activity-item');
    
    activityItems.forEach(item => {
        const name = item.querySelector('.activity-header h3').textContent;
        const photoCount = parseInt(item.querySelector('.photo-count').textContent);
        const tagsInput = item.querySelector('input[name="tags"]');
        const descriptionInput = item.querySelector('textarea[name="description"]');
        
        activities.push({
            name: name,
            photoCount: photoCount,
            tags: tagsInput.value.split(',').map(tag => tag.trim()).filter(tag => tag.length > 0),
            description: descriptionInput.value
        });
    });
    
    return activities;
}

// Télécharger un fichier JSON
function downloadJSON(data, filename) {
    const blob = new Blob([JSON.stringify(data, null, 2)], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    
    const a = document.createElement('a');
    a.href = url;
    a.download = filename;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    
    URL.revokeObjectURL(url);
}

// Notification spécifique à l'admin
function showAdminNotification(message, type = 'success', duration = 5000) {
    const notification = document.createElement('div');
    notification.className = `admin-notification admin-notification-${type}`;
    notification.innerHTML = `
        <div class="notification-icon">
            ${type === 'success' ? '✓' : type === 'error' ? '✗' : 'ℹ'}
        </div>
        <div class="notification-message">${message}</div>
        <button class="notification-close">&times;</button>
    `;
    
    document.body.appendChild(notification);
    
    setTimeout(() => {
        notification.classList.add('show');
    }, 10);
    
    // Auto-fermeture
    setTimeout(() => {
        closeNotification(notification);
    }, duration);
    
    // Fermeture manuelle
    notification.querySelector('.notification-close').addEventListener('click', () => {
        closeNotification(notification);
    });
}

// Fermer une notification
function closeNotification(notification) {
    notification.classList.remove('show');
    setTimeout(() => {
        if (notification.parentNode) {
            notification.parentNode.removeChild(notification);
        }
    }, 300);
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

// Validation en temps réel
function setupRealTimeValidation() {
    const forms = document.querySelectorAll('.activity-form');
    
    forms.forEach(form => {
        const inputs = form.querySelectorAll('input, textarea');
        
        inputs.forEach(input => {
            input.addEventListener('blur', function() {
                validateField(this);
            });
        });
    });
}

// Valider un champ
function validateField(field) {
    const value = field.value.trim();
    let isValid = true;
    let errorMessage = '';
    
    switch(field.name) {
        case 'tags':
            if (value) {
                const tags = value.split(',').map(tag => tag.trim());
                const invalidTags = tags.filter(tag => tag.length > 50 || !/^[a-zA-Z0-9\sÀ-ÿ-]+$/.test(tag));
                
                if (invalidTags.length > 0) {
                    isValid = false;
                    errorMessage = 'Certains tags contiennent des caractères non autorisés ou sont trop longs';
                }
            }
            break;
            
        case 'description':
            if (value.length > 500) {
                isValid = false;
                errorMessage = 'La description ne peut pas dépasser 500 caractères';
            }
            break;
    }
    
    if (isValid) {
        clearFieldError(field);
    } else {
        showFieldError(field, errorMessage);
    }
    
    return isValid;
}

// Gestion des accordéons
function toggleAccordion(header) {
    const content = header.nextElementSibling;
    const toggle = header.querySelector('.accordion-toggle');
    
    if (content.classList.contains('active')) {
        content.classList.remove('active');
        toggle.textContent = '▼';
    } else {
        content.classList.add('active');
        toggle.textContent = '▲';
    }
}

// Initialisation finale
window.addEventListener('load', function() {
    updateStatistics();
    setupAdminSearch();
    setupRealTimeValidation();
    
    // Message de bienvenue
    showAdminNotification('Interface d\'administration chargée avec succès', 'success', 3000);
});