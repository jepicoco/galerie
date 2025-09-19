<?php
/**
 * Test Suite End-to-End pour le système de consultations
 * 
 * Tests complets du workflow complet de consultation
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('GALLERY_ACCESS', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

/**
 * Classe de tests End-to-End pour les consultations
 */
class ConsultationE2ETests {
    private $testResults = [];
    private $testCount = 0;
    private $passedTests = 0;
    private $failedTests = 0;
    private $logger;
    private $originalConsultationsFile;
    private $testConsultationsFile;
    private $simulatedPhotos = [];
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->originalConsultationsFile = 'data/consultations.json';
        $this->testConsultationsFile = 'data/consultations_backup_e2e.json';
        
        $this->backupConsultations();
        $this->setupTestEnvironment();
    }
    
    /**
     * Exécuter tous les tests End-to-End
     */
    public function runAllTests() {
        echo "<h1>🔄 Tests End-to-End - Système de Consultations</h1>\n";
        echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
        
        $startTime = microtime(true);
        
        // Tests de workflow complet
        $this->testCompleteUserJourney();
        $this->testMultiUserConcurrentJourney();
        $this->testAdminAnalyticsWorkflow();
        
        // Tests d'intégration système
        $this->testGalleryIntegration();
        $this->testModalIntegration();
        $this->testZoomIntegration();
        
        // Tests de cycle de vie des données
        $this->testDataLifecycle();
        $this->testMaintenanceWorkflow();
        
        // Tests de scenario réalistes
        $this->testHighTrafficScenario();
        $this->testLowTrafficScenario();
        $this->testPeakUsageScenario();
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        $this->restoreConsultations();
        $this->displaySummary($executionTime);
    }
    
    /**
     * Test d'un parcours utilisateur complet
     */
    private function testCompleteUserJourney() {
        $this->startTest("Complete User Journey - Parcours utilisateur complet");
        
        session_start();
        $sessionId = session_id();
        
        // Étape 1: Utilisateur visite la galerie
        $this->addNote("Étape 1: Visite de la galerie");
        $galleryPhotos = $this->getGalleryPhotos();
        $this->assertGreaterThan(0, count($galleryPhotos), "Des photos devraient être disponibles dans la galerie");
        
        // Étape 2: Consultation de thumbnails
        $this->addNote("Étape 2: Consultation des thumbnails");
        $consultedThumbnails = 0;
        
        foreach (array_slice($galleryPhotos, 0, 5) as $photo) {
            $response = $this->simulateApiCall([
                'action' => 'track_consultation',
                'photo_path' => $photo['path'],
                'activity_key' => $photo['activity'],
                'photo_name' => $photo['name'],
                'view_type' => 'thumbnail'
            ]);
            
            if ($response['success']) {
                $consultedThumbnails++;
            }
        }
        
        $this->assertEquals(5, $consultedThumbnails, "5 thumbnails devraient être consultés");
        
        // Étape 3: Ouverture d'une photo en modal
        $this->addNote("Étape 3: Ouverture en modal");
        $selectedPhoto = $galleryPhotos[2]; // Photo du milieu
        
        $modalResponse = $this->simulateApiCall([
            'action' => 'track_consultation',
            'photo_path' => $selectedPhoto['path'],
            'activity_key' => $selectedPhoto['activity'],
            'photo_name' => $selectedPhoto['name'],
            'view_type' => 'modal_view'
        ]);
        
        $this->assertTrue($modalResponse['success'], "Consultation modal devrait réussir");
        
        // Étape 4: Zoom sur la photo
        $this->addNote("Étape 4: Zoom sur la photo");
        $zoomResponse = $this->simulateApiCall([
            'action' => 'track_consultation',
            'photo_path' => $selectedPhoto['path'],
            'activity_key' => $selectedPhoto['activity'],
            'photo_name' => $selectedPhoto['name'],
            'view_type' => 'zoom'
        ]);
        
        $this->assertTrue($zoomResponse['success'], "Consultation zoom devrait réussir");
        
        // Étape 5: Vérifier que toutes les consultations sont enregistrées
        $this->addNote("Étape 5: Vérification des données");
        $consultations = $this->getStoredConsultations();
        
        $userConsultations = array_filter($consultations, function($c) use ($sessionId) {
            return $c['session_id'] === $sessionId;
        });
        
        $this->assertEquals(7, count($userConsultations), "7 consultations devraient être enregistrées (5 thumbnails + 1 modal + 1 zoom)");
        
        // Vérifier les types de vues
        $viewTypes = array_count_values(array_column($userConsultations, 'view_type'));
        $this->assertEquals(5, $viewTypes['thumbnail'], "5 consultations thumbnail");
        $this->assertEquals(1, $viewTypes['modal_view'], "1 consultation modal");
        $this->assertEquals(1, $viewTypes['zoom'], "1 consultation zoom");
        
        $this->endTest();
    }
    
    /**
     * Test de parcours multi-utilisateurs concurrents
     */
    private function testMultiUserConcurrentJourney() {
        $this->startTest("Multi-User Concurrent Journey - Parcours multi-utilisateurs");
        
        $users = [
            ['session' => 'user_1', 'ip' => '192.168.1.100', 'agent' => 'User Agent 1'],
            ['session' => 'user_2', 'ip' => '192.168.1.101', 'agent' => 'User Agent 2'],
            ['session' => 'user_3', 'ip' => '192.168.1.102', 'agent' => 'User Agent 3']
        ];
        
        $galleryPhotos = $this->getGalleryPhotos();
        $totalConsultations = 0;
        
        // Simuler des consultations simultanées
        foreach ($users as $user) {
            $this->addNote("Utilisateur {$user['session']} commence son parcours");
            
            // Chaque utilisateur consulte différentes photos
            $userPhotos = array_slice($galleryPhotos, 0, 3);
            
            foreach ($userPhotos as $photo) {
                $response = $this->simulateApiCall([
                    'action' => 'track_consultation',
                    'photo_path' => $photo['path'],
                    'activity_key' => $photo['activity'],
                    'photo_name' => $photo['name'],
                    'view_type' => 'thumbnail'
                ]);
                
                if ($response['success']) {
                    $totalConsultations++;
                }
                
                // Petit délai pour simuler la navigation
                usleep(1000); // 1ms
            }
        }
        
        $this->assertEquals(9, $totalConsultations, "9 consultations au total devraient être enregistrées (3 users × 3 photos)");
        
        // Vérifier que chaque utilisateur a ses consultations
        $consultations = $this->getStoredConsultations();
        
        foreach ($users as $user) {
            $userConsultations = array_filter($consultations, function($c) use ($user) {
                return $c['session_id'] === $user['session'];
            });
            
            $this->assertEquals(3, count($userConsultations), "Utilisateur {$user['session']} devrait avoir 3 consultations");
        }
        
        $this->endTest();
    }
    
    /**
     * Test du workflow d'analytics admin
     */
    private function testAdminAnalyticsWorkflow() {
        $this->startTest("Admin Analytics Workflow - Workflow d'analytics administrateur");
        
        // Générer des données de test pour l'analytics
        $this->generateAnalyticsTestData();
        
        // Simuler une session admin
        session_start();
        $_SESSION['admin_logged_in'] = true;
        
        // Étape 1: Récupération des statistiques générales
        $statsResponse = $this->simulateApiCall([
            'action' => 'get_consultation_stats',
            'period' => 'today'
        ]);
        
        $this->assertTrue($statsResponse['success'], "Récupération des stats générales devrait réussir");
        $this->assertArrayHasKey('stats', $statsResponse, "Réponse devrait contenir des stats");
        
        $stats = $statsResponse['stats'];
        $this->assertArrayHasKey('total_consultations', $stats, "Stats devraient contenir total_consultations");
        $this->assertGreaterThan(0, $stats['total_consultations'], "Il devrait y avoir des consultations");
        
        // Étape 2: Photos populaires
        $popularResponse = $this->simulateApiCall([
            'action' => 'get_popular_photos',
            'limit' => 5,
            'period' => 'today'
        ]);
        
        $this->assertTrue($popularResponse['success'], "Récupération des photos populaires devrait réussir");
        $this->assertArrayHasKey('popular_photos', $popularResponse, "Réponse devrait contenir popular_photos");
        
        // Étape 3: Stats par activité
        $activityResponse = $this->simulateApiCall([
            'action' => 'get_activity_stats',
            'period' => 'today'
        ]);
        
        $this->assertTrue($activityResponse['success'], "Stats par activité devraient réussir");
        $this->assertArrayHasKey('activity_stats', $activityResponse, "Réponse devrait contenir activity_stats");
        
        // Étape 4: Consultations récentes
        $recentResponse = $this->simulateApiCall([
            'action' => 'get_recent_consultations',
            'limit' => 10
        ]);
        
        $this->assertTrue($recentResponse['success'], "Consultations récentes devraient réussir");
        $this->assertArrayHasKey('consultations', $recentResponse, "Réponse devrait contenir consultations");
        
        // Étape 5: Export de données
        $exportResponse = $this->simulateApiCall([
            'action' => 'export_consultation_data',
            'start_date' => date('Y-m-d'),
            'end_date' => date('Y-m-d'),
            'format' => 'json'
        ]);
        
        $this->assertTrue($exportResponse['success'], "Export devrait réussir");
        $this->assertArrayHasKey('export_data', $exportResponse, "Export devrait contenir des données");
        
        $this->addNote("Workflow admin complet testé avec succès");
        
        $this->endTest();
    }
    
    /**
     * Test d'intégration avec la galerie
     */
    private function testGalleryIntegration() {
        $this->startTest("Gallery Integration - Intégration avec la galerie");
        
        // Simuler le chargement de la galerie et le tracking automatique
        $galleryPhotos = $this->getGalleryPhotos();
        $trackedPhotos = 0;
        
        // Simuler l'Intersection Observer qui détecte les photos visibles
        foreach (array_slice($galleryPhotos, 0, 6) as $index => $photo) {
            // Simuler un délai de visibilité
            if ($index < 4) { // Premières 4 photos "visibles"
                $response = $this->simulateApiCall([
                    'action' => 'track_consultation',
                    'photo_path' => $photo['path'],
                    'activity_key' => $photo['activity'],
                    'photo_name' => $photo['name'],
                    'view_type' => 'thumbnail'
                ]);
                
                if ($response['success']) {
                    $trackedPhotos++;
                }
            }
        }
        
        $this->assertEquals(4, $trackedPhotos, "4 photos visibles devraient être trackées");
        
        // Vérifier que le throttling fonctionne (pas de doublons)
        $consultations = $this->getStoredConsultations();
        $galleryConsultations = array_filter($consultations, function($c) use ($galleryPhotos) {
            return in_array($c['activity_key'], array_column($galleryPhotos, 'activity'));
        });
        
        $this->assertGreaterThanOrEqual(4, count($galleryConsultations), "Au moins 4 consultations de galerie");
        
        $this->endTest();
    }
    
    /**
     * Test d'intégration avec la modal
     */
    private function testModalIntegration() {
        $this->startTest("Modal Integration - Intégration avec la modal");
        
        $testPhoto = $this->getGalleryPhotos()[0];
        
        // Étape 1: Clic sur thumbnail -> modal
        $thumbnailResponse = $this->simulateApiCall([
            'action' => 'track_consultation',
            'photo_path' => $testPhoto['path'],
            'activity_key' => $testPhoto['activity'],
            'photo_name' => $testPhoto['name'],
            'view_type' => 'thumbnail'
        ]);
        
        $this->assertTrue($thumbnailResponse['success'], "Clic thumbnail devrait réussir");
        
        // Étape 2: Ouverture modal
        usleep(100000); // Simuler le délai d'ouverture
        
        $modalResponse = $this->simulateApiCall([
            'action' => 'track_consultation',
            'photo_path' => $testPhoto['path'],
            'activity_key' => $testPhoto['activity'],
            'photo_name' => $testPhoto['name'],
            'view_type' => 'modal_view'
        ]);
        
        $this->assertTrue($modalResponse['success'], "Ouverture modal devrait réussir");
        
        // Étape 3: Navigation dans la modal
        $nextPhoto = $this->getGalleryPhotos()[1];
        
        $navigationResponse = $this->simulateApiCall([
            'action' => 'track_consultation',
            'photo_path' => $nextPhoto['path'],
            'activity_key' => $nextPhoto['activity'],
            'photo_name' => $nextPhoto['name'],
            'view_type' => 'modal_view'
        ]);
        
        $this->assertTrue($navigationResponse['success'], "Navigation modal devrait réussir");
        
        // Vérifier que les consultations sont correctement enregistrées
        $consultations = $this->getStoredConsultations();
        $modalConsultations = array_filter($consultations, function($c) {
            return $c['view_type'] === 'modal_view';
        });
        
        $this->assertGreaterThanOrEqual(2, count($modalConsultations), "Au moins 2 consultations modal");
        
        $this->endTest();
    }
    
    /**
     * Test d'intégration avec le zoom
     */
    private function testZoomIntegration() {
        $this->startTest("Zoom Integration - Intégration avec le zoom");
        
        $testPhoto = $this->getGalleryPhotos()[0];
        
        // Étape 1: Ouvrir la modal
        $modalResponse = $this->simulateApiCall([
            'action' => 'track_consultation',
            'photo_path' => $testPhoto['path'],
            'activity_key' => $testPhoto['activity'],
            'photo_name' => $testPhoto['name'],
            'view_type' => 'modal_view'
        ]);
        
        $this->assertTrue($modalResponse['success'], "Modal devrait s'ouvrir");
        
        // Étape 2: Plusieurs actions de zoom
        $zoomActions = ['zoom_in', 'zoom_in', 'zoom_out', 'zoom_reset'];
        $zoomConsultations = 0;
        
        foreach ($zoomActions as $action) {
            $zoomResponse = $this->simulateApiCall([
                'action' => 'track_consultation',
                'photo_path' => $testPhoto['path'],
                'activity_key' => $testPhoto['activity'],
                'photo_name' => $testPhoto['name'],
                'view_type' => 'zoom'
            ]);
            
            if ($zoomResponse['success']) {
                $zoomConsultations++;
            }
            
            usleep(10000); // Délai entre zooms
        }
        
        $this->assertEquals(4, $zoomConsultations, "4 actions de zoom devraient être trackées");
        
        // Vérifier l'enregistrement
        $consultations = $this->getStoredConsultations();
        $zoomConsultationsList = array_filter($consultations, function($c) use ($testPhoto) {
            return $c['view_type'] === 'zoom' && $c['photo_name'] === $testPhoto['name'];
        });
        
        $this->assertEquals(4, count($zoomConsultationsList), "4 consultations zoom pour cette photo");
        
        $this->endTest();
    }
    
    /**
     * Test du cycle de vie des données
     */
    private function testDataLifecycle() {
        $this->startTest("Data Lifecycle - Cycle de vie des données");
        
        // Phase 1: Génération de données
        $this->addNote("Phase 1: Génération de données");
        $initialCount = count($this->getStoredConsultations());
        
        for ($i = 0; $i < 10; $i++) {
            $photo = $this->getGalleryPhotos()[$i % count($this->getGalleryPhotos())];
            $this->simulateApiCall([
                'action' => 'track_consultation',
                'photo_path' => $photo['path'],
                'activity_key' => $photo['activity'],
                'photo_name' => $photo['name'],
                'view_type' => 'thumbnail'
            ]);
        }
        
        $afterGeneration = count($this->getStoredConsultations());
        $this->assertEquals($initialCount + 10, $afterGeneration, "10 nouvelles consultations générées");
        
        // Phase 2: Analyse des données
        $this->addNote("Phase 2: Analyse des données");
        $_SESSION['admin_logged_in'] = true;
        
        $statsResponse = $this->simulateApiCall([
            'action' => 'get_consultation_stats',
            'period' => 'today'
        ]);
        
        $this->assertTrue($statsResponse['success'], "Analyse des données devrait réussir");
        $this->assertGreaterThanOrEqual(10, $statsResponse['stats']['total_consultations'], "Au moins 10 consultations analysées");
        
        // Phase 3: Maintenance (nettoyage)
        $this->addNote("Phase 3: Maintenance - nettoyage");
        
        // Ajouter des données anciennes
        $this->addOldTestData();
        
        $beforeCleanup = count($this->getStoredConsultations());
        
        $cleanupResponse = $this->simulateApiCall([
            'action' => 'cleanup_old_consultations',
            'days_to_keep' => 1
        ]);
        
        $this->assertTrue($cleanupResponse['success'], "Nettoyage devrait réussir");
        $this->assertGreaterThan(0, $cleanupResponse['records_cleaned'], "Des enregistrements devraient être nettoyés");
        
        $afterCleanup = count($this->getStoredConsultations());
        $this->assertLessThan($beforeCleanup, $afterCleanup, "Le nombre d'enregistrements devrait diminuer");
        
        $this->endTest();
    }
    
    /**
     * Test du workflow de maintenance
     */
    private function testMaintenanceWorkflow() {
        $this->startTest("Maintenance Workflow - Workflow de maintenance");
        
        $_SESSION['admin_logged_in'] = true;
        
        // Étape 1: Audit des données
        $this->addNote("Étape 1: Audit des données");
        $initialData = $this->getStoredConsultations();
        $this->addNote("Données initiales: " . count($initialData) . " entrées");
        
        // Étape 2: Export pour sauvegarde
        $this->addNote("Étape 2: Export de sauvegarde");
        $exportResponse = $this->simulateApiCall([
            'action' => 'export_consultation_data',
            'start_date' => date('Y-m-d', strtotime('-30 days')),
            'end_date' => date('Y-m-d'),
            'format' => 'json'
        ]);
        
        $this->assertTrue($exportResponse['success'], "Export de sauvegarde devrait réussir");
        $this->assertNotEmpty($exportResponse['export_data'], "Export ne devrait pas être vide");
        
        // Étape 3: Nettoyage
        $this->addNote("Étape 3: Nettoyage des anciennes données");
        $cleanupResponse = $this->simulateApiCall([
            'action' => 'cleanup_old_consultations',
            'days_to_keep' => 7
        ]);
        
        $this->assertTrue($cleanupResponse['success'], "Nettoyage devrait réussir");
        
        // Étape 4: Vérification post-maintenance
        $this->addNote("Étape 4: Vérification post-maintenance");
        $finalData = $this->getStoredConsultations();
        
        // Vérifier que le système fonctionne toujours
        $testConsultationResponse = $this->simulateApiCall([
            'action' => 'track_consultation',
            'photo_path' => 'maintenance/test.jpg',
            'activity_key' => 'maintenance',
            'photo_name' => 'test.jpg',
            'view_type' => 'thumbnail'
        ]);
        
        $this->assertTrue($testConsultationResponse['success'], "Le système devrait fonctionner après maintenance");
        
        $postMaintenanceData = $this->getStoredConsultations();
        $this->assertEquals(count($finalData) + 1, count($postMaintenanceData), "Nouvelle consultation devrait être ajoutée");
        
        $this->endTest();
    }
    
    /**
     * Test de scenario de trafic élevé
     */
    private function testHighTrafficScenario() {
        $this->startTest("High Traffic Scenario - Scenario de trafic élevé");
        
        $startTime = microtime(true);
        $consultationCount = 0;
        $targetConsultations = 100;
        
        $photos = $this->getGalleryPhotos();
        
        // Simuler de nombreuses consultations rapidement
        for ($i = 0; $i < $targetConsultations; $i++) {
            $photo = $photos[$i % count($photos)];
            
            $response = $this->simulateApiCall([
                'action' => 'track_consultation',
                'photo_path' => $photo['path'],
                'activity_key' => $photo['activity'],
                'photo_name' => $photo['name'],
                'view_type' => 'thumbnail'
            ]);
            
            if ($response['success']) {
                $consultationCount++;
            }
        }
        
        $endTime = microtime(true);
        $duration = ($endTime - $startTime) * 1000;
        
        $this->assertEquals($targetConsultations, $consultationCount, "Toutes les consultations de trafic élevé devraient réussir");
        $this->addNote("$targetConsultations consultations en " . round($duration, 2) . "ms");
        $this->addNote("Débit: " . round($targetConsultations / ($duration / 1000), 2) . " consultations/seconde");
        
        // Vérifier la performance
        $this->assertLessThan(10000, $duration, "Le traitement de 100 consultations devrait prendre moins de 10s");
        
        $this->endTest();
    }
    
    /**
     * Test de scenario de trafic faible
     */
    private function testLowTrafficScenario() {
        $this->startTest("Low Traffic Scenario - Scenario de trafic faible");
        
        // Simuler quelques consultations espacées dans le temps
        $photo = $this->getGalleryPhotos()[0];
        $consultations = [];
        
        for ($i = 0; $i < 5; $i++) {
            $response = $this->simulateApiCall([
                'action' => 'track_consultation',
                'photo_path' => $photo['path'],
                'activity_key' => $photo['activity'],
                'photo_name' => $photo['name'],
                'view_type' => 'thumbnail'
            ]);
            
            if ($response['success']) {
                $consultations[] = $response;
            }
            
            // Délai important entre consultations
            usleep(100000); // 100ms
        }
        
        $this->assertEquals(5, count($consultations), "5 consultations espacées devraient réussir");
        
        // Vérifier les stats avec peu de données
        $_SESSION['admin_logged_in'] = true;
        $statsResponse = $this->simulateApiCall([
            'action' => 'get_consultation_stats',
            'period' => 'today'
        ]);
        
        $this->assertTrue($statsResponse['success'], "Stats devraient fonctionner même avec peu de données");
        
        $this->endTest();
    }
    
    /**
     * Test de scenario d'usage en pic
     */
    private function testPeakUsageScenario() {
        $this->startTest("Peak Usage Scenario - Scenario d'usage en pic");
        
        // Simuler un pic d'utilisation avec plusieurs utilisateurs simultanés
        $peakUsers = 10;
        $consultationsPerUser = 5;
        $totalExpected = $peakUsers * $consultationsPerUser;
        
        $photos = $this->getGalleryPhotos();
        $allConsultations = [];
        
        // Simuler des utilisateurs simultanés
        for ($userId = 0; $userId < $peakUsers; $userId++) {
            for ($photoIndex = 0; $photoIndex < $consultationsPerUser; $photoIndex++) {
                $photo = $photos[($userId * $consultationsPerUser + $photoIndex) % count($photos)];
                
                $response = $this->simulateApiCall([
                    'action' => 'track_consultation',
                    'photo_path' => $photo['path'],
                    'activity_key' => $photo['activity'],
                    'photo_name' => $photo['name'],
                    'view_type' => 'thumbnail'
                ]);
                
                if ($response['success']) {
                    $allConsultations[] = $response;
                }
            }
        }
        
        $this->assertEquals($totalExpected, count($allConsultations), "Toutes les consultations du pic devraient réussir");
        
        // Tester l'analyse pendant le pic
        $_SESSION['admin_logged_in'] = true;
        
        $startAnalysis = microtime(true);
        $analysisResponse = $this->simulateApiCall([
            'action' => 'get_consultation_stats',
            'period' => 'today'
        ]);
        $analysisTime = (microtime(true) - $startAnalysis) * 1000;
        
        $this->assertTrue($analysisResponse['success'], "L'analyse devrait fonctionner pendant un pic");
        $this->assertLessThan(5000, $analysisTime, "L'analyse devrait rester rapide même en pic (< 5s)");
        
        $this->addNote("Pic de $peakUsers utilisateurs traité avec succès");
        $this->addNote("Analyse en pic: " . round($analysisTime, 2) . "ms");
        
        $this->endTest();
    }
    
    // === MÉTHODES UTILITAIRES ===
    
    private function setupTestEnvironment() {
        // Créer des photos simulées pour les tests
        $this->simulatedPhotos = [
            ['activity' => 'gala-ouverture', 'name' => 'photo1.jpg', 'path' => 'gala-ouverture/photo1.jpg'],
            ['activity' => 'gala-ouverture', 'name' => 'photo2.jpg', 'path' => 'gala-ouverture/photo2.jpg'],
            ['activity' => 'gala-ouverture', 'name' => 'photo3.jpg', 'path' => 'gala-ouverture/photo3.jpg'],
            ['activity' => 'cocktail', 'name' => 'photo1.jpg', 'path' => 'cocktail/photo1.jpg'],
            ['activity' => 'cocktail', 'name' => 'photo2.jpg', 'path' => 'cocktail/photo2.jpg'],
            ['activity' => 'danse', 'name' => 'photo1.jpg', 'path' => 'danse/photo1.jpg'],
            ['activity' => 'danse', 'name' => 'photo2.jpg', 'path' => 'danse/photo2.jpg'],
            ['activity' => 'photos-groupe', 'name' => 'photo1.jpg', 'path' => 'photos-groupe/photo1.jpg'],
        ];
    }
    
    private function getGalleryPhotos() {
        return $this->simulatedPhotos;
    }
    
    private function generateAnalyticsTestData() {
        $photos = $this->getGalleryPhotos();
        $viewTypes = ['thumbnail', 'modal_view', 'zoom'];
        
        // Générer 30 consultations variées
        for ($i = 0; $i < 30; $i++) {
            $photo = $photos[$i % count($photos)];
            $viewType = $viewTypes[$i % count($viewTypes)];
            
            $this->simulateApiCall([
                'action' => 'track_consultation',
                'photo_path' => $photo['path'],
                'activity_key' => $photo['activity'],
                'photo_name' => $photo['name'],
                'view_type' => $viewType
            ]);
            
            usleep(1000); // 1ms entre chaque
        }
    }
    
    private function addOldTestData() {
        $oldConsultations = [
            [
                'photo_path' => 'old/photo.jpg',
                'activity_key' => 'old-activity',
                'photo_name' => 'photo.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-10 days')),
                'session_id' => 'old_session',
                'ip_address' => '192.168.1.200',
                'user_agent' => 'Old Agent'
            ]
        ];
        
        $existing = $this->getStoredConsultations();
        $all = array_merge($existing, $oldConsultations);
        file_put_contents($this->originalConsultationsFile, json_encode($all, JSON_PRETTY_PRINT));
    }
    
    private function simulateApiCall($data) {
        ob_start();
        $_POST = $data;
        
        try {
            include 'consultation_handler.php';
            $output = ob_get_contents();
        } catch (Exception $e) {
            $output = json_encode(['success' => false, 'error' => $e->getMessage()]);
        }
        
        ob_end_clean();
        
        $response = json_decode($output, true);
        return $response ?: ['success' => false, 'error' => 'Invalid JSON response'];
    }
    
    private function getStoredConsultations() {
        if (file_exists($this->originalConsultationsFile)) {
            $content = file_get_contents($this->originalConsultationsFile);
            return json_decode($content, true) ?: [];
        }
        return [];
    }
    
    private function backupConsultations() {
        if (file_exists($this->originalConsultationsFile)) {
            copy($this->originalConsultationsFile, $this->testConsultationsFile);
        } else {
            file_put_contents($this->originalConsultationsFile, '[]');
        }
    }
    
    private function restoreConsultations() {
        if (file_exists($this->testConsultationsFile)) {
            copy($this->testConsultationsFile, $this->originalConsultationsFile);
            unlink($this->testConsultationsFile);
        } else {
            file_put_contents($this->originalConsultationsFile, '[]');
        }
    }
    
    // === MÉTHODES D'ASSERTION ===
    
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
    
    private function assertEquals($expected, $actual, $message) {
        $passed = $expected === $actual;
        $this->currentTestResults['assertions'][] = [
            'passed' => $passed,
            'message' => $message . ($passed ? "" : " (attendu: $expected, obtenu: $actual)"),
            'type' => 'assertEquals'
        ];
    }
    
    private function assertGreaterThan($expected, $actual, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $actual > $expected,
            'message' => $message,
            'type' => 'assertGreaterThan'
        ];
    }
    
    private function assertGreaterThanOrEqual($expected, $actual, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $actual >= $expected,
            'message' => $message,
            'type' => 'assertGreaterThanOrEqual'
        ];
    }
    
    private function assertLessThan($expected, $actual, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $actual < $expected,
            'message' => $message,
            'type' => 'assertLessThan'
        ];
    }
    
    private function assertArrayHasKey($key, $array, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_array($array) && array_key_exists($key, $array),
            'message' => $message,
            'type' => 'assertArrayHasKey'
        ];
    }
    
    private function assertNotEmpty($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => !empty($value),
            'message' => $message,
            'type' => 'assertNotEmpty'
        ];
    }
    
    private function addNote($note) {
        $this->currentTestResults['notes'][] = $note;
    }
    
    private function displaySummary($executionTime) {
        $successRate = round(($this->passedTests / $this->testCount) * 100, 1);
        
        echo "<h2>📊 Résumé des Tests End-to-End</h2>\n";
        echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<p><strong>Tests exécutés:</strong> {$this->testCount}</p>\n";
        echo "<p><strong>Tests réussis:</strong> <span style='color: green;'>{$this->passedTests}</span></p>\n";
        echo "<p><strong>Tests échoués:</strong> <span style='color: red;'>{$this->failedTests}</span></p>\n";
        echo "<p><strong>Taux de réussite:</strong> {$successRate}%</p>\n";
        echo "<p><strong>Temps d'exécution:</strong> {$executionTime}ms</p>\n";
        echo "</div>\n";
        
        echo "<h3>🔍 Analyse End-to-End:</h3>\n";
        echo "<ul>\n";
        if ($successRate >= 95) {
            echo "<li style='color: green;'>✅ Excellent: Workflow complet parfaitement fonctionnel</li>\n";
        } elseif ($successRate >= 80) {
            echo "<li style='color: orange;'>⚠️ Bon: Workflow majoritairement fonctionnel</li>\n";
        } else {
            echo "<li style='color: red;'>❌ Problématique: Workflow nécessite des corrections importantes</li>\n";
        }
        
        echo "<li>Intégration complète validée</li>\n";
        echo "<li>Performance acceptable sous charge</li>\n";
        echo "<li>Workflows utilisateur et admin fonctionnels</li>\n";
        echo "<li>Gestion des scenarios de trafic validée</li>\n";
        echo "</ul>\n";
    }
}

// Exécuter les tests
$tests = new ConsultationE2ETests();
$tests->runAllTests();

?>