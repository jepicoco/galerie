<?php
/**
 * Handler pour la gestion des commandes - admin_orders_handler.php
 */
if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}

require_once 'config.php';

try {
    require_once 'functions.php';
    
} catch (Exception $e) {
    // Nettoyer la sortie et retourner une erreur JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

// Gestion des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');
    
    $action = $_POST['action'];
    $response = ['success' => false, 'error' => 'Action non reconnue : ' . $action];
    
    switch ($action) {
        case 'get_contact':
            $reference = $_POST['reference'] ?? '';
            $response = getOrderContact($reference);
            break;
            
        case 'process_payment':
            $data = [
                'reference' => $_POST['reference'] ?? '',
                'payment_mode' => $_POST['payment_mode'] ?? '',
                'payment_date' => $_POST['payment_date'] ?? '',
                'desired_deposit_date' => $_POST['desired_deposit_date'] ?? '',
                'actual_deposit_date' => $_POST['actual_deposit_date'] ?? ''
            ];
            $response = processOrderPayment($data);
            break;
            
        case 'export_preparation':
            $response = exportPreparationList();
            break;
            
        case 'export_daily_payments':
            $date = $_POST['date'] ?? date('Y-m-d');
            $response = exportDailyPayments($date);
            break;
            
        case 'archive_orders':
            $days = intval($_POST['days'] ?? 30);
            $response = archiveOldOrders($days);
            break;

        case 'export_preparation_by_activity':
            $response = exportPreparationByActivity();
            break;
            
        case 'export_separation_guide':
            $response = exportSeparationGuide();
            break;
            
        case 'export_printer_summary':
            $response = exportPrinterSummary();
            break;
            
        case 'generate_picking_lists':
            $response = generatePickingListsByActivity();
            break;
        case 'generate_picking_lists_csv':
            $response = generatePickingListsByActivityCSV();
            break;
        case 'check_coherence':
            $response = checkActivityCoherence();
            break;
        case 'resend_confirmation_email':
            if (!isset($_POST['reference'])) {
                echo json_encode(['success' => false, 'message' => 'Référence manquante']);
                exit;
            }
            
            $response = resendOrderConfirmationEmail($_POST['reference']);
            break;
    }
    
    echo json_encode($response);
    exit;
}

/**
 * Obtenir les informations de contact d'une commande
 */
function getOrderContact($reference) {
    $order = new Order($reference);
    if (!$order->load()) {
        return ['success' => false, 'error' => 'Commande introuvable'];
    }
    
    $orderData = $order->getData();
    return [
        'success' => true,
        'contact' => [
            'email' => $orderData['email'],
            'phone' => $orderData['phone'],
            'name' => $orderData['firstname'] . ' ' . $orderData['lastname']
        ]
    ];
}

/**
 * Traiter le règlement d'une commande
 */
function processOrderPayment($paymentData) {
    global $logger;
    
    $reference = $paymentData['reference'];
    $paymentMode = $paymentData['payment_mode'];
    $paymentDate = $paymentData['payment_date'];
    $desiredDepositDate = $paymentData['desired_deposit_date'];
    $actualDepositDate = $paymentData['actual_deposit_date'];
    
    if (empty($reference) || empty($paymentMode) || empty($paymentDate)) {
        return ['success' => false, 'error' => 'Données manquantes'];
    }
    
    try {
        // 1. Charger la commande avec la classe Order
        $order = new Order($reference);
        $order->load();

        if (!$order->load()) {
            return ['success' => false, 'error' => 'Commande introuvable'];
        }
        
        $orderData = $order->getData();
        
        // 2. Exporter vers commandes_reglees.csv
        $exportResult = $order->exportToReglees($paymentMode, $paymentDate, $desiredDepositDate, $actualDepositDate);
        if (!$exportResult['success']) {
            return $exportResult;
        }
        
        // 3. Exporter vers commandes_a_preparer.csv
        $prepareResult = $order->exportToPreparer();
        if (!$prepareResult['success']) {
            return $prepareResult;
        }

        // 4. Met à jour le statut de la commande dans le fichier principal
        $updateResult = $order->updatePaymentStatus($paymentData);
        if (!$updateResult['success']) {
            return $updateResult;
        }
        
        // 5. Marquer comme exporté dans le fichier principal
        $markResult = $order->markAsExported();
        if (!$markResult['success']) {
            return $markResult;
        }
        
        if(isset($logger)) {
            // Log l'action de paiement
            $logger->info('Paiement traité', [
                'reference' => $reference,
                'payment_mode' => $paymentMode,
                'payment_date' => $paymentDate,
                'desired_deposit_date' => $desiredDepositDate,
                'actual_deposit_date' => $actualDepositDate
            ]);
        }
        
        return ['success' => true, 'message' => 'Règlement traité avec succès'];
        
    } catch (Exception $e) {
        error_log('Erreur processOrderPayment: ' . $e->getMessage());
        return ['success' => false, 'error' => 'Erreur lors du traitement: ' . $e->getMessage()];
    }
}

/**
 * Exporter une commande vers commandes_reglees.csv
 */
function exportToReglees($order, $paymentMode, $paymentDate, $desiredDepositDate, $actualDepositDate) {
    $regleesFile = 'commandes/commandes_reglees.csv';
    $isNewFile = !file_exists($regleesFile);
    
    // Créer l'en-tête si nouveau fichier
    if ($isNewFile) {
        $bom = "\xEF\xBB\xBF";
        $header = $bom . "Ref;Nom;Prenom;Email;Tel;Nb photos;Nb USB;Montant;Reglement;Date reglement;Date encaissement souhaitee;Date encaissement reelle\n";
        if (file_put_contents($regleesFile, $header) === false) {
            return ['success' => false, 'error' => 'Impossible de créer le fichier des commandes réglées'];
        }
    }
    
    // Calculer le nombre d'USB (pour l'instant 0, à adapter selon vos besoins)
    $nbUSB = 0;
    
    // Préparer la ligne
    $line = implode(';', [
        $order['reference'],
        $order['lastname'],
        $order['firstname'],
        $order['email'],
        $order['phone'],
        $order['total_photos'],
        $nbUSB,
        $order['amount'],
        $paymentMode,
        $paymentDate,
        $desiredDepositDate,
        $actualDepositDate
    ]) . "\n";
    
    $result = file_put_contents($regleesFile, $line, FILE_APPEND);
    
    if ($result === false) {
        return ['success' => false, 'error' => 'Impossible d\'écrire dans le fichier des commandes réglées'];
    }
    
    return ['success' => true];
}

/**
 * Exporter les photos d'une commande vers commandes_a_preparer.csv
 */
function exportToPreparer($order) {
    $preparerFile = 'commandes/commandes_a_preparer.csv';
    $isNewFile = !file_exists($preparerFile);
    
    // Créer l'en-tête si nouveau fichier
    if ($isNewFile) {
        $bom = "\xEF\xBB\xBF";
        $header = $bom . "Ref;Nom;Prenom;Email;Tel;Nom du dossier;Nom de la photo;Quantite;Date de preparation;Date de recuperation\n";
        if (file_put_contents($preparerFile, $header) === false) {
            return ['success' => false, 'error' => 'Impossible de créer le fichier de préparation'];
        }
    }
    
    // Déterminer le nom du dossier (activité) depuis le nom de la première photo
    $activityName = '';
    if (!empty($order['photos'])) {
        $firstPhoto = $order['photos'][0]['name'];
        // Extraire le nom de l'activité du chemin de la photo
        $pathParts = explode('/', $firstPhoto);
        if (count($pathParts) >= 2) {
            $activityName = $pathParts[0]; // Premier dossier = nom de l'activité
        }
    }
    
    $lines = '';
    foreach ($order['photos'] as $photo) {
        $lines .= implode(';', [
            $order['reference'],
            $order['lastname'],
            $order['firstname'],
            $order['email'],
            $order['phone'],
            $activityName,
            $photo['name'],
            $photo['quantity'],
            '', // Date de préparation (vide)
            $order['retrieval_date'] ?? '' // Date de récupération
        ]) . "\n";
    }
    
    $result = file_put_contents($preparerFile, $lines, FILE_APPEND);
    
    if ($result === false) {
        return ['success' => false, 'error' => 'Impossible d\'écrire dans le fichier de préparation'];
    }
    
    return ['success' => true];
}

/**
 * Marquer une commande comme exportée dans le fichier principal
 */
function markOrderAsExported($reference) {
    $csvFile = 'commandes/commandes.csv';
    
    if (!file_exists($csvFile)) {
        return ['success' => false, 'error' => 'Fichier CSV introuvable'];
    }
    
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return ['success' => false, 'error' => 'Impossible de lire le fichier CSV'];
    }
    
    $header = array_shift($lines);
    $updatedLines = [$header];
    $orderFound = false;
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 10) {
            $updatedLines[] = $line;
            continue;
        }
        
        if ($data[0] === $reference) {
            $orderFound = true;
            
            // Étendre le tableau pour la colonne "exported" (position 17)
            while (count($data) < 18) {
                $data[] = '';
            }
            
            // Marquer comme exporté
            $data[17] = 'exported';
        }
        
        // Sanitiser les données avant export CSV pour éviter les injections de formules
        $sanitizedData = array_map('sanitizeCSVValue', $data);
        $updatedLines[] = implode(';', $sanitizedData);
    }
    
    if (!$orderFound) {
        return ['success' => false, 'error' => 'Commande introuvable'];
    }
    
    $result = file_put_contents($csvFile, implode("\n", $updatedLines) . "\n");
    
    if ($result === false) {
        return ['success' => false, 'error' => 'Impossible de sauvegarder le fichier'];
    }
    
    return ['success' => true];
}

/**
 * Met à jour le statut de paiement d'une commande dans le fichier principal
 * @param string $reference Référence de la commande
 * @param array $paymentData Données de paiement (mode, date, etc.)
 * @return array Résultat de l'opération
 */
// Fonction déplacée vers orders_helpers.php : updateOrderPaymentStatus()
function payOrder($reference, $paymentData) {
    $order = new Order($reference);
    return $order->updatePaymentStatus($paymentData);
}

/**
 * Exporter la liste de préparation - Version corrigée
 * Génère un export complet pour l'imprimeur avec toutes les commandes paid/validated non récupérées
 */
function exportPreparationList() {
    // Utiliser OrdersList pour récupérer les commandes à imprimer
    require_once 'classes/autoload.php';
    $ordersList = new OrdersList();
    
    // Récupérer les commandes paid et validated (non retrieved)
    $paidOrders = $ordersList->loadOrdersData('paid');
    $validatedOrders = $ordersList->loadOrdersData('validated');
    
    $allOrders = array_merge($paidOrders['orders'], $validatedOrders['orders']);
    
    // Filtrer les commandes déjà récupérées
    $ordersToProcess = array_filter($allOrders, function($order) {
        return $order['command_status'] !== 'retrieved' && empty($order['retrieval_date']);
    });
    
    if (empty($ordersToProcess)) {
        return ['success' => false, 'error' => 'Aucune commande à préparer (toutes sont déjà récupérées ou non validées)'];
    }
    
    // Générer le fichier CSV
    $timestamp = date('Y-m-d_H-i-s');
    $downloadFile = 'exports/preparation_complete_' . $timestamp . '.csv';
    
    // Créer le dossier exports s'il n'existe pas
    if (!is_dir('exports')) {
        mkdir('exports', 0755, true);
    }
    
    // Générer le contenu CSV
    $csvContent = generatePreparationListContent($ordersToProcess);
    
    $result = file_put_contents($downloadFile, $csvContent);
    
    if ($result !== false) {
        return [
            'success' => true,
            'file' => $downloadFile,
            'message' => 'Liste de préparation complète générée',
            'orders_count' => count($ordersToProcess),
            'photos_count' => array_sum(array_column($ordersToProcess, 'total_photos'))
        ];
    } else {
        return ['success' => false, 'error' => 'Impossible de générer le fichier'];
    }
}

/**
 * Génère le contenu CSV pour la liste de préparation
 * @param array $orders Liste des commandes à traiter
 * @return string Contenu CSV
 */
function generatePreparationListContent($orders) {
    // BOM UTF-8 pour Excel
    $bom = "\xEF\xBB\xBF";
    
    // En-tête CSV
    $header = "Ref;Statut;Nom;Prenom;Email;Tel;Activite;Photo;Quantite;Prix_unitaire;Sous_total;Date_commande;Mode_paiement;Notes\n";
    
    $content = $bom . $header;
    $totalPhotos = 0;
    $totalAmount = 0;
    
    foreach ($orders as $order) {
        foreach ($order['photos'] as $photo) {
            $line = [
                $order['reference'],
                $order['command_status'],
                sanitizeCSVValue($order['lastname']),
                sanitizeCSVValue($order['firstname']),
                sanitizeCSVValue($order['email']),
                sanitizeCSVValue($order['phone']),
                sanitizeCSVValue($photo['activity_key']),
                sanitizeCSVValue($photo['name']),
                $photo['quantity'],
                number_format($photo['unit_price'], 2, ',', ''),
                number_format($photo['subtotal'], 2, ',', ''),
                $order['created_at'],
                formatOrderStatus($order['payment_mode'] ?? ''),
                '' // Notes vides pour l'imprimeur
            ];
            
            $content .= implode(';', $line) . "\n";
            $totalPhotos += $photo['quantity'];
            $totalAmount += $photo['subtotal'];
        }
    }
    
    // Ajouter une ligne de résumé
    $content .= "\n";
    $content .= "RESUME;;;;;;;;" . $totalPhotos . ";;;" . number_format($totalAmount, 2, ',', '') . ";;;\n";
    $content .= "COMMANDES_TOTAL;;;;;;;;" . count($orders) . ";;;;;;;;\n";
    
    return $content;
}

/**
 * Exporter les règlements du jour
 */
function exportDailyPayments($date) {
    $regleesFile = 'commandes/commandes_reglees.csv';
    
    if (!file_exists($regleesFile)) {
        return ['success' => false, 'error' => 'Aucun règlement trouvé'];
    }
    
    $lines = file($regleesFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Aucun règlement trouvé'];
    }
    
    $header = array_shift($lines);
    $dailyPayments = [$header];
    
    foreach ($lines as $line) {
        $data = str_getcsv($line, ';');
        if (count($data) >= 10 && substr($data[9], 0, 10) === $date) {
            $dailyPayments[] = $line;
        }
    }
    
    if (count($dailyPayments) === 1) {
        return ['success' => false, 'error' => 'Aucun règlement trouvé pour cette date'];
    }
    
    // Générer le fichier d'export
    $timestamp = date('Y-m-d_H-i-s');
    $downloadFile = 'exports/reglements_' . str_replace('-', '', $date) . '_' . $timestamp . '.csv';
    
    if (!is_dir('exports')) {
        mkdir('exports', 0755, true);
    }
    
    $result = file_put_contents($downloadFile, implode("\n", $dailyPayments) . "\n");
    
    if ($result) {
        return [
            'success' => true,
            'file' => $downloadFile,
            'count' => count($dailyPayments) - 1,
            'message' => 'Export des règlements généré'
        ];
    } else {
        return ['success' => false, 'error' => 'Impossible de générer le fichier'];
    }
}

/**
 * Archiver les anciennes commandes
 */
function archiveOldOrders($days = 30) {
    $csvFile = 'commandes/commandes.csv';
    
    if (!file_exists($csvFile)) {
        return ['success' => false, 'error' => 'Fichier CSV introuvable'];
    }
    
    $cutoffDate = date('Y-m-d', strtotime("-$days days"));
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Aucune commande à archiver'];
    }
    
    $header = array_shift($lines);
    $activeLines = [$header];
    $archivedLines = [$header];
    $archivedCount = 0;
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 1) continue;
        
        $orderDate = getOrderCreationDate($data[0]);
        
        if (strtotime($orderDate) < strtotime($cutoffDate)) {
            $archivedLines[] = $line;
            $archivedCount++;
        } else {
            $activeLines[] = $line;
        }
    }
    
    if ($archivedCount === 0) {
        return ['success' => false, 'error' => 'Aucune commande à archiver'];
    }
    
    // Sauvegarder les archives
    $timestamp = date('Y-m-d_H-i-s');
    $archiveFile = 'archives/commandes_archive_' . $timestamp . '.csv';
    
    if (!is_dir('archives')) {
        mkdir('archives', 0755, true);
    }
    
    $archiveResult = file_put_contents($archiveFile, implode("\n", $archivedLines) . "\n");
    
    if ($archiveResult === false) {
        return ['success' => false, 'error' => 'Impossible de créer l\'archive'];
    }
    
    // Mettre à jour le fichier principal
    $updateResult = file_put_contents($csvFile, implode("\n", $activeLines) . "\n");
    
    if ($updateResult === false) {
        return ['success' => false, 'error' => 'Impossible de mettre à jour le fichier'];
    }
    
    return [
        'success' => true,
        'archived_count' => $archivedCount,
        'archive_file' => $archiveFile,
        'message' => "$archivedCount commande(s) archivée(s)"
    ];
}

/**
 * Générer les listes de picking séparées par ACTIVITY_PRICING
 * Version 1.1 - Une ligne par numéro de commande
 */
function generatePickingListsByActivity() {
    $preparerFile = 'commandes/commandes_a_preparer.csv';
    
    if (!file_exists($preparerFile)) {
        return ['success' => false, 'error' => 'Aucune commande à préparer'];
    }
    
    $lines = file($preparerFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier de préparation vide'];
    }
    
    // Ignorer l'en-tête
    array_shift($lines);
    
    $commandesParActivite = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 7) continue;
        
        $activite = $data[5]; // Nom du dossier
        $photoNom = $data[6];
        $quantite = intval($data[7]);
        
        if (!isset($commandesParActivite[$activite])) {
            $commandesParActivite[$activite] = [];
        }
        
        if (!isset($commandesParActivite[$activite][$photoNom])) {
            $commandesParActivite[$activite][$photoNom] = [];
        }
        
        $commandesParActivite[$activite][$photoNom][] = [
            'ref' => $data[0],
            'nom' => $data[1],
            'prenom' => $data[2],
            'email' => isset($data[3]) ? $data[3] : '',
            'telephone' => isset($data[4]) ? $data[4] : '',
            'quantite' => $quantite
        ];
    }
    
    // Trier par activité puis par nom de photo (ordre alphabétique)
    foreach ($commandesParActivite as $activite => &$photos) {
        ksort($photos);
        
        // Trier les commandes par référence pour chaque photo
        foreach ($photos as $photoNom => &$commandes) {
            usort($commandes, function($a, $b) {
                return strcmp($a['ref'], $b['ref']);
            });
        }
    }
    ksort($commandesParActivite);
    
    // Générer le fichier de picking
    $timestamp = date('Y-m-d_H-i-s');
    $pickingFile = 'exports/picking_lists_' . $timestamp . '.txt';
    
    if (!is_dir('exports')) {
        mkdir('exports', 0755, true);
    }
    
    $content = "=== LISTES DE PICKING PAR ACTIVITÉ ===\n";
    $content .= "Générées le " . date('d/m/Y à H:i') . "\n\n";
    $content .= "INSTRUCTIONS:\n";
    $content .= "- Chaque ligne représente une commande individuelle\n";
    $content .= "- Même client peut avoir plusieurs lignes (commandes différentes)\n";
    $content .= "- Préparer la quantité exacte indiquée pour chaque référence\n";
    $content .= "- Cocher chaque ligne une fois la photo distribuée\n\n";
    
    $totalCommandes = 0;
    $totalExemplaires = 0;
    
    foreach ($commandesParActivite as $activite => $photos) {
        $content .= str_repeat("=", 60) . "\n";
        $content .= "ACTIVITÉ: " . $activite . "\n";
        $content .= str_repeat("=", 60) . "\n\n";
        
        foreach ($photos as $photoNom => $commandes) {
            $totalPhotoExemplaires = array_sum(array_column($commandes, 'quantite'));
            
            $content .= "Photo: " . $photoNom . " (Total: " . $totalPhotoExemplaires . " exemplaires)\n";
            $content .= str_repeat("-", 50) . "\n";
            
            // Afficher chaque commande individuellement
            foreach ($commandes as $commande) {
                $content .= sprintf(
                    "☐ Réf: %-15s | %-20s | %2d ex. | %s\n",
                    $commande['ref'],
                    $commande['nom'] . ' ' . $commande['prenom'],
                    $commande['quantite'],
                    !empty($commande['telephone']) ? 'Tel: ' . $commande['telephone'] : 'Email: ' . $commande['email']
                );
                
                $totalCommandes++;
                $totalExemplaires += $commande['quantite'];
            }
            $content .= "\n";
        }
        $content .= "\n";
    }
    
    // Ajouter un résumé en fin de fichier
    $content .= str_repeat("=", 60) . "\n";
    $content .= "RÉSUMÉ GÉNÉRAL\n";
    $content .= str_repeat("=", 60) . "\n";
    $content .= "Total activités: " . count($commandesParActivite) . "\n";
    $content .= "Total commandes: " . $totalCommandes . "\n";
    $content .= "Total exemplaires: " . $totalExemplaires . "\n\n";
    
    // Résumé par activité
    foreach ($commandesParActivite as $activite => $photos) {
        $commandesActivite = 0;
        $exemplairesActivite = 0;
        $photosUniques = count($photos);
        
        foreach ($photos as $commandes) {
            $commandesActivite += count($commandes);
            $exemplairesActivite += array_sum(array_column($commandes, 'quantite'));
        }
        
        $content .= sprintf(
            "%-25s: %2d photos, %3d commandes, %3d exemplaires\n",
            $activite,
            $photosUniques,
            $commandesActivite,
            $exemplairesActivite
        );
    }
    
    $result = file_put_contents($pickingFile, $content);
    
    if ($result) {
        return [
            'success' => true,
            'file' => $pickingFile,
            'activities_count' => count($commandesParActivite),
            'total_orders' => $totalCommandes,
            'total_copies' => $totalExemplaires,
            'message' => 'Listes de picking générées pour ' . count($commandesParActivite) . ' activité(s) - ' . $totalCommandes . ' commandes'
        ];
    } else {
        return ['success' => false, 'error' => 'Impossible de générer le fichier'];
    }
}

/**
 * Version alternative avec format CSV pour Excel/LibreOffice
 * Version 1.0
 */
function generatePickingListsByActivityCSV() {
    $preparerFile = 'commandes/commandes_a_preparer.csv';
    
    if (!file_exists($preparerFile)) {
        return ['success' => false, 'error' => 'Aucune commande à préparer'];
    }
    
    $lines = file($preparerFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier de préparation vide'];
    }
    
    // Ignorer l'en-tête
    array_shift($lines);
    
    $commandesParActivite = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 7) continue;
        
        $activite = $data[5];
        $photoNom = $data[6];
        $quantite = intval($data[7]);
        
        if (!isset($commandesParActivite[$activite])) {
            $commandesParActivite[$activite] = [];
        }
        
        if (!isset($commandesParActivite[$activite][$photoNom])) {
            $commandesParActivite[$activite][$photoNom] = [];
        }
        
        $commandesParActivite[$activite][$photoNom][] = [
            'ref' => $data[0],
            'nom' => $data[1],
            'prenom' => $data[2],
            'email' => isset($data[3]) ? $data[3] : '',
            'telephone' => isset($data[4]) ? $data[4] : '',
            'quantite' => $quantite
        ];
    }
    
    // Trier
    foreach ($commandesParActivite as $activite => &$photos) {
        ksort($photos);
        foreach ($photos as $photoNom => &$commandes) {
            usort($commandes, function($a, $b) {
                return strcmp($a['ref'], $b['ref']);
            });
        }
    }
    ksort($commandesParActivite);
    
    // Générer le fichier CSV
    $timestamp = date('Y-m-d_H-i-s');
    $pickingFileCSV = 'exports/picking_lists_detaillees_' . $timestamp . '.csv';
    
    if (!is_dir('exports')) {
        mkdir('exports', 0755, true);
    }
    
    // En-tête CSV avec BOM UTF-8
    $bom = "\xEF\xBB\xBF";
    $header = "Activite;Photo;Reference;Nom;Prenom;Quantite;Contact;Fait\n";
    $csvContent = $bom . $header;
    
    foreach ($commandesParActivite as $activite => $photos) {
        foreach ($photos as $photoNom => $commandes) {
            foreach ($commandes as $commande) {
                $contact = !empty($commande['telephone']) ? $commande['telephone'] : $commande['email'];
                
                // Préparer les données et les sanitiser contre les injections de formules
                $rowData = [
                    $activite,
                    $photoNom,
                    $commande['ref'],
                    $commande['nom'],
                    $commande['prenom'],
                    $commande['quantite'],
                    $contact,
                    '' // Colonne "Fait" vide pour cocher manuellement
                ];
                
                $sanitizedRowData = array_map('sanitizeCSVValue', $rowData);
                $csvContent .= implode(';', array_map(function($value) {
                    return '"' . str_replace('"', '""', $value) . '"';
                }, $sanitizedRowData)) . "\n";
            }
        }
    }
    
    $resultCSV = file_put_contents($pickingFileCSV, $csvContent);
    
    if ($resultCSV) {
        return [
            'success' => true,
            'file_txt' => $pickingFile ?? null,
            'file_csv' => $pickingFileCSV,
            'format' => 'both',
            'message' => 'Listes de picking générées (formats TXT et CSV)'
        ];
    } else {
        return ['success' => false, 'error' => 'Impossible de générer le fichier CSV'];
    }
}

/**
 * Exporter le guide de séparation des photos
 * Version 1.0
 */
function exportSeparationGuide() {
    $preparerFile = 'commandes/commandes_a_preparer.csv';
    
    if (!file_exists($preparerFile)) {
        return ['success' => false, 'error' => 'Aucune commande à préparer'];
    }
    
    $lines = file($preparerFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier de préparation vide'];
    }
    
    array_shift($lines); // Ignorer l'en-tête
    
    $photosParActivite = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 7) continue;
        
        $activite = $data[5];
        $photoNom = $data[6];
        $quantite = intval($data[7]);
        
        if (!isset($photosParActivite[$activite])) {
            $photosParActivite[$activite] = [];
        }
        
        if (!isset($photosParActivite[$activite][$photoNom])) {
            $photosParActivite[$activite][$photoNom] = 0;
        }
        
        $photosParActivite[$activite][$photoNom] += $quantite;
    }
    
    // Trier
    foreach ($photosParActivite as $activite => &$photos) {
        ksort($photos);
    }
    ksort($photosParActivite);
    
    // Générer le guide
    $timestamp = date('Y-m-d_H-i-s');
    $guideFile = 'exports/guide_separation_' . $timestamp . '.txt';
    
    $content = "=== GUIDE DE SÉPARATION DES PHOTOS REÇUES ===\n";
    $content .= "Généré le " . date('d/m/Y à H:i') . "\n\n";
    
    $content .= "INSTRUCTIONS:\n";
    $content .= "1. Préparer un bac pour chaque activité listée ci-dessous\n";
    $content .= "2. Étiqueter chaque bac avec le nom de l'activité\n";
    $content .= "3. Séparer les photos reçues selon la liste ci-dessous\n";
    $content .= "4. Cocher chaque photo une fois placée dans le bon bac\n\n";
    
    foreach ($photosParActivite as $activite => $photos) {
        $content .= "ACTIVITÉ: " . $activite . "\n";
        $content .= "Photos à isoler du lot général:\n";
        
        foreach ($photos as $photoNom => $quantiteTotale) {
            $content .= "☐ " . $photoNom . " (" . $quantiteTotale . " exemplaires)\n";
        }
        $content .= "\n";
    }
    
    $result = file_put_contents($guideFile, $content);
    
    if ($result) {
        return [
            'success' => true,
            'file' => $guideFile,
            'activities_count' => count($photosParActivite),
            'message' => 'Guide de séparation généré'
        ];
    } else {
        return ['success' => false, 'error' => 'Impossible de générer le fichier'];
    }
}

/**
 * Exporter le résumé pour commande imprimeur
 * Version 1.0
 */
function exportPrinterSummary() {
    $preparerFile = 'commandes/commandes_a_preparer.csv';
    
    if (!file_exists($preparerFile)) {
        return ['success' => false, 'error' => 'Aucune commande à préparer'];
    }
    
    $lines = file($preparerFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier de préparation vide'];
    }
    
    array_shift($lines);
    
    $photosParActivite = [];
    $photosTotales = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 7) continue;
        
        $activite = $data[5];
        $photoNom = $data[6];
        $quantite = intval($data[7]);
        
        // Par activité
        if (!isset($photosParActivite[$activite])) {
            $photosParActivite[$activite] = [];
        }
        if (!isset($photosParActivite[$activite][$photoNom])) {
            $photosParActivite[$activite][$photoNom] = 0;
        }
        $photosParActivite[$activite][$photoNom] += $quantite;
        
        // Total général
        if (!isset($photosTotales[$photoNom])) {
            $photosTotales[$photoNom] = 0;
        }
        $photosTotales[$photoNom] += $quantite;
    }
    
    // Trier
    ksort($photosParActivite);
    ksort($photosTotales);
    
    // Générer le résumé
    $timestamp = date('Y-m-d_H-i-s');
    $summaryFile = 'exports/resume_imprimeur_' . $timestamp . '.txt';
    
    $content = "=== RÉSUMÉ POUR COMMANDE IMPRIMEUR ===\n";
    $content .= "Généré le " . date('d/m/Y à H:i') . "\n\n";
    
    // Résumé par activité
    foreach ($photosParActivite as $activite => $photos) {
        $photosUniques = count($photos);
        $totalExemplaires = array_sum($photos);
        
        $content .= "ACTIVITÉ: " . $activite . "\n";
        $content .= "- Photos différentes: " . $photosUniques . "\n";
        $content .= "- Total exemplaires: " . $totalExemplaires . "\n";
        $content .= "- Photos: " . implode(', ', array_keys($photos)) . "\n\n";
    }
    
    $content .= str_repeat("=", 50) . "\n";
    $content .= "COMMANDE TOTALE POUR L'IMPRIMEUR\n";
    $content .= str_repeat("=", 50) . "\n\n";
    $content .= "-------------------------------------------------\n";
    
    foreach ($photosTotales as $photo => $quantite) {
        $content .= "     □  " . $quantite . " x " . $photo . "\n";
        $content .= "-------------------------------------------------\n";
    }
    
    $result = file_put_contents($summaryFile, $content);
    
    if ($result) {
        return [
            'success' => true,
            'file' => $summaryFile,
            'total_photos' => count($photosTotales),
            'total_copies' => array_sum($photosTotales),
            'message' => 'Résumé imprimeur généré'
        ];
    } else {
        return ['success' => false, 'error' => 'Impossible de générer le fichier'];
    }
}

/**
 * Contrôler la cohérence par activité
 * Version 1.0
 */
function checkActivityCoherence() {
    $preparerFile = 'commandes/commandes_a_preparer.csv';
    
    if (!file_exists($preparerFile)) {
        return ['success' => false, 'error' => 'Aucune commande à préparer'];
    }
    
    $lines = file($preparerFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier de préparation vide'];
    }
    
    array_shift($lines);
    
    $commandesParActivite = [];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 7) continue;
        
        $activite = $data[5];
        
        if (!isset($commandesParActivite[$activite])) {
            $commandesParActivite[$activite] = [];
        }
        
        $commandesParActivite[$activite][] = [
            'photo' => $data[6],
            'quantite' => intval($data[7])
        ];
    }
    
    $coherenceReport = [];
    foreach ($commandesParActivite as $activite => $commandes) {
        $photosGroupees = [];
        foreach ($commandes as $commande) {
            $photo = $commande['photo'];
            if (!isset($photosGroupees[$photo])) {
                $photosGroupees[$photo] = 0;
            }
            $photosGroupees[$photo] += $commande['quantite'];
        }
        
        $coherenceReport[$activite] = [
            'photos_count' => count($photosGroupees),
            'total_copies' => array_sum($photosGroupees),
            'photos' => $photosGroupees
        ];
    }
    
    return [
        'success' => true,
        'activities_count' => count($coherenceReport),
        'report' => $coherenceReport
    ];
}

/**
 * Exporter les commandes de préparation organisées par ACTIVITY_PRICING
 * Version 1.0
 * @return array Résultat de l'export avec informations sur les fichiers générés
 */
function exportPreparationByActivity() {
    $preparerFile = 'commandes/commandes_a_preparer.csv';
    
    if (!file_exists($preparerFile)) {
        return ['success' => false, 'error' => 'Aucune commande à préparer'];
    }
    
    $lines = file($preparerFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier de préparation vide'];
    }
    
    $header = array_shift($lines); // Conserver l'en-tête
    $commandesParActivite = [];
    
    // Grouper les commandes par activité
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 7) continue;
        
        $activite = $data[5]; // Nom du dossier (ACTIVITY_PRICING)
        
        if (!isset($commandesParActivite[$activite])) {
            $commandesParActivite[$activite] = [];
        }
        
        $commandesParActivite[$activite][] = $line;
    }
    
    // Trier les activités par ordre alphabétique
    ksort($commandesParActivite);
    
    // Créer le dossier exports s'il n'existe pas
    if (!is_dir('exports')) {
        mkdir('exports', 0755, true);
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $fichiers_generes = [];
    $total_activites = count($commandesParActivite);
    
    // Option 1: Un fichier par activité
    foreach ($commandesParActivite as $activite => $lignes) {
        // Nettoyer le nom d'activité pour le nom de fichier
        $nomFichierSafe = preg_replace('/[^a-zA-Z0-9_-]/', '_', $activite);
        $nomFichierSafe = trim($nomFichierSafe, '_');
        
        $fichierActivite = 'exports/preparation_' . $nomFichierSafe . '_' . $timestamp . '.csv';
        
        // Trier les lignes de cette activité par nom de photo (colonne 6)
        usort($lignes, function($a, $b) {
            $dataA = str_getcsv($a, ';');
            $dataB = str_getcsv($b, ';');
            $photoA = isset($dataA[6]) ? $dataA[6] : '';
            $photoB = isset($dataB[6]) ? $dataB[6] : '';
            return strcmp($photoA, $photoB);
        });
        
        // Préparer le contenu avec BOM UTF-8
        $bom = "\xEF\xBB\xBF";
        $contenu = $bom . $header . "\n" . implode("\n", $lignes) . "\n";
        
        $resultat = file_put_contents($fichierActivite, $contenu);
        
        if ($resultat !== false) {
            $fichiers_generes[] = [
                'activite' => $activite,
                'fichier' => $fichierActivite,
                'nombre_lignes' => count($lignes)
            ];
        }
    }
    
    // Option 2: Un fichier consolidé avec séparation par activité
    $fichierConsolide = 'exports/preparation_par_activite_' . $timestamp . '.csv';
    
    // En-tête étendu avec colonne activité en première position
    $headerEtendu = 'Activite;' . $header;
    $lignesConsolidees = [$headerEtendu];
    
    foreach ($commandesParActivite as $activite => $lignes) {
        // Trier par nom de photo
        usort($lignes, function($a, $b) {
            $dataA = str_getcsv($a, ';');
            $dataB = str_getcsv($b, ';');
            $photoA = isset($dataA[6]) ? $dataA[6] : '';
            $photoB = isset($dataB[6]) ? $dataB[6] : '';
            return strcmp($photoA, $photoB);
        });
        
        // Ajouter l'activité en première colonne
        foreach ($lignes as $ligne) {
            $lignesConsolidees[] = $activite . ';' . $ligne;
        }
        
        // Ajouter une ligne de séparation entre les activités
        $lignesConsolidees[] = str_repeat(';', substr_count($header, ';') + 1);
    }
    
    // Supprimer la dernière ligne de séparation
    if (end($lignesConsolidees) === str_repeat(';', substr_count($header, ';') + 1)) {
        array_pop($lignesConsolidees);
    }
    
    $bom = "\xEF\xBB\xBF";
    $contenuConsolide = $bom . implode("\n", $lignesConsolidees) . "\n";
    
    $resultatConsolide = file_put_contents($fichierConsolide, $contenuConsolide);
    
    // Option 3: Fichier résumé avec statistiques
    $fichierResume = 'exports/resume_preparation_' . $timestamp . '.txt';
    
    $contenuResume = "=== RÉSUMÉ DE PRÉPARATION PAR ACTIVITÉ ===\n";
    $contenuResume .= "Généré le " . date('d/m/Y à H:i') . "\n\n";
    
    $totalPhotos = 0;
    $totalExemplaires = 0;
    
    foreach ($commandesParActivite as $activite => $lignes) {
        $photosUniques = [];
        $exemplairesActivite = 0;
        
        foreach ($lignes as $ligne) {
            $data = str_getcsv($ligne, ';');
            if (count($data) >= 8) {
                $photo = $data[6];
                $quantite = intval($data[7]);
                
                $photosUniques[$photo] = true;
                $exemplairesActivite += $quantite;
            }
        }
        
        $contenuResume .= "ACTIVITÉ: " . $activite . "\n";
        $contenuResume .= "- Commandes: " . count($lignes) . "\n";
        $contenuResume .= "- Photos uniques: " . count($photosUniques) . "\n";
        $contenuResume .= "- Total exemplaires: " . $exemplairesActivite . "\n";
        $contenuResume .= "- Fichier généré: " . basename($fichiers_generes[array_search($activite, array_column($fichiers_generes, 'activite'))]['fichier'] ?? '') . "\n\n";
        
        $totalPhotos += count($photosUniques);
        $totalExemplaires += $exemplairesActivite;
    }
    
    $contenuResume .= str_repeat("=", 50) . "\n";
    $contenuResume .= "TOTAUX GÉNÉRAUX:\n";
    $contenuResume .= "- Activités: " . $total_activites . "\n";
    $contenuResume .= "- Photos uniques: " . $totalPhotos . "\n";
    $contenuResume .= "- Total exemplaires: " . $totalExemplaires . "\n";
    $contenuResume .= "- Fichier consolidé: " . basename($fichierConsolide) . "\n";
    
    $resultatResume = file_put_contents($fichierResume, $contenuResume);
    
    // Vérifier le succès de l'opération
    if (count($fichiers_generes) > 0 && $resultatConsolide && $resultatResume) {
        return [
            'success' => true,
            'message' => "Export réussi : {$total_activites} activité(s), {$totalExemplaires} exemplaires",
            'files' => [
                'consolidated' => $fichierConsolide,
                'summary' => $fichierResume,
                'by_activity' => $fichiers_generes
            ],
            'stats' => [
                'activities_count' => $total_activites,
                'total_photos' => $totalPhotos,
                'total_copies' => $totalExemplaires,
                'files_generated' => count($fichiers_generes) + 2 // +2 pour consolidé et résumé
            ]
        ];
    } else {
        return [
            'success' => false, 
            'error' => 'Erreur lors de la génération des fichiers d\'export'
        ];
    }
}

/**
 * Fonction utilitaire pour télécharger plusieurs fichiers
 * Version 1.0
 * @param array $files Liste des fichiers à télécharger
 * @return array Résultat de la création de l'archive
 */
function createDownloadArchive($files) {
    if (!extension_loaded('zip')) {
        return ['success' => false, 'error' => 'Extension ZIP non disponible'];
    }
    
    $timestamp = date('Y-m-d_H-i-s');
    $archiveName = 'exports/preparation_activites_' . $timestamp . '.zip';
    
    $zip = new ZipArchive();
    if ($zip->open($archiveName, ZipArchive::CREATE) !== TRUE) {
        return ['success' => false, 'error' => 'Impossible de créer l\'archive ZIP'];
    }
    
    // Ajouter le fichier consolidé
    if (isset($files['consolidated']) && file_exists($files['consolidated'])) {
        $zip->addFile($files['consolidated'], 'preparation_consolidee.csv');
    }
    
    // Ajouter le résumé
    if (isset($files['summary']) && file_exists($files['summary'])) {
        $zip->addFile($files['summary'], 'resume_preparation.txt');
    }
    
    // Ajouter les fichiers par activité
    if (isset($files['by_activity']) && is_array($files['by_activity'])) {
        foreach ($files['by_activity'] as $fileInfo) {
            if (file_exists($fileInfo['fichier'])) {
                $nomDansFichier = 'activite_' . preg_replace('/[^a-zA-Z0-9_-]/', '_', $fileInfo['activite']) . '.csv';
                $zip->addFile($fileInfo['fichier'], $nomDansFichier);
            }
        }
    }
    
    $zip->close();
    
    if (file_exists($archiveName)) {
        return [
            'success' => true,
            'archive' => $archiveName,
            'message' => 'Archive créée avec succès'
        ];
    } else {
        return ['success' => false, 'error' => 'Erreur lors de la création de l\'archive'];
    }
}

/**
 * Renvoie l'email de confirmation pour une commande
 * @param string $reference Référence de la commande
 * @return array Résultat de l'opération
 * @version 1.0
 */
function resendOrderConfirmationEmail($reference) {
    try {
        // Charger les données de la commande
        $order = new Order($reference);
        if (!$order->load()) {
            return ['success' => false, 'message' => 'Commande non trouvée'];
        }
        $orderData = $order->getData();
        
        // Utiliser la fonction d'envoi d'email existante
        require_once 'email_handler.php';
        $emailHandler = new EmailHandler();
        $emailSent = $emailHandler->sendOrderConfirmation($orderData, false);
        
        if ($emailSent) {
            // Logger l'action
            if (isset($logger)) {
                $logger->info("Email de confirmation renvoyé pour la commande " . $reference);
            }
            return ['success' => true, 'message' => 'Email envoyé avec succès'];
        } else {
            return ['success' => false, 'message' => 'Erreur lors de l\'envoi de l\'email'];
        }
        
    } catch (Exception $e) {
        if (function_exists('logError')) {
            logError("Erreur lors du renvoi d'email pour " . $reference . ": " . $e->getMessage());
        } else {
            error_log("Erreur lors du renvoi d'email pour " . $reference . ": " . $e->getMessage());
        }
        return ['success' => false, 'message' => 'Erreur technique'];
    }
}

/**
 * Récupère les données d'une commande par sa référence
 * @param string $reference Référence de la commande
 * @return array|null Données de la commande
 * @version 1.0
 */
function getOrderDataByReference($reference) {
    // Utiliser la classe OrdersList pour charger les données
    $ordersList = new OrdersList();
    $ordersData = $ordersList->loadOrdersData();
    
    // Chercher la commande par référence
    foreach ($ordersData['orders'] as $order) {
        if ($order['reference'] === $reference) {
            // Adapter la structure pour EmailHandler
            $order['customer'] = [
                'firstname' => $order['firstname'],
                'lastname' => $order['lastname'],
                'email' => $order['email'],
                'phone' => $order['phone'] ?? ''
            ];
            
            // Transformer les photos en items pour EmailHandler
            $order['items'] = [];
            if (isset($order['photos']) && is_array($order['photos'])) {
                foreach ($order['photos'] as $photo) {
                    $order['items'][] = [
                        'activity_key' => $photo['activity_key'] ?? '',
                        'photo_name' => $photo['name'] ?? 'Photo',
                        'quantity' => intval($photo['quantity'] ?? 1),
                        'unit_price' => floatval($photo['price'] ?? 0),
                        'total_price' => floatval($photo['price'] ?? 0) * intval($photo['quantity'] ?? 1)
                    ];
                }
            }
            
            return $order;
        }
    }
    
    return null;
}


?>