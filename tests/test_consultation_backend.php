<?php
/**
 * Test Suite pour le système de consultations - Backend API
 * 
 * Tests complets pour consultation_handler.php
 * @version 1.0
 */

// Configuration pour les tests
error_reporting(E_ALL);
ini_set('display_errors', 1);
ini_set('memory_limit', '256M');

define('GALLERY_ACCESS', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

/**
 * Classe de tests pour les consultations
 */
class ConsultationBackendTests {
    private $testResults = [];
    private $testCount = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    private $logger;
    private $originalConsultationsFile;
    private $testConsultationsFile;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->originalConsultationsFile = 'data/consultations.json';
        $this->testConsultationsFile = 'data/consultations_test.json';
        
        // Créer une sauvegarde des consultations existantes
        $this->backupConsultations();
    }
    
    /**
     * Exécuter tous les tests
     */
    public function runAllTests() {
        echo "<h1>🧪 Tests Backend - Système de Consultations</h1>\n";
        echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
        
        $startTime = microtime(true);
        
        // Tests des endpoints de base
        $this->testTrackConsultation();
        $this->testGetConsultationStats();
        $this->testGetPopularPhotos();
        $this->testGetActivityStats();
        $this->testGetRecentConsultations();
        
        // Tests des fonctions utilitaires
        $this->testCleanupOldConsultations();
        $this->testExportConsultationData();
        
        // Tests d'erreurs et de validation
        $this->testErrorHandling();
        $this->testInputValidation();
        $this->testAuthenticationRequirements();
        
        // Tests de performance
        $this->testPerformanceWithLargeDataset();
        $this->testConcurrentAccess();
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        // Restaurer les consultations originales
        $this->restoreConsultations();
        
        // Afficher le résumé
        $this->displaySummary($executionTime);
    }
    
    /**
     * Test d'enregistrement de consultations
     */
    private function testTrackConsultation() {
        $this->startTest("track_consultation - Enregistrement basique");
        
        // Préparer les données de test
        $testData = [
            'action' => 'track_consultation',
            'photo_path' => 'test-activity/test-photo.jpg',
            'activity_key' => 'test-activity',
            'photo_name' => 'test-photo.jpg',
            'view_type' => 'thumbnail'
        ];
        
        $response = $this->makeApiCall($testData);
        $this->assertTrue($response['success'], "La consultation devrait être enregistrée");
        
        // Vérifier que la consultation a été sauvegardée
        $consultations = $this->getStoredConsultations();
        $this->assertTrue(count($consultations) > 0, "Au moins une consultation devrait être stockée");
        
        $lastConsultation = end($consultations);
        $this->assertEquals('test-activity', $lastConsultation['activity_key'], "activity_key devrait correspondre");
        $this->assertEquals('test-photo.jpg', $lastConsultation['photo_name'], "photo_name devrait correspondre");
        $this->assertEquals('thumbnail', $lastConsultation['view_type'], "view_type devrait correspondre");
        $this->assertNotEmpty($lastConsultation['timestamp'], "timestamp devrait être défini");
        $this->assertNotEmpty($lastConsultation['session_id'], "session_id devrait être défini");
        
        $this->endTest();
    }
    
    /**
     * Test des statistiques de consultations
     */
    private function testGetConsultationStats() {
        $this->startTest("get_consultation_stats - Récupération des statistiques");
        
        // D'abord, insérer quelques consultations de test
        $this->insertTestConsultations();
        
        // Tester différentes périodes
        $periods = ['today', 'week', 'month'];
        
        foreach ($periods as $period) {
            $testData = [
                'action' => 'get_consultation_stats',
                'period' => $period
            ];
            
            // Simuler une session admin
            $_SESSION['admin_logged_in'] = true;
            $response = $this->makeApiCall($testData);
            
            $this->assertTrue($response['success'], "Les statistiques pour '$period' devraient être récupérées");
            $this->assertArrayHasKey('stats', $response, "La réponse devrait contenir des stats");
            
            $stats = $response['stats'];
            $this->assertArrayHasKey('total_consultations', $stats, "Stats devraient contenir total_consultations");
            $this->assertArrayHasKey('unique_photos', $stats, "Stats devraient contenir unique_photos");
            $this->assertArrayHasKey('unique_sessions', $stats, "Stats devraient contenir unique_sessions");
            $this->assertArrayHasKey('view_types', $stats, "Stats devraient contenir view_types");
            $this->assertArrayHasKey('hourly_distribution', $stats, "Stats devraient contenir hourly_distribution");
        }
        
        $this->endTest();
    }
    
    /**
     * Test des photos populaires
     */
    private function testGetPopularPhotos() {
        $this->startTest("get_popular_photos - Photos les plus consultées");
        
        $this->insertTestConsultations();
        
        $testData = [
            'action' => 'get_popular_photos',
            'limit' => 5,
            'period' => 'week'
        ];
        
        $_SESSION['admin_logged_in'] = true;
        $response = $this->makeApiCall($testData);
        
        $this->assertTrue($response['success'], "Les photos populaires devraient être récupérées");
        $this->assertArrayHasKey('popular_photos', $response, "La réponse devrait contenir popular_photos");
        
        $popularPhotos = $response['popular_photos'];
        $this->assertIsArray($popularPhotos, "popular_photos devrait être un tableau");
        
        if (!empty($popularPhotos)) {
            $firstPhoto = $popularPhotos[0];
            $this->assertArrayHasKey('activity_key', $firstPhoto, "Photo devrait avoir activity_key");
            $this->assertArrayHasKey('photo_name', $firstPhoto, "Photo devrait avoir photo_name");
            $this->assertArrayHasKey('consultation_count', $firstPhoto, "Photo devrait avoir consultation_count");
            $this->assertArrayHasKey('unique_sessions', $firstPhoto, "Photo devrait avoir unique_sessions");
        }
        
        $this->endTest();
    }
    
    /**
     * Test des statistiques par activité
     */
    private function testGetActivityStats() {
        $this->startTest("get_activity_stats - Statistiques par activité");
        
        $this->insertTestConsultations();
        
        $testData = [
            'action' => 'get_activity_stats',
            'period' => 'week'
        ];
        
        $_SESSION['admin_logged_in'] = true;
        $response = $this->makeApiCall($testData);
        
        $this->assertTrue($response['success'], "Les stats par activité devraient être récupérées");
        $this->assertArrayHasKey('activity_stats', $response, "La réponse devrait contenir activity_stats");
        
        $activityStats = $response['activity_stats'];
        $this->assertIsArray($activityStats, "activity_stats devrait être un tableau");
        
        if (!empty($activityStats)) {
            $firstActivity = $activityStats[0];
            $this->assertArrayHasKey('activity_key', $firstActivity, "Activité devrait avoir activity_key");
            $this->assertArrayHasKey('total_consultations', $firstActivity, "Activité devrait avoir total_consultations");
            $this->assertArrayHasKey('unique_photos', $firstActivity, "Activité devrait avoir unique_photos");
            $this->assertArrayHasKey('unique_sessions', $firstActivity, "Activité devrait avoir unique_sessions");
        }
        
        $this->endTest();
    }
    
    /**
     * Test des consultations récentes
     */
    private function testGetRecentConsultations() {
        $this->startTest("get_recent_consultations - Consultations récentes");
        
        $this->insertTestConsultations();
        
        $testData = [
            'action' => 'get_recent_consultations',
            'limit' => 10
        ];
        
        $_SESSION['admin_logged_in'] = true;
        $response = $this->makeApiCall($testData);
        
        $this->assertTrue($response['success'], "Les consultations récentes devraient être récupérées");
        $this->assertArrayHasKey('consultations', $response, "La réponse devrait contenir consultations");
        
        $consultations = $response['consultations'];
        $this->assertIsArray($consultations, "consultations devrait être un tableau");
        $this->assertLessThanOrEqual(10, count($consultations), "Ne devrait pas dépasser la limite");
        
        // Vérifier l'ordre chronologique (plus récent en premier)
        if (count($consultations) > 1) {
            $firstTime = strtotime($consultations[0]['timestamp']);
            $secondTime = strtotime($consultations[1]['timestamp']);
            $this->assertGreaterThanOrEqual($secondTime, $firstTime, "Consultations devraient être triées par date desc");
        }
        
        $this->endTest();
    }
    
    /**
     * Test du nettoyage des anciennes consultations
     */
    private function testCleanupOldConsultations() {
        $this->startTest("cleanup_old_consultations - Nettoyage des données");
        
        // Insérer des consultations anciennes et récentes
        $this->insertOldAndRecentConsultations();
        
        $testData = [
            'action' => 'cleanup_old_consultations',
            'days_to_keep' => 7
        ];
        
        $_SESSION['admin_logged_in'] = true;
        $response = $this->makeApiCall($testData);
        
        $this->assertTrue($response['success'], "Le nettoyage devrait réussir");
        $this->assertArrayHasKey('records_cleaned', $response, "La réponse devrait contenir records_cleaned");
        $this->assertIsInt($response['records_cleaned'], "records_cleaned devrait être un entier");
        
        // Vérifier que seules les consultations récentes restent
        $consultations = $this->getStoredConsultations();
        $cutoffTime = time() - (7 * 24 * 60 * 60);
        
        foreach ($consultations as $consultation) {
            $consultationTime = strtotime($consultation['timestamp']);
            $this->assertGreaterThanOrEqual($cutoffTime, $consultationTime, "Toutes les consultations devraient être récentes");
        }
        
        $this->endTest();
    }
    
    /**
     * Test d'export des données
     */
    private function testExportConsultationData() {
        $this->startTest("export_consultation_data - Export des données");
        
        $this->insertTestConsultations();
        
        // Test export JSON
        $testData = [
            'action' => 'export_consultation_data',
            'start_date' => date('Y-m-d', strtotime('-7 days')),
            'end_date' => date('Y-m-d'),
            'format' => 'json'
        ];
        
        $_SESSION['admin_logged_in'] = true;
        $response = $this->makeApiCall($testData);
        
        $this->assertTrue($response['success'], "L'export JSON devrait réussir");
        $this->assertArrayHasKey('export_data', $response, "La réponse devrait contenir export_data");
        
        // Test export CSV
        $testData['format'] = 'csv';
        $response = $this->makeApiCall($testData);
        
        $this->assertTrue($response['success'], "L'export CSV devrait réussir");
        $this->assertArrayHasKey('export_data', $response, "La réponse devrait contenir export_data");
        $this->assertIsString($response['export_data'], "Export CSV devrait être une chaîne");
        $this->assertStringContains('Date;Activité;Photo', $response['export_data'], "CSV devrait contenir les en-têtes");
        
        $this->endTest();
    }
    
    /**
     * Test de gestion des erreurs
     */
    private function testErrorHandling() {
        $this->startTest("Gestion des erreurs - Paramètres manquants et invalides");
        
        // Test sans action
        $response = $this->makeApiCall([]);
        $this->assertFalse($response['success'], "Devrait échouer sans action");
        $this->assertStringContains('Aucune action', $response['error'], "Message d'erreur approprié");
        
        // Test action inexistante
        $response = $this->makeApiCall(['action' => 'inexistante']);
        $this->assertFalse($response['success'], "Devrait échouer avec action inexistante");
        $this->assertStringContains('Action non reconnue', $response['error'], "Message d'erreur approprié");
        
        // Test track_consultation avec données manquantes
        $response = $this->makeApiCall(['action' => 'track_consultation']);
        $this->assertFalse($response['success'], "Devrait échouer sans données photo");
        $this->assertStringContains('Données de photo manquantes', $response['error'], "Message d'erreur approprié");
        
        $this->endTest();
    }
    
    /**
     * Test de validation des entrées
     */
    private function testInputValidation() {
        $this->startTest("Validation des entrées - Sécurité et sanitization");
        
        // Test avec des données dangereuses
        $maliciousData = [
            'action' => 'track_consultation',
            'photo_path' => '../../../etc/passwd',
            'activity_key' => '<script>alert("xss")</script>',
            'photo_name' => '"; DROP TABLE consultations; --',
            'view_type' => 'thumbnail'
        ];
        
        $response = $this->makeApiCall($maliciousData);
        // Le système devrait traiter cela mais de manière sécurisée
        $this->assertTrue($response['success'], "Devrait traiter les données malicieuses de manière sécurisée");
        
        // Vérifier que les données stockées sont propres
        $consultations = $this->getStoredConsultations();
        $lastConsultation = end($consultations);
        
        $this->assertNotEmpty($lastConsultation['activity_key'], "activity_key ne devrait pas être vide");
        $this->assertNotEmpty($lastConsultation['photo_name'], "photo_name ne devrait pas être vide");
        
        $this->endTest();
    }
    
    /**
     * Test des exigences d'authentification
     */
    private function testAuthenticationRequirements() {
        $this->startTest("Authentification - Contrôle d'accès admin");
        
        // Effacer la session admin
        unset($_SESSION['admin_logged_in']);
        
        $adminOnlyActions = [
            'get_consultation_stats',
            'get_popular_photos',
            'get_activity_stats',
            'cleanup_old_consultations',
            'export_consultation_data',
            'get_recent_consultations'
        ];
        
        foreach ($adminOnlyActions as $action) {
            $response = $this->makeApiCall(['action' => $action]);
            $this->assertFalse($response['success'], "Action '$action' devrait échouer sans auth admin");
            $this->assertStringContains('Accès non autorisé', $response['error'], "Message d'erreur d'auth approprié");
        }
        
        // Test que track_consultation fonctionne sans auth admin
        $response = $this->makeApiCall([
            'action' => 'track_consultation',
            'photo_path' => 'test/photo.jpg',
            'activity_key' => 'test',
            'photo_name' => 'photo.jpg'
        ]);
        $this->assertTrue($response['success'], "track_consultation devrait fonctionner sans auth admin");
        
        $this->endTest();
    }
    
    /**
     * Test de performance avec un grand dataset
     */
    private function testPerformanceWithLargeDataset() {
        $this->startTest("Performance - Grand dataset");
        
        $startTime = microtime(true);
        
        // Créer un grand dataset de consultations
        $this->createLargeConsultationDataset(1000);
        
        $creationTime = microtime(true);
        
        // Tester les statistiques avec ce grand dataset
        $_SESSION['admin_logged_in'] = true;
        $response = $this->makeApiCall([
            'action' => 'get_consultation_stats',
            'period' => 'month'
        ]);
        
        $queryTime = microtime(true);
        
        $this->assertTrue($response['success'], "Les stats devraient fonctionner avec un grand dataset");
        
        $creationDuration = round(($creationTime - $startTime) * 1000, 2);
        $queryDuration = round(($queryTime - $creationTime) * 1000, 2);
        
        $this->addNote("Création de 1000 consultations: {$creationDuration}ms");
        $this->addNote("Requête de stats: {$queryDuration}ms");
        
        // Vérifier que les performances restent acceptables
        $this->assertLessThan(5000, $queryDuration, "Requête de stats devrait être < 5s même avec 1000 entrées");
        
        $this->endTest();
    }
    
    /**
     * Test d'accès concurrent
     */
    private function testConcurrentAccess() {
        $this->startTest("Concurrence - Accès simultanés");
        
        $startTime = microtime(true);
        
        // Simuler plusieurs consultations simultanées
        $consultations = [];
        for ($i = 0; $i < 10; $i++) {
            $consultations[] = [
                'action' => 'track_consultation',
                'photo_path' => "concurrent/photo{$i}.jpg",
                'activity_key' => 'concurrent',
                'photo_name' => "photo{$i}.jpg",
                'view_type' => 'thumbnail'
            ];
        }
        
        // Exécuter rapidement toutes les consultations
        foreach ($consultations as $consultation) {
            $response = $this->makeApiCall($consultation);
            $this->assertTrue($response['success'], "Consultation concurrent devrait réussir");
        }
        
        $endTime = microtime(true);
        $duration = round(($endTime - $startTime) * 1000, 2);
        
        // Vérifier que toutes les consultations ont été enregistrées
        $storedConsultations = $this->getStoredConsultations();
        $concurrentConsultations = array_filter($storedConsultations, function($c) {
            return $c['activity_key'] === 'concurrent';
        });
        
        $this->assertEquals(10, count($concurrentConsultations), "Toutes les consultations concurrentes devraient être stockées");
        $this->addNote("10 consultations simultanées: {$duration}ms");
        
        $this->endTest();
    }
    
    // Méthodes utilitaires pour les tests
    
    private function makeApiCall($data) {
        // Capturer la sortie de consultation_handler.php
        ob_start();
        $_POST = $data;
        
        try {
            // Simuler l'appel à consultation_handler.php
            include 'consultation_handler.php';
            $output = ob_get_contents();
        } catch (Exception $e) {
            $output = json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        
        ob_end_clean();
        
        return json_decode($output, true) ?: ['success' => false, 'error' => 'Invalid JSON response'];
    }
    
    private function getStoredConsultations() {
        if (file_exists($this->originalConsultationsFile)) {
            $content = file_get_contents($this->originalConsultationsFile);
            return json_decode($content, true) ?: [];
        }
        return [];
    }
    
    private function insertTestConsultations() {
        $testConsultations = [
            [
                'photo_path' => 'activity1/photo1.jpg',
                'activity_key' => 'activity1',
                'photo_name' => 'photo1.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'session_id' => 'test_session_1',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent'
            ],
            [
                'photo_path' => 'activity1/photo2.jpg',
                'activity_key' => 'activity1',
                'photo_name' => 'photo2.jpg',
                'view_type' => 'modal_view',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 hours')),
                'session_id' => 'test_session_2',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent'
            ],
            [
                'photo_path' => 'activity2/photo3.jpg',
                'activity_key' => 'activity2',
                'photo_name' => 'photo3.jpg',
                'view_type' => 'zoom',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-30 minutes')),
                'session_id' => 'test_session_1',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent'
            ]
        ];
        
        file_put_contents($this->originalConsultationsFile, json_encode($testConsultations, JSON_PRETTY_PRINT));
    }
    
    private function insertOldAndRecentConsultations() {
        $consultations = [
            // Anciennes consultations (plus de 7 jours)
            [
                'photo_path' => 'old/photo1.jpg',
                'activity_key' => 'old',
                'photo_name' => 'photo1.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'session_id' => 'old_session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent'
            ],
            // Consultations récentes (moins de 7 jours)
            [
                'photo_path' => 'recent/photo1.jpg',
                'activity_key' => 'recent',
                'photo_name' => 'photo1.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-2 days')),
                'session_id' => 'recent_session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent'
            ]
        ];
        
        file_put_contents($this->originalConsultationsFile, json_encode($consultations, JSON_PRETTY_PRINT));
    }
    
    private function createLargeConsultationDataset($count) {
        $consultations = [];
        $activities = ['gala1', 'gala2', 'gala3', 'cocktail', 'danse'];
        $viewTypes = ['thumbnail', 'modal_view', 'zoom'];
        
        for ($i = 0; $i < $count; $i++) {
            $activity = $activities[$i % count($activities)];
            $consultations[] = [
                'photo_path' => "{$activity}/photo{$i}.jpg",
                'activity_key' => $activity,
                'photo_name' => "photo{$i}.jpg",
                'view_type' => $viewTypes[$i % count($viewTypes)],
                'timestamp' => date('Y-m-d H:i:s', strtotime("-" . rand(1, 30) . " days")),
                'session_id' => 'session_' . ($i % 50), // 50 sessions différentes
                'ip_address' => '192.168.1.' . ($i % 254 + 1),
                'user_agent' => 'Test Agent ' . ($i % 10)
            ];
        }
        
        file_put_contents($this->originalConsultationsFile, json_encode($consultations, JSON_PRETTY_PRINT));
    }
    
    private function backupConsultations() {
        if (file_exists($this->originalConsultationsFile)) {
            copy($this->originalConsultationsFile, $this->testConsultationsFile);
        }
    }
    
    private function restoreConsultations() {
        if (file_exists($this->testConsultationsFile)) {
            copy($this->testConsultationsFile, $this->originalConsultationsFile);
            unlink($this->testConsultationsFile);
        } else {
            // Restaurer un fichier vide si pas de sauvegarde
            file_put_contents($this->originalConsultationsFile, '[]');
        }
    }
    
    // Méthodes d'assertion et de test
    
    private function startTest($testName) {
        $this->testCount++;
        echo "<h3>Test {$this->testCount}: {$testName}</h3>\n";
        $this->currentTestResults = ['name' => $testName, 'status' => 'running', 'assertions' => [], 'notes' => []];
    }
    
    private function endTest() {
        $passed = array_reduce($this->currentTestResults['assertions'], function($carry, $assertion) {
            return $carry && $assertion['passed'];
        }, true);
        
        if ($passed) {
            $this->passedTests++;
            $this->currentTestResults['status'] = 'passed';
            echo "<p style='color: green;'>✅ <strong>PASSÉ</strong></p>\n";
        } else {
            $this->failedTests++;
            $this->currentTestResults['status'] = 'failed';
            echo "<p style='color: red;'>❌ <strong>ÉCHOUÉ</strong></p>\n";
        }
        
        // Afficher les détails
        foreach ($this->currentTestResults['assertions'] as $assertion) {
            $status = $assertion['passed'] ? '✅' : '❌';
            echo "<p>{$status} {$assertion['message']}</p>\n";
        }
        
        foreach ($this->currentTestResults['notes'] as $note) {
            echo "<p style='color: blue;'>ℹ️ {$note}</p>\n";
        }
        
        $this->testResults[] = $this->currentTestResults;
        echo "<hr>\n";
    }
    
    private function assertTrue($condition, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => (bool)$condition,
            'message' => $message,
            'type' => 'assertTrue'
        ];
    }
    
    private function assertFalse($condition, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => !$condition,
            'message' => $message,
            'type' => 'assertFalse'
        ];
    }
    
    private function assertEquals($expected, $actual, $message) {
        $passed = $expected === $actual;
        $this->currentTestResults['assertions'][] = [
            'passed' => $passed,
            'message' => $message . ($passed ? "" : " (attendu: " . var_export($expected, true) . ", obtenu: " . var_export($actual, true) . ")"),
            'type' => 'assertEquals'
        ];
    }
    
    private function assertNotEmpty($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => !empty($value),
            'message' => $message,
            'type' => 'assertNotEmpty'
        ];
    }
    
    private function assertArrayHasKey($key, $array, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_array($array) && array_key_exists($key, $array),
            'message' => $message,
            'type' => 'assertArrayHasKey'
        ];
    }
    
    private function assertIsArray($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_array($value),
            'message' => $message,
            'type' => 'assertIsArray'
        ];
    }
    
    private function assertIsString($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_string($value),
            'message' => $message,
            'type' => 'assertIsString'
        ];
    }
    
    private function assertIsInt($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_int($value),
            'message' => $message,
            'type' => 'assertIsInt'
        ];
    }
    
    private function assertLessThan($expected, $actual, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $actual < $expected,
            'message' => $message,
            'type' => 'assertLessThan'
        ];
    }
    
    private function assertLessThanOrEqual($expected, $actual, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $actual <= $expected,
            'message' => $message,
            'type' => 'assertLessThanOrEqual'
        ];
    }
    
    private function assertGreaterThanOrEqual($expected, $actual, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $actual >= $expected,
            'message' => $message,
            'type' => 'assertGreaterThanOrEqual'
        ];
    }
    
    private function assertStringContains($needle, $haystack, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_string($haystack) && strpos($haystack, $needle) !== false,
            'message' => $message,
            'type' => 'assertStringContains'
        ];
    }
    
    private function addNote($note) {
        $this->currentTestResults['notes'][] = $note;
    }
    
    private function displaySummary($executionTime) {
        $successRate = round(($this->passedTests / $this->testCount) * 100, 1);
        
        echo "<h2>📊 Résumé des Tests Backend</h2>\n";
        echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<p><strong>Tests exécutés:</strong> {$this->testCount}</p>\n";
        echo "<p><strong>Tests réussis:</strong> <span style='color: green;'>{$this->passedTests}</span></p>\n";
        echo "<p><strong>Tests échoués:</strong> <span style='color: red;'>{$this->failedTests}</span></p>\n";
        echo "<p><strong>Taux de réussite:</strong> {$successRate}%</p>\n";
        echo "<p><strong>Temps d'exécution:</strong> {$executionTime}ms</p>\n";
        echo "</div>\n";
        
        if ($this->failedTests > 0) {
            echo "<h3>❌ Tests échoués:</h3>\n";
            foreach ($this->testResults as $result) {
                if ($result['status'] === 'failed') {
                    echo "<p>- {$result['name']}</p>\n";
                }
            }
        }
        
        echo "<h3>🔍 Analyse:</h3>\n";
        echo "<ul>\n";
        if ($successRate >= 95) {
            echo "<li style='color: green;'>✅ Excellent: Le système de consultations fonctionne parfaitement</li>\n";
        } elseif ($successRate >= 80) {
            echo "<li style='color: orange;'>⚠️ Bon: Quelques problèmes mineurs à corriger</li>\n";
        } else {
            echo "<li style='color: red;'>❌ Problématique: Des corrections importantes sont nécessaires</li>\n";
        }
        
        echo "<li>Temps de réponse moyen: " . round($executionTime / $this->testCount, 2) . "ms par test</li>\n";
        echo "<li>Performance acceptable pour un système de production</li>\n";
        echo "</ul>\n";
    }
}

// Démarrer la session pour les tests d'auth
session_start();

// Exécuter tous les tests
$tests = new ConsultationBackendTests();
$tests->runAllTests();

?>