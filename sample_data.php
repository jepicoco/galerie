<?php
/**
 * Générateur de données d'exemple pour la galerie photos
 * 
 * Crée des activités fictives avec leurs métadonnées pour tester
 * et démontrer les fonctionnalités de la galerie
 */

session_start();
define('GALLERY_ACCESS', true);

// Vérifier l'authentification admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('HTTP/1.1 403 Forbidden');
    die('Accès non autorisé. Connectez-vous en tant qu\'administrateur.');
}

require_once 'config.php';
require_once 'classes/autoload.php';

class SampleDataGenerator {
    
    private $logger;
    private $activitiesData = [];
    
    public function __construct() {
        $this->logger = Logger::getInstance();
        $this->defineActivitiesTemplates();
    }
    
    /**
     * Définir les modèles d'activités d'exemple
     */
    private function defineActivitiesTemplates() {
        $this->activitiesData = [
            'randonnee-montagne' => [
                'name' => 'Randonnée en Montagne',
                'description' => 'Magnifique randonnée dans les Alpes avec vue panoramique sur les sommets enneigés. Une aventure inoubliable au cœur de la nature.',
                'tags' => ['montagne', 'randonnée', 'nature', 'sport', 'extérieur', 'alpes'],
                'photos' => [
                    'sommet-panorama.jpg',
                    'chemin-foret.jpg', 
                    'refuge-montagne.jpg',
                    'lever-soleil.jpg',
                    'groupe-randonneurs.jpg'
                ],
                'featured' => true,
                'visibility' => 'public',
                'photos_metadata' => [
                    'sommet-panorama.jpg' => [
                        'width' => 1920,
                        'height' => 1080,
                        'size' => 245000,
                        'mime_type' => 'image/jpeg',
                        'description' => 'Vue panoramique depuis le sommet'
                    ],
                    'chemin-foret.jpg' => [
                        'width' => 1600,
                        'height' => 1200,
                        'size' => 180000,
                        'mime_type' => 'image/jpeg',
                        'description' => 'Sentier traversant la forêt'
                    ]
                ]
            ],
            
            'festival-musique' => [
                'name' => 'Festival de Musique',
                'description' => 'Trois jours de concerts exceptionnels avec des artistes internationaux. Ambiance électrique et moments magiques sous les étoiles.',
                'tags' => ['musique', 'festival', 'concert', 'art', 'culture', 'soirée'],
                'photos' => [
                    'scene-principale.jpg',
                    'foule-concert.jpg',
                    'artiste-solo.jpg',
                    'feux-artifice.jpg'
                ],
                'featured' => false,
                'visibility' => 'public'
            ],
            
            'cours-cuisine' => [
                'name' => 'Cours de Cuisine',
                'description' => 'Atelier de cuisine française traditionnelle avec un chef étoilé. Apprentissage des techniques culinaires et dégustation.',
                'tags' => ['cuisine', 'gastronomie', 'formation', 'intérieur', 'apprentissage'],
                'photos' => [
                    'preparation-ingredients.jpg',
                    'chef-demonstration.jpg',
                    'plat-fini.jpg',
                    'groupe-cuisine.jpg'
                ],
                'featured' => false,
                'visibility' => 'public'
            ],
            
            'voyage-italie' => [
                'name' => 'Voyage en Italie',
                'description' => 'Circuit découverte de l\'Italie du Nord : Venise, Florence, Rome. Architecture, art et gastronomie au rendez-vous.',
                'tags' => ['voyage', 'italie', 'culture', 'architecture', 'art', 'tourisme'],
                'photos' => [
                    'venise-canaux.jpg',
                    'florence-duomo.jpg',
                    'rome-colisee.jpg',
                    'pizza-traditionnelle.jpg',
                    'groupe-fontaine.jpg'
                ],
                'featured' => true,
                'visibility' => 'public'
            ],
            
            'atelier-photo' => [
                'name' => 'Atelier Photographie',
                'description' => 'Workshop de photographie de portrait et paysage. Techniques avancées et post-traitement numérique.',
                'tags' => ['photographie', 'formation', 'art', 'technique', 'créativité'],
                'photos' => [
                    'setup-studio.jpg',
                    'modele-portrait.jpg',
                    'paysage-golden-hour.jpg',
                    'materiel-photo.jpg'
                ],
                'featured' => false,
                'visibility' => 'public'
            ],
            
            'sport-equipe' => [
                'name' => 'Tournoi Sportif',
                'description' => 'Compétition amicale de football entre équipes locales. Fair-play et convivialité étaient au programme.',
                'tags' => ['sport', 'football', 'équipe', 'compétition', 'extérieur'],
                'photos' => [
                    'match-action.jpg',
                    'celebration-but.jpg',
                    'equipe-gagnante.jpg',
                    'remise-trophee.jpg'
                ],
                'featured' => false,
                'visibility' => 'public'
            ],
            
            'jardin-botanique' => [
                'name' => 'Visite Jardin Botanique',
                'description' => 'Découverte de la flore exotique et des serres tropicales. Une immersion dans la biodiversité mondiale.',
                'tags' => ['nature', 'botanique', 'fleurs', 'éducation', 'science', 'détente'],
                'photos' => [
                    'serre-tropicale.jpg',
                    'orchidees-rares.jpg',
                    'bassin-nenuphars.jpg',
                    'cactus-geants.jpg',
                    'papillons-jardin.jpg'
                ],
                'featured' => false,
                'visibility' => 'public'
            ],
            
            'soiree-gala' => [
                'name' => 'Soirée de Gala',
                'description' => 'Événement caritatif élégant avec dîner gastronomique et spectacles. Collecte de fonds pour une noble cause.',
                'tags' => ['gala', 'élégance', 'charité', 'soirée', 'spectacle', 'intérieur'],
                'photos' => [
                    'salle-decoree.jpg',
                    'invites-elegant.jpg',
                    'spectacle-danse.jpg',
                    'remise-cheque.jpg'
                ],
                'featured' => false,
                'visibility' => 'public'
            ]
        ];
    }
    
    /**
     * Générer les données d'exemple
     */
    public function generateSampleData($options = []) {
        try {
            $activitiesCount = $options['activities_count'] ?? count($this->activitiesData);
            $createPhotosFiles = $options['create_photos_files'] ?? false;
            $replaceExisting = $options['replace_existing'] ?? false;
            
            // Vérifier si les données existent déjà
            $activitiesFile = DATA_DIR . 'activities.json';
            
            if (file_exists($activitiesFile) && !$replaceExisting) {
                $existingData = json_decode(file_get_contents($activitiesFile), true);
                if (!empty($existingData)) {
                    throw new Exception("Des données existent déjà. Utilisez l'option 'replace_existing' pour les remplacer.");
                }
            }
            
            // Sélectionner les activités à créer
            $selectedActivities = array_slice($this->activitiesData, 0, $activitiesCount, true);
            
            // Créer les dossiers photos si demandé
            if ($createPhotosFiles) {
                $this->createSamplePhotoDirectories($selectedActivities);
            }
            
            // Ajouter les métadonnées automatiques
            foreach ($selectedActivities as $key => &$activity) {
                $activity['created_date'] = date('Y-m-d H:i:s', strtotime('-' . rand(1, 365) . ' days'));
                $activity['updated_date'] = date('Y-m-d H:i:s', strtotime('-' . rand(1, 30) . ' days'));
                
                // Générer des métadonnées pour toutes les photos si elles n'existent pas
                if (!isset($activity['photos_metadata'])) {
                    $activity['photos_metadata'] = [];
                }
                
                foreach ($activity['photos'] as $photo) {
                    if (!isset($activity['photos_metadata'][$photo])) {
                        $activity['photos_metadata'][$photo] = [
                            'width' => rand(1200, 2400),
                            'height' => rand(800, 1600),
                            'size' => rand(100000, 500000),
                            'mime_type' => 'image/jpeg',
                            'added_date' => $activity['created_date'],
                            'description' => $this->generatePhotoDescription($photo)
                        ];
                    }
                }
            }
            
            // Sauvegarder les activités
            file_put_contents($activitiesFile, json_encode($selectedActivities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
            
            // Générer la liste des photos
            $this->generatePhotosList($selectedActivities);
            
            // Créer des préférences utilisateur d'exemple
            $this->createSamplePreferences();
            
            // Log de l'opération
            $this->logger->adminAction('Données d\'exemple générées', [
                'activities_count' => count($selectedActivities),
                'photos_created' => $createPhotosFiles,
                'replaced_existing' => $replaceExisting
            ]);
            
            return [
                'success' => true,
                'message' => count($selectedActivities) . ' activités créées avec succès',
                'activities' => array_keys($selectedActivities),
                'total_photos' => array_sum(array_map(function($a) { return count($a['photos']); }, $selectedActivities))
            ];
            
        } catch (Exception $e) {
            $this->logger->error('Erreur génération données exemple: ' . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }
    
    /**
     * Créer les dossiers et fichiers photos d'exemple
     */
    private function createSamplePhotoDirectories($activities) {
        foreach ($activities as $activityKey => $activity) {
            $activityDir = PHOTOS_DIR . $activityKey;
            
            if (!is_dir($activityDir)) {
                mkdir($activityDir, 0755, true);
            }
            
            // Créer des fichiers de substitution pour les photos
            foreach ($activity['photos'] as $photo) {
                $photoPath = $activityDir . '/' . $photo;
                
                if (!file_exists($photoPath)) {
                    $this->createPlaceholderImage($photoPath, $photo);
                }
            }
            
            // Créer un fichier README pour l'activité
            $readmePath = $activityDir . '/README.txt';
            $readmeContent = "Activité: {$activity['name']}\n";
            $readmeContent .= "Description: {$activity['description']}\n";
            $readmeContent .= "Tags: " . implode(', ', $activity['tags']) . "\n";
            $readmeContent .= "Photos: " . count($activity['photos']) . "\n";
            $readmeContent .= "Créé le: " . date('Y-m-d H:i:s') . "\n\n";
            $readmeContent .= "Pour utiliser de vraies photos, remplacez les fichiers de substitution\n";
            $readmeContent .= "par vos propres images en conservant les mêmes noms de fichiers.\n";
            
            file_put_contents($readmePath, $readmeContent);
        }
    }
    
    /**
     * Créer une image de substitution
     */
    private function createPlaceholderImage($path, $filename) {
        $width = 800;
        $height = 600;
        
        // Créer une image simple avec GD si disponible
        if (extension_loaded('gd')) {
            $image = imagecreate($width, $height);
            
            // Couleurs
            $background = imagecolorallocate($image, 240, 240, 240);
            $textColor = imagecolorallocate($image, 100, 100, 100);
            $accentColor = imagecolorallocate($image, 11, 171, 223);
            
            // Fond dégradé simple
            imagefill($image, 0, 0, $background);
            
            // Bordure
            imagerectangle($image, 10, 10, $width-11, $height-11, $accentColor);
            
            // Texte
            $text = "Photo d'exemple\n" . basename($filename, '.jpg');
            $lines = explode('\n', $text);
            
            $y = $height / 2 - 20;
            foreach ($lines as $line) {
                $textWidth = strlen($line) * 10;
                $x = ($width - $textWidth) / 2;
                imagestring($image, 4, $x, $y, $line, $textColor);
                $y += 25;
            }
            
            // Icône photo simple (rectangle avec coin replié)
            $iconX = $width / 2 - 30;
            $iconY = $height / 2 - 60;
            imagerectangle($image, $iconX, $iconY, $iconX + 60, $iconY + 40, $accentColor);
            imageline($image, $iconX + 45, $iconY, $iconX + 45, $iconY + 15, $accentColor);
            imageline($image, $iconX + 45, $iconY + 15, $iconX + 60, $iconY + 15, $accentColor);
            
            // Sauvegarder
            imagejpeg($image, $path, 80);
            imagedestroy($image);
        } else {
            // Créer un fichier SVG si GD n'est pas disponible
            $svg = '<?xml version="1.0" encoding="UTF-8"?>
<svg width="' . $width . '" height="' . $height . '" xmlns="http://www.w3.org/2000/svg">
    <rect width="100%" height="100%" fill="#f0f0f0" stroke="#0BABDF" stroke-width="2"/>
    <text x="50%" y="45%" text-anchor="middle" font-family="Arial" font-size="24" fill="#666">
        Photo d\'exemple
    </text>
    <text x="50%" y="55%" text-anchor="middle" font-family="Arial" font-size="16" fill="#999">
        ' . htmlspecialchars(basename($filename, '.jpg')) . '
    </text>
    <rect x="' . ($width/2 - 40) . '" y="' . ($height/2 - 80) . '" width="80" height="50" 
          fill="none" stroke="#0BABDF" stroke-width="2"/>
</svg>';
            
            file_put_contents(str_replace('.jpg', '.svg', $path), $svg);
            
            // Créer un fichier texte comme substitut
            $textContent = "Image de substitution pour: " . $filename . "\n";
            $textContent .= "Remplacez ce fichier par une vraie image.\n";
            $textContent .= "Dimensions suggérées: {$width}x{$height}\n";
            $textContent .= "Créé le: " . date('Y-m-d H:i:s');
            
            file_put_contents(str_replace('.jpg', '.txt', $path), $textContent);
        }
    }
    
    /**
     * Générer une description pour une photo
     */
    private function generatePhotoDescription($filename) {
        $descriptions = [
            'panorama' => 'Vue panoramique exceptionnelle',
            'groupe' => 'Photo de groupe conviviale',
            'action' => 'Moment d\'action capturé',
            'detail' => 'Détail artistique et précis',
            'ambiance' => 'Ambiance chaleureuse',
            'landscape' => 'Paysage à couper le souffle',
            'portrait' => 'Portrait expressif',
            'scene' => 'Scène vivante et dynamique',
            'setup' => 'Installation soignée',
            'celebration' => 'Moment de célébration'
        ];
        
        $baseName = strtolower(basename($filename, '.jpg'));
        
        foreach ($descriptions as $keyword => $desc) {
            if (strpos($baseName, $keyword) !== false) {
                return $desc;
            }
        }
        
        return 'Belle prise de vue';
    }
    
    /**
     * Générer la liste complète des photos
     */
    private function generatePhotosList($activities) {
        $allPhotos = [];
        
        foreach ($activities as $activityKey => $activity) {
            foreach ($activity['photos'] as $photo) {
                $allPhotos[] = [
                    'filename' => $photo,
                    'activity' => $activity['name'],
                    'activity_key' => $activityKey,
                    'path' => PHOTOS_DIR . $activityKey . '/' . $photo,
                    'tags' => $activity['tags'],
                    'featured' => $activity['featured'] ?? false,
                    'metadata' => $activity['photos_metadata'][$photo] ?? []
                ];
            }
        }
        
        $photosFile = DATA_DIR . 'photos_list.json';
        file_put_contents($photosFile, json_encode($allPhotos, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        
        return count($allPhotos);
    }
    
    /**
     * Créer des préférences utilisateur d'exemple
     */
    private function createSamplePreferences() {
        $prefsFile = DATA_DIR . 'user_preferences.json';
        
        $preferences = [
            'interface' => [
                'theme' => 'default',
                'items_per_page' => 12,
                'sort_order' => 'name_asc',
                'show_metadata' => true,
                'language' => 'fr'
            ],
            'gallery' => [
                'auto_play_slideshow' => false,
                'slideshow_interval' => 5000,
                'zoom_mode' => 'click',
                'show_file_names' => true,
                'enable_keyboard_shortcuts' => true
            ],
            'admin' => [
                'auto_backup' => true,
                'backup_frequency' => 'weekly',
                'log_level' => 'INFO',
                'show_debug_info' => false
            ],
            'search' => [
                'search_in_descriptions' => true,
                'fuzzy_search' => true,
                'save_search_history' => true,
                'max_results' => 50
            ]
        ];
        
        file_put_contents($prefsFile, json_encode($preferences, JSON_PRETTY_PRINT));
    }
    
    /**
     * Créer des statistiques d'usage fictives
     */
    public function generateUsageStats() {
        $statsFile = DATA_DIR . 'usage_stats.json';
        
        $stats = [
            'visits' => [
                'total' => rand(1000, 5000),
                'this_month' => rand(100, 500),
                'this_week' => rand(20, 100),
                'today' => rand(5, 30)
            ],
            'popular_activities' => [
                'randonnee-montagne' => rand(100, 300),
                'voyage-italie' => rand(80, 200),
                'festival-musique' => rand(60, 150),
                'atelier-photo' => rand(40, 120),
                'jardin-botanique' => rand(30, 100)
            ],
            'search_terms' => [
                'montagne' => rand(50, 150),
                'voyage' => rand(40, 120),
                'musique' => rand(30, 100),
                'nature' => rand(35, 110),
                'art' => rand(25, 80)
            ],
            'devices' => [
                'desktop' => rand(40, 60),
                'mobile' => rand(30, 50),
                'tablet' => rand(10, 20)
            ],
            'generated_at' => date('Y-m-d H:i:s')
        ];
        
        file_put_contents($statsFile, json_encode($stats, JSON_PRETTY_PRINT));
        return $stats;
    }
    
    /**
     * Nettoyer les données d'exemple
     */
    public function cleanSampleData() {
        $filesToDelete = [
            DATA_DIR . 'activities.json',
            DATA_DIR . 'photos_list.json',
            DATA_DIR . 'user_preferences.json',
            DATA_DIR . 'usage_stats.json'
        ];
        
        $deletedFiles = 0;
        $deletedDirectories = 0;
        
        // Supprimer les fichiers de données
        foreach ($filesToDelete as $file) {
            if (file_exists($file)) {
                unlink($file);
                $deletedFiles++;
            }
        }
        
        // Supprimer les dossiers d'activités d'exemple
        foreach ($this->activitiesData as $activityKey => $activity) {
            $activityDir = PHOTOS_DIR . $activityKey;
            if (is_dir($activityDir)) {
                $this->removeDirectory($activityDir);
                $deletedDirectories++;
            }
        }
        
        $this->logger->adminAction('Données d\'exemple supprimées', [
            'files_deleted' => $deletedFiles,
            'directories_deleted' => $deletedDirectories
        ]);
        
        return [
            'success' => true,
            'files_deleted' => $deletedFiles,
            'directories_deleted' => $deletedDirectories
        ];
    }
    
    /**
     * Supprimer un dossier et son contenu
     */
    private function removeDirectory($dir) {
        if (!is_dir($dir)) return;
        
        $files = array_diff(scandir($dir), ['.', '..']);
        
        foreach ($files as $file) {
            $path = $dir . '/' . $file;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }
        
        rmdir($dir);
    }
    
    /**
     * Obtenir des informations sur les données d'exemple
     */
    public function getSampleDataInfo() {
        return [
            'available_activities' => count($this->activitiesData),
            'activities_list' => array_keys($this->activitiesData),
            'total_sample_photos' => array_sum(array_map(function($a) { 
                return count($a['photos']); 
            }, $this->activitiesData)),
            'sample_tags' => array_unique(array_merge(...array_map(function($a) { 
                return $a['tags']; 
            }, $this->activitiesData)))
        ];
    }
}

// Traitement des actions
$generator = new SampleDataGenerator();
$action = $_POST['action'] ?? $_GET['action'] ?? '';

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    header('Content-Type: application/json');
    
    switch ($action) {
        case 'generate':
            $options = [
                'activities_count' => intval($_POST['activities_count'] ?? 8),
                'create_photos_files' => isset($_POST['create_photos_files']),
                'replace_existing' => isset($_POST['replace_existing'])
            ];
            
            $result = $generator->generateSampleData($options);
            echo json_encode($result);
            break;
            
        case 'generate_stats':
            $stats = $generator->generateUsageStats();
            echo json_encode(['success' => true, 'stats' => $stats]);
            break;
            
        case 'clean':
            $result = $generator->cleanSampleData();
            echo json_encode($result);
            break;
            
        case 'info':
            $info = $generator->getSampleDataInfo();
            echo json_encode(['success' => true, 'info' => $info]);
            break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue']);
    }
    exit;
}

// Interface HTML
$info = $generator->getSampleDataInfo();
$existingData = file_exists(DATA_DIR . 'activities.json');

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Données d'Exemple - Galerie Photos</title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <style>
        .sample-preview {
            background: white;
            border-radius: 8px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            margin-bottom: 2rem;
            overflow: hidden;
        }
        
        .preview-header {
            background: linear-gradient(135deg, #0BABDF, #0A76B7);
            color: white;
            padding: 1.5rem;
            text-align: center;
        }
        
        .preview-content {
            padding: 2rem;
        }
        
        .activities-preview {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 1.5rem;
            margin-top: 1.5rem;
        }
        
        .activity-preview {
            border: 1px solid #ddd;
            border-radius: 8px;
            padding: 1rem;
            background: #f8f9fa;
        }
        
        .activity-title {
            font-weight: bold;
            color: #0A76B7;
            margin-bottom: 0.5rem;
        }
        
        .activity-meta {
            font-size: 0.9rem;
            color: #666;
            margin-bottom: 0.5rem;
        }
        
        .activity-tags {
            display: flex;
            flex-wrap: wrap;
            gap: 0.25rem;
        }
        
        .tag-preview {
            background: #0BABDF;
            color: white;
            padding: 0.2rem 0.5rem;
            border-radius: 12px;
            font-size: 0.7rem;
        }
        
        .generation-options {
            background: #f8f9fa;
            padding: 1.5rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .option-group {
            margin-bottom: 1rem;
        }
        
        .option-group label {
            display: flex;
            align-items: center;
            gap: 0.5rem;
            font-weight: 500;
            margin-bottom: 0.5rem;
        }
        
        .warning-box {
            background: #fff3cd;
            border: 1px solid #ffc107;
            color: #856404;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
        
        .info-box {
            background: #d1ecf1;
            border: 1px solid #bee5eb;
            color: #0c5460;
            padding: 1rem;
            border-radius: 8px;
            margin-bottom: 1.5rem;
        }
    </style>
</head>
<body>
    <header>
        <div class="container">
            <h1>Générateur de Données d'Exemple</h1>
            <nav>
                <a href="admin.php" class="btn btn-secondary">Retour Admin</a>
                <a href="index.php" class="btn btn-outline">Accueil</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            
            <!-- Aperçu des données disponibles -->
            <div class="sample-preview">
                <div class="preview-header">
                    <h2>📋 Données d'exemple disponibles</h2>
                    <p><?php echo $info['available_activities']; ?> activités • <?php echo $info['total_sample_photos']; ?> photos • <?php echo count($info['sample_tags']); ?> tags uniques</p>
                </div>
                
                <div class="preview-content">
                    <p>Ce générateur crée des activités fictives complètes avec leurs métadonnées pour vous permettre de tester et démontrer toutes les fonctionnalités de la galerie.</p>
                    
                    <div class="activities-preview">
                        <?php 
                        $previewActivities = array_slice($generator->getSampleDataInfo()['activities_list'], 0, 6);
                        $activitiesData = [
                            'randonnee-montagne' => ['name' => 'Randonnée en Montagne', 'photos' => 5, 'tags' => ['montagne', 'randonnée', 'nature']],
                            'festival-musique' => ['name' => 'Festival de Musique', 'photos' => 4, 'tags' => ['musique', 'festival', 'concert']],
                            'cours-cuisine' => ['name' => 'Cours de Cuisine', 'photos' => 4, 'tags' => ['cuisine', 'gastronomie', 'formation']],
                            'voyage-italie' => ['name' => 'Voyage en Italie', 'photos' => 5, 'tags' => ['voyage', 'italie', 'culture']],
                            'atelier-photo' => ['name' => 'Atelier Photographie', 'photos' => 4, 'tags' => ['photographie', 'formation', 'art']],
                            'jardin-botanique' => ['name' => 'Visite Jardin Botanique', 'photos' => 5, 'tags' => ['nature', 'botanique', 'fleurs']]
                        ];
                        
                        foreach ($previewActivities as $key): 
                            if (isset($activitiesData[$key])):
                                $activity = $activitiesData[$key];
                        ?>
                            <div class="activity-preview">
                                <div class="activity-title"><?php echo htmlspecialchars($activity['name']); ?></div>
                                <div class="activity-meta"><?php echo $activity['photos']; ?> photos</div>
                                <div class="activity-tags">
                                    <?php foreach (array_slice($activity['tags'], 0, 3) as $tag): ?>
                                        <span class="tag-preview"><?php echo htmlspecialchars($tag); ?></span>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        <?php 
                            endif;
                        endforeach; 
                        ?>
                    </div>
                </div>
            </div>

            <!-- Avertissement si des données existent -->
            <?php if ($existingData): ?>
                <div class="warning-box">
                    <strong>⚠️ Attention :</strong> Des données existent déjà dans votre galerie. 
                    La génération remplacera toutes les activités existantes si vous cochez l'option "Remplacer les données existantes".
                </div>
            <?php endif; ?>

            <!-- Formulaire de génération -->
            <section class="admin-actions">
                <h2>Génération des Données</h2>
                
                <form id="generate-form" class="generation-options">
                    <div class="option-group">
                        <label for="activities_count">
                            📊 Nombre d'activités à créer :
                        </label>
                        <input type="number" id="activities_count" name="activities_count" 
                               value="8" min="1" max="<?php echo $info['available_activities']; ?>" 
                               style="width: 100px;">
                        <small>Maximum : <?php echo $info['available_activities']; ?> activités disponibles</small>
                    </div>
                    
                    <div class="option-group">
                        <label>
                            <input type="checkbox" name="create_photos_files" id="create_photos_files">
                            📸 Créer les dossiers et fichiers photos de substitution
                        </label>
                        <small>Crée la structure complète avec des images de démonstration</small>
                    </div>
                    
                    <?php if ($existingData): ?>
                    <div class="option-group">
                        <label>
                            <input type="checkbox" name="replace_existing" id="replace_existing">
                            🔄 Remplacer les données existantes
                        </label>
                        <small style="color: #D30420;">ATTENTION : Supprimera toutes vos données actuelles !</small>
                    </div>
                    <?php endif; ?>
                    
                    <div style="margin-top: 1.5rem;">
                        <button type="submit" class="btn btn-primary">
                            🎯 Générer les données d'exemple
                        </button>
                    </div>
                </form>
            </section>

            <!-- Actions supplémentaires -->
            <section class="admin-actions">
                <h2>Actions Supplémentaires</h2>
                <div class="actions-grid">
                    
                    <div class="action-form">
                        <h3>Statistiques d'usage</h3>
                        <p>Génère des statistiques fictives pour démonstration</p>
                        <button onclick="generateStats()" class="btn btn-secondary">Générer</button>
                    </div>

                    <div class="action-form">
                        <h3>Informations détaillées</h3>
                        <p>Affiche le détail de toutes les données disponibles</p>
                        <button onclick="showDetailedInfo()" class="btn btn-secondary">Afficher</button>
                    </div>

                    <div class="action-form">
                        <h3>Nettoyer les données</h3>
                        <p>Supprime toutes les données d'exemple créées</p>
                        <button onclick="cleanSampleData()" class="btn btn-danger">Nettoyer</button>
                    </div>
                    
                </div>
            </section>

            <!-- Zone d'informations détaillées -->
            <div id="detailed-info" style="display: none;">
                <section>
                    <h2>Informations Détaillées</h2>
                    <div id="info-content" class="diagnostic-section">
                        <!-- Contenu chargé dynamiquement -->
                    </div>
                </section>
            </div>
            
        </div>
    </main>

    <script>
        // Génération des données
        document.getElementById('generate-form').addEventListener('submit', async function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('action', 'generate');
            
            // Confirmation si remplacement
            if (formData.get('replace_existing') && !confirm('Êtes-vous sûr de vouloir remplacer toutes les données existantes ?')) {
                return;
            }
            
            try {
                showLoading(true);
                const response = await fetch('sample_data.php', {
                    method: 'POST',
                    body: formData
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Génération réussie!\n\n${result.message}\nPhotos totales: ${result.total_photos}`);
                    
                    // Proposer d'aller voir le résultat
                    if (confirm('Voulez-vous voir le résultat dans la galerie ?')) {
                        window.open('index.php', '_blank');
                    }
                } else {
                    alert('Erreur: ' + result.error);
                }
            } catch (error) {
                alert('Erreur de communication: ' + error.message);
            } finally {
                showLoading(false);
            }
        });

        // Générer des statistiques
        async function generateStats() {
            try {
                showLoading(true);
                const response = await fetch('sample_data.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=generate_stats'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert('Statistiques d\'usage générées avec succès!');
                } else {
                    alert('Erreur: ' + result.error);
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            } finally {
                showLoading(false);
            }
        }

        // Afficher les informations détaillées
        async function showDetailedInfo() {
            try {
                showLoading(true);
                const response = await fetch('sample_data.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=info'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    displayDetailedInfo(result.info);
                } else {
                    alert('Erreur: ' + result.error);
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            } finally {
                showLoading(false);
            }
        }

        // Nettoyer les données d'exemple
        async function cleanSampleData() {
            if (!confirm('Êtes-vous sûr de vouloir supprimer toutes les données d\'exemple ?\n\nCette action est irréversible.')) {
                return;
            }
            
            try {
                showLoading(true);
                const response = await fetch('sample_data.php', {
                    method: 'POST',
                    headers: {'Content-Type': 'application/x-www-form-urlencoded'},
                    body: 'action=clean'
                });
                
                const result = await response.json();
                
                if (result.success) {
                    alert(`Nettoyage terminé!\n\nFichiers supprimés: ${result.files_deleted}\nDossiers supprimés: ${result.directories_deleted}`);
                    location.reload();
                } else {
                    alert('Erreur: ' + result.error);
                }
            } catch (error) {
                alert('Erreur: ' + error.message);
            } finally {
                showLoading(false);
            }
        }

        // Afficher les informations détaillées
        function displayDetailedInfo(info) {
            const section = document.getElementById('detailed-info');
            const content = document.getElementById('info-content');
            
            content.innerHTML = `
                <div style="padding: 2rem;">
                    <h3>Activités disponibles (${info.available_activities})</h3>
                    <div style="display: grid; grid-template-columns: repeat(auto-fit, minmax(200px, 1fr)); gap: 1rem; margin: 1rem 0;">
                        ${info.activities_list.map(activity => `
                            <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; border-left: 3px solid #0BABDF;">
                                ${activity.replace('-', ' ').replace(/\b\w/g, l => l.toUpperCase())}
                            </div>
                        `).join('')}
                    </div>
                    
                    <h3>Tags disponibles (${info.sample_tags.length})</h3>
                    <div style="display: flex; flex-wrap: wrap; gap: 0.5rem; margin: 1rem 0;">
                        ${info.sample_tags.map(tag => `
                            <span style="background: #0BABDF; color: white; padding: 0.25rem 0.75rem; border-radius: 15px; font-size: 0.9rem;">
                                ${tag}
                            </span>
                        `).join('')}
                    </div>
                    
                    <h3>Statistiques</h3>
                    <div style="background: #f8f9fa; padding: 1rem; border-radius: 5px; margin: 1rem 0;">
                        <p><strong>Total photos d'exemple :</strong> ${info.total_sample_photos}</p>
                        <p><strong>Moyenne par activité :</strong> ${Math.round(info.total_sample_photos / info.available_activities)} photos</p>
                        <p><strong>Tags uniques :</strong> ${info.sample_tags.length}</p>
                    </div>
                </div>
            `;
            
            section.style.display = 'block';
            section.scrollIntoView({ behavior: 'smooth' });
        }

        // Gestion du loading
        function showLoading(show) {
            document.body.style.opacity = show ? '0.7' : '1';
            document.body.style.pointerEvents = show ? 'none' : 'auto';
        }

        // Validation du formulaire
        document.getElementById('activities_count').addEventListener('input', function() {
            const max = parseInt(this.max);
            if (parseInt(this.value) > max) {
                this.value = max;
            }
        });

        // Animation au chargement
        document.addEventListener('DOMContentLoaded', function() {
            const elements = document.querySelectorAll('.sample-preview, .admin-actions, .generation-options');
            elements.forEach((el, index) => {
                el.style.opacity = '0';
                el.style.transform = 'translateY(20px)';
                
                setTimeout(() => {
                    el.style.transition = 'all 0.6s ease';
                    el.style.opacity = '1';
                    el.style.transform = 'translateY(0)';
                }, index * 200);
            });
        });
    </script>
</body>
</html>