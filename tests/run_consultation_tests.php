<?php
/**
 * Test Runner pour le système de consultations
 * 
 * Lance tous les tests et génère un rapport consolidé
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '512M');
ini_set('max_execution_time', 300); // 5 minutes

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Test Runner - Système de Consultations</title>
    <style>
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, sans-serif;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            background: #f5f5f5;
            line-height: 1.6;
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            border-radius: 10px;
            margin-bottom: 30px;
            text-align: center;
        }
        
        .test-suite {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 20px;
            overflow: hidden;
        }
        
        .test-suite-header {
            background: #2c3e50;
            color: white;
            padding: 15px 20px;
            font-weight: bold;
            cursor: pointer;
        }
        
        .test-suite-content {
            padding: 20px;
            border-left: 4px solid #3498db;
        }
        
        .controls {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            text-align: center;
        }
        
        .btn {
            background: #3498db;
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 5px;
            cursor: pointer;
            font-size: 14px;
            margin: 0 10px;
            transition: all 0.3s ease;
        }
        
        .btn:hover {
            background: #2980b9;
            transform: translateY(-2px);
        }
        
        .btn-success { background: #27ae60; }
        .btn-success:hover { background: #229954; }
        
        .btn-warning { background: #f39c12; }
        .btn-warning:hover { background: #e67e22; }
        
        .btn-danger { background: #e74c3c; }
        .btn-danger:hover { background: #c0392b; }
        
        .summary {
            background: white;
            padding: 20px;
            border-radius: 8px;
            margin-bottom: 20px;
            border-left: 5px solid #27ae60;
        }
        
        .progress-bar {
            width: 100%;
            height: 20px;
            background: #ecf0f1;
            border-radius: 10px;
            overflow: hidden;
            margin: 10px 0;
        }
        
        .progress-fill {
            height: 100%;
            background: linear-gradient(90deg, #27ae60, #2ecc71);
            transition: width 0.3s ease;
            border-radius: 10px;
        }
        
        .loading {
            display: none;
            text-align: center;
            padding: 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #3498db;
            border-radius: 50%;
            width: 40px;
            height: 40px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        .note {
            background: #e8f4fd;
            border-left: 4px solid #3498db;
            padding: 15px;
            margin: 15px 0;
            border-radius: 4px;
        }
        
        .warning {
            background: #fff3cd;
            border-left: 4px solid #ffc107;
            color: #856404;
        }
        
        .success {
            background: #d4edda;
            border-left: 4px solid #28a745;
            color: #155724;
        }
        
        .error {
            background: #f8d7da;
            border-left: 4px solid #dc3545;
            color: #721c24;
        }
    </style>
</head>
<body>
    <div class="header">
        <h1>🧪 Test Runner - Système de Consultations</h1>
        <p>Suite de tests complète pour la validation du système de tracking des consultations photos</p>
        <p><strong>Date:</strong> <?= date('d/m/Y H:i:s') ?></p>
    </div>
    
    <div class="controls">
        <button class="btn btn-success" onclick="runAllTests()">▶️ Exécuter Tous les Tests</button>
        <button class="btn" onclick="runBackendTests()">🔧 Tests Backend</button>
        <button class="btn" onclick="runAdminTests()">👑 Tests Admin</button>
        <button class="btn" onclick="runDataIntegrityTests()">🔒 Tests Intégrité</button>
        <button class="btn" onclick="runE2ETests()">🔄 Tests E2E</button>
        <button class="btn" onclick="runEdgeCaseTests()">⚠️ Tests Cas Limites</button>
        <button class="btn btn-warning" onclick="viewReport()">📋 Voir le Rapport</button>
    </div>
    
    <div class="loading" id="loading">
        <div class="spinner"></div>
        <p>Exécution des tests en cours...</p>
        <div class="progress-bar">
            <div class="progress-fill" id="progress" style="width: 0%"></div>
        </div>
    </div>
    
    <div class="summary" id="summary" style="display: none;">
        <h2>📊 Résumé Global</h2>
        <div id="summary-content">
            <!-- Le contenu sera généré dynamiquement -->
        </div>
    </div>
    
    <!-- Notes importantes -->
    <div class="note">
        <h3>ℹ️ Informations Importantes</h3>
        <p><strong>Prérequis :</strong> Serveur PHP actif avec accès aux fichiers du projet</p>
        <p><strong>Permissions :</strong> Droits d'écriture sur le dossier data/</p>
        <p><strong>Durée :</strong> L'exécution complète prend environ 2-3 minutes</p>
    </div>
    
    <div class="warning note">
        <h3>⚠️ Avertissements</h3>
        <p><strong>Sauvegarde :</strong> Les tests créent des sauvegardes automatiques des données</p>
        <p><strong>Session :</strong> Une session admin sera simulée pour certains tests</p>
        <p><strong>Performance :</strong> Certains tests génèrent temporairement beaucoup de données</p>
    </div>
    
    <!-- Suites de tests -->
    <div class="test-suite">
        <div class="test-suite-header" onclick="toggleSuite('backend')">
            🔧 Tests Backend API (12 tests)
        </div>
        <div class="test-suite-content" id="backend-content" style="display: none;">
            <p><strong>Fichier:</strong> test_consultation_backend.php</p>
            <p><strong>Couverture:</strong> API consultation_handler.php, validation entrées, performance, concurrence</p>
            <div id="backend-results"></div>
        </div>
    </div>
    
    <div class="test-suite">
        <div class="test-suite-header" onclick="toggleSuite('frontend')">
            🌐 Tests Frontend JavaScript (11 tests)
        </div>
        <div class="test-suite-content" id="frontend-content" style="display: none;">
            <p><strong>Fichier:</strong> test_consultation_frontend.html</p>
            <p><strong>Couverture:</strong> Tracking client, Intersection Observer, performance JavaScript</p>
            <div class="note">
                <p><strong>Note:</strong> Les tests frontend nécessitent d'ouvrir le fichier HTML dans un navigateur.</p>
                <a href="test_consultation_frontend.html" target="_blank" class="btn">🔗 Ouvrir Tests Frontend</a>
            </div>
        </div>
    </div>
    
    <div class="test-suite">
        <div class="test-suite-header" onclick="toggleSuite('admin')">
            👑 Tests Interface Admin (8 tests)
        </div>
        <div class="test-suite-content" id="admin-content" style="display: none;">
            <p><strong>Fichier:</strong> test_consultation_admin.php</p>
            <p><strong>Couverture:</strong> Interface admin, analytics, contrôles d'accès</p>
            <div id="admin-results"></div>
        </div>
    </div>
    
    <div class="test-suite">
        <div class="test-suite-header" onclick="toggleSuite('integrity')">
            🔒 Tests Intégrité Données (12 tests)
        </div>
        <div class="test-suite-content" id="integrity-content" style="display: none;">
            <p><strong>Fichier:</strong> test_consultation_data_integrity.php</p>
            <p><strong>Couverture:</strong> JSON structure, encodage, corruption, migration</p>
            <div id="integrity-results"></div>
        </div>
    </div>
    
    <div class="test-suite">
        <div class="test-suite-header" onclick="toggleSuite('e2e')">
            🔄 Tests End-to-End (10 tests)
        </div>
        <div class="test-suite-content" id="e2e-content" style="display: none;">
            <p><strong>Fichier:</strong> test_consultation_e2e.php</p>
            <p><strong>Couverture:</strong> Workflows complets, parcours utilisateur, intégrations</p>
            <div id="e2e-results"></div>
        </div>
    </div>
    
    <div class="test-suite">
        <div class="test-suite-header" onclick="toggleSuite('edge')">
            ⚠️ Tests Cas Limites (15 tests)
        </div>
        <div class="test-suite-content" id="edge-content" style="display: none;">
            <p><strong>Fichier:</strong> test_consultation_edge_cases.php</p>
            <p><strong>Couverture:</strong> Robustesse, sécurité, gestion d'erreurs</p>
            <div id="edge-results"></div>
        </div>
    </div>
    
    <!-- Rapport de synthèse -->
    <div class="success note">
        <h3>✅ État du Système</h3>
        <p><strong>Version:</strong> 1.0 - Système de consultations statistiques</p>
        <p><strong>Composants:</strong> consultation_handler.php, script.js (tracking), admin.php (interface)</p>
        <p><strong>Stockage:</strong> data/consultations.json (format JSON avec verrouillage)</p>
        <p><strong>Documentation:</strong> CONSULTATION_TEST_REPORT.md (rapport détaillé)</p>
    </div>

    <script>
        let testResults = {
            backend: null,
            admin: null,
            integrity: null,
            e2e: null,
            edge: null
        };
        
        function toggleSuite(suiteId) {
            const content = document.getElementById(suiteId + '-content');
            content.style.display = content.style.display === 'none' ? 'block' : 'none';
        }
        
        function showLoading() {
            document.getElementById('loading').style.display = 'block';
        }
        
        function hideLoading() {
            document.getElementById('loading').style.display = 'none';
        }
        
        function updateProgress(percent) {
            document.getElementById('progress').style.width = percent + '%';
        }
        
        async function runTestFile(filename, suiteId) {
            try {
                const response = await fetch(filename);
                const html = await response.text();
                
                // Extraire les informations de base du HTML
                const successMatches = html.match(/Tests réussis:.*?(\d+)/);
                const failedMatches = html.match(/Tests échoués:.*?(\d+)/);
                const totalMatches = html.match(/Tests exécutés:.*?(\d+)/);
                const timeMatches = html.match(/Temps d'exécution:.*?([\d.]+)ms/);
                
                const results = {
                    passed: successMatches ? parseInt(successMatches[1]) : 0,
                    failed: failedMatches ? parseInt(failedMatches[1]) : 0,
                    total: totalMatches ? parseInt(totalMatches[1]) : 0,
                    time: timeMatches ? parseFloat(timeMatches[1]) : 0,
                    html: html
                };
                
                testResults[suiteId] = results;
                
                // Afficher les résultats
                const resultsDiv = document.getElementById(suiteId + '-results');
                const successRate = results.total > 0 ? Math.round((results.passed / results.total) * 100) : 0;
                
                resultsDiv.innerHTML = `
                    <div class="summary">
                        <h4>📊 Résultats</h4>
                        <p><strong>Tests:</strong> ${results.passed}/${results.total} réussis (${successRate}%)</p>
                        <p><strong>Échoués:</strong> ${results.failed}</p>
                        <p><strong>Temps:</strong> ${results.time}ms</p>
                        <div class="progress-bar">
                            <div class="progress-fill" style="width: ${successRate}%; background: ${successRate >= 90 ? '#27ae60' : successRate >= 70 ? '#f39c12' : '#e74c3c'}"></div>
                        </div>
                        <button class="btn" onclick="showDetailedResults('${suiteId}')">📋 Voir Détails</button>
                    </div>
                `;
                
                return results;
            } catch (error) {
                console.error(`Erreur lors de l'exécution de ${filename}:`, error);
                const resultsDiv = document.getElementById(suiteId + '-results');
                resultsDiv.innerHTML = `<div class="error note">❌ Erreur: ${error.message}</div>`;
                return null;
            }
        }
        
        function showDetailedResults(suiteId) {
            if (testResults[suiteId] && testResults[suiteId].html) {
                const newWindow = window.open('', '_blank', 'width=1000,height=700,scrollbars=yes');
                newWindow.document.write(`
                    <html>
                        <head>
                            <title>Résultats détaillés - ${suiteId}</title>
                            <style>
                                body { font-family: Arial, sans-serif; margin: 20px; line-height: 1.6; }
                                h1, h2, h3 { color: #2c3e50; }
                                .success { color: green; }
                                .error { color: red; }
                                .info { color: blue; }
                                hr { margin: 20px 0; }
                            </style>
                        </head>
                        <body>
                            <h1>Résultats détaillés - ${suiteId.toUpperCase()}</h1>
                            ${testResults[suiteId].html}
                        </body>
                    </html>
                `);
                newWindow.document.close();
            }
        }
        
        async function runBackendTests() {
            showLoading();
            updateProgress(0);
            
            document.getElementById('backend-content').style.display = 'block';
            const results = await runTestFile('test_consultation_backend.php', 'backend');
            
            updateProgress(100);
            hideLoading();
        }
        
        async function runAdminTests() {
            showLoading();
            updateProgress(0);
            
            document.getElementById('admin-content').style.display = 'block';
            const results = await runTestFile('test_consultation_admin.php', 'admin');
            
            updateProgress(100);
            hideLoading();
        }
        
        async function runDataIntegrityTests() {
            showLoading();
            updateProgress(0);
            
            document.getElementById('integrity-content').style.display = 'block';
            const results = await runTestFile('test_consultation_data_integrity.php', 'integrity');
            
            updateProgress(100);
            hideLoading();
        }
        
        async function runE2ETests() {
            showLoading();
            updateProgress(0);
            
            document.getElementById('e2e-content').style.display = 'block';
            const results = await runTestFile('test_consultation_e2e.php', 'e2e');
            
            updateProgress(100);
            hideLoading();
        }
        
        async function runEdgeCaseTests() {
            showLoading();
            updateProgress(0);
            
            document.getElementById('edge-content').style.display = 'block';
            const results = await runTestFile('test_consultation_edge_cases.php', 'edge');
            
            updateProgress(100);
            hideLoading();
        }
        
        async function runAllTests() {
            showLoading();
            
            const suites = ['backend', 'admin', 'integrity', 'e2e', 'edge'];
            const files = [
                'test_consultation_backend.php',
                'test_consultation_admin.php', 
                'test_consultation_data_integrity.php',
                'test_consultation_e2e.php',
                'test_consultation_edge_cases.php'
            ];
            
            for (let i = 0; i < suites.length; i++) {
                updateProgress((i / suites.length) * 100);
                document.getElementById(suites[i] + '-content').style.display = 'block';
                
                await runTestFile(files[i], suites[i]);
                
                // Petit délai pour éviter la surcharge
                await new Promise(resolve => setTimeout(resolve, 500));
            }
            
            updateProgress(100);
            hideLoading();
            
            // Générer le résumé global
            generateGlobalSummary();
        }
        
        function generateGlobalSummary() {
            let totalTests = 0;
            let totalPassed = 0;
            let totalFailed = 0;
            let totalTime = 0;
            let validResults = 0;
            
            for (const [suite, result] of Object.entries(testResults)) {
                if (result) {
                    totalTests += result.total;
                    totalPassed += result.passed;
                    totalFailed += result.failed;
                    totalTime += result.time;
                    validResults++;
                }
            }
            
            if (validResults === 0) return;
            
            const globalSuccessRate = Math.round((totalPassed / totalTests) * 100);
            
            const summaryDiv = document.getElementById('summary');
            const summaryContent = document.getElementById('summary-content');
            
            summaryContent.innerHTML = `
                <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 15px; margin-bottom: 20px;">
                    <div style="text-align: center; padding: 15px; background: #e8f5e8; border-radius: 8px;">
                        <h3 style="margin: 0; color: #27ae60;">Tests Exécutés</h3>
                        <p style="font-size: 24px; margin: 5px 0; color: #27ae60;">${totalTests}</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #e8f5e8; border-radius: 8px;">
                        <h3 style="margin: 0; color: #27ae60;">Réussis</h3>
                        <p style="font-size: 24px; margin: 5px 0; color: #27ae60;">${totalPassed}</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: ${totalFailed > 0 ? '#fdf2f2' : '#e8f5e8'}; border-radius: 8px;">
                        <h3 style="margin: 0; color: ${totalFailed > 0 ? '#e74c3c' : '#27ae60'};">Échoués</h3>
                        <p style="font-size: 24px; margin: 5px 0; color: ${totalFailed > 0 ? '#e74c3c' : '#27ae60'};">${totalFailed}</p>
                    </div>
                    <div style="text-align: center; padding: 15px; background: #f8f9fa; border-radius: 8px;">
                        <h3 style="margin: 0; color: #2c3e50;">Taux de Réussite</h3>
                        <p style="font-size: 24px; margin: 5px 0; color: ${globalSuccessRate >= 90 ? '#27ae60' : globalSuccessRate >= 70 ? '#f39c12' : '#e74c3c'};">${globalSuccessRate}%</p>
                    </div>
                </div>
                
                <div class="progress-bar" style="margin: 20px 0;">
                    <div class="progress-fill" style="width: ${globalSuccessRate}%; background: ${globalSuccessRate >= 90 ? '#27ae60' : globalSuccessRate >= 70 ? '#f39c12' : '#e74c3c'}"></div>
                </div>
                
                <div style="margin-top: 20px;">
                    <h4>📋 Analyse Globale</h4>
                    <ul>
                        <li><strong>Temps d'exécution total:</strong> ${totalTime.toFixed(1)}ms</li>
                        <li><strong>Suites testées:</strong> ${validResults}/5</li>
                        <li><strong>Statut système:</strong> ${globalSuccessRate >= 95 ? '🟢 Excellent - Prêt pour production' : globalSuccessRate >= 85 ? '🟡 Bon - Quelques corrections mineures' : '🔴 Problématique - Corrections nécessaires'}</li>
                        <li><strong>Recommandation:</strong> ${globalSuccessRate >= 90 ? 'Système validé pour déploiement' : 'Révision des échecs recommandée'}</li>
                    </ul>
                </div>
            `;
            
            summaryDiv.style.display = 'block';
        }
        
        function viewReport() {
            window.open('CONSULTATION_TEST_REPORT.md', '_blank');
        }
        
        // Auto-show first suite
        document.getElementById('backend-content').style.display = 'block';
    </script>
</body>
</html>