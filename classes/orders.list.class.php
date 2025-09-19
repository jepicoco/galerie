<?php
/**
 * Classe pour la gestion des listes de commandes
 * Gère les opérations sur les collections de commandes (filtrage, statistiques, exports en masse)
 * @version 2.0 - Consolidée avec les meilleures fonctionnalités des deux versions
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
     * Normalise les filtres en tableau avec support legacy et avancé
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

        // Gestion des filtres par chaîne de caractères - Statuts unifiés v2.0
        switch ($filter) {
            case 'unpaid':
                return ['unpaid']; // Utilise le filtre unpaid direct
            case 'paid':
                return ['paid'];
            case 'to_retrieve':
                return ['to_retrieve']; // Commandes payées en attente de récupération
            case 'temp':
                return ['temp']; // Commandes temporaires uniquement
            case 'validated':
                return ['validated']; // Commandes validées non payées
            case 'prepared':
                return ['prepared']; // Commandes préparées
            case 'retrieved':
                return ['retrieved']; // Commandes récupérées
            case 'cancelled':
                return ['cancelled']; // Commandes annulées
            case 'toprepare':
                return ['paid', 'not_retrieved']; // À préparer : payées et non récupérées
            case 'closed':
                return ['retrieved', 'exported']; // Fermées : retirées et exportées
            case 'all':
                return ['all'];
            default:
                return is_array($filter) ? $filter : ['all'];
        }
    }

    /**
     * Vérifie si une ligne correspond aux filtres avec support avancé
     * @param array $data Données de la ligne
     * @param array $filters Filtres à appliquer
     * @return bool True si la ligne correspond
     */
    private function matchesFilters($data, $filters) {
        // CSV structure: REF;Nom;Prenom;Email;Telephone;Date commande;Dossier;N de la photo;Quantite;Montant Total;Mode de paiement;Date encaissement souhaitee;Date encaissement;Date depot;Date de recuperation actuelle;Statut commande;Exported;Date prevue recuperation
        $paymentMode = $data[10] ?? '';      // Mode de paiement - index 10
        $actualRetrievalDate = $data[14] ?? '';    // Date de recuperation actuelle - index 14
        $commandStatus = $data[15] ?? '';    // Statut commande - index 15
        $exported = $data[16] ?? '';         // Exported - index 16
        $expectedRetrievalDate = $data[17] ?? '';  // Date prevue recuperation - index 17

        // Si aucun filtre, accepter tout
        if (empty($filters) || in_array('all', $filters)) {
            return true;
        }

        // Pour les filtres combinés, tous doivent être satisfaits (AND logic)
        foreach ($filters as $filter) {
            $matches = false;

            switch ($filter) {
                case 'temp':
                    $matches = ($commandStatus === 'temp');
                    break;
                case 'validated':
                    $matches = ($commandStatus === 'validated');
                    break;
                case 'paid':
                    $matches = ($commandStatus === 'paid');
                    break;
                case 'to_retrieve':
                    $matches = ($commandStatus === 'paid' && empty($actualRetrievalDate));
                    break;
                case 'prepared':
                    $matches = ($commandStatus === 'prepared');
                    break;
                case 'retrieved':
                    $matches = ($commandStatus === 'retrieved');
                    break;
                case 'cancelled':
                    $matches = ($commandStatus === 'cancelled');
                    break;
                case 'unpaid':
                    // Une commande est unpaid si le Mode de paiement est 'unpaid' OU si le statut est 'temp'/'validated' sans paiement
                    $matches = ($paymentMode === 'unpaid' || (in_array($commandStatus, ['temp', 'validated']) && $paymentMode !== 'paid'));
                    break;
                case 'exported':
                    $matches = ($exported === 'exported');
                    break;
                case 'not_exported':
                    $matches = (empty($exported) || $exported !== 'exported');
                    break;
                case 'not_retrieved':
                    $matches = (empty($actualRetrievalDate));
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

        $debugUnpaidCount = 0;

        foreach ($csvData['data'] as $row) {

            $data = $row['data'];

            // S'assurer que nous avons toutes les colonnes nécessaires (padding avec des chaînes vides)
            while (count($data) < 18) {
                $data[] = '';
            }

            $ref = $data[0];
            $paymentMode = $data[10] ?? '';      // Mode de paiement - index 10
            $commandStatus = $data[15] ?? '';    // Statut commande - index 15
            $exported = $data[16] ?? '';         // Exported - index 16

            // Debug pour unpaid filter
            if (in_array('unpaid', $filters)) {
                $debugUnpaidCount++;
                if ($debugUnpaidCount <= 5) {
                    error_log("OrdersList DEBUG: Ligne $debugUnpaidCount - Ref: {$data[0]}, Mode paiement: '{$data[10]}', Statut: '{$data[15]}'");
                }
            }

            // Appliquer les filtres
            if (!$this->matchesFilters($data, $filters)) {
                if (in_array('unpaid', $filters) && $debugUnpaidCount <= 5) {
                    error_log("OrdersList DEBUG: Ligne {$data[0]} REJETÉE par filtrage");
                }
                continue;
            }

            if (in_array('unpaid', $filters) && $debugUnpaidCount <= 5) {
                error_log("OrdersList DEBUG: Ligne {$data[0]} ACCEPTÉE par filtrage");
            }

            // Stocker les données brutes
            $this->rawData[] = [
                'line_number' => $row['line_number'],
                'data' => $data
            ];

            // Normaliser les données
            $data = array_map('trim', $data);

            // Extraire les dates de récupération
            $actualRetrievalDate = $data[14] ?? '';    // Date réelle de récupération - index 14
            $expectedRetrievalDate = $data[17] ?? '';  // Date prévue de récupération - index 17

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
                    'actual_retrieval_date' => $actualRetrievalDate,    // Date réelle de récupération
                    'expected_retrieval_date' => $expectedRetrievalDate, // Date prévue de récupération
                    'retrieval_date' => $expectedRetrievalDate ?: $actualRetrievalDate, // Compatibilité: prévue ou réelle
                    'desired_payment_date' => $data[11],
                    'actual_payment_date' => $data[12],
                    'command_status' => $commandStatus,
                    'total_price' => 0,
                    'exported' => $exported === 'exported',
                    'created_at' => $this->getOrderCreationDate($ref, $data[5] ?? ''),
                    'total_photos' => 0,
                    'photos_count' => 0,     // Photos normales seulement
                    'usb_keys_count' => 0,   // Clés USB seulement
                    'amount' => 0,
                    'is_urgent' => false, // Sera calculé plus tard
                    'is_overdue' => false, // Sera calculé plus tard
                    'days_until_retrieval' => null // Sera calculé plus tard
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

            // Comptage séparé photos vs clés USB
            if ($data[6] === 'Film du Gala' && strpos($data[7], 'CLE USB') !== false) {
                $this->orders[$ref]['usb_keys_count'] += $quantity;
            } else {
                $this->orders[$ref]['photos_count'] += $quantity;
            }
        }

        // Calculer l'urgence et les jours jusqu'à récupération
        $today = date('Y-m-d');
        foreach ($this->orders as &$order) {
            if (!empty($order['expected_retrieval_date'])) {
                $expectedDate = substr($order['expected_retrieval_date'], 0, 10); // Extraire YYYY-MM-DD
                $daysUntil = (strtotime($expectedDate) - strtotime($today)) / (24 * 3600);

                $order['days_until_retrieval'] = (int)$daysUntil;
                $order['is_urgent'] = ($daysUntil <= 1); // Urgent si récupération dans 1 jour ou moins
                $order['is_overdue'] = ($daysUntil < 0); // En retard si date dépassée
            }
        }

        // Trier par urgence puis par date prévue de récupération
        uasort($this->orders, function($a, $b) {
            // Priorité 1: Commandes en retard (overdue)
            if ($a['is_overdue'] !== $b['is_overdue']) {
                return $b['is_overdue'] - $a['is_overdue']; // En retard en premier
            }

            // Priorité 2: Commandes urgentes
            if ($a['is_urgent'] !== $b['is_urgent']) {
                return $b['is_urgent'] - $a['is_urgent']; // Urgentes en premier
            }

            // Priorité 3: Tri par date prévue de récupération (plus proche en premier)
            $dateA = $a['expected_retrieval_date'] ?: '9999-12-31'; // Date très lointaine si vide
            $dateB = $b['expected_retrieval_date'] ?: '9999-12-31';
            $dateDiff = strtotime($dateA) - strtotime($dateB);

            if ($dateDiff !== 0) {
                return $dateDiff; // Plus proche en premier
            }

            // Priorité 4: Date de création (plus récent en premier)
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });

        return [
            'orders' => array_values($this->orders),
            'raw_data' => $this->rawData
        ];
    }

    /**
     * Extrait la date de création d'une référence de commande
     * @param string $reference Référence de commande
     * @param string $csvDate Date commande du CSV (colonne "Date commande")
     * @return string Date formatée Y-m-d H:i:s
     */
    private function getOrderCreationDate($reference, $csvDate = '') {
        // Priorité 1: Utiliser la date du CSV si disponible
        if (!empty($csvDate)) {
            $timestamp = strtotime($csvDate);
            if ($timestamp !== false && $timestamp > 0) {
                return date('Y-m-d H:i:s', $timestamp);
            }
        }

        // Priorité 2: Essayer d'extraire depuis la référence (mais les 2 derniers chiffres sont aléatoires)
        if (preg_match('/CMD(\d{8})(\d{4})/', $reference, $matches)) {
            $date = $matches[1];
            $time = $matches[2];

            $year = substr($date, 0, 4);
            $month = substr($date, 4, 2);
            $day = substr($date, 6, 2);
            $hour = substr($time, 0, 2);
            $minute = substr($time, 2, 2);

            // Validation de la date
            if (checkdate($month, $day, $year) && $hour <= 23 && $minute <= 59) {
                return sprintf("%04d-%02d-%02d %02d:%02d:00", $year, $month, $day, $hour, $minute);
            }
        }

        // Fallback: Date actuelle
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
            'total_usb_keys' => 0,
            'unpaid_orders' => 0,
            'paid_orders' => 0,
            'paid_today' => 0,
            'retrieved_today' => 0,
            'validated_orders' => 0,
            'temp_orders' => 0,
            'prepared_orders' => 0,
            'cancelled_orders' => 0,
            'exported_orders' => 0,
            'retrieved_orders' => 0
        ];

        $today = date('Y-m-d');

        foreach ($orders as $order) {
            $stats['total_amount'] += $order['total_price'] ?? 0;
            $stats['total_photos'] += $order['total_photos'] ?? 0;

            // Compter les clés USB
            if (isset($order['photos']) && is_array($order['photos'])) {
                foreach ($order['photos'] as $photo) {
                    if ($photo['activity_key'] === 'Film du Gala' &&
                        strpos($photo['name'], 'CLE USB') !== false) {
                        $stats['total_usb_keys'] += $photo['quantity'];
                    }
                }
            }

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

            // Compteurs par statut de commande unifié (v2.0)
            if (isset($order['command_status'])) {
                switch ($order['command_status']) {
                    case 'temp':
                        $stats['temp_orders']++;
                        break;
                    case 'validated':
                        $stats['validated_orders']++;
                        break;
                    case 'paid':
                        break;
                    case 'prepared':
                        $stats['prepared_orders']++;
                        break;
                    case 'retrieved':
                        $stats['retrieved_orders']++;

                        // Compter les commandes récupérées aujourd'hui - utiliser la date réelle
                        $actualRetrievalDate = $order['actual_retrieval_date'] ?? '';
                        if ($actualRetrievalDate && substr($actualRetrievalDate, 0, 10) === $today) {
                            $stats['retrieved_today']++;
                        }
                        break;
                    case 'cancelled':
                        $stats['cancelled_orders']++;
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

            $orderDate = $this->getOrderCreationDate($row['data'][0], $row['data'][5] ?? '');

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
     * @param int $maxAgeHours Âge maximum en heures (défaut: 20)
     * @param bool $force Forcer le nettoyage même si récent
     * @return array Résultat détaillé de l'opération
     */
    public function cleanOldTempOrders($ordersDir, $maxAgeHours = 20, $force = false) {
        // Utiliser la fonction globale optimisée avec conversion du résultat
        $deletedCount = cleanOldTempOrders($ordersDir, 0, $force); // 0 = pas de limite d'intervalle pour cette méthode

        return [
            'success' => true,
            'deleted_count' => $deletedCount,
            'errors' => [],
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
        $data = $this->loadOrdersData('to_retrieve');
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

    /**
     * Marque une commande comme récupérée
     * @param string $reference Référence de la commande
     * @return array Résultat de l'opération
     */
    public function markOrderAsRetrieved($reference) {
        try {
            $csvFile = 'commandes/commandes.csv';
            $tempFile = 'data/commandes_temp.csv';

            if (!file_exists($csvFile)) {
                return ['success' => false, 'message' => 'Fichier commandes non trouvé'];
            }

            $handle = fopen($csvFile, 'r');
            $tempHandle = fopen($tempFile, 'w');

            $headers = fgetcsv($handle, 0, ';');
            // Nettoyer le BOM éventuel du premier en-tête
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }
            fputcsv($tempHandle, $headers, ';');

            $found = false;
            $retrievalDate = date('Y-m-d H:i:s');

            while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                // Vérifier que le nombre de colonnes correspond
                if (count($data) !== count($headers)) {
                    error_log("OrdersList: Ligne CSV malformée - " . count($data) . " colonnes au lieu de " . count($headers));
                    continue;
                }

                $row = array_combine($headers, $data);

                if ($row['REF'] === $reference) {
                    $found = true;
                    // Vérifier si la commande n'est pas déjà récupérée
                    if ($row['Statut commande'] === 'retrieved') {
                        error_log("OrdersList: Commande {$reference} déjà marquée comme récupérée");
                        // Ne pas modifier, mais retourner un succès informatif
                    } else {
                        // Mettre à jour le statut et la date de récupération
                        $row['Statut commande'] = 'retrieved';
                        $row['Date de recuperation'] = $retrievalDate;
                        $row['Exported'] = 'exported';

                        error_log("OrdersList: Commande {$reference} marquée comme récupérée le {$retrievalDate}");
                    }
                }

                fputcsv($tempHandle, array_values($row), ';');
            }

            fclose($handle);
            fclose($tempHandle);

            if ($found) {
                rename($tempFile, $csvFile);
                return [
                    'success' => true,
                    'message' => 'Commande marquée comme récupérée',
                    'retrieval_date' => $retrievalDate
                ];
            } else {
                unlink($tempFile);
                return ['success' => false, 'message' => 'Commande non trouvée'];
            }

        } catch (Exception $e) {
            error_log("OrdersList: Erreur lors du marquage de récupération pour {$reference}: " . $e->getMessage());
            return ['success' => false, 'message' => 'Erreur technique lors de la mise à jour'];
        }
    }

    /**
     * Récupère les informations de contact d'une commande
     * @param string $reference Référence de la commande
     * @return array Informations de contact
     */
    public function getOrderContact($reference) {
        try {
            $csvFile = 'commandes/commandes.csv';

            if (!file_exists($csvFile)) {
                return ['success' => false, 'error' => 'Fichier commandes non trouvé'];
            }

            $handle = fopen($csvFile, 'r');
            $headers = fgetcsv($handle, 0, ';');
            // Nettoyer le BOM éventuel du premier en-tête
            if (isset($headers[0])) {
                $headers[0] = preg_replace('/^\xEF\xBB\xBF/', '', $headers[0]);
            }

            while (($data = fgetcsv($handle, 0, ';')) !== FALSE) {
                // Vérifier que le nombre de colonnes correspond
                if (count($data) !== count($headers)) {
                    error_log("OrdersList: Ligne CSV malformée dans getOrderContact - " . count($data) . " colonnes au lieu de " . count($headers));
                    continue;
                }

                $row = array_combine($headers, $data);

                if ($row['REF'] === $reference) {
                    fclose($handle);
                    return [
                        'success' => true,
                        'contact' => [
                            'email' => $row['Email'] ?? 'Non disponible',
                            'phone' => $row['Telephone'] ?? 'Non disponible'
                        ]
                    ];
                }
            }

            fclose($handle);
            return ['success' => false, 'error' => 'Commande non trouvée'];

        } catch (Exception $e) {
            error_log("OrdersList: Erreur lors de la récupération du contact pour {$reference}: " . $e->getMessage());
            return ['success' => false, 'error' => 'Erreur technique'];
        }
    }
}
?>