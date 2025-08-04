<?php
/**
 * Classe pour la gestion des listes de commandes
 * Gère les opérations sur les collections de commandes (filtrage, statistiques, exports en masse)
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}

require_once 'config.php';
require_once 'csv.class.php';
require_once 'order.class.php';

class OrdersList extends CsvHandler {
    
    private $csvFile;
    private $orders;
    private $rawData;
    
    /**
     * Constructeur
     */
    public function __construct() {
        parent::__construct(';', '"', '\\');
        $this->csvFile = 'commandes/commandes.csv';
        $this->orders = [];
        $this->rawData = [];
    }
    
    /**
     * Charge les données des commandes avec filtres
     * @param string|array $filter Filtre à appliquer
     * @return array Données des commandes avec filtrage
     */
    public function loadOrdersData($filter = null) {
        if (!file_exists($this->csvFile)) {
            return ['orders' => [], 'raw_data' => []];
        }
        
        $csvData = $this->read($this->csvFile, true, 18);
        if ($csvData === false) {
            return ['orders' => [], 'raw_data' => []];
        }
        
        $this->orders = [];
        $this->rawData = [];
        $filters = $this->normalizeFilters($filter);

        print('DEBUG 1');
        print('<br />');
        print($this->csvFile);
        print('<br />');
        print($csvData);
        print('<br />');

        
        foreach ($csvData['data'] as $row) {
            if (count($row['data']) < 18) continue;
            
            $data = $row['data'];
            $ref = $data[0];
            $paymentMode = $data[10] ?? '';      // Mode de paiement - index 10
            $commandStatus = $data[15] ?? '';    // Statut commande - index 15
            $exported = $data[16] ?? '';         // Exported - index 16

            print('DEBUG 2');
            print_r($data); // Debug: afficher les données de la ligne
            print('<br />');
            // Normaliser les données
            $data = array_map('trim', $data);
            print_r($data); // Debug: afficher les données normalisées

            
            // Appliquer les filtres
            if (!$this->matchesFilters($data, $filters)) {
                continue;
            }
            
            // Stocker les données brutes
            $this->rawData[] = [
                'line_number' => $row['line_number'],
                'data' => $data
            ];
            
            // Grouper par référence de commande
            if (!isset($this->orders[$ref])) {
                $this->orders[$ref] = [
                    'reference' => $ref,
                    'lastname' => $data[1],
                    'firstname' => $data[2],
                    'email' => $data[3],
                    'phone' => $data[4],
                    'payment_date' => $data[12],
                    'payment_mode' => $paymentMode,
                    'activity_key' => $data[6],
                    'photos' => [],
                    'quantity' => 0,
                    'retrieval_date' => $data[14],
                    'desired_payment_date' => $data[11],
                    'actual_payment_date' => $data[12],
                    'command_status' => $commandStatus,
                    'total_price' => 0,
                    'exported' => $exported === 'exported',
                    'created_at' => $this->getOrderCreationDate($ref),
                    'total_photos' => 0,
                    'amount' => 0
                ];
            }
            
            // Ajouter la photo à la commande
            $quantity = intval($data[8]);
            $unitPrice = getActivityPrice($data[6]);
            $subtotal = $quantity * $unitPrice;
            
            $this->orders[$ref]['photos'][] = [
                'name' => $data[7],
                'activity_key' => $data[6],
                'quantity' => $quantity,
                'unit_price' => $unitPrice,
                'subtotal' => $subtotal,
                'pricing_type' => getActivityTypeInfo($data[6])['display_name'] ?? 'Photo standard'
            ];
            
            $this->orders[$ref]['total_photos'] += $quantity;
            $this->orders[$ref]['amount'] += $subtotal;
            $this->orders[$ref]['total_price'] += $subtotal;
        }
        
        // Trier par date de création (plus récent en premier)
        uasort($this->orders, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        return [
            'orders' => array_values($this->orders),
            'raw_data' => $this->rawData
        ];
    }
    
    /**
     * Normalise les filtres en tableau
     * @param string|array|null $filter Filtre d'entrée
     * @return array Filtres normalisés
     */
    private function normalizeFilters($filter) {
        // Gestion des constantes legacy (si elles existent)
        if (defined('ORDERSLIST_TEMP') && $filter === ORDERSLIST_TEMP) {
            return ['unpaid', 'not_exported'];
        }
        if (defined('ORDERSLIST_UNPAID') && $filter === ORDERSLIST_UNPAID) {
            return ['unpaid'];
        }
        if (defined('ORDERSLIST_TOPREPARE') && $filter === ORDERSLIST_TOPREPARE) {
            return ['paid', 'not_retrieved'];
        }
        if (defined('ORDERSLIST_CLOSED') && $filter === ORDERSLIST_CLOSED) {
            return ['retrieved', 'exported'];
        }
        
        // Gestion des filtres par chaîne de caractères
        switch ($filter) {
            case 'unpaid':
                return ['unpaid'];
            case 'paid':
                return ['paid'];
            case 'temp':
                return ['unpaid', 'not_exported'];
            case 'toprepare':
                return ['paid', 'not_retrieved'];
            case 'closed':
                return ['retrieved', 'exported'];
            case 'all':
                return ['all'];
            default:
                return is_array($filter) ? $filter : ['all'];
        }
    }
    
    /**
     * Vérifie si une ligne correspond aux filtres
     * @param array $data Données de la ligne
     * @param array $filters Filtres à appliquer
     * @return bool True si la ligne correspond
     */
    private function matchesFilters($data, $filters) {
        // CSV structure: REF;Nom;Prenom;Email;Telephone;Date commande;Dossier;N de la photo;Quantite;Montant Total;Mode de paiement;Date encaissement souhaitee;Date encaissement;Date depot;Date de recuperation;Statut commande;Exported
        $paymentMode = $data[10] ?? '';      // Mode de paiement - index 10
        $retrievalDate = $data[14] ?? '';    // Date de recuperation - index 14
        $commandStatus = $data[15] ?? '';    // Statut commande - index 15
        $exported = $data[16] ?? '';         // Exported - index 16
        
        // Si aucun filtre, accepter tout
        if (empty($filters) || in_array('all', $filters)) {
            return true;
        }
        
        // Pour les filtres combinés, tous doivent être satisfaits (AND logic)
        foreach ($filters as $filter) {
            $matches = false;
            
            switch ($filter) {
                case 'unpaid':
                    $matches = (empty($paymentMode) || $paymentMode === 'unpaid');
                    break;
                case 'paid':
                    $matches = (!empty($paymentMode) && $paymentMode !== 'unpaid');
                    break;
                case 'validated':
                    $matches = ($commandStatus === 'validated');
                    break;
                case 'pending':
                    $matches = ($commandStatus === 'pending');
                    break;
                case 'exported':
                    $matches = ($exported === 'exported');
                    break;
                case 'not_exported':
                    $matches = (empty($exported) || $exported !== 'exported');
                    break;
                case 'not_retrieved':
                    $matches = (empty($retrievalDate));
                    break;
                case 'retrieved':
                    $matches = (!empty($retrievalDate));
                    break;
                case 'all':
                    $matches = true;
                    break;
                default:
                    $matches = true;
                    break;
            }
            
            // Si un filtre ne correspond pas, rejeter la ligne
            if (!$matches) {
                return false;
            }
        }
        
        return true;
    }
    
    /**
     * Extrait la date de création d'une référence de commande
     * @param string $reference Référence de commande
     * @return string Date formatée Y-m-d H:i:s
     */
    private function getOrderCreationDate($reference) {
        if (preg_match('/CMD(\d{8})(\d{6})/', $reference, $matches)) {
            $date = $matches[1];
            $time = $matches[2];
            
            $year = substr($date, 0, 4);
            $month = substr($date, 4, 2);
            $day = substr($date, 6, 2);
            $hour = substr($time, 0, 2);
            $minute = substr($time, 2, 2);
            $second = substr($time, 4, 2);
            
            return "$year-$month-$day $hour:$minute:$second";
        }
        
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Calcule les statistiques des commandes
     * @param array $orders Tableau des commandes (optionnel, utilise les données chargées si null)
     * @return array Statistiques
     */
    public function calculateStats($orders = null) {
        if ($orders === null) {
            $orders = array_values($this->orders);
        }
        
        $stats = [
            'total_orders' => count($orders),
            'total' => count($orders), // Alias pour compatibilité
            'total_amount' => 0,
            'total_photos' => 0,
            'unpaid_orders' => 0,
            'paid_orders' => 0,
            'paid_today' => 0,
            'retrieved_today' => 0,
            'validated_orders' => 0,
            'pending_orders' => 0,
            'exported_orders' => 0,
            'retrieved_orders' => 0
        ];
        
        $today = date('Y-m-d');
        
        foreach ($orders as $order) {
            $stats['total_amount'] += $order['total_price'] ?? 0;
            $stats['total_photos'] += $order['total_photos'] ?? 0;
            
            // Compteurs par statut de paiement
            if (isset($order['payment_mode'])) {
                if ($order['payment_mode'] === 'unpaid') {
                    $stats['unpaid_orders']++;
                } else {
                    $stats['paid_orders']++;
                    
                    // Compter les commandes payées aujourd'hui
                    $paymentDate = $order['payment_date'] ?? $order['actual_payment_date'] ?? '';
                    if ($paymentDate && substr($paymentDate, 0, 10) === $today) {
                        $stats['paid_today']++;
                    }
                }
            }
            
            // Compteurs par statut de commande
            if (isset($order['command_status'])) {
                switch ($order['command_status']) {
                    case 'validated':
                        $stats['validated_orders']++;
                        break;
                    case 'pending':
                        $stats['pending_orders']++;
                        break;
                    case 'retrieved':
                        $stats['retrieved_orders']++;
                        
                        // Compter les commandes récupérées aujourd'hui
                        $retrievalDate = $order['retrieval_date'] ?? '';
                        if ($retrievalDate && substr($retrievalDate, 0, 10) === $today) {
                            $stats['retrieved_today']++;
                        }
                        break;
                }
            }
            
            // Compteur des commandes exportées
            if (isset($order['exported']) && $order['exported']) {
                $stats['exported_orders']++;
            }
        }
        
        return $stats;
    }
    
    /**
     * Marque plusieurs commandes comme exportées
     * @param array $references Tableau des références de commandes
     * @return array Résultat de l'opération
     */
    public function markMultipleAsExported($references) {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($references as $reference) {
            $order = new Order($reference);
            $result = $order->markAsExported();
            
            if ($result['success']) {
                $successCount++;
            } else {
                $errorCount++;
                $errors[] = "Erreur pour $reference: " . ($result['error'] ?? 'Erreur inconnue');
            }
        }
        
        return [
            'success' => $errorCount === 0,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'message' => "$successCount commande(s) marquée(s) comme exportées"
        ];
    }
    
    /**
     * Archive les anciennes commandes
     * @param string $cutoffDate Date limite (YYYY-MM-DD)
     * @return array Résultat de l'opération
     */
    public function archiveOldOrders($cutoffDate) {
        $archiveFile = 'archives/commandes_' . date('Y-m-d_H-i-s') . '.csv';
        
        // Créer le répertoire archives s'il n'existe pas
        if (!is_dir('archives')) {
            mkdir('archives', 0755, true);
        }
        
        $csvData = $this->read($this->csvFile, true);
        if ($csvData === false) {
            return ['success' => false, 'error' => 'Impossible de lire le fichier'];
        }
        
        $activeData = [];
        $archivedData = [];
        $archivedCount = 0;
        
        foreach ($csvData['data'] as $row) {
            if (count($row['data']) < 1) continue;
            
            $orderDate = $this->getOrderCreationDate($row['data'][0]);
            
            if (strtotime($orderDate) < strtotime($cutoffDate)) {
                $archivedData[] = $row['data'];
                $archivedCount++;
            } else {
                $activeData[] = $row['data'];
            }
        }
        
        // Sauvegarder l'archive
        if ($archivedCount > 0) {
            $archiveSuccess = $this->write($archiveFile, $archivedData, $csvData['header'], false, true);
            if (!$archiveSuccess) {
                return ['success' => false, 'error' => 'Impossible de créer l\'archive'];
            }
            
            // Mettre à jour le fichier principal
            $activeSuccess = $this->write($this->csvFile, $activeData, $csvData['header'], false, true);
            if (!$activeSuccess) {
                return ['success' => false, 'error' => 'Impossible de mettre à jour le fichier principal'];
            }
        }
        
        return [
            'success' => true,
            'archived_count' => $archivedCount,
            'archive_file' => $archiveFile,
            'message' => "$archivedCount commande(s) archivée(s)"
        ];
    }
    
    /**
     * Nettoie les commandes temporaires anciennes
     * @param string $ordersDir Répertoire des commandes
     * @param int $maxAgeHours Age maximum en heures (défaut: 20)
     * @return array Résultat de l'opération
     */
    public function cleanOldTempOrders($ordersDir, $maxAgeHours = 20) {
        $tempDir = $ordersDir . 'temp/';
        
        if (!is_dir($tempDir)) {
            return ['success' => true, 'deleted_count' => 0, 'message' => 'Aucun dossier temporaire trouvé'];
        }
        
        $tempFiles = glob($tempDir . '*.json');
        $deletedCount = 0;
        $maxAge = $maxAgeHours * 3600; // Conversion en secondes
        $errors = [];
        
        foreach ($tempFiles as $file) {
            $fileAge = time() - filemtime($file);
            
            if ($fileAge > $maxAge) {
                if (unlink($file)) {
                    $deletedCount++;
                    error_log("Commande temporaire supprimée (age: " . round($fileAge/3600, 1) . "h): " . basename($file));
                } else {
                    $errors[] = "Impossible de supprimer: " . basename($file);
                }
            }
        }
        
        return [
            'success' => empty($errors),
            'deleted_count' => $deletedCount,
            'errors' => $errors,
            'message' => "$deletedCount fichier(s) temporaire(s) supprimé(s)"
        ];
    }
    
    /**
     * Exporte une liste de commandes vers un fichier CSV spécifique
     * @param array $orders Liste des commandes
     * @param string $exportType Type d'export ('reglees' ou 'preparer')
     * @param array $paymentData Données de paiement (pour export 'reglees')
     * @return array Résultat de l'opération
     */
    public function exportMultipleOrders($orders, $exportType, $paymentData = []) {
        $successCount = 0;
        $errorCount = 0;
        $errors = [];
        
        foreach ($orders as $orderData) {
            $order = new Order();
            $order->setData($orderData);
            
            try {
                if ($exportType === 'reglees') {
                    $result = $order->exportToReglees(
                        $paymentData['payment_mode'] ?? '',
                        $paymentData['payment_date'] ?? '',
                        $paymentData['desired_deposit_date'] ?? '',
                        $paymentData['actual_deposit_date'] ?? ''
                    );
                } elseif ($exportType === 'preparer') {
                    $result = $order->exportToPreparer();
                } else {
                    $result = ['success' => false, 'error' => 'Type d\'export invalide'];
                }
                
                if ($result['success']) {
                    $successCount++;
                } else {
                    $errorCount++;
                    $errors[] = "Erreur pour {$orderData['reference']}: " . ($result['error'] ?? 'Erreur inconnue');
                }
            } catch (Exception $e) {
                $errorCount++;
                $errors[] = "Exception pour {$orderData['reference']}: " . $e->getMessage();
            }
        }
        
        return [
            'success' => $errorCount === 0,
            'success_count' => $successCount,
            'error_count' => $errorCount,
            'errors' => $errors,
            'message' => "$successCount commande(s) exportée(s) vers $exportType"
        ];
    }
    
    /**
     * Filtre les commandes par critères multiples
     * @param array $criteria Critères de filtrage
     * @return array Commandes filtrées
     */
    public function filterOrders($criteria) {
        $filteredOrders = array_values($this->orders);
        
        foreach ($criteria as $field => $value) {
            $filteredOrders = array_filter($filteredOrders, function($order) use ($field, $value) {
                switch ($field) {
                    case 'date_range':
                        $orderDate = strtotime($order['created_at']);
                        return $orderDate >= strtotime($value['start']) && $orderDate <= strtotime($value['end']);
                    
                    case 'payment_mode':
                        return $order['payment_mode'] === $value;
                    
                    case 'command_status':
                        return $order['command_status'] === $value;
                    
                    case 'activity':
                        return $order['activity_key'] === $value;
                    
                    case 'exported':
                        return $order['exported'] === $value;
                    
                    case 'min_amount':
                        return $order['amount'] >= floatval($value);
                    
                    case 'max_amount':
                        return $order['amount'] <= floatval($value);
                    
                    default:
                        return true;
                }
            });
        }
        
        return array_values($filteredOrders);
    }
    
    /**
     * Retourne les commandes chargées
     * @return array Commandes
     */
    public function getOrders() {
        return array_values($this->orders);
    }
    
    /**
     * Retourne les données brutes
     * @return array Données brutes
     */
    public function getRawData() {
        return $this->rawData;
    }
    
    /**
     * Compte le nombre de commandes réglées en attente de retrait
     * @return int Nombre de commandes
     */
    public function countPendingRetrievals() {
        // Une commande est en attente de retrait si elle est payée mais pas encore récupérée
        $data = $this->loadOrdersData(['paid', 'not_retrieved']);
        return count($data['orders']);
    }
    
    /**
     * Compte le nombre de commandes en attente de paiement
     * @return int Nombre de commandes
     */
    public function countPendingPayments() {
        $data = $this->loadOrdersData('unpaid');
        return count($data['orders']);
    }
}

?>