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
require_once 'admin_paid_orders_handler.php';

// Charger les données des commandes réglées avec la nouvelle classe
$ordersList = new OrdersList();
$paidOrdersData = $ordersList->loadOrdersData('paid'); // Filtrer les commandes payées
$paidStats = $ordersList->calculateStats($paidOrdersData['orders']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Retraits - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/admin.orders.css">
    <link rel="stylesheet" href="css/print.css">
    <link rel="icon" href="favicon.png" />
</head>
<body>
    
    <?php include('include.header.php'); ?>

    <main class="main-content">
        <div class="container">
            <div class="admin-nav">
                <?php $pendingPaymentsCount = countPendingPayments(); ?>
                <a href="admin_orders.php" class="nav-link">
                    ← Commandes 
                    <?php if ($pendingPaymentsCount > 0): ?>
                        <span class="nav-counter"><?php echo $pendingPaymentsCount; ?></span>
                    <?php endif; ?>
                </a>
                <h1>Retraits</h1>
            </div>

            <!-- Statistiques -->
            <div class="orders-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $paidStats['total']; ?></div>
                    <div class="stat-label">Commandes à retirer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $paidStats['total_photos']; ?></div>
                    <div class="stat-label">Photos à retirer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($paidStats['total_amount'], 2); ?>€</div>
                    <div class="stat-label">Montant total</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $paidStats['retrieved_today']; ?></div>
                    <div class="stat-label">Récupérées aujourd'hui</div>
                </div>
            </div>

            <!-- Liste des commandes réglées -->
            <div class="orders-list">
                <h2>Commandes en attente de retrait</h2>
                
                <?php if (empty($paidOrdersData['orders'])): ?>
                    <div class="no-orders">
                        <h3>Aucune commande en attente de retrait</h3>
                        <p>Toutes les commandes ont été récupérées !</p>
                    </div>
                <?php else: ?>
                    <?php foreach ($paidOrdersData['orders'] as $order): ?>
                        <div class="order-card paid-order" data-reference="<?php echo $order['reference']; ?>">
                            <div class="order-header">
                                <div class="order-info">
                                    <h3><?php echo $order['reference']; ?></h3>
                                    <p class="customer-name"><?php echo $order['firstname'] . ' ' . $order['lastname']; ?></p>
                                    <span class="payment-badge">✅ Réglée le <?php echo date('d/m/Y', strtotime($order['payment_date'])); ?></span>
                                </div>
                                <div class="order-actions">
                                    <button class="btn-contact" onclick="showContactModal('<?php echo $order['reference']; ?>')" title="Informations de contact">
                                        🗓️
                                    </button>
                                    <button class="btn-details" onclick="showDetailsModal('<?php echo $order['reference']; ?>')" title="Liste détaillée">
                                        📋
                                    </button>
                                    <button class="btn-print" onclick="printOrderSlip('<?php echo $order['reference']; ?>')" title="Imprimer bon de commande">
                                        🖨️
                                    </button>
                                    <button class="btn-email" onclick="showEmailConfirmationModal('<?php echo $order['reference']; ?>')" title="Renvoyer email de confirmation">
                                        📧
                                    </button>
                                    <span class="order-amount"><?php echo number_format($order['amount'], 2); ?>€</span>
                                    <button class="btn-retrieved" onclick="showRetrievedModal('<?php echo $order['reference']; ?>')">
                                        Récupéré aujourd'hui
                                    </button>
                                </div>
                            </div>
                            <div class="order-details">
                                <span class="photos-count"><?php echo $order['total_photos']; ?> photo(s)</span>
                                <span class="retrieval-date">À récupérer le <?php echo date('d/m/Y', strtotime($order['retrieval_date'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                <?php endif; ?>
            </div>
        </div>
    </main>

    <!-- Modale Récupération -->
    <div id="retrievedModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmer la récupération</h3>
                <span class="close" onclick="closeModal('retrievedModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="confirmation-message">
                    <p><strong>Êtes-vous sûr que la commande <span id="retrieved-order-reference"></span> a été récupérée aujourd'hui ?</strong></p>
                    <p>Client : <span id="retrieved-customer-name"></span></p>
                    <p>Cette action marquera la commande comme terminée.</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('retrievedModal')">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmOrderRetrieved()">
                        Confirmer
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Inclusion des modales communes -->
    <?php include('modals_common.php'); ?>

    <script>
        // Transmission des données PHP vers JavaScript - version 1.2
        let ordersData = <?php echo json_encode($paidOrdersData['orders'], JSON_UNESCAPED_SLASHES); ?>;
        let ordersStats = <?php echo json_encode($paidStats, JSON_UNESCAPED_SLASHES); ?>;
    </script>

    <?php include('order_print.php'); ?>

    <script src="js/admin_paid_orders.js"></script>

    <?php include('include.footer.php'); ?>

</body>
</html>