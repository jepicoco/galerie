<?php
/**
 * API endpoint pour récupérer les statistiques des commandes
 * Utilisé pour la mise à jour temps réel des badges dans le header
 */
define('GALLERY_ACCESS', true);

require_once 'config.php';

session_start();

require_once 'functions.php';

if (!$is_admin) {
    http_response_code(403);
    header('Content-Type: application/json');
    echo json_encode(['error' => 'Accès non autorisé']);
    exit;
}

// Headers pour la réponse JSON et éviter le cache
header('Content-Type: application/json');
header('Cache-Control: no-cache, no-store, must-revalidate');
header('Pragma: no-cache');
header('Expires: 0');

try {
    $ordersList = new OrdersList();
    
    // Récupérer les statistiques
    $retrieval_count = $ordersList->countPendingRetrievals();  // Commandes payées en attente de retrait
    $orders_count = $ordersList->countPendingPayments();      // Commandes en attente de paiement
    
    // Version plus détaillée si demandée
    $detailed = isset($_GET['detailed']) && $_GET['detailed'] === '1';
    
    $response = [
        'success' => true,
        'timestamp' => time(),
        'stats' => [
            'retrieval_count' => $retrieval_count,
            'orders_count' => $orders_count,
            'total_pending' => $retrieval_count + $orders_count
        ]
    ];
    
    if ($detailed) {
        // Charger toutes les commandes pour des statistiques détaillées
        $allOrdersData = $ordersList->loadOrdersData('all');
        $detailedStats = $ordersList->calculateStats($allOrdersData['orders']);
        
        $response['detailed_stats'] = [
            'total_orders' => $detailedStats['total_orders'],
            'total_amount' => $detailedStats['total_amount'],
            'total_photos' => $detailedStats['total_photos'],
            'unpaid_orders' => $detailedStats['unpaid_orders'],
            'paid_orders' => $detailedStats['paid_orders'],
            'validated_orders' => $detailedStats['validated_orders'],
            'exported_orders' => $detailedStats['exported_orders'],
            'retrieved_orders' => $detailedStats['retrieved_orders'],
            'paid_today' => $detailedStats['paid_today'],
            'retrieved_today' => $detailedStats['retrieved_today']
        ];
    }
    
    echo json_encode($response);
    
} catch (Exception $e) {
    http_response_code(500);
    echo json_encode([
        'success' => false,
        'error' => 'Erreur lors de la récupération des statistiques',
        'message' => $e->getMessage(),
        'stats' => [
            'retrieval_count' => 0,
            'orders_count' => 0,
            'total_pending' => 0
        ]
    ]);
}
?>