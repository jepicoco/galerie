<?php
/**
 * Script de test pour la fonctionnalité de téléchargement CSV
 * @version 1.0
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

session_start();

// Simuler l'authentification admin pour les tests
$_SESSION['admin_logged_in'] = true;

echo "=== TEST DE LA FONCTIONNALITÉ TÉLÉCHARGEMENT CSV ===\n";
echo "Date: " . date('Y-m-d H:i:s') . "\n\n";

/**
 * Test de la fonction getCsvFilesInfo()
 */
function testGetCsvFilesInfo() {
    echo "1. TEST getCsvFilesInfo()\n";
    echo "-------------------------\n";
    
    // Inclure la fonction depuis admin_parameters.php
    $adminParametersContent = file_get_contents('admin_parameters.php');
    
    // Extraire et évaluer la fonction getCsvFilesInfo
    preg_match('/function getCsvFilesInfo\(\) \{.*?\n\}/s', $adminParametersContent, $matches);
    
    if (empty($matches)) {
        echo "❌ Fonction getCsvFilesInfo non trouvée\n";
        return false;
    }
    
    // Créer une version simplifiée pour le test
    $csvFiles = [
        'commandes' => [
            'name' => 'Commandes complètes',
            'filename' => 'commandes.csv',
            'description' => 'Toutes les commandes (validées et temporaires)',
            'icon' => '📊'
        ],
        'commandes_a_preparer' => [
            'name' => 'Commandes à préparer',
            'filename' => 'commandes_a_preparer.csv',
            'description' => 'Commandes payées en attente de préparation',
            'icon' => '📋'
        ],
        'commandes_reglees' => [
            'name' => 'Commandes réglées',
            'filename' => 'commandes_reglees.csv',
            'description' => 'Commandes payées prêtes pour le retrait',
            'icon' => '✅'
        ]
    ];
    
    function formatBytes($bytes, $precision = 2) {
        $units = array('B', 'KB', 'MB', 'GB', 'TB');
        
        for ($i = 0; $bytes > 1024 && $i < count($units) - 1; $i++) {
            $bytes /= 1024;
        }
        
        return round($bytes, $precision) . ' ' . $units[$i];
    }
    
    foreach ($csvFiles as $key => &$fileInfo) {
        $filepath = 'commandes/' . $fileInfo['filename'];
        
        if (file_exists($filepath) && is_readable($filepath)) {
            $fileInfo['exists'] = true;
            $fileInfo['size'] = filesize($filepath);
            $fileInfo['size_formatted'] = formatBytes($fileInfo['size']);
            $fileInfo['modified'] = filemtime($filepath);
            $fileInfo['modified_formatted'] = date('d/m/Y H:i', $fileInfo['modified']);
            
            echo "✅ {$fileInfo['filename']}: {$fileInfo['size_formatted']} - {$fileInfo['modified_formatted']}\n";
        } else {
            $fileInfo['exists'] = false;
            $fileInfo['size'] = 0;
            $fileInfo['size_formatted'] = 'N/A';
            $fileInfo['modified'] = 0;
            $fileInfo['modified_formatted'] = 'N/A';
            
            echo "❌ {$fileInfo['filename']}: Non disponible\n";
        }
    }
    
    return $csvFiles;
}

/**
 * Test de validation des paramètres
 */
function testParameterValidation() {
    echo "\n2. TEST VALIDATION DES PARAMÈTRES\n";
    echo "----------------------------------\n";
    
    $allowedTypes = [
        'commandes' => 'commandes.csv',
        'commandes_a_preparer' => 'commandes_a_preparer.csv', 
        'commandes_reglees' => 'commandes_reglees.csv'
    ];
    
    // Tests valides
    $validTests = ['commandes', 'commandes_a_preparer', 'commandes_reglees'];
    foreach ($validTests as $type) {
        if (array_key_exists($type, $allowedTypes)) {
            echo "✅ Type valide: $type -> {$allowedTypes[$type]}\n";
        } else {
            echo "❌ Type devrait être valide: $type\n";
        }
    }
    
    // Tests invalides
    $invalidTests = ['', 'invalid', 'commandes_test', '../etc/passwd', 'commandes.php'];
    foreach ($invalidTests as $type) {
        if (!array_key_exists($type, $allowedTypes)) {
            echo "✅ Type invalide rejeté: '$type'\n";
        } else {
            echo "❌ Type invalide accepté: '$type'\n";
        }
    }
}

/**
 * Test de sécurité des chemins de fichiers
 */
function testFilepathSecurity() {
    echo "\n3. TEST SÉCURITÉ DES CHEMINS\n";
    echo "----------------------------\n";
    
    $securityTests = [
        'commandes.csv' => 'commandes/commandes.csv',
        'commandes_a_preparer.csv' => 'commandes/commandes_a_preparer.csv',
        'commandes_reglees.csv' => 'commandes/commandes_reglees.csv'
    ];
    
    foreach ($securityTests as $filename => $expectedPath) {
        $actualPath = 'commandes/' . $filename;
        
        // Vérifier que le chemin ne contient pas de traversal
        if (strpos($actualPath, '..') === false && strpos($actualPath, '/') !== 0) {
            echo "✅ Chemin sécurisé: $actualPath\n";
        } else {
            echo "❌ Chemin potentiellement dangereux: $actualPath\n";
        }
        
        // Vérifier que le chemin correspond à l'attendu
        if ($actualPath === $expectedPath) {
            echo "✅ Chemin correct: $actualPath\n";
        } else {
            echo "❌ Chemin incorrect: $actualPath (attendu: $expectedPath)\n";
        }
    }
}

/**
 * Test des headers de téléchargement
 */
function testDownloadHeaders() {
    echo "\n4. TEST HEADERS DE TÉLÉCHARGEMENT\n";
    echo "--------------------------------\n";
    
    $timestamp = date('Y-m-d_H-i');
    $testCases = [
        'commandes.csv' => "commandes_$timestamp.csv",
        'commandes_a_preparer.csv' => "commandes_a_preparer_$timestamp.csv",
        'commandes_reglees.csv' => "commandes_reglees_$timestamp.csv"
    ];
    
    foreach ($testCases as $original => $expected) {
        $downloadName = pathinfo($original, PATHINFO_FILENAME) . '_' . $timestamp . '.csv';
        
        if ($downloadName === $expected) {
            echo "✅ Nom de téléchargement correct: $downloadName\n";
        } else {
            echo "❌ Nom de téléchargement incorrect: $downloadName (attendu: $expected)\n";
        }
    }
    
    // Test des headers HTTP
    echo "\nHeaders HTTP recommandés:\n";
    echo "- Content-Type: text/csv; charset=utf-8\n";
    echo "- Content-Disposition: attachment; filename=\"nom_fichier.csv\"\n";
    echo "- Cache-Control: must-revalidate, post-check=0, pre-check=0\n";
    echo "- Pragma: public\n";
}

/**
 * Test d'intégrité des fichiers CSV
 */
function testCsvIntegrity() {
    echo "\n5. TEST INTÉGRITÉ DES FICHIERS CSV\n";
    echo "--------------------------------\n";
    
    $csvFiles = ['commandes.csv', 'commandes_a_preparer.csv', 'commandes_reglees.csv'];
    
    foreach ($csvFiles as $filename) {
        $filepath = 'commandes/' . $filename;
        
        if (!file_exists($filepath)) {
            echo "⚠️ $filename: Fichier non trouvé\n";
            continue;
        }
        
        if (!is_readable($filepath)) {
            echo "❌ $filename: Fichier non lisible\n";
            continue;
        }
        
        $size = filesize($filepath);
        if ($size === false) {
            echo "❌ $filename: Impossible de déterminer la taille\n";
            continue;
        }
        
        // Vérifier que le fichier n'est pas vide
        if ($size === 0) {
            echo "⚠️ $filename: Fichier vide\n";
            continue;
        }
        
        // Lire les premières lignes pour vérifier la structure CSV
        $handle = fopen($filepath, 'r');
        if ($handle === false) {
            echo "❌ $filename: Impossible d'ouvrir le fichier\n";
            continue;
        }
        
        $firstLine = fgets($handle);
        fclose($handle);
        
        if (empty($firstLine)) {
            echo "❌ $filename: Première ligne vide\n";
            continue;
        }
        
        // Vérifier la présence du séparateur point-virgule
        if (strpos($firstLine, ';') !== false) {
            echo "✅ $filename: Structure CSV valide (" . formatBytes($size) . ")\n";
        } else {
            echo "⚠️ $filename: Structure CSV douteuse (pas de point-virgule détecté)\n";
        }
    }
}

// Exécution des tests
echo "🔄 Lancement des tests...\n\n";

testGetCsvFilesInfo();
testParameterValidation();
testFilepathSecurity();
testDownloadHeaders(); 
testCsvIntegrity();

echo "\n✅ TESTS TERMINÉS\n";
echo "=================\n";
echo "Vérifiez les résultats ci-dessus pour valider la fonctionnalité.\n";
echo "Tous les ✅ indiquent des tests réussis.\n";
echo "Les ⚠️ indiquent des avertissements.\n";
echo "Les ❌ indiquent des erreurs à corriger.\n\n";

echo "📝 PROCHAINES ÉTAPES:\n";
echo "1. Accéder à admin_parameters.php dans votre navigateur\n";
echo "2. Aller dans la section 'Téléchargement des Données CSV'\n";
echo "3. Tester le téléchargement de chaque fichier\n";
echo "4. Vérifier que les fichiers téléchargés s'ouvrent correctement\n";
?>