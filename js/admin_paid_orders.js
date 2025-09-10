/**
 * Script pour la gestion des commandes réglées
 * @version 1.4 - Ajout du filtre de recherche et correction globalité des fonctions
 */

// Variables globales pour les modales
let currentImageIndex = 0;
let currentImageList = [];

/**
 * Affiche la modale de confirmation de récupération
 * @param {string} reference - Référence de la commande
 * @version 1.0
 */
function showRetrievedModal(reference) {
    const order = ordersData.find(o => o.reference === reference);
    if (!order) {
        alert('Commande non trouvée');
        return;
    }
    
    document.getElementById('retrieved-order-reference').textContent = reference;
    document.getElementById('retrieved-customer-name').textContent = order.firstname + ' ' + order.lastname;
    
    // Stocker la référence pour la confirmation
    document.getElementById('retrievedModal').dataset.reference = reference;
    
    openModal('retrievedModal');
}

/**
 * Confirme qu'une commande a été récupérée
 * @version 1.0
 */
function confirmOrderRetrieved() {
    const modal = document.getElementById('retrievedModal');
    const reference = modal.dataset.reference;
    
    if (!reference) {
        alert('Référence de commande manquante');
        return;
    }
    
    // Affichage du loader
    const confirmButton = modal.querySelector('.btn-primary');
    const originalText = confirmButton.textContent;
    confirmButton.textContent = 'Traitement...';
    confirmButton.disabled = true;
    
    fetch('admin_paid_orders_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=mark_as_retrieved&reference=' + encodeURIComponent(reference)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            closeModal('retrievedModal');
            
            // Récupérer les détails de la commande pour les stats
            const order = ordersData ? ordersData.find(o => o.reference === reference) : null;
            const orderPhotos = order ? (order.total_photos || 0) : 0;
            const orderAmount = order ? (order.amount || order.total_price || 0) : 0;
            
            // Mettre à jour les statistiques en temps réel
            updateRetrievedTodayStats();
            updateOrdersToRetrieveStats(-1, -orderPhotos, -orderAmount);
            
            // Supprimer la commande de la liste affichée
            const orderCard = document.querySelector(`[data-reference="${reference}"]`);
            if (orderCard) {
                orderCard.style.opacity = '0.5';
                orderCard.style.transform = 'translateX(-100%)';
                setTimeout(() => {
                    orderCard.remove();
                    // Vérifier s'il n'y a plus de commandes
                    checkIfNoOrdersLeft();
                }, 500);
            }
        } else {
            alert('Erreur : ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors du traitement');
    })
    .finally(() => {
        confirmButton.textContent = originalText;
        confirmButton.disabled = false;
    });
}

// ================================
// FONCTIONS MODALES COMMUNES
// ================================

/**
 * Ouvre une modale
 * @param {string} modalId - ID de la modale à ouvrir
 */
function openModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'flex';
        document.body.style.overflow = 'hidden';
        
        // Focus sur la modale pour l'accessibilité
        modal.focus();
    }
}

/**
 * Ferme une modale
 * @param {string} modalId - ID de la modale à fermer
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
    }
}

/**
 * Affiche la modale de contact pour une commande
 * @param {string} reference - Référence de la commande
 */
async function showContactModal(reference) {
    let order = ordersData ? ordersData.find(o => o.reference === reference) : null;
    
    // Si pas trouvé dans ordersData, récupérer depuis l'API
    if (!order) {
        try {
            const response = await fetch('admin_paid_orders_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_contact&reference=${encodeURIComponent(reference)}`
            });
            
            const result = await response.json();
            
            if (result.success && result.contact) {
                order = {
                    reference: result.contact.reference,
                    firstname: result.contact.firstname,
                    lastname: result.contact.lastname,
                    email: result.contact.email,
                    phone: result.contact.phone,
                    amount: result.contact.amount,
                    total_price: result.contact.amount,
                    total_photos: result.contact.total_photos
                };
            } else {
                alert('Impossible de récupérer les informations de contact');
                return;
            }
        } catch (error) {
            console.error('Erreur récupération contact:', error);
            alert('Erreur lors de la récupération des informations de contact');
            return;
        }
    }
    
    // Remplir les informations de la modale
    document.getElementById('contact-reference').textContent = reference;
    document.getElementById('contact-customer-name').textContent = `${order.firstname} ${order.lastname}`;
    document.getElementById('contact-order-date').textContent = formatDate(order.created_at || order.order_date);
    document.getElementById('contact-amount').textContent = order.amount || order.total_price || '0.00';
    document.getElementById('contact-photos-count').textContent = order.total_photos || 0;
    
    // Informations de contact
    document.getElementById('contact-email').textContent = order.email;
    document.getElementById('contact-phone').textContent = order.phone;
    
    // Configurer les liens
    const emailLink = document.getElementById('contact-email-link');
    emailLink.href = `mailto:${order.email}?subject=Gala 2025 - Commande ${reference}`;
    
    const phoneLink = document.getElementById('contact-phone-link');
    phoneLink.href = `tel:${order.phone}`;
    
    openModal('contactModal');
}

/**
 * Affiche la modale de détails pour une commande
 * @param {string} reference - Référence de la commande
 */
async function showDetailsModal(reference) {
    let order = ordersData ? ordersData.find(o => o.reference === reference) : null;
    
    // Si pas trouvé dans ordersData, récupérer depuis l'API
    if (!order) {
        try {
            const response = await fetch('admin_paid_orders_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_contact&reference=${encodeURIComponent(reference)}`
            });
            
            const result = await response.json();
            
            if (result.success && result.contact) {
                order = {
                    reference: result.contact.reference,
                    firstname: result.contact.firstname,
                    lastname: result.contact.lastname,
                    email: result.contact.email,
                    phone: result.contact.phone,
                    amount: result.contact.amount,
                    total_price: result.contact.amount,
                    total_photos: result.contact.total_photos,
                    command_status: 'paid', // Statut par défaut pour les commandes payées
                    payment_mode: 'unknown'
                };
            } else {
                alert('Impossible de récupérer les informations de la commande');
                return;
            }
        } catch (error) {
            console.error('Erreur récupération détails:', error);
            alert('Erreur lors de la récupération des détails de la commande');
            return;
        }
    }
    
    // Remplir les informations générales
    document.getElementById('details-reference').textContent = reference;
    document.getElementById('details-customer-name').textContent = `${order.firstname} ${order.lastname}`;
    document.getElementById('details-customer-email').textContent = order.email;
    document.getElementById('details-customer-phone').textContent = order.phone;
    document.getElementById('details-order-date').textContent = formatDate(order.created_at || order.order_date);
    document.getElementById('details-retrieval-date').textContent = order.retrieval_date ? formatDate(order.retrieval_date) : 'Non définie';
    document.getElementById('details-payment-mode').textContent = formatPaymentMode(order.payment_mode);
    
    // Statut avec style
    const statusElement = document.getElementById('details-status');
    statusElement.textContent = formatOrderStatus(order.command_status);
    statusElement.className = `status-badge ${order.command_status}`;
    
    // Générer la liste des photos
    const photosContent = document.getElementById('photos-list-content');
    photosContent.innerHTML = '';
    
    if (order.photos && order.photos.length > 0) {
        let totalPhotos = 0;
        let totalAmount = 0;
        
        order.photos.forEach(photo => {
            const row = document.createElement('div');
            row.className = 'photo-row';
            
            const subtotal = (photo.quantity || 1) * (photo.unit_price || photo.price || 2);
            totalPhotos += photo.quantity || 1;
            totalAmount += subtotal;
            
            row.innerHTML = `
                <img src="image.php?src=${encodeURIComponent(photo.activity_key)}/${encodeURIComponent(photo.name)}&type=thumbnail" 
                     alt="${photo.name}" 
                     class="photo-thumbnail"
                     onclick="showImagePreview('${photo.activity_key}', '${photo.name}', '${photo.activity_key}')">
                <span title="${photo.name}">${photo.name}</span>
                <span>${photo.pricing_type || photo.activity_key}</span>
                <span>${photo.quantity || 1}</span>
                <span>${(photo.unit_price || photo.price || 2).toFixed(2)}€</span>
                <span>${subtotal.toFixed(2)}€</span>
            `;
            
            photosContent.appendChild(row);
        });
        
        // Mettre à jour les totaux
        document.getElementById('details-total-photos').textContent = totalPhotos;
        document.getElementById('details-total-amount').textContent = totalAmount.toFixed(2);
    } else {
        // Fallback pour les commandes sans détail photos
        const row = document.createElement('div');
        row.className = 'photo-row';
        row.innerHTML = `
            <span>-</span>
            <span>Photos de la commande</span>
            <span>-</span>
            <span>${order.total_photos || 1}</span>
            <span>2.00€</span>
            <span>${(order.amount || order.total_price || 0).toFixed(2)}€</span>
        `;
        photosContent.appendChild(row);
        
        document.getElementById('details-total-photos').textContent = order.total_photos || 1;
        document.getElementById('details-total-amount').textContent = (order.amount || order.total_price || 0).toFixed(2);
    }
    
    openModal('detailsModal');
}

/**
 * Affiche la modale de confirmation d'envoi d'email
 * @param {string} reference - Référence de la commande
 */
async function showEmailConfirmationModal(reference) {
    let order = ordersData ? ordersData.find(o => o.reference === reference) : null;
    
    // Si pas trouvé dans ordersData, récupérer depuis l'API
    if (!order) {
        try {
            const response = await fetch('admin_paid_orders_handler.php', {
                method: 'POST',
                headers: {
                    'Content-Type': 'application/x-www-form-urlencoded',
                },
                body: `action=get_contact&reference=${encodeURIComponent(reference)}`
            });
            
            const result = await response.json();
            
            if (result.success && result.contact) {
                order = {
                    reference: result.contact.reference,
                    firstname: result.contact.firstname,
                    lastname: result.contact.lastname,
                    email: result.contact.email,
                    amount: result.contact.amount,
                    total_price: result.contact.amount
                };
            } else {
                alert('Impossible de récupérer les informations de la commande');
                return;
            }
        } catch (error) {
            console.error('Erreur récupération email:', error);
            alert('Erreur lors de la récupération des informations de la commande');
            return;
        }
    }
    
    // Remplir les informations de la modale
    document.getElementById('email-order-reference').textContent = reference;
    document.getElementById('email-customer-name').textContent = `${order.firstname} ${order.lastname}`;
    document.getElementById('email-customer-email').textContent = order.email;
    document.getElementById('email-order-amount').textContent = (order.amount || order.total_price || 0).toFixed(2);
    
    openModal('emailConfirmationModal');
}

/**
 * Envoie l'email de confirmation pour une commande
 */
async function sendOrderConfirmationEmail() {
    const reference = document.getElementById('email-order-reference').textContent;
    
    if (!reference) {
        alert('Référence de commande manquante');
        return;
    }
    
    const sendButton = document.getElementById('send-email-btn');
    const originalText = sendButton.textContent;
    
    try {
        // Affichage du loader
        sendButton.textContent = '📧 Envoi en cours...';
        sendButton.disabled = true;
        
        const response = await fetch('admin_paid_orders_handler.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=resend_confirmation&reference=${encodeURIComponent(reference)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert('✅ Email envoyé avec succès !');
            closeModal('emailConfirmationModal');
        } else {
            alert('❌ Erreur lors de l\'envoi : ' + (result.error || 'Erreur inconnue'));
        }
    } catch (error) {
        console.error('Erreur:', error);
        alert('❌ Erreur de communication : ' + error.message);
    } finally {
        sendButton.textContent = originalText;
        sendButton.disabled = false;
    }
}

/**
 * Copie du texte dans le presse-papier
 * @param {string} elementId - ID de l'élément contenant le texte à copier
 */
async function copyToClipboard(elementId) {
    try {
        const element = document.getElementById(elementId);
        const text = element.textContent;
        
        await navigator.clipboard.writeText(text);
        
        // Feedback visuel
        const originalText = element.textContent;
        element.textContent = '✓ Copié !';
        element.style.color = 'green';
        
        setTimeout(() => {
            element.textContent = originalText;
            element.style.color = '';
        }, 1500);
        
    } catch (err) {
        console.error('Erreur copie:', err);
        // Fallback pour les navigateurs qui ne supportent pas l'API clipboard
        const element = document.getElementById(elementId);
        const range = document.createRange();
        range.selectNode(element);
        window.getSelection().removeAllRanges();
        window.getSelection().addRange(range);
        
        try {
            document.execCommand('copy');
            alert('Texte copié dans le presse-papier');
        } catch (e) {
            alert('Impossible de copier automatiquement. Veuillez sélectionner et copier manuellement.');
        }
        
        window.getSelection().removeAllRanges();
    }
}

/**
 * Affiche l'aperçu d'une image
 * @param {string} activityKey - Clé de l'activité
 * @param {string} imageName - Nom de l'image
 * @param {string} activityName - Nom de l'activité
 */
function showImagePreview(activityKey, imageName, activityName) {
    // Configurer l'image
    const previewImg = document.getElementById('preview-image');
    previewImg.src = `image.php?src=${encodeURIComponent(activityKey)}/${encodeURIComponent(imageName)}&type=resized&width=800&height=600`;
    
    // Configurer les informations
    document.getElementById('preview-image-name').textContent = imageName;
    document.getElementById('preview-image-activity').textContent = activityName;
    
    // Configurer la navigation (basique)
    document.getElementById('image-counter').textContent = '1 / 1';
    
    // Afficher la modale
    const modal = document.getElementById('imagePreviewModal');
    modal.style.display = 'flex';
}

/**
 * Ferme l'aperçu d'image
 */
function closeImagePreview() {
    const modal = document.getElementById('imagePreviewModal');
    modal.style.display = 'none';
}

/**
 * Navigation dans l'aperçu d'images (placeholder)
 * @param {number} direction - Direction de navigation (-1 ou 1)
 */
function navigatePreview(direction) {
    // Pour l'instant, navigation simple - peut être étendue plus tard
    console.log('Navigation image:', direction);
}

/**
 * Imprime le bon de commande
 * Note: Utilise la fonction commune du système d'impression
 */
function printOrderSlip(reference) {
    if (!reference) {
        // Si pas de référence fournie, essayer de la récupérer depuis la modale détails
        reference = document.getElementById('details-reference')?.textContent;
    }
    
    if (!reference) {
        alert('Référence de commande manquante');
        return;
    }
    
    // Ouvrir la fenêtre d'impression avec la référence
    const printUrl = `order_print.php?reference=${encodeURIComponent(reference)}`;
    window.open(printUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
}

// ================================
// FONCTIONS UTILITAIRES
// ================================

/**
 * Formate une date au format français
 * @param {string} dateString - Date à formater
 * @return {string} Date formatée
 */
function formatDate(dateString) {
    if (!dateString) return 'Non définie';
    
    try {
        const date = new Date(dateString);
        return date.toLocaleDateString('fr-FR', {
            day: '2-digit',
            month: '2-digit',
            year: 'numeric',
            hour: '2-digit',
            minute: '2-digit'
        });
    } catch (e) {
        return dateString;
    }
}

/**
 * Formate le mode de paiement
 * @param {string} paymentMode - Mode de paiement
 * @return {string} Mode formaté
 */
function formatPaymentMode(paymentMode) {
    const modes = {
        'card': '💳 Carte bancaire',
        'cash': '💰 Espèces',
        'check': '📄 Chèque',
        'transfer': '🏦 Virement',
        'unpaid': '❌ Non payé'
    };
    
    return modes[paymentMode] || paymentMode;
}

/**
 * Formate le statut de commande
 * @param {string} status - Statut de la commande
 * @return {string} Statut formaté
 */
function formatOrderStatus(status) {
    const statuses = {
        'temp': 'Temporaire',
        'validated': 'Validée',
        'paid': 'Payée',
        'prepared': 'Préparée',
        'retrieved': 'Récupérée',
        'cancelled': 'Annulée'
    };
    
    return statuses[status] || status;
}

// ================================
// SYSTÈME DE FILTRAGE
// ================================

/**
 * Normalise une chaîne pour la recherche (supprime accents, casse)
 * @param {string} str - Chaîne à normaliser
 * @return {string} Chaîne normalisée
 */
function normalizeString(str) {
    if (!str) return '';
    return str.toString()
        .toLowerCase()
        .normalize('NFD')
        .replace(/[\u0300-\u036f]/g, ''); // Supprime les accents
}

/**
 * Vérifie si une chaîne correspond aux critères de recherche par nom
 * @param {string} searchTerm - Terme de recherche
 * @param {string} firstName - Prénom
 * @param {string} lastName - Nom de famille
 * @return {boolean} true si correspond
 */
function matchesNameFilter(searchTerm, firstName, lastName) {
    if (!searchTerm || searchTerm.length < 2) return true;
    
    const normalizedSearch = normalizeString(searchTerm);
    const normalizedFirstName = normalizeString(firstName);
    const normalizedLastName = normalizeString(lastName);
    const normalizedFullName = normalizedFirstName + ' ' + normalizedLastName;
    
    return normalizedFirstName.includes(normalizedSearch) ||
           normalizedLastName.includes(normalizedSearch) ||
           normalizedFullName.includes(normalizedSearch);
}

/**
 * Vérifie si une référence correspond aux critères de recherche
 * @param {string} searchTerm - Terme de recherche
 * @param {string} reference - Référence de commande
 * @return {boolean} true si correspond
 */
function matchesReferenceFilter(searchTerm, reference) {
    if (!searchTerm) return true;
    
    // Normaliser la recherche
    const normalizedSearch = normalizeString(searchTerm);
    const normalizedReference = normalizeString(reference);
    
    // Supprimer CMD de la référence pour la comparaison
    const referenceWithoutCmd = normalizedReference.replace(/^cmd/, '');
    const searchWithoutCmd = normalizedSearch.replace(/^cmd/, '');
    
    // Filtrage par référence : minimum 5 caractères numériques
    const numericSearch = searchWithoutCmd.replace(/\D/g, '');
    if (numericSearch.length >= 5) {
        const numericReference = referenceWithoutCmd.replace(/\D/g, '');
        return numericReference.includes(numericSearch);
    }
    
    // Si moins de 5 chiffres, recherche standard
    return normalizedReference.includes(normalizedSearch);
}

/**
 * Applique le filtre de recherche
 * @param {string} searchTerm - Terme de recherche
 */
function applyOrdersFilter(searchTerm) {
    const orderCards = document.querySelectorAll('.order-card');
    const searchCounter = document.getElementById('search-counter');
    const noResultsDiv = document.getElementById('no-search-results');
    let visibleCount = 0;
    
    orderCards.forEach(card => {
        const reference = card.dataset.reference || '';
        const customerNameElement = card.querySelector('.customer-name');
        const customerName = customerNameElement ? customerNameElement.textContent : '';
        const [firstName, ...lastNameParts] = customerName.split(' ');
        const lastName = lastNameParts.join(' ');
        
        // Vérifier si la commande correspond aux critères
        const matchesName = matchesNameFilter(searchTerm, firstName, lastName);
        const matchesReference = matchesReferenceFilter(searchTerm, reference);
        
        if (matchesName || matchesReference) {
            card.style.display = 'block';
            visibleCount++;
        } else {
            card.style.display = 'none';
        }
    });
    
    // Mettre à jour le compteur
    if (searchTerm && searchTerm.length > 0) {
        if (visibleCount === 0) {
            searchCounter.textContent = 'Aucun résultat trouvé';
            searchCounter.className = 'search-counter no-results visible';
            noResultsDiv.style.display = 'block';
        } else {
            searchCounter.textContent = `${visibleCount} résultat${visibleCount > 1 ? 's' : ''} trouvé${visibleCount > 1 ? 's' : ''}`;
            searchCounter.className = 'search-counter has-results visible';
            noResultsDiv.style.display = 'none';
        }
    } else {
        searchCounter.textContent = '';
        searchCounter.className = 'search-counter';
        noResultsDiv.style.display = 'none';
    }
}

/**
 * Efface la recherche
 */
function clearSearch() {
    const searchInput = document.getElementById('orders-search');
    searchInput.value = '';
    applyOrdersFilter('');
    searchInput.focus();
}

// ================================
// GESTION DES ÉVÉNEMENTS
// ================================

// Cette initialisation sera faite dans la fonction DOMContentLoaded principale ci-dessous

// Fermer les modales avec la touche Échap
document.addEventListener('keydown', function(event) {
    if (event.key === 'Escape') {
        // Fermer toutes les modales ouvertes
        const modals = document.querySelectorAll('.modal');
        modals.forEach(modal => {
            if (modal.style.display === 'flex') {
                modal.style.display = 'none';
            }
        });
        
        // Fermer l'aperçu d'image
        const imageModal = document.getElementById('imagePreviewModal');
        if (imageModal && imageModal.style.display === 'flex') {
            imageModal.style.display = 'none';
        }
        
        // Restaurer le scroll
        document.body.style.overflow = 'auto';
    }
});

// Fermer les modales en cliquant à l'extérieur
document.addEventListener('click', function(event) {
    if (event.target.classList.contains('modal')) {
        const modalId = event.target.id;
        closeModal(modalId);
    }
    
    if (event.target.classList.contains('image-preview-modal')) {
        closeImagePreview();
    }
});

// ================================
// FONCTIONS MISE À JOUR TEMPS RÉEL
// ================================

/**
 * Met à jour le compteur "Récupérées aujourd'hui" en temps réel
 */
function updateRetrievedTodayStats() {
    const retrievedTodayElement = document.querySelector('.stat-card .stat-number');
    const retrievedTodayElements = document.querySelectorAll('.stat-card');
    
    // Trouver la stat card "Récupérées aujourd'hui"
    let retrievedTodayCard = null;
    retrievedTodayElements.forEach(card => {
        const label = card.querySelector('.stat-label');
        if (label && label.textContent.includes('Récupérées aujourd\'hui')) {
            retrievedTodayCard = card;
        }
    });
    
    if (retrievedTodayCard) {
        const numberElement = retrievedTodayCard.querySelector('.stat-number');
        let currentCount = parseInt(numberElement.textContent) || 0;
        currentCount++;
        
        // Animation de mise à jour
        numberElement.style.transform = 'scale(1.2)';
        numberElement.style.color = '#28a745';
        numberElement.textContent = currentCount;
        
        setTimeout(() => {
            numberElement.style.transform = 'scale(1)';
            numberElement.style.color = '';
        }, 300);
    }
    
    // Note: updateOrdersToRetrieveStats est appelée avec les détails dans confirmOrderRetrieved()
}

/**
 * Met à jour les statistiques des commandes à retirer
 * @param {number} ordersDelta Changement nombre de commandes (+1 ou -1)
 * @param {number} photosDelta Changement nombre de photos
 * @param {number} amountDelta Changement montant
 */
function updateOrdersToRetrieveStats(ordersDelta, photosDelta = 0, amountDelta = 0) {
    const statCards = document.querySelectorAll('.stat-card');
    
    statCards.forEach(card => {
        const label = card.querySelector('.stat-label');
        const numberElement = card.querySelector('.stat-number');
        
        if (label && numberElement) {
            const labelText = label.textContent;
            
            if (labelText.includes('Commandes à retirer')) {
                let currentCount = parseInt(numberElement.textContent) || 0;
                currentCount += ordersDelta;
                if (currentCount < 0) currentCount = 0;
                numberElement.textContent = currentCount;
                
                // Animation
                animateStatUpdate(numberElement);
                
            } else if (labelText.includes('Photos à retirer') && photosDelta !== 0) {
                let currentCount = parseInt(numberElement.textContent) || 0;
                currentCount += photosDelta;
                if (currentCount < 0) currentCount = 0;
                numberElement.textContent = currentCount;
                
                // Animation
                animateStatUpdate(numberElement);
                
            } else if (labelText.includes('Montant total') && amountDelta !== 0) {
                // Traiter le montant avec format €
                let currentAmount = parseFloat(numberElement.textContent.replace('€', '').replace(',', '.')) || 0;
                currentAmount += amountDelta;
                if (currentAmount < 0) currentAmount = 0;
                numberElement.textContent = currentAmount.toFixed(2) + '€';
                
                // Animation
                animateStatUpdate(numberElement);
            }
        }
    });
}

/**
 * Animation pour la mise à jour d'une statistique
 * @param {Element} element Element à animer
 */
function animateStatUpdate(element) {
    element.style.transition = 'transform 0.3s ease, color 0.3s ease';
    element.style.transform = 'scale(1.1)';
    element.style.color = '#28a745';
    
    setTimeout(() => {
        element.style.transform = 'scale(1)';
        element.style.color = '';
    }, 300);
}

/**
 * Vérifie s'il ne reste plus de commandes et affiche le message approprié
 */
function checkIfNoOrdersLeft() {
    const orderCards = document.querySelectorAll('.order-card');
    const visibleCards = Array.from(orderCards).filter(card => 
        card.style.display !== 'none' && card.offsetParent !== null
    );
    
    if (visibleCards.length === 0) {
        const ordersList = document.querySelector('.orders-list');
        if (ordersList) {
            const noOrdersDiv = document.createElement('div');
            noOrdersDiv.className = 'no-orders';
            noOrdersDiv.innerHTML = `
                <h3>Aucune commande en attente de retrait</h3>
                <p>Toutes les commandes ont été récupérées !</p>
                <p style="margin-top: 20px;">
                    <a href="admin_orders.php" class="btn btn-primary">← Retour aux commandes</a>
                </p>
            `;
            
            // Remplacer le contenu de la liste
            const existingList = ordersList.querySelector('h2').nextElementSibling;
            if (existingList) {
                existingList.replaceWith(noOrdersDiv);
            }
        }
    }
}

/**
 * Actualise les statistiques depuis l'API (fallback si besoin)
 */
async function refreshStats() {
    try {
        // Cette fonction pourrait faire un appel API pour récupérer les stats à jour
        // Pour l'instant, on utilise la mise à jour en temps réel
        console.log('Actualisation des statistiques...');
    } catch (error) {
        console.error('Erreur actualisation stats:', error);
    }
}

// ================================
// EXPOSITION GLOBALE DES FONCTIONS 
// ================================

// APPROCHE DIRECTE : Exposer immédiatement les fonctions au scope global
console.log('🔄 Exposition des fonctions admin_paid_orders.js...');

// Fonctions critiques pour les boutons HTML
window.showRetrievedModal = showRetrievedModal;
window.confirmOrderRetrieved = confirmOrderRetrieved;
window.showContactModal = showContactModal;
window.showDetailsModal = showDetailsModal;
window.showEmailConfirmationModal = showEmailConfirmationModal;
window.sendOrderConfirmationEmail = sendOrderConfirmationEmail;
window.printOrderSlip = printOrderSlip;
window.copyToClipboard = copyToClipboard;

// Fonctions modales
window.openModal = openModal;
window.closeModal = closeModal;
window.showImagePreview = showImagePreview;
window.closeImagePreview = closeImagePreview;
window.navigatePreview = navigatePreview;

// Fonctions de filtrage
window.normalizeString = normalizeString;
window.matchesNameFilter = matchesNameFilter;
window.matchesReferenceFilter = matchesReferenceFilter;
window.applyOrdersFilter = applyOrdersFilter;
window.clearSearch = clearSearch;

// Fonctions utilitaires
window.formatDate = formatDate;
window.formatPaymentMode = formatPaymentMode;
window.formatOrderStatus = formatOrderStatus;
window.updateRetrievedTodayStats = updateRetrievedTodayStats;
window.updateOrdersToRetrieveStats = updateOrdersToRetrieveStats;
window.animateStatUpdate = animateStatUpdate;
window.checkIfNoOrdersLeft = checkIfNoOrdersLeft;
window.refreshStats = refreshStats;

console.log('✅ Admin_paid_orders.js - Fonctions exposées au scope global');

// ================================
// VÉRIFICATION CHARGEMENT SCRIPT
// ================================

// Initialisation complète du script
document.addEventListener('DOMContentLoaded', function() {
    console.log('🔄 Initialisation admin_paid_orders.js...');
    
    // ================================
    // 1. VÉRIFICATION DES FONCTIONS
    // ================================
    const criticalFunctions = [
        'showRetrievedModal',
        'confirmOrderRetrieved', 
        'showContactModal',
        'showDetailsModal',
        'showEmailConfirmationModal',
        'printOrderSlip'
    ];
    
    console.log('🔍 Vérification des fonctions critiques...');
    
    const missingFunctions = criticalFunctions.filter(funcName => {
        const exists = typeof window[funcName] === 'function';
        if (!exists) {
            console.error(`❌ Fonction manquante: ${funcName}`);
        } else {
            console.log(`✅ Fonction trouvée: ${funcName}`);
        }
        return !exists;
    });
    
    if (missingFunctions.length > 0) {
        console.error('❌ Fonctions manquantes:', missingFunctions);
        console.error('Le script admin_paid_orders.js n\'est pas complètement chargé');
        return; // Arrêter l'initialisation si des fonctions manquent
    }
    
    // ================================
    // 2. INITIALISATION DU FILTRE DE RECHERCHE
    // ================================
    const searchInput = document.getElementById('orders-search');
    const clearButton = document.getElementById('clear-search');
    
    if (searchInput) {
        console.log('🔍 Initialisation du filtre de recherche...');
        
        // Filtrage en temps réel avec debouncing
        let debounceTimer;
        searchInput.addEventListener('input', function() {
            const searchTerm = this.value.trim();
            
            // Gérer la visibilité du bouton clear
            const clearButton = document.getElementById('clear-search');
            if (clearButton) {
                if (this.value.length > 0) {
                    clearButton.style.opacity = '1';
                    clearButton.style.visibility = 'visible';
                } else {
                    clearButton.style.opacity = '0';
                    clearButton.style.visibility = 'hidden';
                }
            }
            
            clearTimeout(debounceTimer);
            debounceTimer = setTimeout(() => {
                applyOrdersFilter(searchTerm);
            }, 300); // Attendre 300ms après la dernière saisie
        });
        
        // Gestion des raccourcis clavier
        searchInput.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                clearSearch();
                event.preventDefault();
            }
        });
        
        // Focus automatique avec Ctrl+F
        document.addEventListener('keydown', function(event) {
            if ((event.ctrlKey || event.metaKey) && event.key === 'f') {
                event.preventDefault();
                searchInput.focus();
            }
        });
        
        console.log('✅ Filtre de recherche initialisé');
    } else {
        console.warn('⚠️ Champ de recherche non trouvé');
    }
    
    if (clearButton) {
        clearButton.addEventListener('click', clearSearch);
    }
    
    console.log('✅ Script admin_paid_orders.js chargé avec succès');
});
