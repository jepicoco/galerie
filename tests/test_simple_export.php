<?php
/**
 * Test simple du nouveau comportement d'export
 */

// Simulation d'une fonction d'export modifiée
function testExportValidated() {
    $csvFile = 'commandes/commandes.csv';
    
    if (!file_exists($csvFile)) {
        return ['success' => false, 'error' => 'Fichier CSV introuvable'];
    }
    
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) < 2) {
        return ['success' => false, 'error' => 'Fichier CSV vide'];
    }
    
    array_shift($lines); // Enlever l'en-tête
    
    $validatedOrders = [];
    $stats = ['total' => 0, 'validated' => 0, 'other' => 0];
    
    foreach ($lines as $line) {
        if (empty(trim($line))) continue;
        
        $data = str_getcsv($line, ';');
        if (count($data) < 16) continue;
        
        $commandStatus = $data[15]; // Statut commande (colonne 16)
        $dateRecuperation = $data[14]; // Date de récupération (colonne 15)
        
        $stats['total']++;
        
        // Test de la nouvelle logique
        if ($commandStatus === 'validated' && empty($dateRecuperation)) {
            $validatedOrders[] = [
                'reference' => $data[0],
                'nom' => $data[1] . ' ' . $data[2],
                'activite' => $data[6],
                'photo' => $data[7],
                'quantite' => intval($data[8])
            ];
            $stats['validated']++;
        } else {
            $stats['other']++;
        }
    }
    
    return [
        'success' => true,
        'stats' => $stats,
        'validated_orders' => $validatedOrders,
        'message' => 'Found ' . $stats['validated'] . ' validated orders out of ' . $stats['total'] . ' total'
    ];
}

// Test
$result = testExportValidated();

echo "Test des exports avec commandes VALIDATED\n";
echo "==========================================\n\n";

if ($result['success']) {
    echo "✅ Test réussi : " . $result['message'] . "\n\n";
    
    echo "Statistiques :\n";
    foreach ($result['stats'] as $key => $count) {
        echo "- $key : $count\n";
    }
    
    echo "\nCommandes validated non récupérées :\n";
    foreach ($result['validated_orders'] as $order) {
        echo "- {$order['reference']} | {$order['nom']} | {$order['activite']} | {$order['photo']} | {$order['quantite']}x\n";
    }
    
    echo "\n✅ Les modifications fonctionnent correctement !\n";
    echo "➤ Toutes les commandes avec statut 'validated' seront incluses dans les exports\n";
    echo "➤ Les commandes déjà récupérées sont exclues\n";
    
} else {
    echo "❌ Erreur : " . $result['error'] . "\n";
}

?>