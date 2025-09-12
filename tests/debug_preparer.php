<?php
/**
 * Debug du problème du fichier préparateur vide
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "=== DIAGNOSTIC FICHIER PRÉPARATEUR VIDE ===\n\n";

$csvFile = COMMANDES_DIR . 'commandes.csv';
$preparerFile = COMMANDES_DIR . 'commandes_a_preparer.csv';

echo "1. ANALYSE DU PROBLÈME\n";
echo "─────────────────────\n";

// Vérifier les fichiers
echo "Fichier CSV principal: " . (file_exists($csvFile) ? "✅ Existe" : "❌ Absent") . "\n";
echo "Fichier préparateur: " . (file_exists($preparerFile) ? "✅ Existe" : "❌ Absent") . "\n";

if (file_exists($preparerFile)) {
    $preparerContent = file_get_contents($preparerFile);
    $preparerLines = file($preparerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    echo "Taille fichier préparateur: " . strlen($preparerContent) . " octets\n";
    echo "Nombre de lignes: " . count($preparerLines) . "\n";
    
    if (count($preparerLines) > 0) {
        echo "En-tête: " . $preparerLines[0] . "\n";
        if (count($preparerLines) > 1) {
            echo "Données: " . (count($preparerLines) - 1) . " ligne(s)\n";
        } else {
            echo "❌ PROBLÈME: Aucune donnée, seulement l'en-tête\n";
        }
    }
}

echo "\n2. ANALYSE DES COMMANDES VALIDATED\n";
echo "─────────────────────────────────────\n";

// Chercher les commandes validated
$handle = fopen($csvFile, 'r');
$header = fgetcsv($handle, 0, ';');
$refIndex = array_search('REF', $header);
$statusIndex = array_search('Statut commande', $header);

$validatedCommands = [];
$allRefs = [];

while (($row = fgetcsv($handle, 0, ';')) !== false) {
    $ref = $row[$refIndex] ?? '';
    $status = $row[$statusIndex] ?? '';
    
    if (!empty($ref)) {
        if (!in_array($ref, $allRefs)) {
            $allRefs[] = $ref;
        }
        
        if ($status === 'validated') {
            if (!isset($validatedCommands[$ref])) {
                $validatedCommands[$ref] = 0;
            }
            $validatedCommands[$ref]++;
        }
    }
}
fclose($handle);

echo "Commandes avec statut 'validated':\n";
if (count($validatedCommands) > 0) {
    foreach ($validatedCommands as $ref => $photoCount) {
        echo "  - $ref: $photoCount photo(s)\n";
        
        // Vérifier si cette commande est dans le fichier préparateur
        $inPreparer = isOrderInPreparerFile($ref, $preparerFile);
        echo "    Dans fichier préparateur: " . ($inPreparer ? "✅ Oui" : "❌ Non") . "\n";
    }
} else {
    echo "❌ Aucune commande validated trouvée\n";
}

echo "\n3. TEST AJOUT MANUEL DE LA COMMANDE MANQUANTE\n";
echo "────────────────────────────────────────────────\n";

if (count($validatedCommands) > 0) {
    $testRef = array_keys($validatedCommands)[0];
    echo "Test d'ajout manuel de la commande: $testRef\n";
    
    // Tenter l'ajout
    echo "Appel de addOrderToPreparerFile('$testRef')...\n";
    $result = addOrderToPreparerFile($testRef);
    echo "Résultat: " . ($result ? "✅ SUCCÈS" : "❌ ÉCHEC") . "\n";
    
    // Vérifier le fichier après
    if (file_exists($preparerFile)) {
        $newContent = file_get_contents($preparerFile);
        $newLines = file($preparerFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
        
        echo "Après ajout:\n";
        echo "  Taille: " . strlen($newContent) . " octets\n";
        echo "  Lignes: " . count($newLines) . "\n";
        
        if (count($newLines) > 1) {
            echo "  ✅ Données ajoutées avec succès\n";
            echo "Nouvelles lignes:\n";
            for ($i = 1; $i < count($newLines); $i++) {
                echo "  " . ($i + 1) . ": " . $newLines[$i] . "\n";
            }
        } else {
            echo "  ❌ Toujours vide après ajout\n";
        }
    }
} else {
    echo "Aucune commande validated à ajouter\n";
}

echo "\n4. DIAGNOSTIC DÉTAILLÉ DE LA FONCTION\n";
echo "────────────────────────────────────────\n";

if (count($validatedCommands) > 0) {
    $testRef = array_keys($validatedCommands)[0];
    echo "Diagnostic détaillé pour: $testRef\n";
    
    // Simuler le processus step by step
    echo "\nÉtapes du processus addOrderToPreparerFile():\n";
    
    // 1. Vérifier l'existence du CSV principal
    echo "1. Fichier CSV principal: " . (file_exists($csvFile) ? "✅ Existe" : "❌ Absent") . "\n";
    
    // 2. Ouvrir et lire le CSV
    $handle = fopen($csvFile, 'r');
    if ($handle) {
        echo "2. Ouverture CSV: ✅ Réussie\n";
        
        $header = fgetcsv($handle, 0, ';');
        $refIndex = array_search('REF', $header);
        $statusIndex = array_search('Statut commande', $header);
        
        echo "3. Index colonnes - REF: $refIndex, Statut: $statusIndex\n";
        
        // 3. Chercher les lignes de la commande
        $orderLines = [];
        while (($row = fgetcsv($handle, 0, ';')) !== false) {
            if (($row[$refIndex] ?? '') === $testRef) {
                if (($row[$statusIndex] ?? '') === 'validated') {
                    $orderLines[] = $row;
                    echo "4. Ligne trouvée: " . implode(';', array_slice($row, 0, 5)) . "...\n";
                }
            }
        }
        fclose($handle);
        
        echo "5. Lignes de commande trouvées: " . count($orderLines) . "\n";
        
        if (count($orderLines) > 0) {
            // 4. Vérifier l'état du fichier préparateur
            $isNewFile = !file_exists($preparerFile);
            echo "6. Fichier préparateur nouveau: " . ($isNewFile ? "Oui" : "Non") . "\n";
            
            if (!$isNewFile) {
                $alreadyExists = isOrderInPreparerFile($testRef, $preparerFile);
                echo "7. Commande déjà présente: " . ($alreadyExists ? "Oui" : "Non") . "\n";
            }
            
            // 5. Construire les lignes à ajouter
            echo "8. Construction des lignes préparateur:\n";
            foreach ($orderLines as $i => $row) {
                $preparerLine = [
                    $row[0] ?? '',  // REF
                    $row[1] ?? '',  // Nom
                    $row[2] ?? '',  // Prenom
                    $row[3] ?? '',  // Email
                    $row[4] ?? '',  // Telephone
                    $row[6] ?? '',  // Dossier (activité)
                    $row[7] ?? '',  // N de la photo
                    $row[8] ?? '',  // Quantite
                    '',             // Date de preparation
                    ''              // Date de recuperation
                ];
                echo "   Ligne " . ($i + 1) . ": " . implode(';', $preparerLine) . "\n";
            }
        } else {
            echo "❌ Aucune ligne de commande trouvée avec statut 'validated'\n";
        }
    } else {
        echo "2. Ouverture CSV: ❌ Échec\n";
    }
}

echo "\n5. SOLUTION PROPOSÉE\n";
echo "───────────────────────\n";

echo "🔧 ACTIONS À RÉALISER:\n";
echo "1. Forcer l'ajout de toutes les commandes validated existantes\n";
echo "2. Vérifier que les hooks automatiques fonctionnent pour les futures commandes\n";
echo "3. Nettoyer et recréer le fichier préparateur si nécessaire\n";

echo "\nTest terminé: " . date('Y-m-d H:i:s') . "\n";
?>