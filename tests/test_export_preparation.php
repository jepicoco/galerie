<?php
/**
 * Script de test pour le nouveau processus d'export des commandes à préparer
 * Teste l'ajout automatique des commandes 'validated' et le marquage 'exported'
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'admin_orders_handler.php';

echo "<h1>Test Export Commandes à Préparer - Version 2.0</h1>\n";
echo "<p>Test du nouveau processus incluant l'ajout automatique des commandes validated</p>\n\n";

// Vérifier les fichiers requis
$requiredFiles = [
    'commandes/commandes.csv',
    'commandes/commandes_a_preparer.csv'
];

echo "<h2>1. Vérification des fichiers</h2>\n";
foreach ($requiredFiles as $file) {
    $exists = file_exists($file);
    $size = $exists ? filesize($file) : 0;
    $status = $exists ? "✅ Existe ($size bytes)" : "❌ Manquant";
    echo "<p><strong>" . basename($file) . ":</strong> $status</p>\n";
}

// Analyser les commandes dans le fichier principal
echo "\n<h2>2. Analyse du fichier principal</h2>\n";
$csvFile = 'commandes/commandes.csv';

if (file_exists($csvFile)) {
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    if (count($lines) > 1) {
        $header = array_shift($lines);
        
        $statusCounts = [
            'validated' => 0,
            'paid' => 0,
            'retrieved' => 0,
            'exported' => 0,
            'other' => 0
        ];
        
        $commandesByReference = [];
        
        foreach ($lines as $line) {
            $data = str_getcsv($line, ';');
            if (count($data) >= 16) {
                $reference = $data[0];
                $status = $data[15]; // Statut commande
                $exported = isset($data[16]) ? $data[16] : '';
                
                // Compter par statut
                if (isset($statusCounts[$status])) {
                    $statusCounts[$status]++;
                } else {
                    $statusCounts['other']++;
                }
                
                // Compter les exportés
                if ($exported === 'exported') {
                    $statusCounts['exported']++;
                }
                
                // Grouper par référence
                if (!isset($commandesByReference[$reference])) {
                    $commandesByReference[$reference] = [
                        'status' => $status,
                        'exported' => $exported,
                        'photos' => 0
                    ];
                }
                $commandesByReference[$reference]['photos']++;
            }
        }
        
        echo "<div style='background: #f5f5f5; padding: 10px; border-radius: 5px;'>\n";
        echo "<h3>Statistiques des commandes :</h3>\n";
        echo "<ul>\n";
        foreach ($statusCounts as $status => $count) {
            echo "<li><strong>" . ucfirst($status) . ":</strong> $count ligne(s)</li>\n";
        }
        echo "</ul>\n";
        echo "<p><strong>Commandes uniques:</strong> " . count($commandesByReference) . "</p>\n";
        
        // Commandes validated non exportées
        $validatedNotExported = array_filter($commandesByReference, function($cmd) {
            return $cmd['status'] === 'validated' && $cmd['exported'] !== 'exported';
        });
        
        echo "<p><strong>Commandes validated non exportées:</strong> " . count($validatedNotExported) . "</p>\n";
        echo "</div>\n";
        
    } else {
        echo "<p>⚠️ Fichier principal vide ou sans données</p>\n";
    }
} else {
    echo "<p>❌ Fichier principal inexistant</p>\n";
}

// État initial du fichier à préparer
echo "\n<h2>3. État initial commandes_a_preparer.csv</h2>\n";
$preparerFile = 'commandes/commandes_a_preparer.csv';

if (file_exists($preparerFile)) {
    $preparerLines = file($preparerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $initialCount = count($preparerLines) - 1; // -1 pour enlever l'en-tête
    echo "<p>📋 Lignes existantes: $initialCount</p>\n";
    
    // Afficher les dernières lignes
    if ($initialCount > 0) {
        echo "<h4>Dernières lignes:</h4>\n";
        echo "<pre style='background: #f8f9fa; padding: 10px; font-size: 0.9em;'>\n";
        for ($i = max(0, count($preparerLines) - 3); $i < count($preparerLines); $i++) {
            echo htmlspecialchars($preparerLines[$i]) . "\n";
        }
        echo "</pre>\n";
    }
} else {
    $initialCount = 0;
    echo "<p>📋 Fichier n'existe pas (sera créé)</p>\n";
}

// Test de la fonction exportPreparationList
echo "\n<h2>4. Test exportPreparationList()</h2>\n";

try {
    echo "<p>🔄 Exécution de exportPreparationList()...</p>\n";
    
    $result = exportPreparationList();
    
    if ($result['success']) {
        echo "<div style='border: 2px solid #28a745; background: #d4edda; padding: 15px; border-radius: 5px;'>\n";
        echo "<h3>✅ Export réussi</h3>\n";
        echo "<ul>\n";
        echo "<li><strong>Fichier généré:</strong> " . basename($result['file']) . "</li>\n";
        echo "<li><strong>Commandes traitées:</strong> " . $result['orders_count'] . "</li>\n";
        echo "<li><strong>Photos total:</strong> " . $result['photos_count'] . "</li>\n";
        
        if (isset($result['added_to_preparer'])) {
            echo "<li><strong>🆕 Ajoutées au preparer:</strong> " . $result['added_to_preparer'] . "</li>\n";
        }
        
        if (isset($result['marked_exported'])) {
            echo "<li><strong>📤 Marquées exported:</strong> " . $result['marked_exported'] . "</li>\n";
        }
        
        echo "</ul>\n";
        
        // Vérifier si le fichier a été créé
        if (file_exists($result['file'])) {
            $fileSize = filesize($result['file']);
            echo "<p>📄 Fichier d'export: {$fileSize} bytes</p>\n";
        }
        
        echo "</div>\n";
        
    } else {
        echo "<div style='border: 2px solid #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
        echo "<h3>❌ Export échoué</h3>\n";
        echo "<p><strong>Erreur:</strong> " . $result['error'] . "</p>\n";
        echo "</div>\n";
    }
    
} catch (Exception $e) {
    echo "<div style='border: 2px solid #dc3545; background: #f8d7da; padding: 15px; border-radius: 5px;'>\n";
    echo "<h3>❌ Exception</h3>\n";
    echo "<p><strong>Message:</strong> " . $e->getMessage() . "</p>\n";
    echo "<p><strong>Fichier:</strong> " . $e->getFile() . ":" . $e->getLine() . "</p>\n";
    echo "</div>\n";
}

// Vérifier l'état final
echo "\n<h2>5. État final commandes_a_preparer.csv</h2>\n";

if (file_exists($preparerFile)) {
    $finalLines = file($preparerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    $finalCount = count($finalLines) - 1; // -1 pour enlever l'en-tête
    $addedLines = $finalCount - $initialCount;
    
    echo "<p>📋 Lignes finales: $finalCount (+" . $addedLines . " ajoutées)</p>\n";
    
    if ($addedLines > 0) {
        echo "<h4>Nouvelles lignes ajoutées:</h4>\n";
        echo "<pre style='background: #e7f3ff; padding: 10px; font-size: 0.9em; border-left: 4px solid #007bff;'>\n";
        for ($i = count($finalLines) - $addedLines; $i < count($finalLines); $i++) {
            echo htmlspecialchars($finalLines[$i]) . "\n";
        }
        echo "</pre>\n";
    }
} else {
    echo "<p>❌ Fichier toujours inexistant</p>\n";
}

// Vérifier les changements dans le fichier principal
echo "\n<h2>6. Vérification du marquage 'exported'</h2>\n";

if (file_exists($csvFile)) {
    $updatedLines = file($csvFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    array_shift($updatedLines); // Enlever l'en-tête
    
    $newExportedCount = 0;
    foreach ($updatedLines as $line) {
        $data = str_getcsv($line, ';');
        if (count($data) >= 17 && isset($data[16]) && $data[16] === 'exported') {
            $newExportedCount++;
        }
    }
    
    echo "<p>📤 Commandes marquées 'exported': $newExportedCount</p>\n";
    
    if ($newExportedCount > $statusCounts['exported']) {
        $newlyMarked = $newExportedCount - $statusCounts['exported'];
        echo "<p>✅ $newlyMarked nouvelle(s) commande(s) marquée(s) comme exportées</p>\n";
    }
}

echo "\n<hr>\n";
echo "<h2>✅ Test terminé</h2>\n";
echo "<p>Le nouveau processus d'export inclut maintenant automatiquement les commandes 'validated' et les marque comme 'exported'.</p>\n";

?>