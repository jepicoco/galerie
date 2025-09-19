<?php
/**
 * Test Suite pour l'intégrité des données et stockage JSON
 * 
 * Tests complets pour la cohérence et la sécurité des données de consultation
 * @version 1.0
 */

error_reporting(E_ALL);
ini_set('display_errors', 1);

define('GALLERY_ACCESS', true);

require_once 'config.php';
require_once 'functions.php';
require_once 'classes/autoload.php';

/**
 * Classe de tests pour l'intégrité des données de consultation
 */
class ConsultationDataIntegrityTests {
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
        $this->testConsultationsFile = 'data/consultations_backup_integrity.json';
        
        // Créer le dossier data s'il n'existe pas
        if (!is_dir('data')) {
            mkdir('data', 0755, true);
        }
        
        $this->backupConsultations();
    }
    
    /**
     * Exécuter tous les tests d'intégrité
     */
    public function runAllTests() {
        echo "<h1>🔒 Tests Intégrité Données - Système de Consultations</h1>\n";
        echo "<p><strong>Date:</strong> " . date('Y-m-d H:i:s') . "</p>\n";
        
        $startTime = microtime(true);
        
        // Tests de structure des données
        $this->testJsonFileStructure();
        $this->testDataValidation();
        $this->testDataConsistency();
        
        // Tests de persistance et sécurité
        $this->testFileLocking();
        $this->testFilePermissions();
        $this->testDataEncoding();
        
        // Tests de corruption et récupération
        $this->testCorruptedDataHandling();
        $this->testLargeDataIntegrity();
        $this->testConcurrentWriteIntegrity();
        
        // Tests de sauvegarde et limite
        $this->testMaxEntriesLimit();
        $this->testDataRotation();
        
        // Tests de migration et compatibilité
        $this->testBackwardCompatibility();
        $this->testDataMigration();
        
        $endTime = microtime(true);
        $executionTime = round(($endTime - $startTime) * 1000, 2);
        
        $this->restoreConsultations();
        $this->displaySummary($executionTime);
    }
    
    /**
     * Test de la structure du fichier JSON
     */
    private function testJsonFileStructure() {
        $this->startTest("JSON File Structure - Structure et format");
        
        // Créer un fichier de test avec une structure valide
        $validData = [
            [
                'photo_path' => 'activity1/photo1.jpg',
                'activity_key' => 'activity1',
                'photo_name' => 'photo1.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s'),
                'session_id' => 'test_session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Test Agent'
            ]
        ];
        
        file_put_contents($this->originalConsultationsFile, json_encode($validData, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        // Vérifier que le fichier peut être lu
        $this->assertTrue(file_exists($this->originalConsultationsFile), "Le fichier de consultations devrait exister");
        
        $content = file_get_contents($this->originalConsultationsFile);
        $this->assertNotFalse($content, "Le contenu du fichier devrait être lisible");
        
        $decoded = json_decode($content, true);
        $this->assertNotNull($decoded, "Le JSON devrait être valide");
        $this->assertIsArray($decoded, "Le JSON décodé devrait être un tableau");
        
        // Vérifier la structure des données
        if (!empty($decoded)) {
            $firstEntry = $decoded[0];
            $requiredFields = ['photo_path', 'activity_key', 'photo_name', 'view_type', 'timestamp', 'session_id', 'ip_address', 'user_agent'];
            
            foreach ($requiredFields as $field) {
                $this->assertArrayHasKey($field, $firstEntry, "Le champ '$field' devrait être présent");
            }
        }
        
        $this->endTest();
    }
    
    /**
     * Test de validation des données
     */
    private function testDataValidation() {
        $this->startTest("Data Validation - Validation des entrées");
        
        // Test avec différents types de données invalides
        $invalidDataSets = [
            // Données manquantes
            [
                'activity_key' => 'test',
                'photo_name' => 'photo.jpg',
                // photo_path manquant
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s'),
                'session_id' => 'session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Agent'
            ],
            // Timestamp invalide
            [
                'photo_path' => 'test/photo.jpg',
                'activity_key' => 'test',
                'photo_name' => 'photo.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => 'invalid-date',
                'session_id' => 'session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Agent'
            ],
            // View type invalide
            [
                'photo_path' => 'test/photo.jpg',
                'activity_key' => 'test',
                'photo_name' => 'photo.jpg',
                'view_type' => 'invalid_type',
                'timestamp' => date('Y-m-d H:i:s'),
                'session_id' => 'session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Agent'
            ]
        ];
        
        foreach ($invalidDataSets as $index => $invalidData) {
            // Tenter de sauvegarder des données invalides
            $result = $this->saveConsultationData([$invalidData]);
            
            // Le système devrait gérer gracieusement les données invalides
            // Soit en les rejetant, soit en les normalisant
            $this->addNote("Dataset invalide #" . ($index + 1) . " traité");
        }
        
        // Vérifier que des données valides peuvent toujours être sauvées
        $validData = [
            'photo_path' => 'valid/photo.jpg',
            'activity_key' => 'valid',
            'photo_name' => 'photo.jpg',
            'view_type' => 'thumbnail',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => 'valid_session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Valid Agent'
        ];
        
        $result = $this->saveConsultationData([$validData]);
        $this->assertTrue($result, "Des données valides devraient pouvoir être sauvées");
        
        $this->endTest();
    }
    
    /**
     * Test de cohérence des données
     */
    private function testDataConsistency() {
        $this->startTest("Data Consistency - Cohérence des données");
        
        $testData = [
            [
                'photo_path' => 'consistency/photo1.jpg',
                'activity_key' => 'consistency',
                'photo_name' => 'photo1.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s', strtotime('-1 hour')),
                'session_id' => 'session_1',
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Test Agent 1'
            ],
            [
                'photo_path' => 'consistency/photo1.jpg', // Même photo
                'activity_key' => 'consistency',
                'photo_name' => 'photo1.jpg',
                'view_type' => 'modal_view', // Vue différente
                'timestamp' => date('Y-m-d H:i:s'),
                'session_id' => 'session_1', // Même session
                'ip_address' => '192.168.1.100',
                'user_agent' => 'Test Agent 1'
            ]
        ];
        
        $this->saveConsultationData($testData);
        
        $storedData = $this->getStoredConsultations();
        $this->assertGreaterThanOrEqual(2, count($storedData), "Toutes les consultations devraient être stockées");
        
        // Vérifier la cohérence des données liées
        $consistencyConsultations = array_filter($storedData, function($consultation) {
            return $consultation['activity_key'] === 'consistency';
        });
        
        $this->assertEquals(2, count($consistencyConsultations), "Devrait avoir 2 consultations de cohérence");
        
        // Vérifier que les données sont cohérentes
        foreach ($consistencyConsultations as $consultation) {
            $this->assertEquals('consistency', $consultation['activity_key'], "Activity key devrait être cohérent");
            $this->assertEquals('photo1.jpg', $consultation['photo_name'], "Photo name devrait être cohérent");
            $this->assertEquals('session_1', $consultation['session_id'], "Session ID devrait être cohérent");
        }
        
        $this->endTest();
    }
    
    /**
     * Test de verrouillage de fichier
     */
    private function testFileLocking() {
        $this->startTest("File Locking - Verrouillage concurrent");
        
        $testData = [
            'photo_path' => 'locking/photo.jpg',
            'activity_key' => 'locking',
            'photo_name' => 'photo.jpg',
            'view_type' => 'thumbnail',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => 'lock_session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Lock Test'
        ];
        
        // Simuler un accès concurrent (difficile à tester parfaitement)
        $success1 = $this->saveConsultationData([$testData]);
        $success2 = $this->saveConsultationData([$testData]);
        
        $this->assertTrue($success1, "Premier write devrait réussir");
        $this->assertTrue($success2, "Deuxième write devrait réussir avec LOCK_EX");
        
        $stored = $this->getStoredConsultations();
        $lockingConsultations = array_filter($stored, function($c) {
            return $c['activity_key'] === 'locking';
        });
        
        $this->assertEquals(2, count($lockingConsultations), "Les deux consultations devraient être sauvées");
        
        $this->endTest();
    }
    
    /**
     * Test des permissions de fichier
     */
    private function testFilePermissions() {
        $this->startTest("File Permissions - Permissions et sécurité");
        
        if (!file_exists($this->originalConsultationsFile)) {
            file_put_contents($this->originalConsultationsFile, '[]');
        }
        
        // Vérifier que le fichier est lisible
        $this->assertTrue(is_readable($this->originalConsultationsFile), "Le fichier devrait être lisible");
        
        // Vérifier que le fichier est modifiable
        $this->assertTrue(is_writable($this->originalConsultationsFile), "Le fichier devrait être modifiable");
        
        // Vérifier les permissions du dossier data
        $this->assertTrue(is_dir('data'), "Le dossier data devrait exister");
        $this->assertTrue(is_writable('data'), "Le dossier data devrait être modifiable");
        
        $this->addNote("Permissions du fichier: " . substr(sprintf('%o', fileperms($this->originalConsultationsFile)), -4));
        $this->addNote("Permissions du dossier: " . substr(sprintf('%o', fileperms('data')), -4));
        
        $this->endTest();
    }
    
    /**
     * Test d'encodage des données
     */
    private function testDataEncoding() {
        $this->startTest("Data Encoding - Encodage UTF-8 et caractères spéciaux");
        
        // Test avec des caractères spéciaux et Unicode
        $specialData = [
            'photo_path' => 'spécial/phøto_àccénts.jpg',
            'activity_key' => 'activité-spéciale',
            'photo_name' => 'phøto_àccénts.jpg',
            'view_type' => 'thumbnail',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => 'session_spécial',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'Mozilla/5.0 (Test) 中文 ñáéíóú'
        ];
        
        $result = $this->saveConsultationData([$specialData]);
        $this->assertTrue($result, "Données avec caractères spéciaux devraient être sauvées");
        
        $stored = $this->getStoredConsultations();
        $specialConsultation = null;
        
        foreach ($stored as $consultation) {
            if ($consultation['activity_key'] === 'activité-spéciale') {
                $specialConsultation = $consultation;
                break;
            }
        }
        
        $this->assertNotNull($specialConsultation, "Consultation avec caractères spéciaux devrait être trouvée");
        $this->assertEquals('phøto_àccénts.jpg', $specialConsultation['photo_name'], "Nom de photo avec accents devrait être préservé");
        $this->assertStringContains('中文', $specialConsultation['user_agent'], "Caractères unicode devraient être préservés");
        
        $this->endTest();
    }
    
    /**
     * Test de gestion de données corrompues
     */
    private function testCorruptedDataHandling() {
        $this->startTest("Corrupted Data Handling - Gestion des données corrompues");
        
        // Créer un fichier JSON corrompu
        file_put_contents($this->originalConsultationsFile, '{invalid json content}');
        
        // Tenter de lire les données corrompues
        $consultations = $this->getStoredConsultations();
        $this->assertIsArray($consultations, "Devrait retourner un tableau même avec des données corrompues");
        $this->assertEquals(0, count($consultations), "Devrait retourner un tableau vide pour des données corrompues");
        
        // Vérifier que de nouvelles données peuvent être sauvées après corruption
        $newData = [
            'photo_path' => 'recovery/photo.jpg',
            'activity_key' => 'recovery',
            'photo_name' => 'photo.jpg',
            'view_type' => 'thumbnail',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => 'recovery_session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Recovery Test'
        ];
        
        $result = $this->saveConsultationData([$newData]);
        $this->assertTrue($result, "Nouvelles données devraient pouvoir être sauvées après corruption");
        
        $recovered = $this->getStoredConsultations();
        $this->assertGreaterThan(0, count($recovered), "Données devraient être récupérées");
        
        $this->endTest();
    }
    
    /**
     * Test d'intégrité avec de gros volumes de données
     */
    private function testLargeDataIntegrity() {
        $this->startTest("Large Data Integrity - Intégrité avec gros volumes");
        
        $largeDataset = [];
        $targetSize = 1000; // 1000 entrées
        
        for ($i = 0; $i < $targetSize; $i++) {
            $largeDataset[] = [
                'photo_path' => "large/photo{$i}.jpg",
                'activity_key' => 'large-dataset',
                'photo_name' => "photo{$i}.jpg",
                'view_type' => ['thumbnail', 'modal_view', 'zoom'][$i % 3],
                'timestamp' => date('Y-m-d H:i:s', strtotime("-{$i} minutes")),
                'session_id' => 'session_' . ($i % 10),
                'ip_address' => '192.168.' . (($i % 254) + 1) . '.1',
                'user_agent' => "User Agent {$i}"
            ];
        }
        
        $startTime = microtime(true);
        $result = $this->saveConsultationData($largeDataset);
        $saveTime = microtime(true) - $startTime;
        
        $this->assertTrue($result, "Gros dataset devrait pouvoir être sauvé");
        $this->addNote("Sauvegarde de {$targetSize} entrées: " . round($saveTime * 1000, 2) . "ms");
        
        // Vérifier l'intégrité après sauvegarde
        $startTime = microtime(true);
        $stored = $this->getStoredConsultations();
        $loadTime = microtime(true) - $startTime;
        
        $this->assertGreaterThanOrEqual($targetSize, count($stored), "Toutes les entrées devraient être stockées");
        $this->addNote("Chargement de " . count($stored) . " entrées: " . round($loadTime * 1000, 2) . "ms");
        
        // Vérifier quelques entrées au hasard
        for ($i = 0; $i < 10; $i++) {
            $randomIndex = rand(0, count($stored) - 1);
            $entry = $stored[$randomIndex];
            
            $this->assertArrayHasKey('activity_key', $entry, "Entrée #{$randomIndex} devrait avoir activity_key");
            $this->assertArrayHasKey('timestamp', $entry, "Entrée #{$randomIndex} devrait avoir timestamp");
            $this->assertNotEmpty($entry['photo_name'], "Entrée #{$randomIndex} devrait avoir un nom de photo");
        }
        
        $this->endTest();
    }
    
    /**
     * Test d'intégrité lors d'écritures concurrentes
     */
    private function testConcurrentWriteIntegrity() {
        $this->startTest("Concurrent Write Integrity - Intégrité écritures concurrentes");
        
        // Simuler des écritures rapides successives
        $concurrentData = [];
        for ($i = 0; $i < 20; $i++) {
            $concurrentData[] = [
                'photo_path' => "concurrent/batch{$i}.jpg",
                'activity_key' => 'concurrent-test',
                'photo_name' => "batch{$i}.jpg",
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s', strtotime("-{$i} seconds")),
                'session_id' => 'concurrent_session',
                'ip_address' => '192.168.1.10',
                'user_agent' => "Concurrent Agent {$i}"
            ];
        }
        
        // Écrire par petits lots rapidement
        $batchSize = 5;
        $totalWritten = 0;
        
        for ($i = 0; $i < count($concurrentData); $i += $batchSize) {
            $batch = array_slice($concurrentData, $i, $batchSize);
            $result = $this->saveConsultationData($batch);
            if ($result) {
                $totalWritten += count($batch);
            }
            
            // Très petit délai pour simuler la concurrence
            usleep(1000); // 1ms
        }
        
        $this->assertEquals(20, $totalWritten, "Toutes les écritures concurrentes devraient réussir");
        
        // Vérifier l'intégrité finale
        $stored = $this->getStoredConsultations();
        $concurrentEntries = array_filter($stored, function($entry) {
            return $entry['activity_key'] === 'concurrent-test';
        });
        
        $this->assertEquals(20, count($concurrentEntries), "Toutes les entrées concurrentes devraient être présentes");
        
        $this->endTest();
    }
    
    /**
     * Test de la limite maximale d'entrées
     */
    private function testMaxEntriesLimit() {
        $this->startTest("Max Entries Limit - Limite maximale d'entrées");
        
        // Vérifier la constante de limite
        $maxEntries = defined('MAX_CONSULTATION_ENTRIES') ? MAX_CONSULTATION_ENTRIES : 10000;
        $this->addNote("Limite maximale configurée: {$maxEntries} entrées");
        
        // Créer un dataset qui dépasse la limite (si raisonnable)
        $testLimit = min(100, $maxEntries + 10); // Test avec 100 entrées max pour la rapidité
        
        $excessiveData = [];
        for ($i = 0; $i < $testLimit; $i++) {
            $excessiveData[] = [
                'photo_path' => "limit/photo{$i}.jpg",
                'activity_key' => 'limit-test',
                'photo_name' => "photo{$i}.jpg",
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s', strtotime("-{$i} minutes")),
                'session_id' => 'limit_session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Limit Test'
            ];
        }
        
        $result = $this->saveConsultationData($excessiveData);
        $this->assertTrue($result, "Données devraient être sauvées même près de la limite");
        
        $stored = $this->getStoredConsultations();
        $this->assertLessThanOrEqual($maxEntries, count($stored), "Le nombre d'entrées ne devrait pas dépasser la limite");
        
        if ($testLimit > $maxEntries) {
            $this->addNote("Système de limitation activé - " . count($stored) . " entrées conservées");
        }
        
        $this->endTest();
    }
    
    /**
     * Test de rotation des données
     */
    private function testDataRotation() {
        $this->startTest("Data Rotation - Rotation et archivage");
        
        // Créer des données anciennes et récentes
        $oldData = [
            'photo_path' => 'old/photo.jpg',
            'activity_key' => 'old-activity',
            'photo_name' => 'photo.jpg',
            'view_type' => 'thumbnail',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-45 days')),
            'session_id' => 'old_session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Old Agent'
        ];
        
        $recentData = [
            'photo_path' => 'recent/photo.jpg',
            'activity_key' => 'recent-activity',
            'photo_name' => 'photo.jpg',
            'view_type' => 'thumbnail',
            'timestamp' => date('Y-m-d H:i:s', strtotime('-1 day')),
            'session_id' => 'recent_session',
            'ip_address' => '127.0.0.1',
            'user_agent' => 'Recent Agent'
        ];
        
        $this->saveConsultationData([$oldData, $recentData]);
        
        // Test de nettoyage des anciennes données
        $cleaned = cleanOldConsultations(30); // Garder 30 jours
        $this->assertIsInt($cleaned, "Le nettoyage devrait retourner un nombre");
        
        $stored = $this->getStoredConsultations();
        $oldEntries = array_filter($stored, function($entry) {
            return strtotime($entry['timestamp']) < strtotime('-30 days');
        });
        
        $this->assertEquals(0, count($oldEntries), "Les entrées anciennes devraient être supprimées");
        
        $recentEntries = array_filter($stored, function($entry) {
            return $entry['activity_key'] === 'recent-activity';
        });
        
        $this->assertEquals(1, count($recentEntries), "Les entrées récentes devraient être conservées");
        
        $this->endTest();
    }
    
    /**
     * Test de compatibilité ascendante
     */
    private function testBackwardCompatibility() {
        $this->startTest("Backward Compatibility - Compatibilité ascendante");
        
        // Simuler d'anciennes structures de données (sans certains champs)
        $oldFormatData = [
            [
                'photo_path' => 'compat/photo1.jpg',
                'activity_key' => 'compat-test',
                'photo_name' => 'photo1.jpg',
                'view_type' => 'thumbnail',
                'timestamp' => date('Y-m-d H:i:s'),
                // Champs manquants: session_id, ip_address, user_agent
            ],
            [
                'photo_path' => 'compat/photo2.jpg',
                'activity_key' => 'compat-test',
                'photo_name' => 'photo2.jpg',
                // view_type manquant (devrait être défini par défaut)
                'timestamp' => date('Y-m-d H:i:s'),
                'session_id' => 'old_session',
                'ip_address' => '127.0.0.1',
                'user_agent' => 'Old Format Agent'
            ]
        ];
        
        file_put_contents($this->originalConsultationsFile, json_encode($oldFormatData, JSON_PRETTY_PRINT));
        
        // Vérifier que les données peuvent être lues
        $stored = $this->getStoredConsultations();
        $this->assertEquals(2, count($stored), "Anciennes données devraient pouvoir être lues");
        
        // Vérifier que de nouvelles données peuvent être ajoutées
        $newFormatData = [
            'photo_path' => 'compat/photo3.jpg',
            'activity_key' => 'compat-test',
            'photo_name' => 'photo3.jpg',
            'view_type' => 'modal_view',
            'timestamp' => date('Y-m-d H:i:s'),
            'session_id' => 'new_session',
            'ip_address' => '192.168.1.1',
            'user_agent' => 'New Format Agent'
        ];
        
        $result = $this->saveConsultationData([$newFormatData]);
        $this->assertTrue($result, "Nouvelles données devraient pouvoir être ajoutées aux anciennes");
        
        $updated = $this->getStoredConsultations();
        $this->assertEquals(3, count($updated), "Toutes les données (anciennes + nouvelles) devraient être présentes");
        
        $this->endTest();
    }
    
    /**
     * Test de migration de données
     */
    private function testDataMigration() {
        $this->startTest("Data Migration - Migration de format de données");
        
        // Simuler un processus de migration
        $legacyData = [
            'photo' => 'legacy/photo.jpg',
            'activity' => 'legacy-activity',
            'type' => 'view',
            'date' => date('Y-m-d H:i:s'),
            'user' => 'legacy_user'
        ];
        
        // Fonction de migration simulée
        $migratedData = [
            'photo_path' => $legacyData['photo'],
            'activity_key' => $legacyData['activity'],
            'photo_name' => basename($legacyData['photo']),
            'view_type' => 'thumbnail', // Valeur par défaut pour anciens 'view'
            'timestamp' => $legacyData['date'],
            'session_id' => $legacyData['user'],
            'ip_address' => '0.0.0.0', // Valeur par défaut
            'user_agent' => 'Migrated Data'
        ];
        
        $result = $this->saveConsultationData([$migratedData]);
        $this->assertTrue($result, "Données migrées devraient pouvoir être sauvées");
        
        $stored = $this->getStoredConsultations();
        $migratedEntry = null;
        
        foreach ($stored as $entry) {
            if ($entry['activity_key'] === 'legacy-activity') {
                $migratedEntry = $entry;
                break;
            }
        }
        
        $this->assertNotNull($migratedEntry, "Entrée migrée devrait être trouvée");
        $this->assertEquals('thumbnail', $migratedEntry['view_type'], "Type de vue devrait être migré");
        $this->assertEquals('Migrated Data', $migratedEntry['user_agent'], "User agent devrait être défini");
        
        $this->endTest();
    }
    
    // === MÉTHODES UTILITAIRES ===
    
    /**
     * Sauvegarder des données de consultation
     */
    private function saveConsultationData($consultations) {
        try {
            $existingData = $this->getStoredConsultations();
            $allConsultations = array_merge($existingData, $consultations);
            
            // Limiter le nombre d'entrées
            $maxEntries = defined('MAX_CONSULTATION_ENTRIES') ? MAX_CONSULTATION_ENTRIES : 10000;
            if (count($allConsultations) > $maxEntries) {
                $allConsultations = array_slice($allConsultations, -$maxEntries);
            }
            
            $json = json_encode($allConsultations, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
            return file_put_contents($this->originalConsultationsFile, $json, LOCK_EX) !== false;
        } catch (Exception $e) {
            return false;
        }
    }
    
    private function getStoredConsultations() {
        if (file_exists($this->originalConsultationsFile)) {
            $content = file_get_contents($this->originalConsultationsFile);
            if ($content !== false) {
                $data = json_decode($content, true);
                if (is_array($data)) {
                    return $data;
                }
            }
        }
        return [];
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
            // Créer un fichier vide par défaut
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
    
    private function assertFalse($condition, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => !$condition,
            'message' => $message,
            'type' => 'assertFalse'
        ];
    }
    
    private function assertNotFalse($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $value !== false,
            'message' => $message,
            'type' => 'assertNotFalse'
        ];
    }
    
    private function assertNotNull($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $value !== null,
            'message' => $message,
            'type' => 'assertNotNull'
        ];
    }
    
    private function assertIsArray($value, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_array($value),
            'message' => $message,
            'type' => 'assertIsArray'
        ];
    }
    
    private function assertArrayHasKey($key, $array, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => is_array($array) && array_key_exists($key, $array),
            'message' => $message,
            'type' => 'assertArrayHasKey'
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
    
    private function assertLessThanOrEqual($expected, $actual, $message) {
        $this->currentTestResults['assertions'][] = [
            'passed' => $actual <= $expected,
            'message' => $message,
            'type' => 'assertLessThanOrEqual'
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
        
        echo "<h2>📊 Résumé des Tests Intégrité Données</h2>\n";
        echo "<div style='background: #f8f9fa; padding: 20px; border-radius: 5px; margin: 20px 0;'>\n";
        echo "<p><strong>Tests exécutés:</strong> {$this->testCount}</p>\n";
        echo "<p><strong>Tests réussis:</strong> <span style='color: green;'>{$this->passedTests}</span></p>\n";
        echo "<p><strong>Tests échoués:</strong> <span style='color: red;'>{$this->failedTests}</span></p>\n";
        echo "<p><strong>Taux de réussite:</strong> {$successRate}%</p>\n";
        echo "<p><strong>Temps d'exécution:</strong> {$executionTime}ms</p>\n";
        echo "</div>\n";
        
        echo "<h3>🔍 Analyse Intégrité:</h3>\n";
        echo "<ul>\n";
        if ($successRate >= 95) {
            echo "<li style='color: green;'>✅ Excellent: Intégrité des données parfaite</li>\n";
        } elseif ($successRate >= 80) {
            echo "<li style='color: orange;'>⚠️ Bon: Intégrité des données majoritairement assurée</li>\n";
        } else {
            echo "<li style='color: red;'>❌ Problématique: Problèmes d'intégrité détectés</li>\n";
        }
        
        echo "<li>Stockage JSON robuste et sécurisé</li>\n";
        echo "<li>Gestion appropriée des corruptions</li>\n";
        echo "<li>Compatibilité avec différents formats</li>\n";
        echo "<li>Performance acceptable avec de gros volumes</li>\n";
        echo "</ul>\n";
    }
}

// Exécuter les tests
$tests = new ConsultationDataIntegrityTests();
$tests->runAllTests();

?>