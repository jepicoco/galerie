<?php
/**
 * Page d'impression pour les bons de commande
 * Version: 1.1 - Correction pour éviter l'exécution lors de l'inclusion
 */

// IMPORTANT: Ne s'exécuter que si appelé directement, pas si inclus
if (basename($_SERVER['PHP_SELF']) !== 'order_print.php') {
    // Si inclus dans une autre page, ne rien faire
    return;
}

if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}
require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

// Récupérer la référence de commande
$reference = $_GET['reference'] ?? '';
$printDate = date('d/m/Y H:i');

// Initialiser les données de commande vides
$orderData = null;

if (!empty($reference)) {
    try {
        // Charger les données de la commande
        $order = new Order($reference);
        if ($order->load()) {
            $orderData = $order->getData();
            
            // Enrichir les données si nécessaire
            if (!isset($orderData['photos'])) {
                $orderData['photos'] = [];
                // Créer une photo basique basée sur les données CSV
                if (!empty($orderData['photo_name'])) {
                    $orderData['photos'][] = [
                        'name' => $orderData['photo_name'],
                        'activity_key' => $orderData['activity_key'],
                        'quantity' => $orderData['quantity'],
                        'unit_price' => getActivityPrice($orderData['activity_key']),
                        'pricing_type' => getActivityTypeInfo($orderData['activity_key'])['display_name'] ?? 'Photo standard'
                    ];
                }
            }
        }
    } catch (Exception $e) {
        error_log('Erreur chargement commande pour impression: ' . $e->getMessage());
    }
}

// Si aucune commande trouvée, afficher une erreur
if (!$orderData) {
    echo "<h1>Erreur</h1>";
    echo "<p>Commande non trouvée ou référence manquante.</p>";
    echo "<p><a href='admin_paid_orders.php'>← Retour</a></p>";
    exit;
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de commande - <?php echo $reference; ?></title>
    <link rel="stylesheet" href="css/print.css">
    <style>
        @media screen {
            body { margin: 20px; }
            .no-print { display: block; }
            .print-only { display: block; }
        }
        @media print {
            body { margin: 0; }
            .no-print { display: none !important; }
        }
    </style>
</head>
<body>
    <!-- Boutons d'action (masqués à l'impression) -->
    <div class="no-print" style="margin-bottom: 20px;">
        <button onclick="window.print()" class="btn btn-primary">🖨️ Imprimer</button>
        <button onclick="window.close()" class="btn btn-secondary">Fermer</button>
        <a href="admin_paid_orders.php" class="btn btn-outline">← Retour</a>
    </div>

    <!-- Section d'impression (cachée à l'écran) -->
    <div id="print-content" class="print-only">
    <!-- Container principal qui sera rempli dynamiquement -->
    <div id="print-container">
        <!-- Page principale avec les deux exemplaires côte à côte -->
        <div class="print-single-page">
            <div class="print-duplicates">
                <!-- Exemplaire 1 - Adhérent -->
                <div class="print-copy">
                    <div class="copy-main-header">
                        <h1><?php echo(SITE_NAME); ?></h1>
                        <div class="copy-print-date">Imprimé le <?php echo $printDate; ?></div>
                    </div>
                    <div class="copy-header">
                        <h2>📋 RÉCAPITULATIF DE COMMANDE</h2>
                        <div class="copy-type">Exemplaire Adhérent</div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <strong>Référence :</strong>
                            <span class="highlight"><?php echo htmlspecialchars($orderData['reference']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Date :</strong>
                            <span><?php echo date('d/m/Y H:i', strtotime($orderData['order_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Statut :</strong>
                            <span><?php echo ucfirst($orderData['command_status']); ?></span>
                        </div>
                    </div>
                    
                    <div class="customer-details">
                        <h3>👤 Informations client</h3>
                        <div class="detail-row">
                            <strong>Nom :</strong>
                            <span><?php echo htmlspecialchars($orderData['firstname'] . ' ' . $orderData['lastname']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Email :</strong>
                            <span><?php echo htmlspecialchars($orderData['email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Téléphone :</strong>
                            <span><?php echo htmlspecialchars($orderData['phone']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Section photos supprimée de la première page -->
                    <div class="photos-info">
                        <div class="total-section">
                            <strong>TOTAL : <?php echo $orderData['quantity']; ?> photo(s) - <?php echo number_format($orderData['total_price'], 2); ?>€</strong>
                            <br><small>Détail des photos ci-dessous</small>
                        </div>
                    </div>
                    
                    <div class="print-footer">
                        <p class="print-footer-user">Conservez précieusement cette référence pour tout suivi.</p>
                    </div>
                </div>
                
                <!-- Exemplaire 2 - Organisation -->
                <div class="print-copy">
                    <div class="copy-main-header">
                        <h1><?php echo(SITE_NAME) ?></h1>
                        <div class="copy-print-date">Imprimé le <?php echo $printDate; ?></div>
                    </div>
                    
                    <div class="copy-header">
                        <h2>📋 RÉCAPITULATIF DE COMMANDE</h2>
                        <div class="copy-type organization">Exemplaire Organisation</div>
                    </div>
                    
                    <div class="order-details">
                        <div class="detail-row">
                            <strong>Référence :</strong>
                            <span class="highlight"><?php echo htmlspecialchars($orderData['reference']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Date :</strong>
                            <span><?php echo date('d/m/Y H:i', strtotime($orderData['order_date'])); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Statut :</strong>
                            <span><?php echo ucfirst($orderData['command_status']); ?></span>
                        </div>
                    </div>
                    
                    <div class="customer-details">
                        <h3>👤 Informations client</h3>
                        <div class="detail-row">
                            <strong>Nom :</strong>
                            <span><?php echo htmlspecialchars($orderData['firstname'] . ' ' . $orderData['lastname']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Email :</strong>
                            <span><?php echo htmlspecialchars($orderData['email']); ?></span>
                        </div>
                        <div class="detail-row">
                            <strong>Téléphone :</strong>
                            <span><?php echo htmlspecialchars($orderData['phone']); ?></span>
                        </div>
                    </div>
                    
                    <!-- Détail des photos -->
                    <div class="items-details">
                        <h3>📷 Photos commandées</h3>
                        <table class="items-table">
                            <thead>
                                <tr>
                                    <th>Activité</th>
                                    <th>Photo</th>
                                    <th>Qté</th>
                                    <th>Prix unit.</th>
                                    <th>Sous-total</th>
                                </tr>
                            </thead>
                            <tbody>
                                <?php if (!empty($orderData['photos'])): ?>
                                    <?php foreach ($orderData['photos'] as $photo): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($photo['pricing_type']); ?></td>
                                            <td><?php echo htmlspecialchars($photo['name']); ?></td>
                                            <td><?php echo $photo['quantity']; ?></td>
                                            <td><?php echo number_format($photo['unit_price'], 2); ?>€</td>
                                            <td><?php echo number_format($photo['quantity'] * $photo['unit_price'], 2); ?>€</td>
                                        </tr>
                                    <?php endforeach; ?>
                                <?php else: ?>
                                    <tr>
                                        <td><?php echo htmlspecialchars(getActivityTypeInfo($orderData['activity_key'])['display_name'] ?? 'Photo standard'); ?></td>
                                        <td><?php echo htmlspecialchars($orderData['photo_name']); ?></td>
                                        <td><?php echo $orderData['quantity']; ?></td>
                                        <td><?php echo number_format(getActivityPrice($orderData['activity_key']), 2); ?>€</td>
                                        <td><?php echo number_format($orderData['total_price'], 2); ?>€</td>
                                    </tr>
                                <?php endif; ?>
                            </tbody>
                        </table>
                        <div class="total-section">
                            <strong>TOTAL : <?php echo $orderData['quantity']; ?> photo(s) - <?php echo number_format($orderData['total_price'], 2); ?>€</strong>
                        </div>
                    </div>
                    
                    <div class="print-footer">
                        <div class="status-section">
                            <h4>📝 Suivi de commande</h4>
                            <div class="status-boxes">
                                <div class="status-box">☐ Préparation</div>
                                <div class="status-box">☐ Prêt</div>
                                <div class="status-box">☐ Livré</div>
                            </div>
                            <div class="payment-section">
                                <div class="payment-line">
                                    <strong>Paiement :</strong> 
                                    <span class="checkbox">☐ Espèces</span>
                                    <span class="checkbox">☐ Chèque</span>
                                    <span class="checkbox">☐ CB</span>
                                </div>
                                <div class="payment-line">
                                    <strong>Montant :</strong> <?php echo number_format($orderData['total_price'], 2); ?> €
                                    <strong style="margin-left: 2rem;">Le :</strong> ___________
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

</body>
</html>