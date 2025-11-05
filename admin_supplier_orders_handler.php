<?php
/**
 * Handler pour la gestion des commandes fournisseur - admin_supplier_orders_handler.php
 * Version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}

require_once 'config.php';
require_once 'functions.php';

// Gestion des requêtes AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    header('Content-Type: application/json');

    $action = $_POST['action'];
    $response = ['success' => false, 'error' => 'Action non reconnue'];

    switch ($action) {
        case 'export_supplier_order':
            $supplier = $_POST['supplier'] ?? '';
            $response = exportSupplierOrder($supplier);
            break;

        case 'generate_distribution_list':
            $response = getDistributionListData();
            break;

        case 'export_distribution_csv':
            $response = exportDistributionCSV();
            break;

        case 'reset_exported_status':
            $response = resetExportedStatus();
            break;
    }

    echo json_encode($response);
    exit;
}

/**
 * Compte les articles par fournisseur depuis commandes.csv
 * @return array Statistiques par fournisseur
 */
function countSupplierArticles() {
    global $ACTIVITY_PRICING;

    $csvFile = 'commandes/commandes.csv';

    if (!file_exists($csvFile)) {
        return [
            'supplier_a' => ['total_items' => 0, 'items' => []],
            'supplier_b' => ['total_items' => 0, 'items' => []]
        ];
    }

    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return [
            'supplier_a' => ['total_items' => 0, 'items' => []],
            'supplier_b' => ['total_items' => 0, 'items' => []]
        ];
    }

    // Ignorer l'en-tête
    array_shift($lines);

    $supplierA = ['total_items' => 0, 'items' => []];
    $supplierB = ['total_items' => 0, 'items' => []];

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        $data = str_getcsv($line, ';');
        if (count($data) < 9) continue;

        // Colonnes: REF;Nom;Prenom;Email;Telephone;Date commande;Dossier;N de la photo;Quantite;...
        $folder = $data[6];      // Dossier
        $photo = $data[7];       // N de la photo
        $quantity = intval($data[8]); // Quantite

        // Identifier le fournisseur : "Film du Gala" = USB (B), autres = PHOTO (A)
        $isUSB = (trim($folder) === 'Film du Gala');

        if ($isUSB) {
            // Fournisseur B - USB
            $key = $photo;
            if (!isset($supplierB['items'][$key])) {
                $supplierB['items'][$key] = [
                    'photo' => $photo,
                    'quantity' => 0
                ];
            }
            $supplierB['items'][$key]['quantity'] += $quantity;
            $supplierB['total_items'] += $quantity;
        } else {
            // Fournisseur A - PHOTO
            $key = $folder . '/' . $photo;
            if (!isset($supplierA['items'][$key])) {
                $supplierA['items'][$key] = [
                    'folder' => $folder,
                    'photo' => $photo,
                    'quantity' => 0
                ];
            }
            $supplierA['items'][$key]['quantity'] += $quantity;
            $supplierA['total_items'] += $quantity;
        }
    }

    // Trier les items par nom
    usort($supplierA['items'], function($a, $b) {
        return strcmp($a['folder'] . $a['photo'], $b['folder'] . $b['photo']);
    });

    usort($supplierB['items'], function($a, $b) {
        return strcmp($a['photo'], $b['photo']);
    });

    return [
        'supplier_a' => $supplierA,
        'supplier_b' => $supplierB
    ];
}

/**
 * Obtenir les statistiques pour l'affichage initial
 * @return array Statistiques des commandes fournisseur
 */
function getSupplierOrdersStats() {
    $stats = countSupplierArticles();

    return [
        'supplier_a' => [
            'total_items' => $stats['supplier_a']['total_items'],
            'items' => array_values($stats['supplier_a']['items'])
        ],
        'supplier_b' => [
            'total_items' => $stats['supplier_b']['total_items'],
            'items' => array_values($stats['supplier_b']['items'])
        ]
    ];
}

/**
 * Exporter la commande pour un fournisseur spécifique
 * @param string $supplier 'A' ou 'B'
 * @return array Résultat de l'export
 */
function exportSupplierOrder($supplier) {
    if (!in_array($supplier, ['A', 'B'])) {
        return ['success' => false, 'error' => 'Fournisseur invalide'];
    }

    $stats = countSupplierArticles();
    $supplierKey = 'supplier_' . strtolower($supplier);
    $supplierData = $stats[$supplierKey];

    if ($supplierData['total_items'] === 0) {
        return ['success' => false, 'error' => 'Aucun article à commander pour ce fournisseur'];
    }

    // Créer le dossier exports s'il n'existe pas
    if (!is_dir('exports')) {
        mkdir('exports', 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $filename = 'exports/commande_fournisseur_' . $supplier . '_' . $timestamp . '.csv';

    // Générer le contenu CSV
    $bom = "\xEF\xBB\xBF";

    if ($supplier === 'A') {
        // Photos
        $header = "Dossier;Photo;Quantite\n";
        $content = $bom . $header;

        foreach ($supplierData['items'] as $item) {
            $content .= implode(';', [
                '"' . $item['folder'] . '"',
                '"' . $item['photo'] . '"',
                $item['quantity']
            ]) . "\n";
        }
    } else {
        // USB
        $header = "Article;Quantite\n";
        $content = $bom . $header;

        foreach ($supplierData['items'] as $item) {
            $content .= implode(';', [
                '"' . $item['photo'] . '"',
                $item['quantity']
            ]) . "\n";
        }
    }

    $result = file_put_contents($filename, $content);

    if ($result === false) {
        return ['success' => false, 'error' => 'Impossible de créer le fichier'];
    }

    return [
        'success' => true,
        'message' => 'Commande fournisseur ' . $supplier . ' exportée (' . $supplierData['total_items'] . ' articles)',
        'file' => $filename
    ];
}

/**
 * Générer la liste de répartition des commandes (PHOTO + USB réunis)
 * @return array Données de répartition par commande
 */
function getDistributionListData() {
    $csvFile = 'commandes/commandes.csv';

    if (!file_exists($csvFile)) {
        return ['success' => false, 'error' => 'Fichier CSV introuvable'];
    }

    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    if (!$lines || count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier CSV vide'];
    }

    array_shift($lines);

    $ordersByReference = [];

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        $data = str_getcsv($line, ';');
        if (count($data) < 9) continue;

        $ref = $data[0];
        $lastname = $data[1];
        $firstname = $data[2];
        $folder = $data[6];
        $photo = $data[7];
        $quantity = intval($data[8]);

        if (!isset($ordersByReference[$ref])) {
            $ordersByReference[$ref] = [
                'reference' => $ref,
                'lastname' => $lastname,
                'firstname' => $firstname,
                'items' => []
            ];
        }

        $ordersByReference[$ref]['items'][] = [
            'folder' => $folder,
            'photo' => $photo,
            'quantity' => $quantity
        ];
    }

    // Trier par référence
    ksort($ordersByReference);

    return [
        'success' => true,
        'data' => array_values($ordersByReference),
        'message' => count($ordersByReference) . ' commande(s) à répartir'
    ];
}

/**
 * Formate le nom de photo pour avoir des numéros à 3 chiffres entre parenthèses
 * Exemple: DSC_1234 (1).jpg -> DSC_1234 (001).jpg
 * @param string $photoName Nom original de la photo
 * @return string Nom formaté
 */
function formatPhotoNumberForSort($photoName) {
    // Pattern pour capturer le numéro entre parenthèses : (1), (12), (123), etc.
    $pattern = '/\((\d+)\)/';

    $formattedName = preg_replace_callback($pattern, function($matches) {
        // $matches[1] contient le numéro capturé
        $number = $matches[1];
        // Formater sur 3 chiffres avec padding de zéros
        $paddedNumber = str_pad($number, 3, '0', STR_PAD_LEFT);
        return '(' . $paddedNumber . ')';
    }, $photoName);

    return $formattedName;
}

/**
 * Exporter la liste de répartition en CSV
 * @return array Résultat de l'export
 */
function exportDistributionCSV() {
    $result = getDistributionListData();

    if (!$result['success']) {
        return $result;
    }

    if (empty($result['data'])) {
        return ['success' => false, 'error' => 'Aucune commande à exporter'];
    }

    // Créer le dossier exports s'il n'existe pas
    if (!is_dir('exports')) {
        mkdir('exports', 0755, true);
    }

    $timestamp = date('Y-m-d_H-i-s');
    $filename = 'exports/repartition_commandes_' . $timestamp . '.csv';

    $bom = "\xEF\xBB\xBF";
    $header = "Reference;Nom;Prenom;Dossier;Photo;Photo (tri);Quantite;Fait\n";
    $content = $bom . $header;

    foreach ($result['data'] as $order) {
        foreach ($order['items'] as $item) {
            $photoName = $item['photo'];
            $photoNameFormatted = formatPhotoNumberForSort($photoName);

            $content .= implode(';', [
                '"' . $order['reference'] . '"',
                '"' . $order['lastname'] . '"',
                '"' . $order['firstname'] . '"',
                '"' . $item['folder'] . '"',
                '"' . $photoName . '"',
                '"' . $photoNameFormatted . '"',
                $item['quantity'],
                '' // Colonne "Fait" vide
            ]) . "\n";
        }
    }

    $writeResult = file_put_contents($filename, $content);

    if ($writeResult === false) {
        return ['success' => false, 'error' => 'Impossible de créer le fichier'];
    }

    return [
        'success' => true,
        'message' => 'Liste de répartition exportée',
        'file' => $filename
    ];
}

/**
 * Réinitialiser le statut "Exported" dans commandes.csv
 * @return array Résultat de l'opération
 */
function resetExportedStatus() {
    $csvFile = 'commandes/commandes.csv';

    if (!file_exists($csvFile)) {
        return ['success' => false, 'error' => 'Fichier CSV introuvable'];
    }

    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    if (!$lines) {
        return ['success' => false, 'error' => 'Impossible de lire le fichier CSV'];
    }

    $header = array_shift($lines);
    $updatedLines = [$header];
    $resetCount = 0;

    foreach ($lines as $line) {
        if (empty(trim($line))) continue;

        $data = str_getcsv($line, ';');

        // Étendre le tableau pour avoir la colonne "Exported" (position 16)
        while (count($data) < 18) {
            $data[] = '';
        }

        // Si la colonne Exported était à "exported", la réinitialiser
        if (isset($data[16]) && $data[16] === 'exported') {
            $data[16] = '';
            $resetCount++;
        }

        $updatedLines[] = implode(';', $data);
    }

    // Sauvegarder le fichier
    $result = file_put_contents($csvFile, implode("\n", $updatedLines) . "\n");

    if ($result === false) {
        return ['success' => false, 'error' => 'Impossible de sauvegarder le fichier'];
    }

    return [
        'success' => true,
        'message' => $resetCount . ' ligne(s) réinitialisée(s)',
        'reset_count' => $resetCount
    ];
}

?>
