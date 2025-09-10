/**
 * JavaScript pour la gestion des commandes - admin_orders.js
 */

// Variables globales
let currentOrderReference = null;

// Initialisation
document.addEventListener('DOMContentLoaded', function() {
    setupEventListeners();
    setupPaymentFormLogic();

    // Fermeture de la prévisualisation en cliquant à l'extérieur
    window.addEventListener('click', function(e) {
    if (e.target.id === 'imagePreviewModal') {
        closeImagePreview();
    }
    });
    });

/**
 * Configuration des événements
 */
function setupEventListeners() {
    // Fermeture des modales en cliquant à l'extérieur
    window.addEventListener('click', function(e) {
        if (e.target.classList.contains('modal')) {
            closeModal(e.target.id);
        }
    });
    
    // Gestion des raccourcis clavier
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeAllModals();
        }
    });
}

/**
 * Configuration de la logique du formulaire de paiement
 */
function setupPaymentFormLogic() {
    const paymentForm = document.getElementById('paymentForm');
    const paymentModeSelect = document.getElementById('payment-mode');
    const checkFields = document.getElementById('check-fields');
    
    // Afficher/masquer les champs spécifiques aux chèques
    paymentModeSelect.addEventListener('change', function() {
        if (this.value === 'check') {
            checkFields.style.display = 'block';
        } else {
            checkFields.style.display = 'none';
            // Vider les champs de date d'encaissement
            document.getElementById('desired-deposit-date').value = '';
            document.getElementById('actual-deposit-date').value = '';
        }
    });
    
    // Soumission du formulaire
    paymentForm.addEventListener('submit', function(e) {
        e.preventDefault();
        processPayment();
    });
}

/**
 * Afficher la modale de contact
 */
async function showContactModal(reference) {
    try {
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=get_contact&reference=${encodeURIComponent(reference)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            document.getElementById('contact-email').textContent = result.contact.email;
            document.getElementById('contact-phone').textContent = result.contact.phone;
            
            // Mettre à jour les liens d'action
            document.getElementById('contact-email-link').href = 'mailto:' + result.contact.email;
            document.getElementById('contact-phone-link').href = 'tel:' + result.contact.phone;
            
            showModal('contactModal');
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Afficher la modale de paiement
 */
function showPaymentModal(reference) {
    currentOrderReference = reference;
    
    // Trouver les détails de la commande
    console.log(currentOrderReference);
    const order = ordersData.find(o => o.reference === reference);
    console.log(!order);
    if (!order) {
        showNotification('Commande introuvable', 'error');
        return;
    }
    
    // Pré-remplir le formulaire
    document.getElementById('reference').value = reference;
    document.getElementById('payment-mode').value = '';
    document.getElementById('payment-date').value = new Date().toISOString().split('T')[0];
    document.getElementById('desired-deposit-date').value = '';
    document.getElementById('actual-deposit-date').value = '';
    document.getElementById('check-fields').style.display = 'none';
    
    console.log(order);
    showModal('paymentModal');
}

/**
 * Traiter le règlement d'une commande
 */
async function processPayment() {
    const form = document.getElementById('paymentForm');
    const formData = new FormData(form);
    formData.append('action', 'process_payment');
    
    const submitBtn = form.querySelector('button[type="submit"]');
    const originalText = submitBtn.textContent;
    
    try {
        submitBtn.disabled = true;
        submitBtn.textContent = 'Traitement...';
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Règlement traité avec succès', 'success');
            closeModal('paymentModal');
            
            // Recharger la page pour mettre à jour les données
            setTimeout(() => {
                window.location.reload();
            }, 1000);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    } finally {
        submitBtn.disabled = false;
        submitBtn.textContent = originalText;
    }
}

/**
 * Exporter la liste de préparation
 */
async function exportPreparationList() {
    try {
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=export_preparation'
        });
        
        const result = await response.json();
        
        if (result.success) {
            const message = result.message || 'Liste de préparation générée';
            const details = result.orders_count ? ` (${result.orders_count} commandes, ${result.photos_count} photos)` : '';
            
            // Afficher les informations sur les commandes ajoutées
            let addedInfo = '';
            if (result.added_to_preparer && result.added_to_preparer > 0) {
                addedInfo = ` + ${result.added_to_preparer} commande(s) validated ajoutée(s)`;
            }
            
            showNotification(message + details + addedInfo, 'success');
            downloadFile(result.file);
            
            // Notification supplémentaire si des commandes ont été traitées
            if (result.added_to_preparer && result.added_to_preparer > 0) {
                setTimeout(() => {
                    showNotification(
                        `✅ ${result.added_to_preparer} commande(s) ajoutée(s) dans commandes_a_preparer.csv et marquée(s) comme exportées`, 
                        'info'
                    );
                }, 1500);
            }
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Exporter les règlements du jour
 */
async function exportDailyPayments() {
    const date = prompt('Date des règlements (YYYY-MM-DD):', new Date().toISOString().split('T')[0]);
    if (!date) return;
    
    try {
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=export_daily_payments&date=${encodeURIComponent(date)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`${result.count} règlement(s) exporté(s)`, 'success');
            downloadFile(result.file);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Archiver les anciennes commandes
 */
async function archiveOldOrders() {
    const days = prompt('Archiver les commandes de plus de X jours:', '30');
    if (!days || isNaN(days)) return;
    
    if (!confirm(`Archiver toutes les commandes de plus de ${days} jours ?`)) {
        return;
    }
    
    try {
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=archive_orders&days=${encodeURIComponent(days)}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            setTimeout(() => {
                window.location.reload();
            }, 1500);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Afficher une modale
 */
function showModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'block';
        // Focus sur le premier élément focusable
        const firstInput = modal.querySelector('input, select, button');
        if (firstInput) {
            setTimeout(() => firstInput.focus(), 100);
        }
    }
}

/**
 * Fermer une modale
 */
function closeModal(modalId) {
    const modal = document.getElementById(modalId);
    if (modal) {
        modal.style.display = 'none';
    }
}

/**
 * Fermer toutes les modales
 */
function closeAllModals() {
    const modals = document.querySelectorAll('.modal');
    modals.forEach(modal => {
        modal.style.display = 'none';
    });
}

/**
 * Copier du texte dans le presse-papiers
 */
async function copyToClipboard(elementId) {
    const element = document.getElementById(elementId);
    if (!element) return;
    
    const text = element.textContent;
    
    try {
        await navigator.clipboard.writeText(text);
        showNotification('Copié dans le presse-papiers', 'success');
        
        // Animation visuelle
        element.style.backgroundColor = '#28a745';
        element.style.color = 'white';
        setTimeout(() => {
            element.style.backgroundColor = '';
            element.style.color = '';
        }, 500);
    } catch (error) {
        // Fallback pour les navigateurs plus anciens
        const textArea = document.createElement('textarea');
        textArea.value = text;
        document.body.appendChild(textArea);
        textArea.select();
        document.execCommand('copy');
        document.body.removeChild(textArea);
        showNotification('Copié dans le presse-papiers', 'success');
    }
}

/**
 * Télécharger un fichier
 */
function downloadFile(filePath) {
    const link = document.createElement('a');
    link.href = filePath;
    link.download = filePath.split('/').pop();
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}

/**
 * Afficher une notification
 */
function showNotification(message, type = 'info') {
    // Supprimer les notifications existantes
    const existingNotifications = document.querySelectorAll('.notification');
    existingNotifications.forEach(n => n.remove());
    
    const notification = document.createElement('div');
    notification.className = `notification notification-${type}`;
    notification.textContent = message;
    
    // Styles de base
    notification.style.cssText = `
        position: fixed;
        top: 20px;
        right: 20px;
        padding: 1rem 2rem;
        border-radius: 5px;
        color: white;
        font-weight: 500;
        z-index: 9999;
        animation: slideInRight 0.3s ease;
        cursor: pointer;
        max-width: 400px;
        box-shadow: 0 4px 12px rgba(0,0,0,0.2);
    `;
    
    // Couleurs selon le type
    const colors = {
        success: '#28a745',
        error: '#dc3545',
        warning: '#ffc107',
        info: '#17a2b8'
    };
    
    notification.style.backgroundColor = colors[type] || colors.info;
    
    // Ajouter au DOM
    document.body.appendChild(notification);
    
    // Fermeture automatique
    const autoClose = setTimeout(() => {
        removeNotification(notification);
    }, 5000);
    
    // Fermeture au clic
    notification.addEventListener('click', () => {
        clearTimeout(autoClose);
        removeNotification(notification);
    });
}

/**
 * Supprimer une notification
 */
function removeNotification(notification) {
    if (notification && notification.parentNode) {
        notification.style.animation = 'slideOutRight 0.3s ease';
        setTimeout(() => {
            if (notification.parentNode) {
                notification.parentNode.removeChild(notification);
            }
        }, 300);
    }
}

// Ajouter les animations CSS
const style = document.createElement('style');
style.textContent = `
    @keyframes slideInRight {
        from {
            transform: translateX(100%);
            opacity: 0;
        }
        to {
            transform: translateX(0);
            opacity: 1;
        }
    }
    
    @keyframes slideOutRight {
        from {
            transform: translateX(0);
            opacity: 1;
        }
        to {
            transform: translateX(100%);
            opacity: 0;
        }
    }
    
    .notification {
        transition: all 0.3s ease;
    }
    
    .notification:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 16px rgba(0,0,0,0.3) !important;
    }
`;
document.head.appendChild(style);

/**
 * Retourne les deux prochains vendredis à partir d'une date donnée
 */
function getNextFridays(fromDate) {
    const fridays = [];
    let date = new Date(fromDate);
    // Trouver le prochain vendredi
    date.setDate(date.getDate() + ((5 - date.getDay() + 7) % 7));
    fridays.push(new Date(date));
    // Ajouter le vendredi suivant
    date.setDate(date.getDate() + 7);
    fridays.push(new Date(date));
    return fridays;
}

// Initialisation des boutons de sélection de vendredi
document.addEventListener('DOMContentLoaded', function() {
    const today = new Date();
    const fridays = getNextFridays(today);
    const btn1 = document.getElementById('friday1-btn');
    const btn2 = document.getElementById('friday2-btn');
    const input = document.getElementById('desired-deposit-date');

    // Formater la date au format AAAA-MM-JJ
    function formatDate(d) {
        return d.toISOString().slice(0, 10);
    }

    // Formater l'étiquette du bouton au format français
    function formatLabel(d) {
        return d.toLocaleDateString('fr-FR', { weekday: 'short', day: '2-digit', month: '2-digit', year: 'numeric' });
    }

    // Mettre à jour le texte des boutons
    btn1.textContent = formatLabel(fridays[0]);
    btn2.textContent = formatLabel(fridays[1]);

    // Remplir le champ de date lors du clic sur un bouton
    btn1.onclick = function() { input.value = formatDate(fridays[0]); };
    btn2.onclick = function() { input.value = formatDate(fridays[1]); };
});

/**
 * Gérer les erreurs de chargement d'image
 */
function handleImageError(img) {
    img.style.display = 'none';
    const placeholder = document.createElement('div');
    placeholder.className = 'photo-thumbnail error';
    placeholder.textContent = '🖼️';
    placeholder.title = 'Image non trouvée';
    img.parentNode.insertBefore(placeholder, img);
}

/**
 * Afficher la prévisualisation d'image
 */
function showImagePreview(imagePath, filename) {
    const modal = document.getElementById('imagePreviewModal');
    const img = document.getElementById('preview-image');
    const filenameDiv = document.getElementById('preview-filename');
    
    img.src = imagePath;
    filenameDiv.textContent = filename;
    modal.style.display = 'block';
    
    // Fermer avec la touche Escape
    document.addEventListener('keydown', function escapeHandler(e) {
        if (e.key === 'Escape') {
            closeImagePreview();
            document.removeEventListener('keydown', escapeHandler);
        }
    });
}

/**
 * Fermer la prévisualisation d'image
 */
function closeImagePreview() {
    document.getElementById('imagePreviewModal').style.display = 'none';
}

// Fonction pour détecter si le conteneur a besoin de scroll
function checkScrollIndicator() {
    const container = document.getElementById('photos-list-content');
    const table = document.querySelector('.photos-table');
    
    if (container && table) {
        if (container.scrollHeight > container.clientHeight) {
            table.classList.add('has-scroll');
        } else {
            table.classList.remove('has-scroll');
        }
    }
}

/**
 * Afficher la modale de détails de commande
 */
function showDetailsModal(reference) {
    // Trouver les détails de la commande
    const order = ordersData.find(o => o.reference === reference);
    if (!order) {
        showNotification('Commande introuvable', 'error');
        return;
    }
    
    // Stocker la référence courante pour l'impression
    currentOrderReference = reference;
    
    // Remplir les informations client
    document.getElementById('details-reference').textContent = reference;
    document.getElementById('details-customer-name').textContent = order.firstname + ' ' + order.lastname;
    document.getElementById('details-customer-email').textContent = order.email;
    document.getElementById('details-customer-phone').textContent = order.phone;
    document.getElementById('details-retrieval-date').textContent = order.retrieval_date || 'Non précisée';
    
    // Remplir la liste des photos
    const photosContainer = document.getElementById('photos-list-content');
    photosContainer.innerHTML = '';
    
    order.photos.forEach(photo => {
        console.log(photo);
        const row = document.createElement('div');
        row.className = 'photo-row';
        
        // Extraire l'activité du nom de la photo
        let activityKey = photo.activity_key;
        let photoName = photo.name;
        
        // Construire le chemin de l'image
        const photosDir = 'photos/';
        const imagePath = getPhotoUrl(photo, 'thumbnail');
        
        const price = getActivityPrice(activityKey);
        const typeInfo = getActivityTypeInfo(activityKey);
        const subtotal = price * parseInt(photo.quantity);

        row.innerHTML = `
            <img class="photo-thumbnail" 
                src="${getPhotoUrl(photo, 'thumbnail')}" 
                alt="${getPhotoName(photo)}"
                onclick="showImagePreview('${getPhotoUrl(photo, 'thumbnail')}', '${getPhotoName(photo)}')"
                onerror="handleImageError(this)">
            <div class="photo-info">
                <span class="photo-name">${getPhotoName(photo)}</span>
                <small class="photo-type">${typeInfo.display_name} - ${price}€</small>
            </div>
            <span class="photo-quantity">${photo.quantity}</span>
            <span class="photo-subtotal">${subtotal.toFixed(2)}€</span>
        `;
        photosContainer.appendChild(row);
    });
    
    // Remplir les totaux
    let totalAmount = 0;
    let totalPhotos = 0;

    order.photos.forEach(photo => {
        const activityKey = photo.activity_key;
        const quantity = parseInt(photo.quantity) || 1;
        const price = getActivityPrice(activityKey);
        
        totalAmount += price * quantity;
        totalPhotos += quantity;
    });

    // Remplir les totaux
    document.getElementById('details-total-photos').textContent = totalPhotos;
    document.getElementById('details-total-amount').textContent = totalAmount.toFixed(2);
    
    // ✅ AJOUTER ICI : Gestion de l'indicateur de scroll
    setTimeout(() => {
        checkScrollIndicator();
    }, 100);
    
    showModal('detailsModal');
}

/**
 * Vérifier si le conteneur nécessite un scroll et afficher l'indicateur
 */
function checkScrollIndicator() {
    const container = document.getElementById('photos-list-content');
    const table = document.querySelector('.photos-table');
    
    if (container && table) {
        if (container.scrollHeight > container.clientHeight) {
            table.classList.add('has-scroll');
        } else {
            table.classList.remove('has-scroll');
        }
    }
}

// Observer les changements de taille
if (window.ResizeObserver) {
    const resizeObserver = new ResizeObserver(checkScrollIndicator);
    const container = document.getElementById('photos-list-content');
    if (container) {
        resizeObserver.observe(container);
    }
}

//Fonction pour récupérer le prix selon le type d'activité
function getActivityPrice(activityKey) {
    // Chercher l'activité dans les données
    console.log(activities);
    const activity = activities.find(a => a.key === activityKey);
    if (activity && activity.pricing_type && ACTIVITY_PRICING[activity.pricing_type]) {
        return ACTIVITY_PRICING[activity.pricing_type].price;
    }
    // Fallback sur le prix par défaut
    return ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]?.price || 2;
}

//Fonction pour récupérer les infos complètes du type de tarification
function getActivityTypeInfo(activityKey) {
    const activity = activities.find(a => a.key === activityKey);
    const pricingType = activity?.pricing_type || DEFAULT_ACTIVITY_TYPE;
    return ACTIVITY_PRICING[pricingType] || ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE];
}

/**
 * Générer les listes de picking par activité
 */
async function generatePickingLists() {
    try {
        showNotification('Génération des listes de picking détaillées...', 'info');
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=generate_picking_lists'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            
            // Afficher les statistiques
            if (result.total_orders && result.total_copies) {
                setTimeout(() => {
                    showNotification(
                        `📊 ${result.total_orders} commandes individuelles - ${result.total_copies} exemplaires total`, 
                        'info'
                    );
                }, 1000);
            }
            
            // Télécharger le fichier principal
            downloadFile(result.file);
            
            // Si format CSV disponible, proposer aussi le téléchargement
            if (result.file_csv) {
                setTimeout(() => {
                    if (confirm('Voulez-vous aussi télécharger la version CSV (compatible Excel) ?')) {
                        downloadFile(result.file_csv);
                    }
                }, 2000);
            }
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Générer spécifiquement la version CSV
 */
async function generatePickingListsCSV() {
    try {
        showNotification('Génération de la liste CSV...', 'info');
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=generate_picking_lists_csv'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Liste CSV générée', 'success');
            downloadFile(result.file_csv);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Exporter le guide de séparation
 */
async function exportSeparationGuide() {
    try {
        showNotification('Génération du guide de séparation...', 'info');
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=export_separation_guide'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            downloadFile(result.file);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Exporter le résumé pour l'imprimeur
 */
async function exportPrinterSummary() {
    try {
        showNotification('Génération du résumé imprimeur...', 'info');
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=export_printer_summary'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(`${result.total_photos} photos - ${result.total_copies} exemplaires`, 'success');
            downloadFile(result.file);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Vérifier la cohérence par activité
 */
async function checkCoherence() {
    try {
        showNotification('Vérification de la cohérence...', 'info');
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=check_coherence'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showCoherenceModal(result.report);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Afficher la modale de rapport de cohérence
 */
function showCoherenceModal(report) {
    // Créer la modale dynamiquement
    const modalHtml = `
        <div id="coherenceModal" class="modal">
            <div class="modal-content" style="max-width: 800px;">
                <div class="modal-header">
                    <h3>Rapport de cohérence par activité</h3>
                    <span class="close" onclick="closeModal('coherenceModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="coherence-report">
                        ${generateCoherenceReport(report)}
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Supprimer l'ancienne modale si elle existe
    const existingModal = document.getElementById('coherenceModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Ajouter la nouvelle modale
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    showModal('coherenceModal');
}

/**
 * Générer le contenu du rapport de cohérence
 */
function generateCoherenceReport(report) {
    let html = '<div class="activities-summary">';
    
    for (const [activite, data] of Object.entries(report)) {
        html += `
            <div class="activity-card">
                <div class="activity-header">
                    <h4>📁 ${activite}</h4>
                    <div class="activity-stats">
                        <span class="stat">${data.photos_count} photos</span>
                        <span class="stat">${data.total_copies} exemplaires</span>
                    </div>
                </div>
                <div class="photos-detail">
        `;
        
        for (const [photo, quantite] of Object.entries(data.photos)) {
            html += `
                <div class="photo-item">
                    <span class="photo-name">${photo}</span>
                    <span class="photo-quantity">${quantite} ex.</span>
                </div>
            `;
        }
        
        html += '</div></div>';
    }
    
    html += '</div>';
    
    return html;
}

/**
 * Exporter la préparation par activité
 * Version 1.0
 */
async function exportPreparationByActivity() {
    try {
        showNotification('Génération de l\'export par activité...', 'info');
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=export_preparation_by_activity'
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification(result.message, 'success');
            
            // Afficher une modale avec les options de téléchargement
            showDownloadOptionsModal(result.files, result.stats);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur de communication: ' + error.message, 'error');
    }
}

/**
 * Afficher la modale des options de téléchargement
 */
function showDownloadOptionsModal(files, stats) {
    const modalHtml = `
        <div id="downloadOptionsModal" class="modal">
            <div class="modal-content" style="max-width: 600px;">
                <div class="modal-header">
                    <h3>📥 Fichiers de préparation générés</h3>
                    <span class="close" onclick="closeModal('downloadOptionsModal')">&times;</span>
                </div>
                <div class="modal-body">
                    <div class="download-stats">
                        <div class="stat-grid">
                            <div class="stat-item">
                                <span class="stat-number">${stats.activities_count}</span>
                                <span class="stat-label">Activités</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">${stats.total_photos}</span>
                                <span class="stat-label">Photos uniques</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">${stats.total_copies}</span>
                                <span class="stat-label">Exemplaires</span>
                            </div>
                            <div class="stat-item">
                                <span class="stat-number">${stats.files_generated}</span>
                                <span class="stat-label">Fichiers générés</span>
                            </div>
                        </div>
                    </div>
                    
                    <div class="download-options">
                        <h4>📂 Fichiers disponibles :</h4>
                        
                        <div class="file-option">
                            <div class="file-info">
                                <strong>📋 Fichier consolidé</strong>
                                <p>Toutes les activités dans un seul fichier CSV</p>
                            </div>
                            <button class="btn btn-primary" onclick="downloadFile('${files.consolidated}')">
                                Télécharger
                            </button>
                        </div>
                        
                        <div class="file-option">
                            <div class="file-info">
                                <strong>📊 Résumé de préparation</strong>
                                <p>Statistiques et informations par activité</p>
                            </div>
                            <button class="btn btn-secondary" onclick="downloadFile('${files.summary}')">
                                Télécharger
                            </button>
                        </div>
                        
                        <div class="file-option">
                            <div class="file-info">
                                <strong>📁 Fichiers par activité</strong>
                                <p>${stats.activities_count} fichier(s) CSV séparé(s)</p>
                            </div>
                            <div class="activity-files">
                                ${generateActivityFilesList(files.by_activity)}
                            </div>
                        </div>
                        
                        <div class="file-option download-all">
                            <div class="file-info">
                                <strong>📦 Télécharger tout</strong>
                                <p>Archive ZIP avec tous les fichiers</p>
                            </div>
                            <button class="btn btn-success" onclick="downloadAllFiles('${JSON.stringify(files).replace(/"/g, '&quot;')}')">
                                Tout télécharger
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    `;
    
    // Supprimer l'ancienne modale si elle existe
    const existingModal = document.getElementById('downloadOptionsModal');
    if (existingModal) {
        existingModal.remove();
    }
    
    // Ajouter la nouvelle modale
    document.body.insertAdjacentHTML('beforeend', modalHtml);
    showModal('downloadOptionsModal');
}

/**
 * Générer la liste des fichiers par activité
 */
function generateActivityFilesList(activityFiles) {
    let html = '';
    
    activityFiles.forEach(fileInfo => {
        html += `
            <div class="activity-file-item">
                <span class="activity-name">${fileInfo.activite}</span>
                <span class="file-stats">${fileInfo.nombre_lignes} ligne(s)</span>
                <button class="btn btn-outline btn-sm" onclick="downloadFile('${fileInfo.fichier}')">
                    📥
                </button>
            </div>
        `;
    });
    
    return html;
}

/**
 * Télécharger tous les fichiers (créer une archive)
 */
async function downloadAllFiles(filesJson) {
    try {
        const files = JSON.parse(filesJson.replace(/&quot;/g, '"'));
        
        showNotification('Création de l\'archive...', 'info');
        
        const response = await fetch('admin_orders_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: `action=create_download_archive&files=${encodeURIComponent(JSON.stringify(files))}`
        });
        
        const result = await response.json();
        
        if (result.success) {
            showNotification('Archive créée', 'success');
            downloadFile(result.archive);
        } else {
            showNotification('Erreur: ' + result.error, 'error');
        }
    } catch (error) {
        showNotification('Erreur: ' + error.message, 'error');
    }
}

/**
 * Affiche la modale de confirmation d'envoi d'email
 * @param {string} reference - Référence de la commande
 * @version 1.0
 */
function showEmailConfirmationModal(reference) {
    const order = ordersData.find(o => o.reference === reference);

    if (!order) {
        alert('Commande non trouvée');
        return;
    }
    
    document.getElementById('email-order-reference').textContent = reference;
    document.getElementById('email-customer-email').textContent = order.email;
    
    // Stocker la référence pour l'envoi
    document.getElementById('emailConfirmationModal').dataset.reference = reference;
    
    showModal('emailConfirmationModal');
}

/**
 * Envoie l'email de confirmation de commande
 * @version 1.0
 */
function sendOrderConfirmationEmail() {
    const modal = document.getElementById('emailConfirmationModal');
    const reference = modal.dataset.reference;

    if (!reference) {
        alert('Référence de commande manquante');
        return;
    }
    
    // Affichage du loader
    const confirmButton = modal.querySelector('.btn-primary');
    const originalText = confirmButton.textContent;
    confirmButton.textContent = 'Envoi en cours...';
    confirmButton.disabled = true;
    
    fetch('admin_orders_handler.php', {
        method: 'POST',
        headers: {
            'Content-Type': 'application/x-www-form-urlencoded',
        },
        body: 'action=resend_confirmation_email&reference=' + encodeURIComponent(reference)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            alert('Email de confirmation envoyé avec succès !');
            closeModal('emailConfirmationModal');
        } else {
            alert('Erreur lors de l\'envoi : ' + (data.message || 'Erreur inconnue'));
        }
    })
    .catch(error => {
        console.error('Erreur:', error);
        alert('Erreur lors de l\'envoi de l\'email');
    })
    .finally(() => {
        confirmButton.textContent = originalText;
        confirmButton.disabled = false;
    });
}

/**
 * Imprimer le bon de commande
 * @param {string} reference - Référence de la commande à imprimer
 */
function printOrderSlip(reference = null) {
    // Utiliser la référence passée ou celle stockée globalement
    const orderReference = reference || currentOrderReference;
    
    if (!orderReference) {
        showNotification('Aucune commande sélectionnée pour l\'impression', 'error');
        return;
    }
    
    // Ouvrir la fenêtre d'impression avec la référence
    const printUrl = `order_print.php?reference=${encodeURIComponent(orderReference)}`;
    const printWindow = window.open(printUrl, '_blank', 'width=800,height=600,scrollbars=yes,resizable=yes');
    
    if (!printWindow) {
        showNotification('Impossible d\'ouvrir la fenêtre d\'impression (bloqueur de pop-up ?)', 'error');
        return;
    }
    
    // Optionnel : déclencher l'impression automatiquement quand la page se charge
    printWindow.addEventListener('load', function() {
        setTimeout(() => {
            printWindow.focus();
            printWindow.print();
        }, 1000);
    });
}

// ================================
// EXPOSITION GLOBALE DES FONCTIONS
// ================================

// APPROCHE DIRECTE : Exposer immédiatement les fonctions au scope global
console.log('🔄 Exposition des fonctions admin_orders.js...');

// Fonctions critiques pour les boutons HTML
window.showPaymentModal = showPaymentModal;
window.showContactModal = showContactModal;
window.showDetailsModal = showDetailsModal;
window.showEmailConfirmationModal = showEmailConfirmationModal;
window.sendOrderConfirmationEmail = sendOrderConfirmationEmail;
window.printOrderSlip = printOrderSlip;
window.copyToClipboard = copyToClipboard;

// Fonctions d'export et actions
window.exportSeparationGuide = exportSeparationGuide;
window.exportPrinterSummary = exportPrinterSummary;
window.generatePickingListsCSV = generatePickingListsCSV;
window.exportPreparationList = exportPreparationList;
window.exportDailyPayments = exportDailyPayments;
window.checkCoherence = checkCoherence;
window.archiveOldOrders = archiveOldOrders;

// Fonctions modales
window.showModal = showModal;
window.closeModal = closeModal;
window.closeAllModals = closeAllModals;
window.showImagePreview = showImagePreview;
window.closeImagePreview = closeImagePreview;
window.showCoherenceModal = showCoherenceModal;
window.showDownloadOptionsModal = showDownloadOptionsModal;

// Fonctions utilitaires
window.setupEventListeners = setupEventListeners;
window.setupPaymentFormLogic = setupPaymentFormLogic;
window.downloadFile = downloadFile;
window.showNotification = showNotification;
window.removeNotification = removeNotification;
window.getNextFridays = getNextFridays;
window.handleImageError = handleImageError;
window.checkScrollIndicator = checkScrollIndicator;
window.getActivityPrice = getActivityPrice;
window.getActivityTypeInfo = getActivityTypeInfo;
window.generateCoherenceReport = generateCoherenceReport;
window.generateActivityFilesList = generateActivityFilesList;

console.log('✅ Admin_orders.js - Fonctions exposées au scope global');