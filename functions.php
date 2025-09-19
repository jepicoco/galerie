<?php
require_once 'classes/autoload.php';
require_once 'email_handler.php';
// Récupération de la configuration Watermark
$watermarkConfig = getWatermarkConfig();

// Vérifier si c'est une connexion admin
$is_admin = is_admin();

// Fonction pour lire les valeurs du watermark depuis config_watermark.php
function getWatermarkConfig() {
    $configFile = 'config_watermark.php';
    $defaults = [
        'WATERMARK_ENABLED' => true,
        'WATERMARK_TEXT' => 'Gala de danse',
        'WATERMARK_OPACITY' => 0.3,
        'WATERMARK_SIZE' => '24px',
        'WATERMARK_COLOR' => '#FFFFFF',
        'WATERMARK_ANGLE' => '-45'
    ];

    if (file_exists($configFile)) {
        $content = file_get_contents($configFile);
        
        // Extraire les valeurs avec regex
        foreach ($defaults as $key => $default) {
            if (preg_match("/define\('$key',\s*(.+?)\);/", $content, $matches)) {
                $value = trim($matches[1], " '\"");
                if ($key === 'WATERMARK_ENABLED') {
                    $defaults[$key] = ($value === 'true');
                } elseif ($key === 'WATERMARK_OPACITY') {
                    $defaults[$key] = floatval($value);
                } else {
                    $defaults[$key] = $value;
                }
            }
        }
    }
    
    return $defaults;
}

// Vérifier si c'est une connexion admin
function is_admin(){
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}


// Fonctions utilitaires (extraites de admin.php)
function scanPhotosDirectories() {
    $photosDir = PHOTOS_DIR;
    $activities = [];
    
    if (!is_dir($photosDir)) {
        return $activities;
    }
    
    $directories = array_filter(glob($photosDir . '*'), 'is_dir');
    
    foreach ($directories as $dir) {
        $activityKey = basename($dir);
        
        // Ignorer les dossiers système
        if (in_array($activityKey, ['.', '..', 'thumbs', 'cache'])) {
            continue;
        }
        
        $photos = [];
        $imageExtensions = ['jpg', 'jpeg', 'png', 'gif', 'webp'];
        
        foreach ($imageExtensions as $ext) {
            $files = glob($dir . '/*.' . $ext);
            $files = array_merge($files, glob($dir . '/*.' . strtoupper($ext)));
            
            foreach ($files as $file) {
                $photos[] = basename($file);
            }
        }
        
        if (!empty($photos)) {
            natsort($photos);
            $photos = array_values($photos); // Réindexer après natsort
            
            $activities[$activityKey] = [
                'name' => ucfirst(str_replace(['_', '-'], ' ', $activityKey)),
                'photos' => $photos,
                'description' => '',
                'tags' => [],
                'featured' => false,
                'visibility' => 'public',
                'pricing_type' => $pricing_type ?? DEFAULT_ACTIVITY_TYPE,
                'created_at' => date('Y-m-d H:i:s'),
                'updated_at' => date('Y-m-d H:i:s')
            ];
        }
    }
    
    // Fusionner avec les données existantes
    $existingActivities = loadActivitiesData();
    foreach ($activities as $key => $activity) {
        if (isset($existingActivities[$key])) {
            // Garder les métadonnées existantes, mettre à jour les photos
            $existingActivities[$key]['photos'] = $activity['photos'];
            $existingActivities[$key]['updated_at'] = date('Y-m-d H:i:s');
        } else {
            $existingActivities[$key] = $activity;
        }
    }
    
    saveActivitiesData($existingActivities);
    return $activities;
}

/**
 * Sauver les données des activités depuis le fichier JSON
 */
function saveActivitiesData($activities) {
    $dataFile = DATA_DIR . 'activities.json';
    
    if (!is_dir(DATA_DIR)) {
        mkdir(DATA_DIR, 0755, true);
    }
    
    return file_put_contents($dataFile, json_encode($activities, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
}

/**
 * Charger les données des activités depuis le fichier JSON
 */
function loadActivitiesData() {
    $dataFile = DATA_DIR . 'activities.json';

    if (file_exists($dataFile)) {
        $content = file_get_contents($dataFile);
        return json_decode($content, true) ?: [];
    }

    return [];
}

/**
 * Debug des fichiers de commandes
 */
function debugOrderFiles($reference, $ordersDir) {
    $files = [
        'temp' => $ordersDir . 'temp/' . $reference . '.json',
        'validated' => glob($ordersDir . $reference . '_*.json')
    ];
    
    $info = [
        'reference' => $reference,
        'temp_exists' => file_exists($files['temp']),
        'validated_files' => $files['validated'],
        'validated_count' => count($files['validated'])
    ];
    
    error_log("Debug commande $reference: " . json_encode($info));
    return $info;
}

function calculateOrderTotal($order) {
    $total = 0;
    foreach ($order['items'] as $item) {
        $total += $item['total_price']*$item['quantity'];
    }
    return $total;
}

/**
 * Charger les données des activités avec leur configuration de tarification
 */
function loadActivitiesConfiguration() {
    $activitiesFile = DATA_DIR . 'activities.json';
    
    if (!file_exists($activitiesFile)) {
        return [];
    }
    
    $content = file_get_contents($activitiesFile);
    return json_decode($content, true) ?: [];
}

/**
 * Récupérer le prix d'une activité selon son type de tarification
 */
function getActivityPrice($activityKey) {
    global $ACTIVITY_PRICING;
    static $activitiesData = null;
    
    // Charger les données une seule fois (cache statique)
    if ($activitiesData === null) {
        $activitiesData = loadActivitiesConfiguration();
    }
    
    // Récupérer le type de tarification de l'activité
    $pricingType = DEFAULT_ACTIVITY_TYPE; // Valeur par défaut
    
    if (isset($activitiesData[$activityKey]['pricing_type'])) {
        $pricingType = $activitiesData[$activityKey]['pricing_type'];
    }
    
    // Retourner le prix selon le type
    if (isset($ACTIVITY_PRICING[$pricingType])) {
        return $ACTIVITY_PRICING[$pricingType]['price'];
    }
    
    // Fallback sur le prix par défaut
    return isset($ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]) 
        ? $ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]['price'] 
        : 2; // Prix de secours
}

/**
 * Récupérer les informations complètes du type de tarification
 */
function getActivityTypeInfo($activityKey) {
    global $ACTIVITY_PRICING;
    static $activitiesData = null;
    
    if ($activitiesData === null) {
        $activitiesData = loadActivitiesConfiguration();
    }

    $pricingType = $activitiesData[$activityKey]['pricing_type'] ?? DEFAULT_ACTIVITY_TYPE;
    
    return $ACTIVITY_PRICING[$pricingType] ?? $ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE];
}

// Charger les données des activités
$activities_file = DATA_DIR . 'activities.json';
$activities = [];
if (file_exists($activities_file)) {
    $activities = json_decode(file_get_contents($activities_file), true) ?: [];
}

// Enrichir les données des activités avec les URLs des thumbnails
$enrichedActivities = [];

foreach ($activities as $activityKey => $activity) {
    // Copier les données de base de l'activité
    $enrichedActivity = $activity;
    
    // Enrichir chaque photo avec les URLs
    if (isset($activity['photos']) && is_array($activity['photos'])) {
        $enrichedPhotos = [];
        
        foreach ($activity['photos'] as $photoName) {
            // Construire le chemin relatif de la photo
            $photoPath = $activityKey . '/' . $photoName;
            
            // Créer l'objet photo enrichi
            $enrichedPhoto = [
                'name' => $photoName,
                'path' => $photoPath,
                'originalUrl' => GetImageUrl($photoPath, IMG_ORIGINAL),
                'thumbPath' => GetImageUrl($photoPath, IMG_THUMBNAIL),
                'resizedUrl' => GetImageUrl($photoPath, IMG_RESIZED)
            ];
            
            // Ajouter les métadonnées si elles existent
            if (isset($activity['photos_metadata'][$photoName])) {
                $enrichedPhoto['metadata'] = $activity['photos_metadata'][$photoName];
            }
            
            $enrichedPhotos[] = $enrichedPhoto;
        }
        
        // Remplacer le tableau simple par le tableau enrichi
        $enrichedActivity['photos'] = $enrichedPhotos;
    }
    
    $enrichedActivities[$activityKey] = $enrichedActivity;
}

/**
 * Compte le nombre de commandes réglées en attente de retrait
 * @return int Nombre de commandes
 * @version 1.0
 */
function countPendingRetrievals() {
    require_once 'classes/orders.list.class.php';
    $ordersList = new OrdersList();
    return $ordersList->countPendingRetrievals();
}

/**
 * Compte le nombre de commandes en attente de paiement
 * @return int Nombre de commandes
 * @version 1.0
 */
function countPendingPayments() {
    require_once 'classes/orders.list.class.php';
    $ordersList = new OrdersList();
    return $ordersList->countPendingPayments();
}

/**
 * Nettoie les commandes temporaires anciennes
 * @param string $ordersDir Répertoire des commandes
 * @return int Nombre de fichiers supprimés
 */
function cleanOldTempOrders($ordersDir) {
    $tempDir = $ordersDir . 'temp/';
    
    if (!is_dir($tempDir)) {
        return 0;
    }
    
    $tempFiles = glob($tempDir . '*.json');
    $deletedCount = 0;
    $maxAge = 20 * 3600; // 20 heures en secondes
    
    foreach ($tempFiles as $file) {
        $fileAge = time() - filemtime($file);
        
        if ($fileAge > $maxAge) {
            if (unlink($file)) {
                $deletedCount++;
                error_log("Commande temporaire supprimée (age: " . round($fileAge/3600, 1) . "h): " . basename($file));
            }
        }
    }
    
    return $deletedCount;
}

/**
 * Obtenir les statistiques de consultations pour l'administration
 * @param string $period Période ('today', 'week', 'month')
 * @return array Statistiques de consultations
 * @version 1.0
 */
function getConsultationsSummary($period = 'today') {
    $consultationsFile = DATA_DIR . 'consultations.json';
    
    if (!file_exists($consultationsFile)) {
        return [
            'total_consultations' => 0,
            'unique_photos' => 0,
            'unique_sessions' => 0,
            'most_viewed_activity' => null
        ];
    }
    
    $content = file_get_contents($consultationsFile);
    $consultations = json_decode($content, true);
    
    if (!is_array($consultations)) {
        return [];
    }
    
    // Filtrer par période
    $startTime = getConsultationStartTimeForPeriod($period);
    $filteredConsultations = array_filter($consultations, function($consultation) use ($startTime) {
        return strtotime($consultation['timestamp']) >= $startTime;
    });
    
    // Calculer les statistiques de base
    $uniquePhotos = [];
    $uniqueSessions = [];
    $activityCounts = [];
    
    foreach ($filteredConsultations as $consultation) {
        $photoKey = $consultation['activity_key'] . '/' . $consultation['photo_name'];
        $uniquePhotos[$photoKey] = true;
        $uniqueSessions[$consultation['session_id']] = true;
        
        $activity = $consultation['activity_key'];
        $activityCounts[$activity] = ($activityCounts[$activity] ?? 0) + 1;
    }
    
    // Trouver l'activité la plus consultée
    $mostViewedActivity = null;
    if (!empty($activityCounts)) {
        arsort($activityCounts);
        $mostViewedActivity = array_key_first($activityCounts);
    }
    
    return [
        'total_consultations' => count($filteredConsultations),
        'unique_photos' => count($uniquePhotos),
        'unique_sessions' => count($uniqueSessions),
        'most_viewed_activity' => $mostViewedActivity,
        'activity_counts' => $activityCounts
    ];
}

/**
 * Nettoyer les anciennes consultations
 * @param int $daysToKeep Nombre de jours à conserver
 * @return int Nombre d'entrées supprimées
 */
function cleanOldConsultations($daysToKeep = 30) {
    $consultationsFile = DATA_DIR . 'consultations.json';
    
    if (!file_exists($consultationsFile)) {
        return 0;
    }
    
    $content = file_get_contents($consultationsFile);
    $consultations = json_decode($content, true);
    
    if (!is_array($consultations)) {
        return 0;
    }
    
    $cutoffTime = time() - ($daysToKeep * 24 * 60 * 60);
    $originalCount = count($consultations);
    
    // Filtrer les consultations récentes
    $recentConsultations = array_filter($consultations, function($consultation) use ($cutoffTime) {
        return strtotime($consultation['timestamp']) >= $cutoffTime;
    });
    
    // Sauvegarder les consultations filtrées
    $json = json_encode(array_values($recentConsultations), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    file_put_contents($consultationsFile, $json, LOCK_EX);
    
    return $originalCount - count($recentConsultations);
}

/**
 * Obtenir le timestamp de début pour une période de consultation
 * @param string $period Période demandée
 * @return int Timestamp de début
 */
function getConsultationStartTimeForPeriod($period) {
    switch ($period) {
        case 'today':
            return strtotime('today');
        case 'yesterday':
            return strtotime('yesterday');
        case 'week':
            return strtotime('-7 days');
        case 'month':
            return strtotime('-30 days');
        case 'year':
            return strtotime('-1 year');
        default:
            return strtotime('today');
    }
}

/**
 * Obtenir les photos les plus consultées pour l'interface admin
 * @param int $limit Nombre maximum de photos à retourner
 * @param string $period Période à analyser
 * @return array Photos populaires avec leurs statistiques
 */
function getTopConsultedPhotos($limit = 5, $period = 'week') {
    $consultationsFile = DATA_DIR . 'consultations.json';
    
    if (!file_exists($consultationsFile)) {
        return [];
    }
    
    $content = file_get_contents($consultationsFile);
    $consultations = json_decode($content, true);
    
    if (!is_array($consultations)) {
        return [];
    }
    
    // Filtrer par période
    $startTime = getConsultationStartTimeForPeriod($period);
    $filteredConsultations = array_filter($consultations, function($consultation) use ($startTime) {
        return strtotime($consultation['timestamp']) >= $startTime;
    });
    
    // Compter les consultations par photo
    $photoCounts = [];
    foreach ($filteredConsultations as $consultation) {
        $photoKey = $consultation['activity_key'] . '/' . $consultation['photo_name'];
        
        if (!isset($photoCounts[$photoKey])) {
            $photoCounts[$photoKey] = [
                'activity_key' => $consultation['activity_key'],
                'photo_name' => $consultation['photo_name'],
                'consultation_count' => 0,
                'unique_sessions' => []
            ];
        }
        
        $photoCounts[$photoKey]['consultation_count']++;
        $photoCounts[$photoKey]['unique_sessions'][$consultation['session_id']] = true;
    }
    
    // Convertir les sessions uniques en nombre et générer les URLs
    foreach ($photoCounts as $photoKey => &$photoData) {
        $photoData['unique_sessions'] = count($photoData['unique_sessions']);
        $photoData['thumbnail_url'] = GetImageUrl(
            $photoData['activity_key'] . '/' . $photoData['photo_name'], 
            IMG_THUMBNAIL
        );
    }
    
    // Trier par nombre de consultations
    uasort($photoCounts, function($a, $b) {
        return $b['consultation_count'] - $a['consultation_count'];
    });
    
    return array_slice($photoCounts, 0, $limit);
}

/**
 * Nettoie une valeur pour l'utilisation dans un CSV
 * Échappe les caractères spéciaux qui peuvent causer des problèmes dans les CSV
 * 
 * @param mixed $value La valeur à nettoyer
 * @return string La valeur nettoyée pour CSV
 */
function cleanCSVValue($value) {
    $cleanValue = str_replace([";", "\n", "\r", "\""], ["", "", "", "\"\""], (string)$value);
    return $cleanValue;
}
?>