<?php
define('GALLERY_ACCESS', true);

// Mock de la classe OrdersList pour tester notre logique
class MockOrdersList {
    public function loadOrdersData($filter = null) {
        $csvFile = 'commandes/commandes.csv';
        $orders = [];

        if (!file_exists($csvFile)) {
            return ['orders' => []];
        }

        $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
        $header = array_shift($lines);

        foreach ($lines as $line) {
            if (empty(trim($line))) continue;

            $data = str_getcsv($line, ';');
            if (count($data) < 16) continue;

            $paymentMode = $data[10] ?? '';
            $commandStatus = $data[15] ?? '';

            // Appliquer le filtre
            $include = false;
            switch ($filter) {
                case 'unpaid':
                    $include = ($paymentMode === 'unpaid' || (in_array($commandStatus, ['temp', 'validated']) && $paymentMode !== 'paid'));
                    break;
                case 'paid':
                    $include = ($commandStatus === 'paid');
                    break;
                case 'all':
                default:
                    $include = true;
                    break;
            }

            if ($include) {
                $orders[] = [
                    'reference' => $data[0],
                    'firstname' => $data[2],
                    'lastname' => $data[1],
                    'created_at' => $data[5],
                    'amount' => $data[9],
                    'total_photos' => $data[8]
                ];
            }
        }

        return ['orders' => $orders];
    }

    public function calculateStats($orders) {
        return [
            'total' => count($orders),
            'total_photos' => array_sum(array_column($orders, 'total_photos')),
            'total_amount' => array_sum(array_column($orders, 'amount')),
            'paid_today' => 0
        ];
    }
}

echo "=== SIMULATION DES ONGLETS ===\n\n";

$ordersList = new MockOrdersList();

// Calculer les statistiques pour les onglets
$unpaidData = $ordersList->loadOrdersData('unpaid');
$paidData = $ordersList->loadOrdersData('paid');
$allData = $ordersList->loadOrdersData('all');

$unpaidStats = $ordersList->calculateStats($unpaidData['orders']);
$paidStats = $ordersList->calculateStats($paidData['orders']);

$tabStats = [
    'unpaid' => [
        'count' => count($unpaidData['orders']),
        'amount' => $unpaidStats['total_amount']
    ],
    'paid' => [
        'count' => count($paidData['orders']),
        'amount' => $paidStats['total_amount']
    ],
    'all' => [
        'count' => count($allData['orders']),
        'amount' => $ordersList->calculateStats($allData['orders'])['total_amount']
    ]
];

echo "Badge onglet 'En attente de règlement': " . $tabStats['unpaid']['count'] . "\n";
echo "Badge onglet 'Réglées': " . $tabStats['paid']['count'] . "\n";
echo "Total toutes commandes: " . $tabStats['all']['count'] . "\n\n";

echo "Montant unpaid: " . number_format($tabStats['unpaid']['amount'], 2) . "€\n";
echo "Montant paid: " . number_format($tabStats['paid']['amount'], 2) . "€\n";

echo "\n=== RÉSULTATS ATTENDUS ===\n";
echo "L'onglet 'En attente de règlement' devrait afficher: " . $tabStats['unpaid']['count'] . "\n";
echo "L'onglet 'Réglées' devrait afficher: " . $tabStats['paid']['count'] . "\n";
?>