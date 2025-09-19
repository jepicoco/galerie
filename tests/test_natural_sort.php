<?php
/**
 * Test du tri naturel des noms de photos
 */

echo "<h2>Test du tri naturel des photos</h2>\n";

// Simuler des noms de photos typiques
$testPhotos = [
    'AMITIE (1).jpg',
    'AMITIE (10).jpg',
    'AMITIE (100).jpg',
    'AMITIE (11).jpg',
    'AMITIE (12).jpg',
    'AMITIE (2).jpg',
    'AMITIE (20).jpg',
    'AMITIE (21).jpg',
    'AMITIE (3).jpg',
    'AMITIE (30).jpg',
    'AMITIE (4).jpg',
    'AMITIE (5).jpg',
    'AMITIE (9).jpg'
];

echo "<h3>Photos test dans l'ordre original :</h3>\n";
foreach ($testPhotos as $i => $photo) {
    echo "$i: $photo<br>\n";
}

echo "<h3>Avec sort() (tri alphabétique - PROBLÈME) :</h3>\n";
$sortedAlphabetic = $testPhotos;
sort($sortedAlphabetic);
foreach ($sortedAlphabetic as $i => $photo) {
    echo "$i: $photo<br>\n";
}

echo "<h3>Avec natsort() (tri naturel - SOLUTION) :</h3>\n";
$sortedNatural = $testPhotos;
natsort($sortedNatural);
$sortedNatural = array_values($sortedNatural); // Réindexer
foreach ($sortedNatural as $i => $photo) {
    echo "$i: $photo<br>\n";
}

echo "<h3>Comparaison côte à côte :</h3>\n";
echo "<table border='1' style='border-collapse: collapse;'>\n";
echo "<tr><th>Index</th><th>sort() - Alphabétique</th><th>natsort() - Naturel</th></tr>\n";
for ($i = 0; $i < count($testPhotos); $i++) {
    $alpha = $sortedAlphabetic[$i] ?? '';
    $natural = $sortedNatural[$i] ?? '';
    $highlight = ($alpha !== $natural) ? ' style="background-color: yellow;"' : '';
    echo "<tr$highlight><td>$i</td><td>$alpha</td><td>$natural</td></tr>\n";
}
echo "</table>\n";

// Test avec les vraies données si disponibles
define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

echo "<h3>Test avec les vraies données (AMITIE) :</h3>\n";
$realDir = PHOTOS_DIR . 'AMITIE';
if (is_dir($realDir)) {
    $realPhotos = [];
    $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];

    foreach ($imageExtensions as $ext) {
        $files = glob($realDir . '/*.' . $ext);
        $files = array_merge($files, glob($realDir . '/*.' . strtoupper($ext)));

        foreach ($files as $file) {
            $realPhotos[] = basename($file);
        }
    }

    if (!empty($realPhotos)) {
        echo "<h4>Avec sort() (premiers 15) :</h4>\n";
        $realSortedAlpha = $realPhotos;
        sort($realSortedAlpha);
        foreach (array_slice($realSortedAlpha, 0, 15) as $i => $photo) {
            echo "$i: $photo<br>\n";
        }

        echo "<h4>Avec natsort() (premiers 15) :</h4>\n";
        $realSortedNat = $realPhotos;
        natsort($realSortedNat);
        $realSortedNat = array_values($realSortedNat);
        foreach (array_slice($realSortedNat, 0, 15) as $i => $photo) {
            echo "$i: $photo<br>\n";
        }
    } else {
        echo "Aucune photo trouvée dans AMITIE<br>\n";
    }
} else {
    echo "Dossier AMITIE non trouvé<br>\n";
}

?>