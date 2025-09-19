<?php
/**
 * API pour récupérer les compteurs de badges de notifications
 * Fournit les nombres de commandes pour affichage dans les bulles du header admin
 */

// Vérification d'accès
if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/orders.list.class.php';

session_start();

// Vérifier l'authentification admin
$is_admin = is_admin();

if (!$is_admin) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

/**
 * Compte les commandes non payées
 * @return int Nombre de commandes non payées
 */
function countUnpaidOrders() {
    $ordersList = new OrdersList();
    return $ordersList->countPendingPayments();
}

/**
 * Compte les commandes prêtes pour retrait (payées mais pas encore retirées)
 * @return int Nombre de commandes prêtes pour retrait
 */
function countReadyForPickup() {
    $ordersList = new OrdersList();
    return $ordersList->countPendingRetrievals();
}

/**
 * Compte les nouvelles commandes (créées dans les dernières 24h)
 * @return int Nombre de nouvelles commandes
 */
function countNewOrders() {
    $ordersList = new OrdersList();
    $allOrdersData = $ordersList->loadOrdersData('all');
    $count = 0;
    $oneDayAgo = time() - 24 * 3600;

    foreach ($allOrdersData['orders'] as $order) {
        $orderDate = $order['created_at'] ?? '';

        if (!empty($orderDate)) {
            // Convertir la date de commande en timestamp
            $orderTimestamp = strtotime($orderDate);

            // Vérifier si la commande a été créée dans les dernières 24h
            if ($orderTimestamp >= $oneDayAgo) {
                $count++;
            }
        }
    }

    return $count;
}

// Nettoyer la sortie
ob_start();
ob_clean();

// Headers pour JSON et cache
header('Content-Type: application/json');
header('Cache-Control: no-cache, must-revalidate');
header('Expires: Mon, 26 Jul 1997 05:00:00 GMT');

try {
    // Récupérer les compteurs
    $unpaidCount = countUnpaidOrders();
    $pickupCount = countReadyForPickup();
    $newCount = countNewOrders();
    
    // Construire la réponse
    $response = [
        'success' => true,
        'badges' => [
            'unpaid_orders' => $unpaidCount,
            'ready_for_pickup' => $pickupCount,
            'new_orders' => $newCount,
            'total_pending' => $unpaidCount + $pickupCount
        ],
        'timestamp' => time(),
        'formatted_time' => date('Y-m-d H:i:s')
    ];
    
    // Log de l'action admin
    if (isset($logger)) {
        $logger->adminAction('badge_counts_requested', [
            'unpaid' => $unpaidCount,
            'pickup' => $pickupCount,
            'new' => $newCount
        ]);
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des compteurs: ' . $e->getMessage()
    ]);
}
?>