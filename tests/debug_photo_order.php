<?php
/**
 * Script de diagnostic pour analyser l'ordre des photos
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "<h2>Diagnostic de l'ordre des photos</h2>\n";

// 1. Vérifier le contenu d'un dossier photo brut
echo "<h3>1. Contenu brut d'un dossier (par exemple 'AMITIE')</h3>\n";
$testDir = PHOTOS_DIR . 'AMITIE';
if (is_dir($testDir)) {
    $rawFiles = scandir($testDir);
    echo "<h4>Fichiers dans l'ordre de scandir() :</h4>\n";
    foreach ($rawFiles as $i => $file) {
        if (!in_array($file, ['.', '..'])) {
            echo "$i: $file<br>\n";
        }
    }

    // Tester le tri PHP
    $photoFiles = [];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($imageExtensions as $ext) {
        $files = glob($testDir . '/*.' . $ext);
        $files = array_merge($files, glob($testDir . '/*.' . strtoupper($ext)));

        foreach ($files as $file) {
            $photoFiles[] = basename($file);
        }
    }

    echo "<h4>Avant sort() :</h4>\n";
    foreach ($photoFiles as $i => $file) {
        echo "$i: $file<br>\n";
    }

    sort($photoFiles);

    echo "<h4>Après sort() :</h4>\n";
    foreach ($photoFiles as $i => $file) {
        echo "$i: $file<br>\n";
    }
} else {
    echo "Dossier AMITIE non trouvé<br>\n";
}

// 2. Vérifier le contenu d'activities.json
echo "<h3>2. Contenu d'activities.json</h3>\n";
$activitiesFile = DATA_DIR . 'activities.json';
if (file_exists($activitiesFile)) {
    $activities = json_decode(file_get_contents($activitiesFile), true);

    if (isset($activities['AMITIE'])) {
        echo "<h4>Photos dans activities.json pour AMITIE :</h4>\n";
        foreach ($activities['AMITIE']['photos'] as $i => $photo) {
            echo "$i: $photo<br>\n";
        }
    } else {
        echo "Activité AMITIE non trouvée dans activities.json<br>\n";
    }
} else {
    echo "Fichier activities.json non trouvé<br>\n";
}

// 3. Tester la fonction scanPhotosDirectories() directement
echo "<h3>3. Test de scanPhotosDirectories()</h3>\n";
$scannedActivities = scanPhotosDirectories();

if (isset($scannedActivities['AMITIE'])) {
    echo "<h4>Résultat de scanPhotosDirectories() pour AMITIE :</h4>\n";
    foreach ($scannedActivities['AMITIE']['photos'] as $i => $photo) {
        echo "$i: $photo<br>\n";
    }
} else {
    echo "AMITIE non trouvée dans les résultats de scan<br>\n";
}

// 4. Comparer différents types de tri
echo "<h3>4. Comparaison des méthodes de tri</h3>\n";
if (is_dir($testDir)) {
    $testFiles = [];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($imageExtensions as $ext) {
        $files = glob($testDir . '/*.' . $ext);
        $files = array_merge($files, glob($testDir . '/*.' . strtoupper($ext)));

        foreach ($files as $file) {
            $testFiles[] = basename($file);
        }
    }

    echo "<h4>Tri standard sort() :</h4>\n";
    $sorted1 = $testFiles;
    sort($sorted1);
    foreach (array_slice($sorted1, 0, 10) as $i => $file) {
        echo "$i: $file<br>\n";
    }

    echo "<h4>Tri naturel natsort() :</h4>\n";
    $sorted2 = $testFiles;
    natsort($sorted2);
    $sorted2 = array_values($sorted2);
    foreach (array_slice($sorted2, 0, 10) as $i => $file) {
        echo "$i: $file<br>\n";
    }

    echo "<h4>Tri insensible à la casse natcasesort() :</h4>\n";
    $sorted3 = $testFiles;
    natcasesort($sorted3);
    $sorted3 = array_values($sorted3);
    foreach (array_slice($sorted3, 0, 10) as $i => $file) {
        echo "$i: $file<br>\n";
    }
}

// 5. Vérifier si le fichier activities.json est à jour
echo "<h3>5. Informations sur activities.json</h3>\n";
if (file_exists($activitiesFile)) {
    $fileTime = filemtime($activitiesFile);
    echo "Dernière modification : " . date('Y-m-d H:i:s', $fileTime) . "<br>\n";
    echo "Il y a " . round((time() - $fileTime) / 60) . " minutes<br>\n";
} else {
    echo "Fichier inexistant<br>\n";
}

// 6. Test avec loadActivitiesData()
echo "<h3>6. Test de loadActivitiesData()</h3>\n";
$loadedActivities = loadActivitiesData();
if (isset($loadedActivities['AMITIE'])) {
    echo "<h4>loadActivitiesData() pour AMITIE (premiers 10) :</h4>\n";
    foreach (array_slice($loadedActivities['AMITIE']['photos'], 0, 10) as $i => $photo) {
        echo "$i: $photo<br>\n";
    }
} else {
    echo "AMITIE non trouvée dans loadActivitiesData()<br>\n";
}

?>