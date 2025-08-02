<?php
/**
 * Outil de diagnostic système pour la galerie photos
 * 
 * Vérifie l'état du système, les performances et la configuration
 * Accessible uniquement en mode admin
 */

session_start();
define('GALLERY_ACCESS', true);

// Vérifier l'authentification admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    // Permettre l'accès direct avec un paramètre de diagnostic
    if (!isset($_GET['diagnostic_key']) || $_GET['diagnostic_key'] !== 'system_check_2024') {
        header('HTTP/1.1 403 Forbidden');
        die('Accès non autorisé. Connectez-vous en tant qu\'administrateur.');
    }
}

require_once 'config.php';
require_once 'classes/autoload.php';

class SystemDiagnostic {
    
    private $results = [];
    private $logger;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Lancer tous les diagnostics
     */
    public function runFullDiagnostic() {
        $this->checkSystemRequirements();
        $this->checkFileSystemHealth();
        $this->checkDatabaseIntegrity();
        $this->checkPerformance();
        $this->checkSecurity();
        $this->checkConfiguration();
        $this->analyzeUsage();
        
        return $this->results;
    }
    
    /**
     * Vérifier les prérequis système
     */
    private function checkSystemRequirements() {
        $checks = [];
        
        // Version PHP
        $phpVersion = PHP_VERSION;
        $checks['php_version'] = [
            'name' => 'Version PHP',
            'value' => $phpVersion,
            'status' => version_compare($phpVersion, '7.4.0', '>=') ? 'ok' : 'error',
            'recommendation' => version_compare($phpVersion, '8.0.0', '<') ? 'Mise à jour vers PHP 8+ recommandée' : null
        ];
        
        // Extensions PHP
        $extensions = ['json', 'session', 'gd', 'zip', 'fileinfo'];
        foreach ($extensions as $ext) {
            $checks["ext_{$ext}"] = [
                'name' => "Extension {$ext}",
                'value' => extension_loaded($ext) ? 'Installée' : 'Manquante',
                'status' => extension_loaded($ext) ? 'ok' : 'error'
            ];
        }
        
        // Limites PHP
        $memoryLimit = ini_get('memory_limit');
        $checks['memory_limit'] = [
            'name' => 'Limite mémoire',
            'value' => $memoryLimit,
            'status' => $this->convertToBytes($memoryLimit) >= (128 * 1024 * 1024) ? 'ok' : 'warning'
        ];
        
        $maxExecutionTime = ini_get('max_execution_time');
        $checks['max_execution_time'] = [
            'name' => 'Temps d\'exécution max',
            'value' => $maxExecutionTime . 's',
            'status' => ($maxExecutionTime == 0 || $maxExecutionTime >= 300) ? 'ok' : 'warning'
        ];
        
        $this->results['system_requirements'] = [
            'title' => 'Prérequis Système',
            'checks' => $checks
        ];
    }
    
    /**
     * Vérifier la santé du système de fichiers
     */
    private function checkFileSystemHealth() {
        $checks = [];
        
        // Permissions des dossiers
        $directories = [
            '.' => 'Dossier racine',
            'data' => 'Données',
            'logs' => 'Logs',
            'photos' => 'Photos'
        ];
        
        foreach ($directories as $dir => $name) {
            $readable = is_readable($dir);
            $writable = is_writable($dir);
            $exists = is_dir($dir);
            
            $status = 'error';
            $value = 'Introuvable';
            
            if ($exists) {
                if ($readable && ($dir === 'photos' || $writable)) {
                    $status = 'ok';
                    $value = 'OK';
                } elseif ($readable) {
                    $status = 'warning';
                    $value = 'Lecture seule';
                } else {
                    $value = 'Permissions insuffisantes';
                }
            }
            
            $checks["dir_{$dir}"] = [
                'name' => "Dossier {$name}",
                'value' => $value,
                'status' => $status
            ];
        }
        
        // Espace disque
        $freeSpace = disk_free_space('.');
        $totalSpace = disk_total_space('.');
        
        if ($freeSpace && $totalSpace) {
            $freePercent = ($freeSpace / $totalSpace) * 100;
            $checks['disk_space'] = [
                'name' => 'Espace disque libre',
                'value' => $this->formatBytes($freeSpace) . ' (' . round($freePercent, 1) . '%)',
                'status' => $freePercent > 10 ? 'ok' : ($freePercent > 5 ? 'warning' : 'error')
            ];
        }
        
        $this->results['filesystem_health'] = [
            'title' => 'Santé du Système de Fichiers',
            'checks' => $checks
        ];
    }
    
    /**
     * Vérifier l'intégrité des données
     */
    private function checkDatabaseIntegrity() {
        $checks = [];
        
        // Fichier des activités
        $activitiesFile = DATA_DIR . 'activities.json';
        if (file_exists($activitiesFile)) {
            $activities = json_decode(file_get_contents($activitiesFile), true);
            $isValid = $activities !== null;
            
            $checks['activities_file'] = [
                'name' => 'Fichier activités',
                'value' => $isValid ? count($activities) . ' activités' : 'JSON invalide',
                'status' => $isValid ? 'ok' : 'error'
            ];
            
            if ($isValid) {
                // Vérifier la cohérence des photos
                $missingPhotos = 0;
                $totalPhotos = 0;
                
                foreach ($activities as $key => $activity) {
                    if (isset($activity['photos'])) {
                        $totalPhotos += count($activity['photos']);
                        foreach ($activity['photos'] as $photo) {
                            $photoPath = PHOTOS_DIR . $key . '/' . $photo;
                            if (!file_exists($photoPath)) {
                                $missingPhotos++;
                            }
                        }
                    }
                }
                
                $checks['photos_integrity'] = [
                    'name' => 'Intégrité des photos',
                    'value' => $missingPhotos > 0 ? "{$missingPhotos} photo(s) manquante(s) sur {$totalPhotos}" : "Toutes les photos présentes ({$totalPhotos})",
                    'status' => $missingPhotos === 0 ? 'ok' : 'warning'
                ];
            }
        } else {
            $checks['activities_file'] = [
                'name' => 'Fichier activités',
                'value' => 'Manquant',
                'status' => 'warning'
            ];
        }
        
        $this->results['database_integrity'] = [
            'title' => 'Intégrité des Données',
            'checks' => $checks
        ];
    }
    
    /**
     * Vérifier les performances
     */
    private function checkPerformance() {
        $checks = [];
        
        // Test de performance d'écriture
        $startTime = microtime(true);
        $testFile = DATA_DIR . 'performance_test.tmp';
        $testData = str_repeat('0123456789', 1000); // 10KB
        
        file_put_contents($testFile, $testData);
        $writeTime = microtime(true) - $startTime;
        
        // Test de performance de lecture
        $startTime = microtime(true);
        $readData = file_get_contents($testFile);
        $readTime = microtime(true) - $startTime;
        
        unlink($testFile);
        
        $checks['write_performance'] = [
            'name' => 'Performance écriture',
            'value' => round($writeTime * 1000, 2) . ' ms',
            'status' => $writeTime < 0.1 ? 'ok' : ($writeTime < 0.5 ? 'warning' : 'error')
        ];
        
        $checks['read_performance'] = [
            'name' => 'Performance lecture',
            'value' => round($readTime * 1000, 2) . ' ms',
            'status' => $readTime < 0.05 ? 'ok' : ($readTime < 0.2 ? 'warning' : 'error')
        ];
        
        // Test de performance JSON
        $startTime = microtime(true);
        $testArray = array_fill(0, 1000, ['name' => 'test', 'value' => 123, 'tags' => ['a', 'b', 'c']]);
        $jsonData = json_encode($testArray);
        $jsonTime = microtime(true) - $startTime;
        
        $checks['json_performance'] = [
            'name' => 'Performance JSON',
            'value' => round($jsonTime * 1000, 2) . ' ms',
            'status' => $jsonTime < 0.1 ? 'ok' : ($jsonTime < 0.5 ? 'warning' : 'error')
        ];
        
        $this->results['performance'] = [
            'title' => 'Performance',
            'checks' => $checks
        ];
    }
    
    /**
     * Vérifier la sécurité
     */
    private function checkSecurity() {
        $checks = [];
        
        // Vérifier si le mot de passe par défaut est utilisé
        $checks['default_password'] = [
            'name' => 'Mot de passe par défaut',
            'value' => ADMIN_PASSWORD === 'admin123' ? 'En cours d\'utilisation' : 'Modifié',
            'status' => ADMIN_PASSWORD === 'admin123' ? 'error' : 'ok',
            'recommendation' => ADMIN_PASSWORD === 'admin123' ? 'Changez immédiatement le mot de passe par défaut !' : null
        ];
        
        // Vérifier la protection des fichiers
        $protectedFiles = ['.htaccess', 'config.php'];
        foreach ($protectedFiles as $file) {
            $checks["protection_{$file}"] = [
                'name' => "Protection {$file}",
                'value' => file_exists($file) ? 'Présent' : 'Manquant',
                'status' => file_exists($file) ? 'ok' : 'warning'
            ];
        }
        
        // Vérifier les headers de sécurité
        $securityHeaders = [
            'X-XSS-Protection',
            'X-Content-Type-Options', 
            'X-Frame-Options'
        ];
        
        foreach ($securityHeaders as $header) {
            $headerSet = false;
            foreach (headers_list() as $sentHeader) {
                if (stripos($sentHeader, $header) === 0) {
                    $headerSet = true;
                    break;
                }
            }
            
            $checks["header_{$header}"] = [
                'name' => "Header {$header}",
                'value' => $headerSet ? 'Configuré' : 'Manquant',
                'status' => $headerSet ? 'ok' : 'warning'
            ];
        }
        
        $this->results['security'] = [
            'title' => 'Sécurité',
            'checks' => $checks
        ];
    }
    
    /**
     * Vérifier la configuration
     */
    private function checkConfiguration() {
        $checks = [];
        
        // Vérifier les constantes importantes
        $constants = [
            'SITE_NAME' => 'Nom du site',
            'PHOTOS_DIR' => 'Dossier photos',
            'DATA_DIR' => 'Dossier données',
            'LOGS_ENABLED' => 'Logs activés'
        ];
        
        foreach ($constants as $const => $name) {
            $isDefined = defined($const);
            $checks["const_{$const}"] = [
                'name' => $name,
                'value' => $isDefined ? (is_bool(constant($const)) ? (constant($const) ? 'Oui' : 'Non') : constant($const)) : 'Non défini',
                'status' => $isDefined ? 'ok' : 'error'
            ];
        }
        
        // Vérifier la timezone
        $timezone = date_default_timezone_get();
        $checks['timezone'] = [
            'name' => 'Fuseau horaire',
            'value' => $timezone,
            'status' => 'ok'
        ];
        
        $this->results['configuration'] = [
            'title' => 'Configuration',
            'checks' => $checks
        ];
    }
    
    /**
     * Analyser l'utilisation
     */
    private function analyzeUsage() {
        $checks = [];
        
        // Analyser les logs si disponibles
        if (LOGS_ENABLED && class_exists('Logger')) {
            $logger = Logger::getInstance();
            $stats = $logger->getLogStats();
            
            $checks['log_entries'] = [
                'name' => 'Entrées de logs',
                'value' => number_format($stats['total_entries']),
                'status' => 'ok'
            ];
            
            $checks['log_size'] = [
                'name' => 'Taille des logs',
                'value' => $stats['file_size_formatted'],
                'status' => $stats['file_size'] < (10 * 1024 * 1024) ? 'ok' : 'warning'
            ];
        }
        
        // Statistiques des activités
        $activitiesFile = DATA_DIR . 'activities.json';
        if (file_exists($activitiesFile)) {
            $activities = json_decode(file_get_contents($activitiesFile), true);
            if ($activities) {
                $totalPhotos = 0;
                $totalTags = [];
                
                foreach ($activities as $activity) {
                    if (isset($activity['photos'])) {
                        $totalPhotos += count($activity['photos']);
                    }
                    if (isset($activity['tags'])) {
                        $totalTags = array_merge($totalTags, $activity['tags']);
                    }
                }
                
                $checks['total_activities'] = [
                    'name' => 'Total activités',
                    'value' => count($activities),
                    'status' => 'ok'
                ];
                
                $checks['total_photos'] = [
                    'name' => 'Total photos',
                    'value' => number_format($totalPhotos),
                    'status' => 'ok'
                ];
                
                $checks['unique_tags'] = [
                    'name' => 'Tags uniques',
                    'value' => count(array_unique($totalTags)),
                    'status' => 'ok'
                ];
            }
        }
        
        $this->results['usage_analysis'] = [
            'title' => 'Analyse d\'Utilisation',
            'checks' => $checks
        ];
    }
    
    /**
     * Convertir une taille en octets
     */
    private function convertToBytes($value) {
        $value = trim($value);
        $last = strtolower($value[strlen($value)-1]);
        $value = (int) $value;
        
        switch($last) {
            case 'g': $value *= 1024;
            case 'm': $value *= 1024;
            case 'k': $value *= 1024;
        }
        
        return $value;
    }
    
    /**
     * Formater une taille en octets
     */
    private function formatBytes($size) {
        $units = ['B', 'KB', 'MB', 'GB'];
        
        for ($i = 0; $size > 1024 && $i < count($units) - 1; $i++) {
            $size /= 1024;
        }
        
        return round($size, 2) . ' ' . $units[$i];
    }
    
    /**
     * Obtenir un résumé global
     */
    public function getSummary() {
        $totalChecks = 0;
        $okChecks = 0;
        $warningChecks = 0;
        $errorChecks = 0;
        
        foreach ($this->results as $section) {
            if (isset($section['checks'])) {
                foreach ($section['checks'] as $check) {
                    $totalChecks++;
                    switch ($check['status']) {
                        case 'ok': $okChecks++; break;
                        case 'warning': $warningChecks++; break;
                        case 'error': $errorChecks++; break;
                    }
                }
            }
        }
        
        $overallStatus = 'ok';
        if ($errorChecks > 0) {
            $overallStatus = 'error';
        } elseif ($warningChecks > 0) {
            $overallStatus = 'warning';
        }
        
        return [
            'overall_status' => $overallStatus,
            'total_checks' => $totalChecks,
            'ok_checks' => $okChecks,
            'warning_checks' => $warningChecks,
            'error_checks' => $errorChecks,
            'health_percentage' => $totalChecks > 0 ? round(($okChecks / $totalChecks) * 100, 1) : 0
        ];
    }
}

// Exécuter le diagnostic
$diagnostic = new SystemDiagnostic();
$results = $diagnostic->runFullDiagnostic();
$summary = $diagnostic->getSummary();

// Log du diagnostic
if (class_exists('Logger')) {
    $logger = Logger::getInstance();
    $logger->adminAction('Diagnostic système exécuté', [
        'total_checks' => $summary['total_checks'],
        'health_percentage' => $summary['health_percentage'],
        'overall_status' => $summary['overall_status']
    ]);
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Diagnostic Système - Galerie Photos</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .diagnostic-summary {
            background: linear-gradient(135deg, #0BABDF, #0A76B7);
            color: white;
            padding: 2rem;
            border-radius: 10px;
            margin-bottom: 2rem;
            text-align: center;
        }
        
        .health-score {
            font-size: 3rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .health-status {
            font-size: 1.2rem;
            opacity: 0.9;
        }
        
        .diagnostic-section {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .section-header {
            background: #f8f9fa;
            padding: 1rem 2rem;
            border-bottom: 1px solid #dee2e6;
        }
        
        .section-header h3 {
            margin: 0;
            color: #0A76B7;
        }
        
        .checks-list {
            padding: 0;
        }
        
        .check-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
            padding: 1rem 2rem;
            border-bottom: 1px solid #f8f9fa;
        }
        
        .check-item:last-child {
            border-bottom: none;
        }
        
        .check-name {
            font-weight: 500;
        }
        
        .check-value {
            display: flex;
            align-items: center;
            gap: 1rem;
        }
        
        .status-badge {
            padding: 0.25rem 0.75rem;
            border-radius: 20px;
            font-size: 0.8rem;
            font-weight: 500;
            text-transform: uppercase;
        }
        
        .status-ok {
            background: #d4edda;
            color: #155724;
        }
        
        .status-warning {
            background: #fff3cd;
            color: #856404;
        }
        
        .status-error {
            background: #f8d7da;
            color: #721c24;
        }
        
        .recommendation {
            background: #fff3cd;
            color: #856404;
            padding: 0.5rem 1rem;
            margin-top: 0.5rem;
            border-radius: 4px;
            font-size: 0.9rem;
        }
        
        .stats-overview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 1rem;
            margin-bottom: 2rem;
        }
        
        .overview-card {
            background: white;
            padding: 1.5rem;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .overview-number {
            font-size: 2rem;
            font-weight: bold;
            margin-bottom: 0.5rem;
        }
        
        .overview-ok { color: #28a745; }
        .overview-warning { color: #ffc107; }
        .overview-error { color: #dc3545; }
        
        .refresh-btn {
            position: fixed;
            bottom: 2rem;
            right: 2rem;
            z-index: 1000;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Diagnostic Système</h1>
            <nav>
                <a href="admin.php" class="btn btn-secondary">Retour Admin</a>
                <a href="index.php" class="btn btn-outline">Accueil</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            
            <!-- Résumé global -->
            <div class="diagnostic-summary">
                <div class="health-score"><?php echo $summary['health_percentage']; ?>%</div>
                <div class="health-status">
                    <?php 
                    switch($summary['overall_status']) {
                        case 'ok': echo '✅ Système en bonne santé'; break;
                        case 'warning': echo '⚠️ Attention requise'; break;
                        case 'error': echo '❌ Problèmes détectés'; break;
                    }
                    ?>
                </div>
                <p style="margin-top: 1rem; opacity: 0.9;">
                    Diagnostic exécuté le <?php echo date('d/m/Y à H:i:s'); ?>
                </p>
            </div>

            <!-- Vue d'ensemble -->
            <div class="stats-overview">
                <div class="overview-card">
                    <div class="overview-number overview-ok"><?php echo $summary['ok_checks']; ?></div>
                    <div>Vérifications OK</div>
                </div>
                <div class="overview-card">
                    <div class="overview-number overview-warning"><?php echo $summary['warning_checks']; ?></div>
                    <div>Avertissements</div>
                </div>
                <div class="overview-card">
                    <div class="overview-number overview-error"><?php echo $summary['error_checks']; ?></div>
                    <div>Erreurs</div>
                </div>
                <div class="overview-card">
                    <div class="overview-number"><?php echo $summary['total_checks']; ?></div>
                    <div>Total vérifications</div>
                </div>
            </div>

            <!-- Sections de diagnostic -->
            <?php foreach ($results as $sectionKey => $section): ?>
                <div class="diagnostic-section">
                    <div class="section-header">
                        <h3><?php echo htmlspecialchars($section['title']); ?></h3>
                    </div>
                    <div class="checks-list">
                        <?php foreach ($section['checks'] as $checkKey => $check): ?>
                            <div class="check-item">
                                <div class="check-name"><?php echo htmlspecialchars($check['name']); ?></div>
                                <div class="check-value">
                                    <span><?php echo htmlspecialchars($check['value']); ?></span>
                                    <span class="status-badge status-<?php echo $check['status']; ?>">
                                        <?php echo strtoupper($check['status']); ?>
                                    </span>
                                </div>
                            </div>
                            <?php if (isset($check['recommendation'])): ?>
                                <div style="padding: 0 2rem;">
                                    <div class="recommendation">
                                        <strong>Recommandation:</strong> <?php echo htmlspecialchars($check['recommendation']); ?>
                                    </div>
                                </div>
                            <?php endif; ?>
                        <?php endforeach; ?>
                    </div>
                </div>
            <?php endforeach; ?>

            <!-- Actions recommandées -->
            <?php if ($summary['error_checks'] > 0 || $summary['warning_checks'] > 0): ?>
                <div class="diagnostic-section">
                    <div class="section-header">
                        <h3>Actions Recommandées</h3>
                    </div>
                    <div style="padding: 2rem;">
                        <?php if ($summary['error_checks'] > 0): ?>
                            <div class="alert alert-danger">
                                <strong>Problèmes critiques détectés!</strong><br>
                                Corrigez les erreurs marquées en rouge avant de continuer.
                            </div>
                        <?php endif; ?>
                        
                        <?php if ($summary['warning_checks'] > 0): ?>
                            <div class="alert" style="background: #fff3cd; border-color: #ffc107; color: #856404;">
                                <strong>Améliorations possibles:</strong><br>
                                Consultez les avertissements pour optimiser votre installation.
                            </div>
                        <?php endif; ?>
                        
                        <h4 style="margin: 1.5rem 0 1rem;">Actions suggérées:</h4>
                        <ul style="margin-left: 2rem; line-height: 1.8;">
                            <?php if (ADMIN_PASSWORD === 'admin123'): ?>
                                <li><strong>URGENT:</strong> Changez le mot de passe administrateur par défaut</li>
                            <?php endif; ?>
                            
                            <?php if ($summary['warning_checks'] > 0): ?>
                                <li>Consultez les recommandations spécifiques pour chaque avertissement</li>
                                <li>Vérifiez les permissions des dossiers</li>
                                <li>Optimisez la configuration PHP si nécessaire</li>
                            <?php endif; ?>
                            
                            <li>Effectuez des sauvegardes régulières via l'interface d'administration</li>
                            <li>Surveillez les logs système pour détecter les problèmes</li>
                            <li>Relancez ce diagnostic après avoir effectué des modifications</li>
                        </ul>
                    </div>
                </div>
            <?php endif; ?>

        </div>
    </main>

    <!-- Bouton de rafraîchissement -->
    <a href="?" class="btn btn-primary refresh-btn" title="Relancer le diagnostic">
        🔄 Actualiser
    </a>

    <script>
        // Auto-refresh optionnel
        document.addEventListener('DOMContentLoaded', function() {
            // Ajouter des tooltips aux éléments de statut
            document.querySelectorAll('.status-badge').forEach(badge => {
                const status = badge.textContent.toLowerCase();
                let tooltip = '';
                
                switch(status) {
                    case 'ok': tooltip = 'Aucun problème détecté'; break;
                    case 'warning': tooltip = 'Attention requise'; break;
                    case 'error': tooltip = 'Problème critique'; break;
                }
                
                badge.title = tooltip;
            });
            
            // Animation progressive des sections
            const sections = document.querySelectorAll('.diagnostic-section');
            sections.forEach((section, index) => {
                section.style.opacity = '0';
                section.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    section.style.transition = 'all 0.5s ease';
                    section.style.opacity = '1';
                    section.style.transform = 'translateY(0)';
                }, index * 100);
            });
        });
    </script>
</body>
</html>