<?php
echo "=== TEST DIRECT CSV ===\n\n";

$csvFile = 'commandes/commandes.csv';

if (!file_exists($csvFile)) {
    echo "Erreur: fichier CSV non trouvé\n";
    exit;
}

$lines = file($csvFile, FILE_IGNORE_NEW_LINES);
$header = array_shift($lines);

echo "En-tête CSV: $header\n\n";

$unpaidCount = 0;
$paidCount = 0;
$totalCount = 0;

foreach ($lines as $line) {
    if (empty(trim($line))) continue;

    $data = str_getcsv($line, ';');
    $totalCount++;

    $paymentMode = $data[10] ?? '';
    $commandStatus = $data[15] ?? '';

    // Test de notre nouvelle logique
    $isUnpaid = ($paymentMode === 'unpaid' || (in_array($commandStatus, ['temp', 'validated']) && $paymentMode !== 'paid'));
    $isPaid = ($commandStatus === 'paid');

    if ($isUnpaid) {
        $unpaidCount++;
        if ($unpaidCount <= 3) {
            echo "Unpaid #{$unpaidCount}: {$data[0]} - Mode: '$paymentMode', Statut: '$commandStatus'\n";
        }
    }

    if ($isPaid) {
        $paidCount++;
        if ($paidCount <= 3) {
            echo "Paid #{$paidCount}: {$data[0]} - Mode: '$paymentMode', Statut: '$commandStatus'\n";
        }
    }
}

echo "\n=== RÉSULTATS ===\n";
echo "Total lignes: $totalCount\n";
echo "Commandes unpaid: $unpaidCount\n";
echo "Commandes paid: $paidCount\n";
echo "Autres: " . ($totalCount - $unpaidCount - $paidCount) . "\n";
?>