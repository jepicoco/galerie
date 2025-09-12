<?php
/**
 * Modales communes pour les interfaces d'administration
 * Utilisé par admin_orders.php et admin_paid_orders.php
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}
?>

<!-- Modale Détails de commande -->
<div id="detailsModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Détails de la commande <span id="details-reference"></span></h3>
            <span class="close" onclick="closeModal('detailsModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="order-summary">
                <div class="customer-info">
                    <h4>Informations client</h4>
                    <p><strong>Nom :</strong> <span id="details-customer-name"></span></p>
                    <p><strong>Email :</strong> <span id="details-customer-email"></span></p>
                    <p><strong>Téléphone :</strong> <span id="details-customer-phone"></span></p>
                    <p><strong>Date de commande :</strong> <span id="details-order-date"></span></p>
                    <p><strong>Date de récupération :</strong> <span id="details-retrieval-date"></span></p>
                    <p><strong>Mode de paiement :</strong> <span id="details-payment-mode"></span></p>
                    <p><strong>Statut :</strong> <span id="details-status" class="status-badge"></span></p>
                </div>
                
                <div class="photos-list">
                    <h4>Photos commandées</h4>
                    <div class="photos-table">
                        <div class="table-header">
                            <span>Aperçu</span>
                            <span>Photo</span>
                            <span>Activité</span>
                            <span>Quantité</span>
                            <span>Prix unitaire</span>
                            <span>Sous-total</span>
                        </div>
                        <div id="photos-list-content">
                            <!-- Contenu généré dynamiquement par JavaScript -->
                        </div>
                    </div>
                    
                    <div class="order-total">
                        <strong>Total : <span id="details-total-photos"></span> photo(s) - <span id="details-total-amount"></span>€</strong>
                    </div>
                </div>
            </div>
            
            <div class="modal-actions">
                <button class="btn btn-print-modal" onclick="printOrderSlip()" title="Imprimer le bon de commande">
                    🖨️ Imprimer le bon de commande
                </button>
                <button class="btn btn-secondary" onclick="closeModal('detailsModal')">
                    Fermer
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale Informations de contact -->
<div id="contactModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Informations de contact - <span id="contact-reference"></span></h3>
            <span class="close" onclick="closeModal('contactModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="contact-info">
                <div class="customer-summary">
                    <h4><span id="contact-customer-name"></span></h4>
                    <p><strong>Commande du :</strong> <span id="contact-order-date"></span></p>
                    <p><strong>Montant :</strong> <span id="contact-amount"></span>€</p>
                    <p><strong>Photos :</strong> <span id="contact-photos-count"></span> photo(s)</p>
                </div>
                
                <div class="contact-details">
                    <div class="contact-item">
                        <strong>📧 Email :</strong>
                        <span id="contact-email"></span>
                        <div class="contact-actions">
                            <button class="btn-copy" onclick="copyToClipboard('contact-email')" title="Copier l'email">
                                📋
                            </button>
                            <a id="contact-email-link" class="btn-action" href="#" title="Ouvrir l'application email" target="_blank">
                                📧
                            </a>
                        </div>
                    </div>
                    
                    <div class="contact-item">
                        <strong>📞 Téléphone :</strong>
                        <span id="contact-phone"></span>
                        <div class="contact-actions">
                            <button class="btn-copy" onclick="copyToClipboard('contact-phone')" title="Copier le téléphone">
                                📋
                            </button>
                            <a id="contact-phone-link" class="btn-action" href="#" title="Appeler le numéro" target="_blank">
                                📞
                            </a>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-primary" onclick="showEmailConfirmationModal(document.getElementById('contact-reference').textContent)" title="Envoyer email de confirmation">
                        📧 Envoyer email de confirmation
                    </button>
                    <button class="btn btn-secondary" onclick="closeModal('contactModal')">
                        Fermer
                    </button>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Modale Envoi d'email de confirmation -->
<div id="emailConfirmationModal" class="modal">
    <div class="modal-content">
        <div class="modal-header">
            <h3>Envoyer l'email de confirmation</h3>
            <span class="close" onclick="closeModal('emailConfirmationModal')">&times;</span>
        </div>
        <div class="modal-body">
            <div class="confirmation-message">
                <p><strong>Êtes-vous sûr de vouloir envoyer l'email de confirmation pour la commande <span id="email-order-reference"></span> ?</strong></p>
                <p><strong>Client :</strong> <span id="email-customer-name"></span></p>
                <p><strong>Email :</strong> <span id="email-customer-email"></span></p>
                <p><strong>Montant :</strong> <span id="email-order-amount"></span>€</p>
                
                <div class="email-preview">
                    <h4>Aperçu du contenu :</h4>
                    <div class="email-content-preview">
                        <p>✅ Confirmation de commande</p>
                        <p>📋 Détails de la commande et photos</p>
                        <p>💰 Montant et mode de paiement</p>
                        <p>📞 Informations de contact</p>
                    </div>
                </div>
            </div>
            
            <div class="form-actions">
                <button type="button" class="btn btn-secondary" onclick="closeModal('emailConfirmationModal')">
                    Annuler
                </button>
                <button type="button" class="btn btn-primary" onclick="sendOrderConfirmationEmail()" id="send-email-btn">
                    📧 Confirmer l'envoi
                </button>
            </div>
        </div>
    </div>
</div>

<!-- Modale Prévisualisation d'image -->
<div id="imagePreviewModal" class="image-preview-modal">
    <span class="image-preview-close" onclick="closeImagePreview()">&times;</span>
    <div class="image-preview-content">
        <img id="preview-image" src="" alt="Aperçu">
        <div class="image-preview-info">
            <p id="preview-image-name"></p>
            <p id="preview-image-activity"></p>
        </div>
    </div>
    
    <div class="image-preview-nav">
        <button class="nav-btn prev-btn" onclick="navigatePreview(-1)" title="Image précédente">
            ◀ Précédent
        </button>
        <span id="image-counter">1 / 1</span>
        <button class="nav-btn next-btn" onclick="navigatePreview(1)" title="Image suivante">
            Suivant ▶
        </button>
    </div>
</div>

<style>
/* Styles spécifiques aux modales communes */
.contact-info {
    max-width: 500px;
}

.customer-summary {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 8px;
    margin-bottom: 20px;
}

.customer-summary h4 {
    margin: 0 0 10px 0;
    color: #333;
    font-size: 1.2em;
}

.contact-details {
    margin: 20px 0;
}

.contact-item {
    display: flex;
    align-items: center;
    margin: 15px 0;
    padding: 10px;
    background: #f8f9fa;
    border-radius: 6px;
}

.contact-item strong {
    min-width: 120px;
    margin-right: 10px;
}

.contact-item span {
    flex: 1;
    margin-right: 10px;
    font-family: monospace;
    font-size: 14px;
}

.contact-actions {
    display: flex;
    gap: 5px;
}

.btn-copy, .btn-action {
    padding: 5px 8px;
    border: 1px solid #ddd;
    background: white;
    border-radius: 4px;
    cursor: pointer;
    text-decoration: none;
    color: #666;
    transition: all 0.2s;
}

.btn-copy:hover, .btn-action:hover {
    background: #e9ecef;
    border-color: #adb5bd;
}

.photos-table {
    border: 1px solid #ddd;
    border-radius: 6px;
    overflow: hidden;
    margin: 15px 0;
}

.table-header {
    display: grid;
    grid-template-columns: 80px 1fr 120px 80px 100px 100px;
    gap: 10px;
    padding: 10px;
    background: #f8f9fa;
    font-weight: bold;
    border-bottom: 1px solid #ddd;
}

.photo-row {
    display: grid;
    grid-template-columns: 80px 1fr 120px 80px 100px 100px;
    gap: 10px;
    padding: 10px;
    border-bottom: 1px solid #eee;
    align-items: center;
}

.photo-row:last-child {
    border-bottom: none;
}

.photo-thumbnail {
    width: 60px;
    height: 40px;
    object-fit: cover;
    border-radius: 4px;
    cursor: pointer;
    border: 1px solid #ddd;
}

.order-total {
    text-align: right;
    padding: 15px;
    background: #e8f5e8;
    border-radius: 6px;
    margin-top: 15px;
}

.status-badge {
    padding: 4px 8px;
    border-radius: 12px;
    font-size: 12px;
    font-weight: bold;
    text-transform: uppercase;
}

.status-badge.paid {
    background: #d4edda;
    color: #155724;
}

.status-badge.validated {
    background: #fff3cd;
    color: #856404;
}

.status-badge.retrieved {
    background: #d1ecf1;
    color: #0c5460;
}

.email-preview {
    background: #f8f9fa;
    padding: 15px;
    border-radius: 6px;
    margin: 15px 0;
}

.email-content-preview {
    margin-top: 10px;
    font-size: 14px;
}

.email-content-preview p {
    margin: 5px 0;
    color: #666;
}

.image-preview-modal {
    display: none;
    position: fixed;
    z-index: 2000;
    left: 0;
    top: 0;
    width: 100%;
    height: 100%;
    background: rgba(0, 0, 0, 0.95);
    align-items: center;
    justify-content: center;
}

.image-preview-content {
    max-width: 90vw;
    max-height: 80vh;
    text-align: center;
}

.image-preview-content img {
    max-width: 100%;
    max-height: 70vh;
    object-fit: contain;
    border-radius: 8px;
}

.image-preview-info {
    color: white;
    margin-top: 15px;
}

.image-preview-close {
    position: absolute;
    top: 20px;
    right: 35px;
    color: white;
    font-size: 40px;
    font-weight: bold;
    cursor: pointer;
    z-index: 2001;
}

.image-preview-nav {
    position: absolute;
    bottom: 30px;
    left: 50%;
    transform: translateX(-50%);
    display: flex;
    align-items: center;
    gap: 20px;
    background: rgba(0, 0, 0, 0.7);
    padding: 10px 20px;
    border-radius: 25px;
    color: white;
}

.nav-btn {
    background: rgba(255, 255, 255, 0.2);
    border: 1px solid rgba(255, 255, 255, 0.3);
    color: white;
    padding: 8px 12px;
    border-radius: 6px;
    cursor: pointer;
    transition: all 0.3s;
}

.nav-btn:hover {
    background: rgba(255, 255, 255, 0.3);
}

.nav-btn:disabled {
    opacity: 0.5;
    cursor: not-allowed;
}

#image-counter {
    font-size: 14px;
    min-width: 60px;
    text-align: center;
}
</style>