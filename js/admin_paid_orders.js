/**
 * Script pour la gestion des commandes réglées
 * @version 1.2
 */

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
            alert('Commande marquée comme récupérée !');
            closeModal('retrievedModal');
            // Recharger la page pour actualiser la liste
            location.reload();
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
