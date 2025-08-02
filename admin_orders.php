<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';

session_start();

require_once 'functions.php';

// Vérifier l'authentification admin
$is_admin = is_admin();

if (!$is_admin) {
    header('Location: index.php');
    exit;
}

// Inclure l'autoloader pour les classes
require_once 'classes/autoload.php';

// Inclure le handler pour les fonctions
require_once 'admin_orders_handler.php';

// Charger les données pour l'affichage avec la nouvelle classe
$ordersList = new OrdersList();
$ordersData = $ordersList->loadOrdersData('unpaid'); // Filtrer les commandes non payées
$stats = $ordersList->calculateStats($ordersData['orders']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion des Commandes - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/admin.orders.css">
    <link rel="stylesheet" href="css/print.css">
    <link rel="icon" href="favicon.png" />
</head>
<body>
    
    <?php include('include.header.php'); ?>

    <!-- Modale Détails de commande -->
    <div id="detailsModal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
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
                        <p><strong>Date de récupération :</strong> <span id="details-retrieval-date"></span></p>
                    </div>
                    
                    <div class="photos-list">
                        <h4>Photos commandées</h4>
                        <div class="photos-table">
                            <div class="table-header">
                                <span>Aperçu</span>
                                <span>Photo</span>
                                <span>Quantité</span>
                                <span>Sous-total</span>
                            </div>
                            <div id="photos-list-content">
                                <!-- Contenu généré dynamiquement -->
                            </div>
                        </div>
                        
                        <div class="order-total">
                            <strong>Total : <span id="details-total-photos"></span> photo(s) - <span id="details-total-amount"></span>€</strong>
                        </div>
                    </div>
                </div>
                
                <div class="modal-actions">
                    <button class="btn btn-print-modal" onclick="printOrderSlip()">
                        🖨️ Imprimer le bon de commande
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale Prévisualisation d'image -->
    <div id="imagePreviewModal" class="image-preview-modal">
        <span class="image-preview-close" onclick="closeImagePreview()">&times;</span>
        <div class="image-preview-content">
            <img id="preview-image" src="" alt="Prévisualisation">
            <div class="image-preview-info">
                <div id="preview-filename"></div>
            </div>
        </div>
    </div>

    <main class="main-content">
        <div class="container">
            <!-- Navigation avec compteur optimisée -->
            <div class="admin-nav">
                <h1>Gestion des Commandes</h1>
                <?php $paidOrdersCount = countPendingRetrievals(); ?>
                <a href="admin_paid_orders.php" class="nav-link">
                    Retraits 
                    <?php if ($paidOrdersCount > 0): ?>
                        <span class="nav-counter"><?php echo $paidOrdersCount; ?></span>
                    <?php endif; ?>
                    →
                </a>
            </div>
            <!-- Statistiques -->
            <div class="orders-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total']; ?></div>
                    <div class="stat-label">Commandes à payer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['total_photos']; ?></div>
                    <div class="stat-label">Photos à préparer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($stats['total_amount'], 2); ?>€</div>
                    <div class="stat-label">Montant total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $stats['paid_today']; ?></div>
                    <div class="stat-label">Réglées aujourd'hui</div>
                </div>
            </div>

            <!-- Contenu principal en 2 colonnes -->
            <div class="orders-layout">
                <!-- Colonne gauche : Liste des commandes -->
                <div class="orders-list">
                    <h2>Commandes en attente de règlement</h2>
                    
                    <?php if (empty($ordersData['orders'])): ?>
                        <div class="no-orders">
                            <h3>Aucune commande en attente</h3>
                            <p>Toutes les commandes ont été traitées !</p>
                        </div>
                    <?php else: ?>
                        <?php foreach ($ordersData['orders'] as $order): ?>
                            <div class="order-card" data-reference="<?php echo $order['reference']; ?>">
                                <div class="order-header">
                                    <div class="order-info">
                                        <h3><?php echo $order['reference']; ?></h3>
                                        <p class="customer-name"><?php echo $order['firstname'] . ' ' . $order['lastname']; ?></p>
                                    </div>
                                    <div class="order-actions">
                                        <button class="btn-contact" onclick="showContactModal('<?php echo $order['reference']; ?>')" title="Informations de contact">
                                            🗓️
                                        </button>
                                        <button class="btn-details" onclick="showDetailsModal('<?php echo $order['reference']; ?>')" title="Liste détaillée">
                                            📋
                                        </button>
                                        <button class="btn-print" onclick="printOrderSlip('<?php echo $order['reference']; ?>')" title="Liste détaillée">
                                            🖨️
                                        </button>
                                        <button class="btn-email" onclick="showEmailConfirmationModal('<?php echo $order['reference']; ?>')" title="Renvoyer email de confirmation">
                                            📧
                                        </button>
                                        <span class="order-amount"><?php echo number_format($order['amount'], 2); ?>€</span>
                                        <button class="btn-payment" onclick="showPaymentModal('<?php echo $order['reference']; ?>')">
                                            Régler
                                        </button>
                                    </div>
                                </div>
                                <div class="order-details">
                                    <span class="photos-count"><?php echo $order['total_photos']; ?> photo(s)</span>
                                    <span class="order-date">Créée le <?php echo date('d/m/Y H:m', strtotime($order['created_at'])); ?></span>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    <?php endif; ?>
                </div>

                <!-- Remplacer la section "Actions rapides" existante par : -->
                <div class="quick-actions">
                    <h2>Actions rapides</h2>

                    <div class="action-card">
                        <h3>🏭 Commande imprimeur</h3>
                        <p>Résumé optimisé pour la commande à l'imprimeur</p>
                        <button class="btn btn-primary" onclick="exportPrinterSummary()">
                            📊 Résumé imprimeur
                        </button>
                    </div>
                    
                    <div class="action-card">
                        <h3>📋 Préparation des commandes</h3>
                        <div class="action-buttons">
                            <button class="btn btn-secondary" onclick="exportSeparationGuide()">
                                📦 Vérification de la commande
                            </button>
                            <button class="btn btn-primary" onclick="generatePickingListsCSV()">
                                📝 Listes de répartition
                            </button>
                        </div>
                    </div>
                    
                    <div class="action-card">
                        <h3>📋 Export classique</h3>
                        <p>Liste de préparation simple (ancienne méthode)</p>
                        <button class="btn btn-outline" onclick="exportPreparationList()">
                            Générer la liste
                        </button>
                    </div>
                    
                    <div class="action-card">
                        <h3>📊 Export comptable</h3>
                        <p>Exporter les règlements du jour</p>
                        <button class="btn btn-secondary" onclick="exportDailyPayments()">
                            Export du jour
                        </button>
                    </div>
                    
                    <div class="action-card">
                        <h3>🔍 Contrôle cohérence</h3>
                        <p>Vérifier la cohérence par activité</p>
                        <button class="btn btn-info" onclick="checkCoherence()">
                            Vérifier
                        </button>
                    </div>
                    
                    <div class="action-card">
                        <h3>🗂️ Archiver</h3>
                        <p>Archiver les commandes anciennes</p>
                        <button class="btn btn-outline" onclick="archiveOldOrders()">
                            Archiver
                        </button>
                    </div>
                </div>
            </div>
        </div>
    </main>

    <!-- Modale Contact -->
    <div id="contactModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Informations de contact</h3>
                <span class="close" onclick="closeModal('contactModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="contact-info">
                    <div class="contact-item">
                        <strong>📧 Email :</strong>
                        <span id="contact-email"></span>
                        <button class="btn-copy" onclick="copyToClipboard('contact-email')">📋</button>
                        <a id="contact-email-link" class="btn-action" href="#" title="Ouvrir l'application email">
                            ➤
                        </a>
                    </div>
                    <div class="contact-item">
                        <strong>📞 Téléphone :</strong>
                        <span id="contact-phone"></span>
                        <button class="btn-copy" onclick="copyToClipboard('contact-phone')">📋</button>
                        <a id="contact-phone-link" class="btn-action" href="#" title="Ouvrir l'application téléphone">
                            ➤
                        </a>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Modale Règlement -->
    <div id="paymentModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Régler la commande</h3>
                <span class="close" onclick="closeModal('paymentModal')">&times;</span>
            </div>
            <div class="modal-body">
                <form id="paymentForm">
                    <input type="hidden" id="payment-reference" name="reference">
                    
                    <div class="form-group">
                        <label for="payment-mode">Mode de règlement *</label>
                        <select id="payment-mode" name="payment_mode" required>
                            <option value="">Sélectionner...</option>
                            <option value="Espèces">Espèces</option>
                            <option value="Chèque">Chèque</option>
                            <option value="CB">Carte bancaire</option>
                            <option value="Virement">Virement</option>
                        </select>
                    </div>
                    
                    <div class="form-group">
                        <label for="payment-date">Date de règlement *</label>
                        <input type="date" id="payment-date" name="payment_date" value="<?php echo date('Y-m-d'); ?>" required>
                    </div>
                    
                    <div id="check-fields" class="check-specific" style="display: none;">
                        <div class="form-group">
                            <label for="desired-deposit-date">Date d'encaissement souhaitée</label>
                            <input type="date" id="desired-deposit-date" name="desired_deposit_date" value="<?php echo date('Y-m-d'); ?>">
                            <div id="friday-buttons" style="margin-top: 8px;">
                                <button type="button" class="btn btn-secondary" id="friday1-btn"></button>
                                <button type="button" class="btn btn-secondary" id="friday2-btn"></button>
                            </div>
                        </div>
                        <div class="form-group" id="form-deposit-date" style="display: none;">
                            <label for="actual-deposit-date">Date d'encaissement réelle</label>
                            <input type="date" id="actual-deposit-date" name="actual_deposit_date">
                        </div>
                    </div>
                    
                    <div class="form-actions">
                        <button type="button" class="btn btn-secondary" onclick="closeModal('paymentModal')">
                            Annuler
                        </button>
                        <button type="submit" class="btn btn-primary">
                            Valider le règlement
                        </button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Modale Confirmation Email -->
    <div id="emailConfirmationModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Renvoyer l'email de confirmation</h3>
                <span class="close" onclick="closeModal('emailConfirmationModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="confirmation-message">
                    <p><strong>Êtes-vous sûr de vouloir renvoyer l'email de confirmation pour la commande <span id="email-order-reference"></span> ?</strong></p>
                    <p>L'email sera envoyé à : <span id="email-customer-email"></span></p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('emailConfirmationModal')">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="sendOrderConfirmationEmail()">
                        Confirmer l'envoi
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Transmission des données PHP vers JavaScript - version 1.1
        let ordersData = <?php echo json_encode($ordersData['orders'], JSON_UNESCAPED_SLASHES); ?>;
        let ordersStats = <?php echo json_encode($stats, JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <?php include('order_print.php'); ?>

    <script src="js/admin_orders.js"></script>
    
    <?php include('include.footer.php'); ?>
</body>
</html>