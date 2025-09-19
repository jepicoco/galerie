<?php
/**
 * Test du système de badges de notification des commandes
 * Ce script permet de vérifier le bon fonctionnement de l'API et des compteurs
 */

define('GALLERY_ACCESS', true);
require_once 'config.php';
require_once 'functions.php';

// Simuler une session admin pour les tests
session_start();
$_SESSION['admin'] = true;

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Badges Commandes - <?php echo SITE_NAME; ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .test-container {
            max-width: 800px;
            margin: 2rem auto;
            padding: 2rem;
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        }
        
        .test-section {
            margin: 2rem 0;
            padding: 1.5rem;
            border: 1px solid #ddd;
            border-radius: 8px;
        }
        
        .test-button {
            background: var(--primary-color);
            color: white;
            border: none;
            padding: 0.75rem 1.5rem;
            border-radius: 4px;
            cursor: pointer;
            margin: 0.5rem;
            font-size: 14px;
        }
        
        .test-button:hover {
            background: var(--secondary-color);
        }
        
        .test-result {
            margin-top: 1rem;
            padding: 1rem;
            border-radius: 4px;
            font-family: monospace;
            white-space: pre-wrap;
        }
        
        .test-success {
            background: #d4edda;
            border: 1px solid #c3e6cb;
            color: #155724;
        }
        
        .test-error {
            background: #f8d7da;
            border: 1px solid #f5c6cb;
            color: #721c24;
        }
        
        .test-info {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
        }
        
        .header-demo {
            background: linear-gradient(135deg, var(--primary-color), var(--secondary-color));
            color: white;
            padding: 1rem;
            border-radius: 8px;
            margin: 1rem 0;
        }
        
        .nav-demo {
            display: flex;
            gap: 1rem;
            flex-wrap: wrap;
            align-items: center;
        }
    </style>
</head>
<body>
    <div class="test-container">
        <h1>🧪 Test Système de Badges de Notification</h1>
        <p>Cette page permet de tester le système de badges qui affiche le nombre de commandes dans la navigation admin.</p>
        
        <!-- Demo des badges dans un header simulé -->
        <div class="test-section">
            <h2>📱 Aperçu des Badges</h2>
            <p>Voici à quoi ressemblent les boutons admin avec les badges :</p>
            
            <div class="header-demo">
                <div class="nav-demo">
                    <a href="#" class="btn btn-secondary btn-with-badge" data-badge-type="pickup">
                        📦 Retraits
                        <span class="notification-badge pickup show" id="demo-pickup">3</span>
                    </a>
                    <a href="#" class="btn btn-secondary btn-with-badge" data-badge-type="orders">
                        📋 Commandes
                        <span class="notification-badge orders show" id="demo-orders">7</span>
                    </a>
                    <a href="#" class="btn btn-secondary">🖼️ Gestion de la galerie</a>
                </div>
            </div>
        </div>

        <!-- Test de l'API -->
        <div class="test-section">
            <h2>🔌 Test de l'API</h2>
            <p>Test de l'endpoint <code>api_order_badges.php</code> :</p>
            
            <button class="test-button" onclick="testAPI()">Tester l'API</button>
            <button class="test-button" onclick="simulateData()">Simuler des Données</button>
            
            <div id="api-result" class="test-result" style="display: none;"></div>
        </div>

        <!-- Test du JavaScript -->
        <div class="test-section">
            <h2>⚡ Test du JavaScript</h2>
            <p>Test du système de mise à jour automatique des badges :</p>
            
            <button class="test-button" onclick="testJavaScript()">Tester le Manager</button>
            <button class="test-button" onclick="forceUpdate()">Forcer Mise à Jour</button>
            <button class="test-button" onclick="changeRefreshRate()">Changer Fréquence (5s)</button>
            
            <div id="js-result" class="test-result" style="display: none;"></div>
        </div>

        <!-- Test des compteurs -->
        <div class="test-section">
            <h2>📊 État des Commandes</h2>
            <p>Analyse des fichiers de commandes existants :</p>
            
            <button class="test-button" onclick="analyzeOrders()">Analyser les Commandes</button>
            
            <div id="orders-analysis" class="test-result" style="display: none;"></div>
        </div>

        <!-- Console de debug -->
        <div class="test-section">
            <h2>🐛 Console de Debug</h2>
            <p>Messages du système :</p>
            <div id="debug-console" class="test-result test-info" style="display: block;">
                Initialisation...\n
            </div>
        </div>
    </div>

    <script>
    // Override console.log pour afficher dans notre console de debug
    const originalConsoleLog = console.log;
    console.log = function(...args) {
        originalConsoleLog.apply(console, args);
        
        const debugConsole = document.getElementById('debug-console');
        if (debugConsole) {
            debugConsole.textContent += new Date().toLocaleTimeString() + ': ' + args.join(' ') + '\n';
            debugConsole.scrollTop = debugConsole.scrollHeight;
        }
    };

    // Test de l'API
    async function testAPI() {
        const resultDiv = document.getElementById('api-result');
        resultDiv.style.display = 'block';
        resultDiv.textContent = 'Test en cours...';
        resultDiv.className = 'test-result test-info';
        
        try {
            const response = await fetch('api_order_badges.php');
            const data = await response.json();
            
            resultDiv.className = 'test-result test-success';
            resultDiv.textContent = 'API Response:\n' + JSON.stringify(data, null, 2);
            
            console.log('Test API réussi:', data);
            
        } catch (error) {
            resultDiv.className = 'test-result test-error';
            resultDiv.textContent = 'Erreur API:\n' + error.message;
            
            console.error('Test API échoué:', error);
        }
    }

    // Simulation de données pour test visuel
    function simulateData() {
        const pickupBadge = document.getElementById('demo-pickup');
        const ordersBadge = document.getElementById('demo-orders');
        
        const pickupCount = Math.floor(Math.random() * 10);
        const ordersCount = Math.floor(Math.random() * 15);
        
        pickupBadge.textContent = pickupCount;
        ordersBadge.textContent = ordersCount;
        
        // Ajouter animation
        pickupBadge.classList.add('urgent');
        ordersBadge.classList.add('urgent');
        
        setTimeout(() => {
            pickupBadge.classList.remove('urgent');
            ordersBadge.classList.remove('urgent');
        }, 3000);
        
        console.log(`Données simulées: ${ordersCount} commandes, ${pickupCount} retraits`);
    }

    // Test du JavaScript Manager
    function testJavaScript() {
        const resultDiv = document.getElementById('js-result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result test-info';
        
        if (typeof window.OrderBadgeManager !== 'undefined') {
            const manager = window.OrderBadgeManager.getInstance();
            
            if (manager) {
                resultDiv.className = 'test-result test-success';
                resultDiv.textContent = 'OrderBadgeManager trouvé et actif!\n' +
                    'Instance: ' + typeof manager + '\n' +
                    'Badges identifiés: ' + (manager.validBadges ? manager.validBadges.length : 'N/A');
                
                console.log('Test JavaScript réussi, manager actif');
            } else {
                resultDiv.className = 'test-result test-error';
                resultDiv.textContent = 'OrderBadgeManager non initialisé';
                
                console.error('Manager non initialisé');
            }
        } else {
            resultDiv.className = 'test-result test-error';
            resultDiv.textContent = 'OrderBadgeManager non disponible - Script non chargé?';
            
            console.error('Script order-badges.js non chargé');
        }
    }

    // Force une mise à jour
    function forceUpdate() {
        if (window.OrderBadgeManager) {
            window.OrderBadgeManager.forceUpdate();
            console.log('Mise à jour forcée des badges');
        } else {
            console.error('OrderBadgeManager non disponible');
        }
    }

    // Change la fréquence de mise à jour
    function changeRefreshRate() {
        if (window.OrderBadgeManager) {
            window.OrderBadgeManager.setRefreshRate(5000); // 5 secondes
            console.log('Fréquence changée à 5 secondes');
        } else {
            console.error('OrderBadgeManager non disponible');
        }
    }

    // Analyse les commandes
    async function analyzeOrders() {
        const resultDiv = document.getElementById('orders-analysis');
        resultDiv.style.display = 'block';
        resultDiv.textContent = 'Analyse en cours...';
        resultDiv.className = 'test-result test-info';
        
        try {
            // Simuler une analyse des commandes
            const response = await fetch('api_order_badges.php');
            const data = await response.json();
            
            if (data.success) {
                const badges = data.badges;
                let analysis = 'ANALYSE DES COMMANDES:\n\n';
                analysis += `📋 Commandes non payées: ${badges.unpaid_orders}\n`;
                analysis += `📦 Prêtes pour retrait: ${badges.ready_for_pickup}\n`;
                analysis += `🆕 Nouvelles (24h): ${badges.new_orders}\n`;
                analysis += `📊 Total en attente: ${badges.total_pending}\n\n`;
                analysis += `⏰ Dernière mise à jour: ${data.formatted_time}`;
                
                resultDiv.className = 'test-result test-success';
                resultDiv.textContent = analysis;
                
                console.log('Analyse des commandes réussie');
            } else {
                throw new Error(data.error || 'Erreur inconnue');
            }
            
        } catch (error) {
            resultDiv.className = 'test-result test-error';
            resultDiv.textContent = 'Erreur analyse:\n' + error.message;
            
            console.error('Erreur analyse commandes:', error);
        }
    }

    // Auto-test au chargement
    document.addEventListener('DOMContentLoaded', function() {
        console.log('Page de test chargée');
        
        // Test automatique après 2 secondes
        setTimeout(() => {
            console.log('Lancement des tests automatiques...');
            testAPI();
            setTimeout(testJavaScript, 1000);
        }, 2000);
    });
    </script>

    <!-- Charger le script des badges -->
    <script src="js/order-badges.js"></script>
</body>
</html>