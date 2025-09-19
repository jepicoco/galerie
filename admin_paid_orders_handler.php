<?php
/**
 * Handler pour la gestion des commandes réglées
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}

try {
    require_once 'config.php';
    require_once 'functions.php';
    require_once 'classes/orders.list.class.php';
} catch (Exception $e) {
    // Nettoyer la sortie et retourner une erreur JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

$action = $_GET['action'] ?? '';

// Vérifier l'authentification admin seulement pour les appels AJAX
if (!empty($action)) {
    if (!is_admin()) {
        http_response_code(403);
        header('Content-Type: application/json');
        echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
        exit;
    }
}

if ($action === 'get_orders') {
    $status = $_GET['status'] ?? 'all_paid';
    $search = $_GET['search'] ?? '';

    $ordersList = new OrdersList();

    // Charger les données selon le statut
    switch ($status) {
        case 'to_retrieve':
            $data = $ordersList->loadOrdersData('to_retrieve');
            break;
        case 'retrieved':
            $data = $ordersList->loadOrdersData('retrieved');
            break;
        case 'all_paid':
        default:
            // Charger toutes les commandes payées (à retirer + retirées)
            $toRetrieveData = $ordersList->loadOrdersData('to_retrieve');
            $retrievedData = $ordersList->loadOrdersData('retrieved');

            // Fusionner les deux tableaux
            $allOrders = array_merge($toRetrieveData['orders'], $retrievedData['orders']);

            $data = ['orders' => $allOrders];
            break;
    }

    $orders = $data['orders'];

    // Appliquer le filtre de recherche si nécessaire
    if (!empty($search)) {
        $orders = array_filter($orders, function($order) use ($search) {
            $searchLower = strtolower($search);
            return strpos(strtolower($order['reference']), $searchLower) !== false ||
                   strpos(strtolower($order['lastname']), $searchLower) !== false;
        });
    }

    // Calculer les statistiques
    $stats = $ordersList->calculateStats($orders);

    // Calculer les tabStats corrects
    $toRetrieveCount = count($ordersList->loadOrdersData('to_retrieve')['orders']);
    $retrievedCount = count($ordersList->loadOrdersData('retrieved')['orders']);
    $allPaidCount = $toRetrieveCount + $retrievedCount;

    // Préparer la réponse
    $response = [
        'success' => true,
        'orders' => array_values($orders),
        'stats' => $stats,
        'tabStats' => [
            'to_retrieve' => ['count' => $toRetrieveCount],
            'retrieved' => ['count' => $retrievedCount],
            'all_paid' => ['count' => $allPaidCount]
        ],
        'total_count' => count($orders)
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

if ($action === 'get_stats') {
    $ordersList = new OrdersList();

    // Calculer les statistiques pour chaque onglet
    $toRetrieveData = $ordersList->loadOrdersData('to_retrieve');
    $retrievedData = $ordersList->loadOrdersData('retrieved');
    // Pour all_paid, fusionner les commandes à retirer et retirées
    $allPaidOrders = array_merge($toRetrieveData['orders'], $retrievedData['orders']);

    $toRetrieveStats = $ordersList->calculateStats($toRetrieveData['orders']);
    $retrievedStats = $ordersList->calculateStats($retrievedData['orders']);
    $allPaidStats = $ordersList->calculateStats($allPaidOrders);

    $response = [
        'to_retrieve' => [
            'count' => count($toRetrieveData['orders']),
            'stats' => $toRetrieveStats
        ],
        'retrieved' => [
            'count' => count($retrievedData['orders']),
            'stats' => $retrievedStats
        ],
        'all_paid' => [
            'count' => count($allPaidOrders),
            'stats' => $allPaidStats
        ]
    ];

    header('Content-Type: application/json');
    echo json_encode($response);
    exit;
}

// Traitement des actions POST existantes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'mark_as_retrieved':
            if (!isset($_POST['reference'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Référence manquante']);
                exit;
            }

            try {
                $ordersList = new OrdersList();
                $result = $ordersList->markOrderAsRetrieved($_POST['reference']);

                // Log pour debug
                error_log("Handler markOrderAsRetrieved: " . $_POST['reference'] . " - " . json_encode($result));

                header('Content-Type: application/json');
                echo json_encode($result);
                exit;
            } catch (Exception $e) {
                error_log("Handler ERROR markOrderAsRetrieved: " . $_POST['reference'] . " - " . $e->getMessage());
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'message' => 'Erreur serveur: ' . $e->getMessage()]);
                exit;
            }

        case 'get_contact':
            if (!isset($_POST['reference'])) {
                header('Content-Type: application/json');
                echo json_encode(['success' => false, 'error' => 'Référence manquante']);
                exit;
            }

            $ordersList = new OrdersList();
            $contact = $ordersList->getOrderContact($_POST['reference']);
            header('Content-Type: application/json');
            echo json_encode($contact);
            exit;
    }
}

if (!empty($action)) {
    http_response_code(400);
    echo 'Action non reconnue';
    exit;
}

/**
 * Charge les données des commandes réglées
 * @return array Données des commandes réglées
 * @version 1.0
 */
function loadPaidOrdersData() {
    $csvFile = 'commandes/commandes.csv';
    $orders = [];
    
    if (!file_exists($csvFile)) {
        return ['orders' => $orders];
    }
    
    $handle = fopen($csvFile, 'r');
    $headers = fgetcsv($handle, 0, ';');
    
    while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
        $row = array_combine($headers, $data);
        
        // Filtrer les commandes réglées mais non récupérées
        if ($row['Statut paiement'] === 'paid' && $row['Statut retrait'] !== 'retrieved') {
            $orders[] = [
                'reference' => $row['REF'],
                'firstname' => $row['Prenom'],
                'lastname' => $row['Nom'],
                'email' => $row['Email'],
                'phone' => $row['Telephone'],
                'payment_date' => $row['Paiement effectue le'],
                'payment_mode' => $row['Mode de paiement'],
                'retrieval_date' => $row['Date de recuperation'],
                'amount' => floatval($row['Montant']),
                'total_photos' => intval($row['Quantite']),
                'created_at' => $row['Paiement effectue le'] // À ajuster selon la structure
            ];
        }
    }
    
    fclose($handle);
    
    // Trier par date de récupération prévue
    usort($orders, function($a, $b) {
        return strtotime($a['retrieval_date']) - strtotime($b['retrieval_date']);
    });
    
    return ['orders' => $orders];
}

/**
 * Calcule les statistiques des commandes réglées
 * @param array $orders Liste des commandes réglées
 * @return array Statistiques calculées
 * @version 1.0
 */
function calculatePaidOrdersStats($orders) {
    $stats = [
        'total' => count($orders),
        'total_photos' => 0,
        'total_amount' => 0,
        'retrieved_today' => 0
    ];
    
    $today = date('Y-m-d');
    
    foreach ($orders as $order) {
        $stats['total_photos'] += $order['total_photos'];
        $stats['total_amount'] += $order['amount'];
        
        // Compter les récupérations du jour (si vous avez cette info)
        // $stats['retrieved_today'] sera mis à jour selon votre logique
    }
    
    return $stats;
}

/**
 * Marque une commande comme récupérée
 * @param string $reference Référence de la commande
 * @return array Résultat de l'opération
 * @version 1.0
 */
function markOrderAsRetrieved($reference) {
    try {
        $csvFile = 'commandes/commandes.csv';
        $tempFile = 'data/commandes_temp.csv';
        
        if (!file_exists($csvFile)) {
            return ['success' => false, 'message' => 'Fichier commandes non trouvé'];
        }
        
        $handle = fopen($csvFile, 'r');
        $tempHandle = fopen($tempFile, 'w');
        
        $headers = fgetcsv($handle, 0, ';');
        fputcsv($tempHandle, $headers, ';');
        
        $found = false;
        
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            $row = array_combine($headers, $data);
            
            if ($row['REF'] === $reference) {
                $found = true;
                $row['Statut retrait'] = 'retrieved';
                $row['Exported'] = 'exported';
            }
            
            fputcsv($tempHandle, array_values($row), ';');
        }
        
        fclose($handle);
        fclose($tempHandle);
        
        if ($found) {
            rename($tempFile, $csvFile);
            error_log("Commande " . $reference . " marquée comme récupérée");
            return ['success' => true, 'message' => 'Commande marquée comme récupérée'];
        } else {
            unlink($tempFile);
            return ['success' => false, 'message' => 'Commande non trouvée'];
        }
        
    } catch (Exception $e) {
        error_log("Erreur lors du marquage de récupération pour " . $reference . ": " . $e->getMessage());
        return ['success' => false, 'message' => 'Erreur technique'];
    }
}
?>