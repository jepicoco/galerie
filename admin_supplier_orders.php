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

// Inclure le handler pour les fonctions
require_once 'admin_supplier_orders_handler.php';

// Charger les statistiques des commandes fournisseur
$supplierStats = getSupplierOrdersStats();

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Commandes Fournisseur - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/admin.supplier_orders.css">
    <link rel="icon" href="favicon.png" />
</head>
<body>

    <?php include('include.header.php'); ?>

    <!-- Inclusion des modales communes -->
    <?php include('modals_common.php'); ?>

    <main class="main-content">
        <div class="container">
            <div class="admin-nav">
                <h1>Commandes Fournisseur</h1>
                <a href="admin_orders.php" class="nav-link">← Retour aux commandes</a>
            </div>

            <!-- Section 1: Commande Fournisseur -->
            <div class="supplier-section">
                <h2>📦 Commande Fournisseur</h2>
                <p class="section-description">Résumé des articles à commander auprès des fournisseurs</p>

                <div class="supplier-stats">
                    <div class="supplier-card supplier-a">
                        <h3>Fournisseur A - Photos</h3>
                        <div class="stat-number"><?php echo $supplierStats['supplier_a']['total_items']; ?></div>
                        <div class="stat-label">Photos à commander</div>
                        <button class="btn btn-primary" onclick="exportSupplierOrder('A')">
                            📄 Export commande fournisseur A
                        </button>
                    </div>

                    <div class="supplier-card supplier-b">
                        <h3>Fournisseur B - USB</h3>
                        <div class="stat-number"><?php echo $supplierStats['supplier_b']['total_items']; ?></div>
                        <div class="stat-label">Clés USB à commander</div>
                        <button class="btn btn-primary" onclick="exportSupplierOrder('B')">
                            📄 Export commande fournisseur B
                        </button>
                    </div>
                </div>

                <div class="detail-section">
                    <div class="accordion-header" onclick="toggleAccordion('supplier-a-details')">
                        <h3>Détail des articles - Fournisseur A (Photos)</h3>
                        <button class="accordion-toggle" id="toggle-supplier-a-details" aria-label="Toggle details">+</button>
                    </div>
                    <div id="supplier-a-details" class="items-list accordion-content">
                        <?php if (empty($supplierStats['supplier_a']['items'])): ?>
                            <p class="no-items">Aucune photo à commander</p>
                        <?php else: ?>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Dossier</th>
                                        <th>Photo</th>
                                        <th>Quantité</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supplierStats['supplier_a']['items'] as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['folder']); ?></td>
                                            <td><?php echo htmlspecialchars($item['photo']); ?></td>
                                            <td class="quantity"><?php echo $item['quantity']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>

                    <div class="accordion-header" onclick="toggleAccordion('supplier-b-details')">
                        <h3>Détail des articles - Fournisseur B (USB)</h3>
                        <button class="accordion-toggle" id="toggle-supplier-b-details" aria-label="Toggle details">+</button>
                    </div>
                    <div id="supplier-b-details" class="items-list accordion-content">
                        <?php if (empty($supplierStats['supplier_b']['items'])): ?>
                            <p class="no-items">Aucune clé USB à commander</p>
                        <?php else: ?>
                            <table class="items-table">
                                <thead>
                                    <tr>
                                        <th>Article</th>
                                        <th>Quantité</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php foreach ($supplierStats['supplier_b']['items'] as $item): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['photo']); ?></td>
                                            <td class="quantity"><?php echo $item['quantity']; ?></td>
                                        </tr>
                                    <?php endforeach; ?>
                                </tbody>
                            </table>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Section 2: Répartition des Commandes -->
            <div class="distribution-section">
                <h2>📋 Répartition des Commandes</h2>
                <p class="section-description">Liste des articles à distribuer par client</p>

                <div class="distribution-actions">
                    <button class="btn btn-primary" onclick="generateDistributionList()">
                        📝 Générer liste de répartition
                    </button>
                    <button class="btn btn-secondary" onclick="exportDistributionCSV()">
                        📊 Export CSV
                    </button>
                </div>

                <div id="distribution-container" class="distribution-container">
                    <!-- Contenu généré dynamiquement -->
                </div>
            </div>

            <!-- Section 3: Reset Export -->
            <div class="reset-section">
                <h2>🔄 Gestion des Exports</h2>
                <p class="section-description">Réinitialiser le statut d'export pour recommencer les tests</p>

                <button class="btn btn-warning" onclick="showResetConfirmation()">
                    ⚠️ Reset Statut Export
                </button>
            </div>
        </div>
    </main>

    <!-- Modale Confirmation Reset -->
    <div id="resetModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h3>Confirmer la réinitialisation</h3>
                <span class="close" onclick="closeModal('resetModal')">&times;</span>
            </div>
            <div class="modal-body">
                <div class="warning-message">
                    <p><strong>⚠️ Attention !</strong></p>
                    <p>Cette action va réinitialiser la colonne "Exported" de toutes les commandes dans le fichier CSV.</p>
                    <p>Êtes-vous sûr de vouloir continuer ?</p>
                </div>
                <div class="form-actions">
                    <button type="button" class="btn btn-secondary" onclick="closeModal('resetModal')">
                        Annuler
                    </button>
                    <button type="button" class="btn btn-warning" onclick="confirmReset()">
                        Confirmer la réinitialisation
                    </button>
                </div>
            </div>
        </div>
    </div>

    <script>
        // Accordion toggle function
        function toggleAccordion(contentId) {
            const content = document.getElementById(contentId);
            const toggleBtn = document.getElementById('toggle-' + contentId);

            if (content.classList.contains('open')) {
                content.classList.remove('open');
                toggleBtn.textContent = '+';
            } else {
                content.classList.add('open');
                toggleBtn.textContent = '-';
            }
        }

        function exportSupplierOrder(supplier) {
            showNotification('Génération de la commande fournisseur ' + supplier + '...', 'info');

            fetch('admin_supplier_orders_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=export_supplier_order&supplier=' + encodeURIComponent(supplier)
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showNotification(result.message, 'success');
                    if (result.file) {
                        window.location.href = result.file;
                    }
                } else {
                    showNotification('Erreur: ' + result.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur: ' + error.message, 'error');
            });
        }

        function generateDistributionList() {
            showNotification('Génération de la liste de répartition...', 'info');

            fetch('admin_supplier_orders_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=generate_distribution_list'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    displayDistributionList(result.data);
                    showNotification(result.message, 'success');
                } else {
                    showNotification('Erreur: ' + result.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur: ' + error.message, 'error');
            });
        }

        function displayDistributionList(data) {
            const container = document.getElementById('distribution-container');

            if (!data || data.length === 0) {
                container.innerHTML = '<p class="no-items">Aucune commande à répartir</p>';
                return;
            }

            let html = '<div class="orders-distribution">';

            data.forEach(order => {
                html += `
                    <div class="order-distribution-card">
                        <div class="order-header">
                            <h4>${order.reference}</h4>
                            <span class="customer-name">${order.firstname} ${order.lastname}</span>
                        </div>
                        <div class="order-items">
                            <table class="items-table-compact">
                                <thead>
                                    <tr>
                                        <th>Article</th>
                                        <th>Quantité</th>
                                        <th>Fait</th>
                                    </tr>
                                </thead>
                                <tbody>
                `;

                order.items.forEach((item, index) => {
                    html += `
                        <tr>
                            <td>${item.folder} / ${item.photo}</td>
                            <td class="quantity">${item.quantity}</td>
                            <td><input type="checkbox" class="item-checkbox" data-ref="${order.reference}" data-index="${index}"></td>
                        </tr>
                    `;
                });

                html += `
                                </tbody>
                            </table>
                        </div>
                    </div>
                `;
            });

            html += '</div>';
            container.innerHTML = html;
        }

        function exportDistributionCSV() {
            showNotification('Export CSV en cours...', 'info');

            fetch('admin_supplier_orders_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=export_distribution_csv'
            })
            .then(response => response.json())
            .then(result => {
                if (result.success) {
                    showNotification(result.message, 'success');
                    if (result.file) {
                        window.location.href = result.file;
                    }
                } else {
                    showNotification('Erreur: ' + result.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur: ' + error.message, 'error');
            });
        }

        function showResetConfirmation() {
            document.getElementById('resetModal').style.display = 'block';
        }

        function confirmReset() {
            showNotification('Réinitialisation en cours...', 'info');

            fetch('admin_supplier_orders_handler.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                body: 'action=reset_exported_status'
            })
            .then(response => response.json())
            .then(result => {
                closeModal('resetModal');
                if (result.success) {
                    showNotification(result.message, 'success');
                    setTimeout(() => location.reload(), 1500);
                } else {
                    showNotification('Erreur: ' + result.error, 'error');
                }
            })
            .catch(error => {
                showNotification('Erreur: ' + error.message, 'error');
            });
        }

        function closeModal(modalId) {
            document.getElementById(modalId).style.display = 'none';
        }

        // Fermer la modale en cliquant à l'extérieur
        window.onclick = function(event) {
            const modals = document.getElementsByClassName('modal');
            for (let modal of modals) {
                if (event.target === modal) {
                    modal.style.display = 'none';
                }
            }
        }
    </script>

    <script src="js/admin_modals.js"></script>

    <?php include('include.footer.php'); ?>
</body>
</html>
