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
            <div class="orders-stats" id="orders-stats">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $paidStats['total']; ?></div>
                    <div class="stat-label">Commandes à retirer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $paidStats['total_photos']; ?></div>
                    <div class="stat-label">Photos à retirer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $paidStats['total_usb_keys']; ?></div>
                    <div class="stat-label">Clés USB à retirer</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($paidStats['total_amount'], 2); ?>€</div>
                    <div class="stat-label">Montant total</div>
                </div>
            </div>

            <!-- Onglets de filtrage des commandes -->
            <div class="orders-tabs">
                <button class="tab-button active" data-status="to_retrieve" id="tab-to_retrieve">
                    À retirer
                    <span class="tab-badge" id="to_retrieve-badge"><?php echo count($ordersList->loadOrdersData('to_retrieve')['orders']); ?></span>
                </button>
                <button class="tab-button" data-status="retrieved" id="tab-retrieved">
                    Retirées
                    <span class="tab-badge" id="retrieved-badge"><?php echo count($ordersList->loadOrdersData('retrieved')['orders']); ?></span>
                </button>
                <button class="tab-button" data-status="all_paid" id="tab-all_paid">
                    Toutes les commandes
                    <span class="tab-badge" id="all_paid-badge"><?php echo count($ordersList->loadOrdersData('paid')['orders']) + count($ordersList->loadOrdersData('retrieved')['orders']); ?></span>
                </button>
            </div>

            <!-- Filtre de recherche -->
            <div class="search-filters">
                <div class="search-container">
                    <input type="text" id="search-filter" class="search-input" placeholder="Rechercher par numéro de commande ou nom..." />
                    <button type="button" id="clear-search" class="search-clear" title="Effacer la recherche">✕</button>
                </div>
            </div>

            <!-- Liste des commandes réglées -->
            <div class="orders-list">
                <!-- Indicateur de chargement -->
                <div id="loading-indicator" class="loading-indicator" style="display: none;">
                    <div class="spinner"></div>
                    <span>Chargement des commandes...</span>
                </div>

                <h2 id="orders-list-title">Commandes en attente de retrait</h2>

                <div id="orders-container">
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
                                        Valider retrait
                                    </button>
                                </div>
                            </div>
                            <div class="order-details">
                                <span class="photos-count">
                                    <?php
                                    $parts = [];
                                    if ($order['photos_count'] > 0) {
                                        $parts[] = $order['photos_count'] . ' photo' . ($order['photos_count'] > 1 ? 's' : '');
                                    }
                                    if ($order['usb_keys_count'] > 0) {
                                        $parts[] = $order['usb_keys_count'] . ' clé' . ($order['usb_keys_count'] > 1 ? 's' : '') . ' USB';
                                    }
                                    echo implode(' + ', $parts);
                                    ?>
                                </span>
                                <span class="retrieval-date">À récupérer le <?php echo date('d/m/Y', strtotime($order['retrieval_date'])); ?></span>
                            </div>
                        </div>
                    <?php endforeach; ?>
                    <?php endif; ?>
                </div> <!-- /orders-container -->
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
                    <p><strong>Êtes-vous sûr que la commande <span id="retrieved-reference"></span> a été récupérée aujourd'hui ?</strong></p>
                    <p>Client : <span id="retrieved-customer-name"></span></p>
                    <p>Cette action marquera la commande comme terminée.</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('retrievedModal')">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-primary" onclick="confirmRetrieved()">
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

    <script>
        // === Système d'onglets et de recherche pour admin_paid_orders.php ===

        let currentStatus = 'to_retrieve'; // Statut par défaut
        let isLoadingTab = false;
        let allOrdersCache = {}; // Cache pour éviter les requêtes répétées

        /**
         * Formate l'affichage des photos et clés USB pour une commande
         */
        function formatPhotosAndUSB(order) {
            const parts = [];

            if (order.photos_count && order.photos_count > 0) {
                parts.push(`${order.photos_count} photo${order.photos_count > 1 ? 's' : ''}`);
            }

            if (order.usb_keys_count && order.usb_keys_count > 0) {
                parts.push(`${order.usb_keys_count} clé${order.usb_keys_count > 1 ? 's' : ''} USB`);
            }

            return parts.length > 0 ? parts.join(' + ') : '0 item';
        }

        /**
         * Bascule entre les onglets de statut de commandes
         */
        async function switchTab(status, forceRefresh = false) {
            console.log('🔄 switchTab appelée avec status:', status, 'forceRefresh:', forceRefresh);

            if (isLoadingTab) {
                console.log('⏳ Chargement en cours, action ignorée');
                return;
            }

            if (currentStatus === status && !forceRefresh) {
                console.log('📊 Même statut, pas de changement');
                return;
            }

            isLoadingTab = true;
            showLoadingIndicator();

            try {
                // Charger les données selon le statut
                const response = await fetch(`admin_paid_orders_handler.php?action=get_orders&status=${status}`, {
                    method: 'GET',
                    headers: {
                        'Cache-Control': 'no-cache',
                        'X-Requested-With': 'XMLHttpRequest'
                    }
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const data = await response.json();
                console.log('📦 Données AJAX reçues pour', status, ':', data);
                console.log('📊 Nombre de commandes:', data.orders?.length || 'undefined');

                if (data.success) {
                    currentStatus = status;
                    updateTabButtons(status);
                    updateOrdersList(data.orders);
                    updateStats(data.stats);
                    updateTabBadges(data.tabStats);
                    updateOrdersTitle(status);
                    clearSearch(); // Effacer la recherche lors du changement d'onglet
                } else {
                    throw new Error(data.error || 'Erreur lors du chargement des commandes');
                }

            } catch (error) {
                console.error('❌ Erreur lors du changement d\'onglet:', error);
                showError('Erreur lors du chargement des commandes: ' + error.message);
            } finally {
                hideLoadingIndicator();
                isLoadingTab = false;
            }
        }

        /**
         * Met à jour les boutons d'onglets
         */
        function updateTabButtons(activeStatus) {
            document.querySelectorAll('.tab-button').forEach(button => {
                const status = button.getAttribute('data-status');
                if (status === activeStatus) {
                    button.classList.add('active');
                } else {
                    button.classList.remove('active');
                }
            });
        }

        /**
         * Met à jour le titre de la liste
         */
        function updateOrdersTitle(status) {
            const titleElement = document.getElementById('orders-list-title');
            const titles = {
                'to_retrieve': 'Commandes en attente de retrait',
                'retrieved': 'Commandes retirées',
                'all_paid': 'Toutes les commandes payées'
            };
            titleElement.textContent = titles[status] || 'Commandes';
        }

        /**
         * Met à jour la liste des commandes
         */
        function updateOrdersList(orders) {
            const container = document.getElementById('orders-container');

            if (!orders || orders.length === 0) {
                container.innerHTML = `
                    <div class="no-orders">
                        <h3>Aucune commande trouvée</h3>
                        <p>Aucune commande ne correspond aux critères actuels.</p>
                    </div>
                `;
                return;
            }

            const ordersHTML = orders.map(order => {
                // Bouton d'action selon le statut
                let actionButton = '';
                if (order.command_status === 'paid') {
                    actionButton = `
                        <button class="btn-retrieved"
                                onclick="showRetrievedModal('${order.reference}')"
                                title="Valider le retrait de cette commande">
                            Valider retrait
                        </button>
                    `;
                }

                return `
                    <div class="order-card paid-order" data-reference="${order.reference}">
                        <div class="order-header">
                            <div class="order-info">
                                <h3>${order.reference}</h3>
                                <p class="customer-name">${order.firstname} ${order.lastname}</p>
                            </div>
                            <div class="order-actions">
                                <button class="btn-contact" onclick="showContactModal('${order.reference}')" title="Informations de contact">🗓️</button>
                                <button class="btn-details" onclick="showDetailsModal('${order.reference}')" title="Liste détaillée">📋</button>
                                <button class="btn-print" onclick="printOrderSlip('${order.reference}')" title="Imprimer">🖨️</button>
                                <span class="order-amount">${parseFloat(order.amount).toFixed(2)}€</span>
                                ${actionButton}
                            </div>
                        </div>
                        <div class="order-details">
                            <span class="photos-count">${formatPhotosAndUSB(order)}</span>
                            <span class="retrieval-date">
                                ${order.command_status === 'retrieved'
                                    ? `Récupérée le ${new Date(order.actual_retrieval_date).toLocaleDateString('fr-FR')}`
                                    : `À récupérer le ${new Date(order.retrieval_date).toLocaleDateString('fr-FR')}`
                                }
                            </span>
                        </div>
                    </div>
                `;
            }).join('');

            container.innerHTML = ordersHTML;
        }

        /**
         * Met à jour les statistiques
         */
        function updateStats(stats) {
            console.log('📊 updateStats appelée avec:', stats);
            const statsContainer = document.getElementById('orders-stats');
            console.log('📊 statsContainer trouvé:', !!statsContainer);

            if (!statsContainer || !stats) {
                console.log('⚠️ updateStats: Conteneur ou stats manquant');
                return;
            }

            const statusLabels = {
                'to_retrieve': 'à retirer',
                'retrieved': 'retirées',
                'all_paid': 'payées'
            };

            statsContainer.innerHTML = `
                <div class="stat-card">
                    <div class="stat-number">${stats.total}</div>
                    <div class="stat-label">Commandes ${statusLabels[currentStatus] || ''}</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.total_photos}</div>
                    <div class="stat-label">Photos</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${stats.total_usb_keys || 0}</div>
                    <div class="stat-label">Clés USB</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number">${parseFloat(stats.total_amount).toFixed(2)}€</div>
                    <div class="stat-label">Montant total</div>
                </div>
            `;
        }

        /**
         * Met à jour les badges des onglets
         */
        function updateTabBadges(newTabStats) {
            document.getElementById('to_retrieve-badge').textContent = newTabStats.to_retrieve.count;
            document.getElementById('retrieved-badge').textContent = newTabStats.retrieved.count;
            document.getElementById('all_paid-badge').textContent = newTabStats.all_paid.count;
        }

        /**
         * Affiche l'indicateur de chargement
         */
        function showLoadingIndicator() {
            document.getElementById('loading-indicator').style.display = 'flex';
            document.getElementById('orders-container').style.opacity = '0.5';
        }

        /**
         * Masque l'indicateur de chargement
         */
        function hideLoadingIndicator() {
            document.getElementById('loading-indicator').style.display = 'none';
            document.getElementById('orders-container').style.opacity = '1';
        }

        /**
         * Affiche un message d'erreur
         */
        function showError(message) {
            // Réutiliser le système d'alerte existant ou créer une notification
            alert('Erreur: ' + message);
        }

        /**
         * Filtre de recherche
         */
        function filterOrders() {
            const searchTerm = document.getElementById('search-filter').value.toLowerCase().trim();
            const clearBtn = document.getElementById('clear-search');
            const orders = document.querySelectorAll('.order-card');

            // Afficher/masquer le bouton effacer
            clearBtn.style.display = searchTerm ? 'block' : 'none';

            if (!searchTerm) {
                // Afficher toutes les commandes si pas de recherche
                orders.forEach(order => order.style.display = 'block');
                return;
            }

            // Filtrer les commandes
            orders.forEach(order => {
                const reference = order.getAttribute('data-reference').toLowerCase();
                const customerName = order.querySelector('.customer-name').textContent.toLowerCase();

                if (reference.includes(searchTerm) || customerName.includes(searchTerm)) {
                    order.style.display = 'block';
                } else {
                    order.style.display = 'none';
                }
            });
        }

        /**
         * Efface la recherche
         */
        function clearSearch() {
            document.getElementById('search-filter').value = '';
            document.getElementById('clear-search').style.display = 'none';
            filterOrders();
        }

        // === Initialisation ===
        document.addEventListener('DOMContentLoaded', function() {
            // Event listeners pour les onglets
            document.querySelectorAll('.tab-button').forEach(button => {
                button.addEventListener('click', function() {
                    const status = this.getAttribute('data-status');
                    switchTab(status);
                });
            });

            // Event listeners pour la recherche
            document.getElementById('search-filter').addEventListener('input', filterOrders);
            document.getElementById('clear-search').addEventListener('click', clearSearch);

            console.log('🎯 Système d\'onglets et recherche initialisé pour admin_paid_orders');
        });

        // ===== FONCTIONS MODALES =====

        /**
         * Affiche la modale de contact
         */
        async function showContactModal(reference) {
            try {
                const response = await fetch('admin_paid_orders_handler.php', {
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
                    showError('Erreur: ' + result.error);
                }
            } catch (error) {
                console.error('Erreur lors du chargement des informations de contact:', error);
                showError('Erreur lors du chargement des informations de contact');
            }
        }

        /**
         * Affiche la modale des détails
         */
        function showDetailsModal(reference) {
            // Trouver les détails de la commande dans les données JavaScript
            let orderData = null;

            // D'abord chercher dans ordersData global
            if (typeof ordersData !== 'undefined' && ordersData) {
                orderData = ordersData.find(o => o.reference === reference);
            }

            // Si pas trouvé, charger via AJAX
            if (!orderData) {
                // Fallback: récupérer depuis le DOM
                const orders = document.querySelectorAll('.order-card');
                for (let orderCard of orders) {
                    if (orderCard.getAttribute('data-reference') === reference) {
                        const nameText = orderCard.querySelector('.order-info h3, .order-info h4')?.textContent || '';
                        const nameParts = nameText.split(' ');
                        orderData = {
                            reference: reference,
                            firstname: nameParts[0] || '',
                            lastname: nameParts.slice(1).join(' ') || '',
                            email: orderCard.querySelector('.order-info p')?.textContent || 'Non disponible',
                            phone: 'Non disponible',
                            retrieval_date: 'Non précisée',
                            payment_date: 'Non précisée',
                            payment_mode: 'Non précisé'
                        };
                        break;
                    }
                }
            }

            if (!orderData) {
                showError('Commande introuvable');
                return;
            }

            // Remplir les informations client selon la structure de modals_common.php
            document.getElementById('details-reference').textContent = reference;
            document.getElementById('details-customer-name').textContent = orderData.firstname + ' ' + orderData.lastname;
            document.getElementById('details-customer-email').textContent = orderData.email;
            document.getElementById('details-customer-phone').textContent = orderData.phone;
            document.getElementById('details-retrieval-date').textContent = orderData.retrieval_date;

            // Remplir les informations supplémentaires si disponibles
            if (document.getElementById('details-order-date')) {
                document.getElementById('details-order-date').textContent = 'Non précisée';
            }
            if (document.getElementById('details-payment-mode')) {
                document.getElementById('details-payment-mode').textContent = 'Non précisé';
            }
            if (document.getElementById('details-status')) {
                document.getElementById('details-status').textContent = 'Payée';
                document.getElementById('details-status').className = 'status-badge paid';
            }

            showModal('detailsModal');
        }

        /**
         * Affiche la modale de confirmation de récupération
         */
        function showRetrievedModal(reference) {
            document.getElementById('retrieved-reference').textContent = reference;
            showModal('retrievedModal');
        }

        /**
         * Confirme le marquage comme récupéré
         */
        async function confirmRetrieved() {
            const reference = document.getElementById('retrieved-reference').textContent;

            try {
                const response = await fetch('admin_paid_orders_handler.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: `action=mark_as_retrieved&reference=${encodeURIComponent(reference)}`
                });

                if (!response.ok) {
                    throw new Error(`HTTP ${response.status}: ${response.statusText}`);
                }

                const result = await response.json();

                if (result.success) {
                    closeModal('retrievedModal');
                    showNotification('Commande marquée comme récupérée', 'success');
                    // Recharger la page pour actualiser toutes les données
                    setTimeout(() => {
                        location.reload();
                    }, 1000); // Délai pour laisser voir la notification
                } else {
                    showError('Erreur: ' + result.message);
                }
            } catch (error) {
                console.error('Erreur lors du marquage:', error);
                showError('Erreur technique: ' + error.message);
            }
        }

        /**
         * Fonctions utilitaires pour les modales
         */
        function showModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'block';
            }
        }

        function closeModal(modalId) {
            const modal = document.getElementById(modalId);
            if (modal) {
                modal.style.display = 'none';
            }
        }

        /**
         * Fonction d'impression
         */
        function printOrderSlip(reference = null) {
            if (!reference) {
                showError('Référence de commande manquante');
                return;
            }

            // Ouvrir la page d'impression dans une nouvelle fenêtre
            const printUrl = `print_order.php?reference=${encodeURIComponent(reference)}`;
            window.open(printUrl, '_blank', 'width=800,height=600');
        }

        /**
         * Affiche les notifications
         */
        function showNotification(message, type = 'info') {
            // Créer une notification simple
            const notification = document.createElement('div');
            notification.style.cssText = `
                position: fixed;
                top: 20px;
                right: 20px;
                background: ${type === 'success' ? '#4CAF50' : type === 'error' ? '#f44336' : '#2196F3'};
                color: white;
                padding: 15px 20px;
                border-radius: 4px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
                z-index: 10000;
                font-size: 14px;
                max-width: 300px;
            `;
            notification.textContent = message;

            document.body.appendChild(notification);

            // Supprimer après 3 secondes
            setTimeout(() => {
                if (notification.parentNode) {
                    notification.parentNode.removeChild(notification);
                }
            }, 3000);
        }

    </script>

    <!-- Script admin_paid_orders.js supprimé - toutes les fonctions sont maintenant dans le script principal -->

    <?php include('include.footer.php'); ?>

</body>
</html>