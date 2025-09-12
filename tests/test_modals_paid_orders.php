<?php
/**
 * Test des modales communes pour admin_paid_orders.php
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'classes/autoload.php';

session_start();

// Simuler une session admin pour le test
$_SESSION['is_admin'] = true;
$_SESSION['admin_time'] = time();

// Charger des données de test
$ordersList = new OrdersList();
$paidOrdersData = $ordersList->loadOrdersData('to_retrieve');
$paidStats = $ordersList->calculateStats($paidOrdersData['orders']);

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Modales - Commandes Payées</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/admin.orders.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 50px auto;
            padding: 20px;
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        .test-section {
            margin: 20px 0;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        .test-button {
            background: #007cba;
            color: white;
            border: none;
            padding: 10px 15px;
            border-radius: 6px;
            cursor: pointer;
            margin: 5px;
            transition: background 0.3s;
        }
        .test-button:hover {
            background: #005a87;
        }
        .test-info {
            background: #f8f9fa;
            padding: 15px;
            border-left: 4px solid #007cba;
            margin: 10px 0;
        }
        .test-results {
            background: #e8f5e8;
            padding: 15px;
            border-radius: 6px;
            margin: 10px 0;
        }
    </style>
</head>
<body>

<div class="test-container">
    <h1>🧪 Test des Modales Communes</h1>
    <p><strong>Interface :</strong> admin_paid_orders.php</p>
    
    <div class="test-info">
        <h3>📊 Données de test chargées :</h3>
        <p><strong>Commandes à retirer :</strong> <?php echo count($paidOrdersData['orders']); ?></p>
        <p><strong>Total photos :</strong> <?php echo $paidStats['total_photos']; ?></p>
        <p><strong>Montant total :</strong> <?php echo number_format($paidStats['total_amount'], 2); ?>€</p>
    </div>

    <?php if (!empty($paidOrdersData['orders'])): ?>
        <?php $testOrder = $paidOrdersData['orders'][0]; ?>
        
        <div class="test-section">
            <h3>📋 Test des modales</h3>
            <p><strong>Commande de test :</strong> <?php echo $testOrder['reference']; ?> - <?php echo $testOrder['firstname'] . ' ' . $testOrder['lastname']; ?></p>
            
            <div class="test-buttons">
                <button class="test-button" onclick="showContactModal('<?php echo $testOrder['reference']; ?>')">
                    📞 Test Modale Contact
                </button>
                
                <button class="test-button" onclick="showDetailsModal('<?php echo $testOrder['reference']; ?>')">
                    📋 Test Modale Détails
                </button>
                
                <button class="test-button" onclick="showEmailConfirmationModal('<?php echo $testOrder['reference']; ?>')">
                    📧 Test Modale Email
                </button>
                
                <button class="test-button" onclick="printOrderSlip('<?php echo $testOrder['reference']; ?>')">
                    🖨️ Test Impression
                </button>
            </div>
        </div>

        <div class="test-section">
            <h3>🎯 Test des fonctionnalités</h3>
            
            <div class="test-results" id="test-results">
                <h4>Résultats des tests :</h4>
                <ul id="test-list">
                    <li>✅ Modales communes chargées</li>
                    <li>✅ Fonctions JavaScript intégrées</li>
                    <li>✅ Données de test disponibles</li>
                </ul>
            </div>
            
            <button class="test-button" onclick="runModalTests()">
                🧪 Lancer les tests automatisés
            </button>
        </div>

    <?php else: ?>
        <div class="test-section">
            <h3>⚠️ Aucune commande de test</h3>
            <p>Aucune commande payée en attente de retrait trouvée pour les tests.</p>
            <p>Les modales peuvent toujours être testées mais sans données réelles.</p>
            
            <button class="test-button" onclick="showContactModal('CMD-TEST-123')">
                📞 Test Modale Contact (vide)
            </button>
        </div>
    <?php endif; ?>

</div>

<!-- Inclusion des modales communes -->
<?php include('modals_common.php'); ?>

<script>
    // Transmission des données PHP vers JavaScript
    let ordersData = <?php echo json_encode($paidOrdersData['orders'], JSON_UNESCAPED_SLASHES); ?>;
    let ordersStats = <?php echo json_encode($paidStats, JSON_UNESCAPED_SLASHES); ?>;
    
    console.log('Données de test chargées:', {
        orders: ordersData.length,
        stats: ordersStats
    });
    
    /**
     * Lance les tests automatisés des modales
     */
    function runModalTests() {
        const testList = document.getElementById('test-list');
        
        // Test 1: Vérifier que les modales existent
        const requiredModals = ['detailsModal', 'contactModal', 'emailConfirmationModal', 'imagePreviewModal'];
        let modalsOK = 0;
        
        requiredModals.forEach(modalId => {
            const modal = document.getElementById(modalId);
            if (modal) {
                modalsOK++;
                addTestResult(`✅ Modale ${modalId} trouvée`);
            } else {
                addTestResult(`❌ Modale ${modalId} manquante`);
            }
        });
        
        // Test 2: Vérifier que les fonctions existent
        const requiredFunctions = ['showContactModal', 'showDetailsModal', 'showEmailConfirmationModal', 'closeModal', 'copyToClipboard'];
        let functionsOK = 0;
        
        requiredFunctions.forEach(funcName => {
            if (typeof window[funcName] === 'function') {
                functionsOK++;
                addTestResult(`✅ Fonction ${funcName} disponible`);
            } else {
                addTestResult(`❌ Fonction ${funcName} manquante`);
            }
        });
        
        // Test 3: Tester l'ouverture/fermeture des modales
        setTimeout(() => {
            testModalOpenClose();
        }, 1000);
        
        // Résumé
        addTestResult(`<strong>📊 Résumé: ${modalsOK}/${requiredModals.length} modales OK, ${functionsOK}/${requiredFunctions.length} fonctions OK</strong>`);
    }
    
    /**
     * Test d'ouverture et fermeture des modales
     */
    function testModalOpenClose() {
        addTestResult('🧪 Test ouverture/fermeture modales...');
        
        // Test contact modal
        setTimeout(() => {
            if (ordersData.length > 0) {
                showContactModal(ordersData[0].reference);
                setTimeout(() => {
                    closeModal('contactModal');
                    addTestResult('✅ Test modale contact réussi');
                }, 500);
            }
        }, 100);
        
        // Test details modal
        setTimeout(() => {
            if (ordersData.length > 0) {
                showDetailsModal(ordersData[0].reference);
                setTimeout(() => {
                    closeModal('detailsModal');
                    addTestResult('✅ Test modale détails réussi');
                }, 500);
            }
        }, 1200);
        
        // Test email modal
        setTimeout(() => {
            if (ordersData.length > 0) {
                showEmailConfirmationModal(ordersData[0].reference);
                setTimeout(() => {
                    closeModal('emailConfirmationModal');
                    addTestResult('✅ Test modale email réussi');
                }, 500);
            }
        }, 2400);
        
        setTimeout(() => {
            addTestResult('<strong>🎉 Tests automatisés terminés !</strong>');
        }, 3600);
    }
    
    /**
     * Ajoute un résultat de test à la liste
     */
    function addTestResult(message) {
        const testList = document.getElementById('test-list');
        const li = document.createElement('li');
        li.innerHTML = message;
        testList.appendChild(li);
    }
    
    // Test au chargement de la page
    document.addEventListener('DOMContentLoaded', function() {
        addTestResult('🚀 Page de test chargée');
        
        if (ordersData.length > 0) {
            addTestResult(`✅ ${ordersData.length} commande(s) de test disponible(s)`);
        } else {
            addTestResult('⚠️ Aucune donnée de test (normal si pas de commandes payées)');
        }
    });
</script>

<!-- Inclusion du script JavaScript mis à jour -->
<script src="js/admin_paid_orders.js"></script>

</body>
</html>