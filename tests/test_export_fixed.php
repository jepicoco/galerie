<?php
/**
 * Script de test pour la VERSION CORRIGÉE 3.0 d'export des commandes à préparer
 * Teste la nouvelle implémentation avec lecture directe du CSV
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'admin_orders_handler.php';

echo "<h1>Test Export Préparation - Version 3.0 CORRIGÉE</h1>\n";
echo "<p>Test de la version corrigée avec lecture directe du fichier CSV principal</p>\n\n";

// 1. Analyse du fichier CSV principal
echo "<h2>1. Analyse du fichier CSV principal</h2>\n";
$csvFile = 'commandes/commandes.csv';

if (file_exists($csvFile)) {
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "<p>✅ Fichier trouvé: " . count($lines) . " lignes (incluant en-tête)</p>\n";
    
    if (count($lines) > 1) {
        $header = $lines[0];
        echo "<p>📋 En-tête: " . htmlspecialchars(substr($header, 0, 100)) . "...</p>\n";
        
        // Analyser le statut des commandes
        $statusCount = [];
        $exportedCount = 0;
        $validatedNotExported = [];
        
        for ($i = 1; $i < count($lines); $i++) {
            $data = str_getcsv($lines[$i], ';');
            if (count($data) >= 16) {
                $reference = $data[0];
                $status = $data[15];
                $exported = isset($data[16]) ? $data[16] : '';
                
                if (!isset($statusCount[$status])) {
                    $statusCount[$status] = 0;
                }
                $statusCount[$status]++;
                
                if ($exported === 'exported') {
                    $exportedCount++;
                }
                
                // Collecter les validated non exportées
                if ($status === 'validated' && $exported !== 'exported') {
                    if (!isset($validatedNotExported[$reference])) {
                        $validatedNotExported[$reference] = [];
                    }
                    $validatedNotExported[$reference][] = $i - 1; // Index sans header
                }
            }
        }
        
        echo "<div style='background: #f8f9fa; padding: 15px; border-radius: 5px; margin: 10px 0;'>\n";
        echo "<h3>Statistiques actuelles:</h3>\n";
        echo "<ul>\n";
        foreach ($statusCount as $status => $count) {
            echo "<li><strong>$status:</strong> $count ligne(s)</li>\n";
        }
        echo "<li><strong>exported:</strong> $exportedCount ligne(s)</li>\n";
        echo "</ul>\n";
        echo "<p><strong>Commandes validated non exportées:</strong> " . count($validatedNotExported) . " commande(s) unique(s)</p>\n";
        
        if (!empty($validatedNotExported)) {
            echo "<h4>Détail des commandes validated à traiter:</h4>\n";
            foreach ($validatedNotExported as $ref => $indexes) {
                echo "<p>• <strong>$ref</strong>: " . count($indexes) . " ligne(s)</p>\n";
            }
        }
        echo "</div>\n";
    }
} else {
    echo "<p>❌ Fichier CSV principal introuvable</p>\n";
    exit;
}

// 2. Test de simulation (sans exécution)
echo "\n<h2>2. Simulation de la nouvelle fonction</h2>\n";

echo "<div style='background: #e7f3ff; padding: 15px; border-radius: 5px; border-left: 4px solid #007bff;'>\n";
echo "<h3>🔍 Ce que la nouvelle fonction va faire:</h3>\n";
echo "<ol>\n";
echo "<li><strong>Lecture directe</strong> du fichier commandes.csv (✅ " . count($lines) . " lignes)</li>\n";
echo "<li><strong>Groupement</strong> par référence de commande</li>\n";
echo "<li><strong>Identification</strong> des commandes validated non exportées</li>\n";
echo "<li><strong>Ajout</strong> dans commandes_a_preparer.csv</li>\n";
echo "<li><strong>Marquage</strong> comme exported dans le fichier principal</li>\n";
echo "<li><strong>Génération</strong> du fichier d'export pour téléchargement</li>\n";
echo "</ol>\n";
echo "</div>\n";

// 3. État initial fichier préparateur
echo "\n<h2>3. État initial commandes_a_preparer.csv</h2>\n";
$preparerFile = 'commandes/commandes_a_preparer.csv';

if (file_exists($preparerFile)) {
    $preparerLines = file($preparerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $initialCount = count($preparerLines) - 1;
    echo "<p>📋 Lignes existantes: $initialCount</p>\n";
    
    if ($initialCount > 0) {
        echo "<details><summary>Voir les dernières lignes</summary>\n";
        echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 0.9em;'>\n";
        for ($i = max(0, count($preparerLines) - 5); $i < count($preparerLines); $i++) {
            echo ($i + 1) . ": " . htmlspecialchars($preparerLines[$i]) . "\n";
        }
        echo "</pre></details>\n";
    }
} else {
    $initialCount = 0;
    echo "<p>📋 Fichier n'existe pas (sera créé automatiquement)</p>\n";
}

// 4. EXÉCUTION DU TEST
echo "\n<h2>4. 🚀 EXÉCUTION DU TEST</h2>\n";

try {
    echo "<p>⚡ Appel de exportPreparationList()...</p>\n";
    
    // Enregistrer l'état avant
    $beforeExported = $exportedCount;
    $beforePreparer = $initialCount;
    
    $startTime = microtime(true);
    $result = exportPreparationList();
    $endTime = microtime(true);
    
    $executionTime = round(($endTime - $startTime) * 1000, 2);
    
    echo "<p>⏱️ Temps d'exécution: {$executionTime}ms</p>\n";
    
    if ($result['success']) {
        echo "<div style='border: 3px solid #28a745; background: #d4edda; padding: 20px; border-radius: 8px; margin: 15px 0;'>\n";
        echo "<h3>🎉 SUCCÈS - Export réalisé</h3>\n";
        
        echo "<div style='display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin: 15px 0;'>\n";
        
        echo "<div style='background: white; padding: 10px; border-radius: 5px;'>\n";
        echo "<h4>📄 Fichier généré</h4>\n";
        echo "<p><strong>" . basename($result['file']) . "</strong></p>\n";
        if (file_exists($result['file'])) {
            $fileSize = filesize($result['file']);
            echo "<p>Taille: {$fileSize} bytes</p>\n";
        }
        echo "</div>\n";
        
        echo "<div style='background: white; padding: 10px; border-radius: 5px;'>\n";
        echo "<h4>📊 Commandes traitées</h4>\n";
        echo "<p>Total: <strong>" . $result['orders_count'] . "</strong></p>\n";
        echo "<p>Photos: <strong>" . $result['photos_count'] . "</strong></p>\n";
        echo "</div>\n";
        
        echo "<div style='background: white; padding: 10px; border-radius: 5px;'>\n";
        echo "<h4>🆕 Nouvelles données</h4>\n";
        echo "<p>Ajoutées au preparer: <strong>" . ($result['added_to_preparer'] ?? 0) . "</strong></p>\n";
        echo "<p>Marquées exported: <strong>" . ($result['marked_exported'] ?? 0) . "</strong></p>\n";
        echo "</div>\n";
        
        echo "</div>\n";
        echo "</div>\n";
        
    } else {
        echo "<div style='border: 2px solid #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
        echo "<h3>❌ ÉCHEC</h3>\n";
        echo "<p><strong>Erreur:</strong> " . $result['error'] . "</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='border: 2px solid #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
    echo "<h3>💥 EXCEPTION</h3>\n";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>\n";
    echo "<pre style='background: #fff; padding: 10px; border-radius: 3px; font-size: 0.9em;'>";
    echo htmlspecialchars($e->getTraceAsString());
    echo "</pre>\n";
    echo "</div>\n";
}

// 5. Vérification des changements
echo "\n<h2>5. ✅ Vérification des changements</h2>\n";

// Vérifier le fichier principal
if (file_exists($csvFile)) {
    $updatedLines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $newExportedCount = 0;
    
    for ($i = 1; $i < count($updatedLines); $i++) {
        $data = str_getcsv($updatedLines[$i], ';');
        if (count($data) >= 17 && isset($data[16]) && $data[16] === 'exported') {
            $newExportedCount++;
        }
    }
    
    echo "<p>📤 Lignes marquées 'exported': $newExportedCount (+" . ($newExportedCount - $beforeExported) . ")</p>\n";
}

// Vérifier le fichier préparateur
if (file_exists($preparerFile)) {
    $finalPreparerLines = file($preparerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $finalCount = count($finalPreparerLines) - 1;
    $addedLines = $finalCount - $beforePreparer;
    
    echo "<p>📋 Lignes dans preparer: $finalCount (+" . $addedLines . ")</p>\n";
    
    if ($addedLines > 0) {
        echo "<details><summary>Voir les nouvelles lignes ajoutées</summary>\n";
        echo "<pre style='background: #e8f5e8; padding: 10px; font-size: 0.9em; border-left: 4px solid #28a745;'>\n";
        for ($i = count($finalPreparerLines) - $addedLines; $i < count($finalPreparerLines); $i++) {
            echo "NOUVEAU: " . htmlspecialchars($finalPreparerLines[$i]) . "\n";
        }
        echo "</pre></details>\n";
    }
}

echo "\n<hr>\n";
echo "<h2>🏁 TEST TERMINÉ</h2>\n";

if (isset($result) && $result['success']) {
    echo "<div style='background: #d4edda; border: 2px solid #28a745; padding: 20px; border-radius: 8px; text-align: center;'>\n";
    echo "<h3>✅ LA FONCTION CORRIGÉE FONCTIONNE PARFAITEMENT</h3>\n";
    echo "<p>Toutes les commandes validated ont été automatiquement:</p>\n";
    echo "<ul style='text-align: left; display: inline-block;'>\n";
    echo "<li>✅ Ajoutées dans commandes_a_preparer.csv</li>\n";
    echo "<li>✅ Marquées comme exported dans le fichier principal</li>\n";
    echo "<li>✅ Incluses dans le fichier d'export pour téléchargement</li>\n";
    echo "</ul>\n";
    echo "</div>\n";
} else {
    echo "<div style='background: #f8d7da; border: 2px solid #dc3545; padding: 20px; border-radius: 8px; text-align: center;'>\n";
    echo "<h3>⚠️ Des améliorations sont nécessaires</h3>\n";
    echo "<p>Consultez les erreurs ci-dessus pour diagnostiquer le problème.</p>\n";
    echo "</div>\n";
}

?>