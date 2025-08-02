<?php
/**
 * Test de validation pour admin_orders.php
 * À exécuter pour vérifier que les mises à jour fonctionnent
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Fonction updateOrderInCSV corrigée (copiez-la ici si pas encore dans admin_orders.php)
function updateOrderInCSV($reference, $updates) {
    global $logger;
    
    $csvFile = 'commandes/commandes.csv';
    
    if (!file_exists($csvFile)) {
        return ['success' => false, 'error' => 'Fichier CSV introuvable'];
    }
    
    $content = file_get_contents($csvFile);
    if ($content === false) {
        return ['success' => false, 'error' => 'Impossible de lire le fichier CSV'];
    }
    
    // Supprimer le BOM UTF-8 si présent
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    
    $lines = explode("\n", $content);
    $lines = array_filter($lines, function($line) {
        return trim($line) !== '';
    });
    
    if (count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier CSV vide ou invalide'];
    }
    
    $header = array_shift($lines);
    $updatedLines = [$header];
    $orderFound = false;
    $updatedCount = 0;
    
    // Mapping exact selon votre structure CSV confirmée
    $columnMapping = [
        'payment_date' => 5,        // Paiement effectue le
        'payment_mode' => 6,        // Mode de paiement
        'retrieval_date' => 9,      // Date de recuperation
        'desired_deposit_date' => 10, // Date encaissement souhaitee
        'actual_deposit_date' => 11,  // Date encaissement
        'order_status' => 12,       // Statut commande
        'payment_status' => 13,     // Statut paiement
        'retrieval_status' => 14,   // Statut retrait
        'amount' => 15,             // Montant
        'actual_retrieval_date' => 16 // Date retrait effective
    ];
    
    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;
        
        $data = str_getcsv($line, ';');
        
        // Vérifier que la ligne a au moins 17 colonnes
        while (count($data) < 17) {
            $data[] = '';
        }
        
        // Si c'est la ligne à modifier
        if (isset($data[0]) && $data[0] === $reference) {
            $orderFound = true;
            $updatedCount++;
            
            echo "📝 Modification de la ligne: " . implode(';', array_slice($data, 0, 5)) . "...\n";
            
            // Appliquer les mises à jour
            foreach ($updates as $field => $value) {
                if (isset($columnMapping[$field])) {
                    $index = $columnMapping[$field];
                    $oldValue = $data[$index];
                    
                    // Nettoyage de la valeur
                    $cleanValue = str_replace([';', "\n", "\r"], ['_', ' ', ' '], $value);
                    $data[$index] = $cleanValue;
                    
                    echo "   ✏️  $field (index $index): '$oldValue' → '$cleanValue'\n";
                }
            }
            
            $updatedLine = implode(';', $data);
            $updatedLines[] = $updatedLine;
        } else {
            $updatedLines[] = $line;
        }
    }
    
    if (!$orderFound) {
        return ['success' => false, 'error' => "Commande $reference introuvable"];
    }
    
    // Sauvegarder le fichier avec BOM UTF-8
    $content = "\xEF\xBB\xBF" . implode("\n", $updatedLines) . "\n";
    
    $result = file_put_contents($csvFile, $content);
    
    if ($result === false) {
        return ['success' => false, 'error' => 'Impossible de sauvegarder le fichier'];
    }
    
    return [
        'success' => true, 
        'message' => "Commande $reference mise à jour avec succès ($updatedCount ligne(s) modifiée(s))"
    ];
}

// ===== TESTS =====

echo "=== TEST DE VALIDATION admin_orders.php ===\n\n";

// 1. Vérifier la structure du CSV
echo "1. Vérification de la structure CSV...\n";
$csvFile = 'commandes/commandes.csv';

if (!file_exists($csvFile)) {
    echo "❌ ERREUR: Fichier CSV introuvable\n";
    exit;
}

$content = file_get_contents($csvFile);
if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
    $content = substr($content, 3);
}

$lines = explode("\n", $content);
$lines = array_filter($lines, function($line) { return trim($line) !== ''; });

if (count($lines) < 2) {
    echo "❌ ERREUR: CSV vide ou invalide\n";
    exit;
}

$header = $lines[0];
$headerCols = explode(';', $header);

echo "✅ CSV trouvé avec " . count($headerCols) . " colonnes\n";
echo "✅ Première ligne de données disponible\n\n";

// 2. Identifier une commande de test
echo "2. Identification d'une commande de test...\n";
$testLine = $lines[1]; // Première ligne de données
$testData = str_getcsv($testLine, ';');
$testRef = $testData[0];

echo "📋 Commande de test: $testRef\n";
echo "   Client: {$testData[1]} {$testData[2]}\n";
echo "   Statut actuel: {$testData[13]} (paiement), {$testData[14]} (retrait)\n\n";

// 3. Test de mise à jour
echo "3. Test de mise à jour...\n";

// Sauvegarder l'état original
$originalPaymentStatus = $testData[13];
$originalPaymentMode = $testData[6];

// Appliquer une mise à jour
$newPaymentStatus = ($originalPaymentStatus === 'paid') ? 'pending' : 'paid';
$testUpdates = [
    'payment_status' => $newPaymentStatus,
    'payment_mode' => 'test_mode',
    'payment_date' => date('Y-m-d H:i:s')
];

echo "Mise à jour de la commande $testRef...\n";
$result = updateOrderInCSV($testRef, $testUpdates);

if ($result['success']) {
    echo "✅ " . $result['message'] . "\n\n";
    
    // 4. Vérifier la modification
    echo "4. Vérification de la modification...\n";
    $content = file_get_contents($csvFile);
    if (substr($content, 0, 3) === "\xEF\xBB\xBF") {
        $content = substr($content, 3);
    }
    $lines = explode("\n", $content);
    $lines = array_filter($lines, function($line) { return trim($line) !== ''; });
    
    foreach ($lines as $line) {
        $data = str_getcsv($line, ';');
        if ($data[0] === $testRef) {
            echo "   📊 Statut paiement: '{$data[13]}' (était '$originalPaymentStatus')\n";
            echo "   📊 Mode paiement: '{$data[6]}' (était '$originalPaymentMode')\n";
            echo "   📊 Date paiement: '{$data[5]}'\n";
            
            if ($data[13] === $newPaymentStatus) {
                echo "✅ Modification confirmée dans le CSV\n";
            } else {
                echo "❌ Modification non visible dans le CSV\n";
            }
            break;
        }
    }
    
    // 5. Restaurer l'état original
    echo "\n5. Restauration de l'état original...\n";
    $restoreResult = updateOrderInCSV($testRef, [
        'payment_status' => $originalPaymentStatus,
        'payment_mode' => $originalPaymentMode,
        'payment_date' => ''
    ]);
    
    if ($restoreResult['success']) {
        echo "✅ État original restauré\n";
    } else {
        echo "⚠️  Problème lors de la restauration: " . $restoreResult['error'] . "\n";
    }
    
} else {
    echo "❌ ERREUR: " . $result['error'] . "\n";
}

echo "\n=== FIN DU TEST ===\n";
echo "\nSi tous les tests sont ✅, alors admin_orders.php devrait fonctionner correctement !\n";
?>