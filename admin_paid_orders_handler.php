<?php
/**
 * Handler pour la gestion des commandes réglées
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}

require_once 'config.php';

try {
    require_once 'functions.php';
    require_once 'email_handler.php';

} catch (Exception $e) {
    // Nettoyer la sortie et retourner une erreur JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

// Initialiser le logger si non défini
if (!isset($logger)) {
    // Exemple d'initialisation simple, à adapter selon votre application
    class SimpleLogger {
        public function adminAction($action, $data = []) {
            // Vous pouvez écrire dans un fichier ou simplement ignorer pour éviter l'erreur
            // file_put_contents('admin_actions.log', date('Y-m-d H:i:s') . " $action: " . json_encode($data) . "\n", FILE_APPEND);
        }
    }
    $logger = new SimpleLogger();
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'mark_as_retrieved':
            if (!isset($_POST['reference'])) {
                echo json_encode(['success' => false, 'message' => 'Référence manquante']);
                exit;
            }
            
            $order = new Order($_POST['reference']);
            $result = $order->updateRetrievalStatus('retrieved', date('Y-m-d H:i:s'));
            echo json_encode($result);
            exit;
    }
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
        // S'assurer qu'on a 17 colonnes (padding avec chaînes vides si nécessaire)
        while (count($data) < 17) {
            $data[] = '';
        }
        
        // Structure CSV réelle: REF;Nom;Prenom;Email;Telephone;Date commande;Dossier;N de la photo;Quantite;Montant Total;Mode de paiement;Date encaissement souhaitee;Date encaissement;Date depot;Date de recuperation;Statut commande;Exported
        // Indices:               0   1   2      3     4         5             6       7               8        9             10             11                          12                13        14                     15              16
        
        $commandStatus = $data[15] ?? '';    // Statut commande unifié
        $retrievalDate = $data[14] ?? '';    // Date de recuperation
        
        // Filtrer les commandes payées mais non récupérées (statuts unifiés v2.0)
        if ($commandStatus === 'paid' && empty($retrievalDate)) {
            $orders[] = [
                'reference' => $data[0],      // REF
                'firstname' => $data[2],      // Prenom
                'lastname' => $data[1],       // Nom
                'email' => $data[3],          // Email
                'phone' => $data[4],          // Telephone
                'payment_date' => $data[12],  // Date encaissement
                'payment_mode' => $data[10],  // Mode de paiement
                'retrieval_date' => $data[14], // Date de recuperation
                'amount' => floatval($data[9] ?? 0), // Montant Total
                'total_photos' => intval($data[8] ?? 0), // Quantite
                'created_at' => $data[5] ?? '', // Date commande
                'command_status' => $commandStatus
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
        // Sanitiser l'en-tête aussi au cas où
        $sanitizedHeaders = array_map('sanitizeCSVValue', $headers);
        fputcsv($tempHandle, $sanitizedHeaders, ';');
        
        $found = false;
        
        while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
            // S'assurer qu'on a 17 colonnes
            while (count($data) < 17) {
                $data[] = '';
            }
            
            if ($data[0] === $reference) { // REF à l'index 0
                $found = true;
                $data[14] = date('Y-m-d H:i:s'); // Date de recuperation à l'index 14
                $data[15] = 'retrieved';         // Statut commande unifié à l'index 15
                $data[16] = 'exported';          // Exported à l'index 16
            }
            
            // Sanitiser les données avant export CSV pour éviter les injections de formules
            $sanitizedData = array_map('sanitizeCSVValue', $data);
            fputcsv($tempHandle, $sanitizedData, ';');
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