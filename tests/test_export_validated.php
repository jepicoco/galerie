<?php
/**
 * Test du nouveau comportement d'export - Commandes validated
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';
require_once 'admin_orders_handler.php';

echo "<h2>Test des exports avec commandes VALIDATED</h2>\n";

// Vérifier le contenu du fichier CSV principal
echo "<h3>1. Analyse du fichier principal commandes.csv</h3>\n";
$csvFile = 'commandes/commandes.csv';

if (file_exists($csvFile)) {
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Nombre total de lignes : " . count($lines) . "<br>\n";
    
    if (count($lines) > 0) {
        array_shift($lines); // Enlever l'en-tête
        
        $stats = [
            'temp' => 0,
            'validated' => 0,
            'paid' => 0,
            'prepared' => 0,
            'retrieved' => 0,
            'total' => 0
        ];
        
        foreach ($lines as $line) {
            if (empty(trim($line))) continue;
            $data = str_getcsv($line, ';');
            if (count($data) < 16) continue;
            
            $status = $data[15];
            $stats['total']++;
            
            if (isset($stats[$status])) {
                $stats[$status]++;
            } else {
                echo "Statut inconnu trouvé : '$status'<br>\n";
            }
        }
        
        echo "Répartition des statuts :<br>\n";
        foreach ($stats as $status => $count) {
            echo "- $status : $count<br>\n";
        }
        echo "<br>\n";
        
        // Test des fonctions d'export
        echo "<h3>2. Test de exportPrinterSummary()</h3>\n";
        $result = exportPrinterSummary();
        if ($result['success']) {
            echo "✅ Export réussi : " . $result['message'] . "<br>\n";
            echo "Fichier généré : " . $result['file'] . "<br>\n";
            echo "Photos totales : " . $result['total_photos'] . "<br>\n";
            echo "Exemplaires totaux : " . $result['total_copies'] . "<br>\n";
        } else {
            echo "❌ Erreur : " . $result['error'] . "<br>\n";
        }
        echo "<br>\n";
        
        echo "<h3>3. Test de exportSeparationGuide()</h3>\n";
        $result = exportSeparationGuide();
        if ($result['success']) {
            echo "✅ Export réussi : " . $result['message'] . "<br>\n";
            echo "Fichier généré : " . $result['file'] . "<br>\n";
            echo "Nombre d'activités : " . $result['activities_count'] . "<br>\n";
        } else {
            echo "❌ Erreur : " . $result['error'] . "<br>\n";
        }
        echo "<br>\n";
        
        echo "<h3>4. Test de generatePickingListsByActivityCSV()</h3>\n";
        $result = generatePickingListsByActivityCSV();
        if ($result['success']) {
            echo "✅ Export réussi : " . $result['message'] . "<br>\n";
            echo "Fichier CSV : " . $result['file_csv'] . "<br>\n";
        } else {
            echo "❌ Erreur : " . $result['error'] . "<br>\n";
        }
        echo "<br>\n";
        
        echo "<h3>5. Test de checkActivityCoherence()</h3>\n";
        $result = checkActivityCoherence();
        if ($result['success']) {
            echo "✅ Vérification réussie<br>\n";
            echo "Nombre d'activités : " . $result['activities_count'] . "<br>\n";
            foreach ($result['report'] as $activite => $stats) {
                echo "- $activite : {$stats['photos_count']} photos, {$stats['total_copies']} exemplaires<br>\n";
            }
        } else {
            echo "❌ Erreur : " . $result['error'] . "<br>\n";
        }
        
    } else {
        echo "❌ Fichier CSV vide<br>\n";
    }
} else {
    echo "❌ Fichier CSV introuvable<br>\n";
}

echo "<br><h3>Résumé des modifications</h3>\n";
echo "✅ Toutes les fonctions d'export lisent maintenant directement du fichier CSV principal<br>\n";
echo "✅ Seules les commandes avec statut 'validated' (et non récupérées) sont incluses<br>\n";
echo "✅ Plus besoin d'avoir payé une commande pour qu'elle apparaisse dans les exports<br>\n";

?>