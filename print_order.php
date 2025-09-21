<?php
/**
 * Fichier d'impression de commande
 * Redirige vers la méthode d'impression JavaScript appropriée
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}

require_once 'config.php';
require_once 'functions.php';

// Vérifier l'authentification admin
if (!is_admin()) {
    http_response_code(403);
    die('Accès non autorisé');
}

// Récupérer la référence de commande
$reference = $_GET['reference'] ?? '';

if (empty($reference)) {
    http_response_code(400);
    die('Référence de commande manquante');
}

// Charger les données de la commande pour vérifier qu'elle existe
$orderFile = "commandes/{$reference}.json";
$tempOrderFile = "commandes/temp/{$reference}.json";

$orderData = null;
if (file_exists($orderFile)) {
    $orderData = json_decode(file_get_contents($orderFile), true);
} elseif (file_exists($tempOrderFile)) {
    $orderData = json_decode(file_get_contents($tempOrderFile), true);
}

if (!$orderData) {
    http_response_code(404);
    die('Commande non trouvée');
}

// Définir la date d'impression
$printDate = date('d/m/Y H:i');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Impression Commande <?php echo htmlspecialchars($reference); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/print.css">
</head>
<body>
    <div class="container">
        <h1>Impression de la commande <?php echo htmlspecialchars($reference); ?></h1>
        <p>Redirection vers l'interface d'administration pour l'impression...</p>

        <div class="print-actions">
            <button onclick="goToAdminOrders()" class="btn btn-primary">
                Aller aux commandes
            </button>
            <button onclick="printDirectly()" class="btn btn-secondary">
                Imprimer directement
            </button>
        </div>
    </div>

    <?php include('order_print.php'); ?>

    <script src="js/script.js"></script>
    <script src="js/admin_modals.js"></script>
    <script src="js/print.js"></script>

    <script>
        // Données de la commande
        const orderData = <?php echo json_encode($orderData, JSON_UNESCAPED_SLASHES); ?>;
        const reference = "<?php echo htmlspecialchars($reference); ?>";

        function goToAdminOrders() {
            // Rediriger vers admin_orders.php ou admin_paid_orders.php selon le statut
            if (orderData.payment_status === 'paid') {
                window.location.href = 'admin_paid_orders.php';
            } else {
                window.location.href = 'admin_orders.php';
            }
        }

        function printDirectly() {
            // Imprimer directement la commande
            printOrderSlip(reference);
        }

        // Auto-redirection après 3 secondes
        setTimeout(() => {
            goToAdminOrders();
        }, 3000);
    </script>
</body>
</html>