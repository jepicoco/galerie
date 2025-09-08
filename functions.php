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

/**
 * Génère une référence de commande unique et robuste
 * @param string $existingReferencesFile Fichier pour vérifier l'unicité (optionnel)
 * @return string Référence unique
 */
function generateUniqueOrderReference($existingReferencesFile = null) {
    $maxAttempts = 100;
    $attempt = 0;
    
    do {
        // Base timestamp avec secondes et microsecondes
        $timestamp = date('YmdHis');
        
        // Ajouter des microsecondes (6 chiffres)
        $microseconds = str_pad(substr(microtime(), 2, 6), 6, '0', STR_PAD_LEFT);
        
        // Générer une partie aléatoire cryptographiquement sécurisée
        if (function_exists('random_int')) {
            $randomPart = random_int(100000, 999999);
        } else {
            // Fallback pour versions PHP < 7.0
            $randomPart = mt_rand(100000, 999999);
        }
        
        // Format: CMD + YYYYMMDDHHMMSS + 6_digit_microseconds + 6_digit_random
        $reference = 'CMD' . $timestamp . $microseconds . $randomPart;
        
        // Vérifier l'unicité si un fichier de référence est fourni
        if ($existingReferencesFile && file_exists($existingReferencesFile)) {
            $existingRefs = file_get_contents($existingReferencesFile);
            if (strpos($existingRefs, $reference) === false) {
                break; // Référence unique trouvée
            }
        } else {
            // Si pas de vérification fichier, vérifier dans le dossier commandes
            $commandesDir = 'commandes/';
            $tempDir = $commandesDir . 'temp/';
            $found = false;
            
            // Vérifier dans les fichiers existants
            foreach ([glob($commandesDir . '*.json'), glob($tempDir . '*.json')] as $files) {
                foreach ($files as $file) {
                    if (strpos(basename($file), $reference) !== false) {
                        $found = true;
                        break 2;
                    }
                }
            }
            
            if (!$found) {
                break; // Référence unique trouvée
            }
        }
        
        $attempt++;
        // Petite pause pour éviter les collisions de microsecondes
        usleep(1000); // 1ms
        
    } while ($attempt < $maxAttempts);
    
    if ($attempt >= $maxAttempts) {
        // En cas d'échec, ajouter un UUID-like suffix
        $reference .= bin2hex(random_bytes(4));
    }
    
    return $reference;
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
            sort($photos);
            
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
        $total += $item['total_price'];
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
 * Récupérer le prix d'une activité selon son type de tarification avec validation
 * @param string $activityKey Clé de l'activité
 * @param bool $validateData Valider la cohérence des données (défaut: true)
 * @return float Prix de l'activité
 */
function getActivityPrice($activityKey, $validateData = true) {
    global $ACTIVITY_PRICING;
    static $activitiesData = null;
    
    // Charger les données une seule fois (cache statique)
    if ($activitiesData === null) {
        $activitiesData = loadActivitiesConfiguration();
    }
    
    // Récupérer le type de tarification de l'activité
    $pricingType = DEFAULT_ACTIVITY_TYPE; // Valeur par défaut
    $originalPricingType = $pricingType;
    
    if (isset($activitiesData[$activityKey]['pricing_type'])) {
        $pricingType = $activitiesData[$activityKey]['pricing_type'];
        $originalPricingType = $pricingType;
    }
    
    // Validation de cohérence si demandée
    if ($validateData) {
        // Vérifier que le pricing_type existe dans ACTIVITY_PRICING
        if (!isset($ACTIVITY_PRICING[$pricingType])) {
            error_log("PRIX INCOHÉRENT: pricing_type '$pricingType' pour activité '$activityKey' non trouvé dans ACTIVITY_PRICING");
            $pricingType = DEFAULT_ACTIVITY_TYPE;
        }
        
        // Log si fallback utilisé
        if ($originalPricingType !== $pricingType) {
            error_log("PRIX FALLBACK: Activité '$activityKey' - Type '$originalPricingType' -> '$pricingType'");
        }
    }
    
    // Retourner le prix selon le type validé
    if (isset($ACTIVITY_PRICING[$pricingType])) {
        $price = $ACTIVITY_PRICING[$pricingType]['price'];
        
        // Validation supplémentaire du prix
        if (!is_numeric($price) || $price < 0) {
            error_log("PRIX INVALIDE: Prix '$price' pour activité '$activityKey' (type: '$pricingType')");
            $price = isset($ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]) 
                ? $ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]['price'] 
                : 2; // Prix de secours
        }
        
        return floatval($price);
    }
    
    // Fallback critique avec log d'erreur
    error_log("PRIX CRITIQUE: Impossible de trouver le prix pour activité '$activityKey' - utilisation prix de secours");
    return isset($ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]) 
        ? floatval($ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]['price']) 
        : 2.0; // Prix de secours
}

/**
 * Valide la cohérence des prix et types de tarification
 * @return array Rapport de validation avec erreurs trouvées
 */
function validatePricingConsistency() {
    global $ACTIVITY_PRICING;
    $activitiesData = loadActivitiesConfiguration();
    $errors = [];
    $warnings = [];
    
    // 1. Vérifier que tous les pricing_types utilisés existent dans ACTIVITY_PRICING
    foreach ($activitiesData as $activityKey => $activityData) {
        if (isset($activityData['pricing_type'])) {
            $pricingType = $activityData['pricing_type'];
            
            if (!isset($ACTIVITY_PRICING[$pricingType])) {
                $errors[] = "Activité '$activityKey': pricing_type '$pricingType' non défini dans ACTIVITY_PRICING";
            } else {
                // Vérifier que le prix est valide
                $price = $ACTIVITY_PRICING[$pricingType]['price'] ?? null;
                if (!is_numeric($price) || $price < 0) {
                    $errors[] = "Pricing_type '$pricingType': prix invalide '$price'";
                }
            }
        } else {
            $warnings[] = "Activité '$activityKey': aucun pricing_type défini (utilisera DEFAULT_ACTIVITY_TYPE)";
        }
    }
    
    // 2. Vérifier que DEFAULT_ACTIVITY_TYPE existe et est valide
    if (!defined('DEFAULT_ACTIVITY_TYPE')) {
        $errors[] = "DEFAULT_ACTIVITY_TYPE n'est pas définie";
    } elseif (!isset($ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE])) {
        $errors[] = "DEFAULT_ACTIVITY_TYPE '" . DEFAULT_ACTIVITY_TYPE . "' non trouvé dans ACTIVITY_PRICING";
    }
    
    // 3. Vérifier que tous les types ACTIVITY_PRICING ont un prix valide
    foreach ($ACTIVITY_PRICING as $type => $config) {
        if (!isset($config['price'])) {
            $errors[] = "ACTIVITY_PRICING['$type']: prix manquant";
        } elseif (!is_numeric($config['price']) || $config['price'] < 0) {
            $errors[] = "ACTIVITY_PRICING['$type']: prix invalide '{$config['price']}'";
        }
    }
    
    return [
        'valid' => empty($errors),
        'errors' => $errors,
        'warnings' => $warnings,
        'activities_count' => count($activitiesData),
        'pricing_types_count' => count($ACTIVITY_PRICING)
    ];
}

/**
 * Obtient le prix d'une activité avec information détaillée de debug
 * @param string $activityKey Clé de l'activité
 * @return array Information détaillée sur le calcul du prix
 */
function getActivityPriceDebug($activityKey) {
    global $ACTIVITY_PRICING;
    $activitiesData = loadActivitiesConfiguration();
    
    $debug = [
        'activity_key' => $activityKey,
        'activity_exists' => isset($activitiesData[$activityKey]),
        'configured_pricing_type' => null,
        'resolved_pricing_type' => DEFAULT_ACTIVITY_TYPE,
        'pricing_type_exists' => false,
        'final_price' => null,
        'fallback_used' => false,
        'errors' => []
    ];
    
    // Récupérer le pricing_type configuré
    if (isset($activitiesData[$activityKey]['pricing_type'])) {
        $debug['configured_pricing_type'] = $activitiesData[$activityKey]['pricing_type'];
        $debug['resolved_pricing_type'] = $activitiesData[$activityKey]['pricing_type'];
    } else {
        $debug['fallback_used'] = true;
        $debug['errors'][] = "Aucun pricing_type configuré pour l'activité";
    }
    
    // Vérifier si le pricing_type existe
    $debug['pricing_type_exists'] = isset($ACTIVITY_PRICING[$debug['resolved_pricing_type']]);
    
    if (!$debug['pricing_type_exists']) {
        $debug['errors'][] = "Pricing_type '{$debug['resolved_pricing_type']}' non trouvé";
        $debug['resolved_pricing_type'] = DEFAULT_ACTIVITY_TYPE;
        $debug['fallback_used'] = true;
    }
    
    // Calculer le prix final
    $debug['final_price'] = getActivityPrice($activityKey, false); // Sans re-validation pour éviter la récursion
    
    return $debug;
}

/**
 * Traduit un statut ou mode de paiement en français
 * @param string $status Le statut/mode à traduire
 * @return string Le statut traduit ou le statut original si pas de traduction
 */
function translateOrderStatus($status) {
    global $ORDER_STATUT_PRINT;
    return $ORDER_STATUT_PRINT[$status] ?? $status;
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
    $ACTIVITY_PRICING[$pricingType]['pricing_type'] = $pricingType;
    
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
 * Nettoie les commandes temporaires anciennes avec optimisation de fréquence
 * @param string $ordersDir Répertoire des commandes
 * @param int $minIntervalMinutes Intervalle minimum entre nettoyages (défaut: 30 minutes)
 * @param bool $force Forcer le nettoyage même si l'intervalle n'est pas écoulé
 * @return int Nombre de fichiers supprimés
 */
function cleanOldTempOrders($ordersDir, $minIntervalMinutes = 30, $force = false) {
    $tempDir = $ordersDir . 'temp/';
    $lockFile = $tempDir . '.last_cleanup';
    
    if (!is_dir($tempDir)) {
        return 0;
    }
    
    // Vérifier si le nettoyage récent a eu lieu (sauf si forcé)
    if (!$force && file_exists($lockFile)) {
        $lastCleanup = filemtime($lockFile);
        $timeSinceLastCleanup = time() - $lastCleanup;
        $minInterval = $minIntervalMinutes * 60; // Conversion en secondes
        
        if ($timeSinceLastCleanup < $minInterval) {
            // Nettoyage trop récent, on skip (sauf si forcé)
            return 0;
        }
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
    
    // Mettre à jour le timestamp du dernier nettoyage
    touch($lockFile);
    
    if ($deletedCount > 0) {
        error_log("Nettoyage des commandes temporaires: $deletedCount fichier(s) supprimé(s)");
    }
    
    return $deletedCount;
}

/**
 * Nettoyage automatique intelligent pour les pages publiques
 * Ne s'exécute que si nécessaire (intervalle étendu pour éviter la surcharge)
 * @param string $ordersDir Répertoire des commandes
 * @return int Nombre de fichiers supprimés
 */
function smartCleanupTempOrders($ordersDir) {
    // Pour les pages publiques, nettoyage toutes les 2 heures maximum
    return cleanOldTempOrders($ordersDir, 120, false);
}

/**
 * Nettoyage administratif (peut être plus fréquent)
 * @param string $ordersDir Répertoire des commandes 
 * @param bool $force Forcer le nettoyage
 * @return int Nombre de fichiers supprimés
 */
function adminCleanupTempOrders($ordersDir, $force = false) {
    // Pour les admins, nettoyage toutes les 15 minutes maximum
    return cleanOldTempOrders($ordersDir, 15, $force);
}

/**
 * Sanitise une valeur pour éviter les injections de formules Excel/CSV
 * @param string $value Valeur à sanitiser
 * @return string Valeur sanitisée
 */
function sanitizeCSVValue($value) {
    if (!is_string($value)) {
        return $value;
    }
    
    // Caractères dangereux pour injection de formules Excel/Calc
    $dangerousChars = ['=', '+', '-', '@', '\t', '\r', '\n'];
    
    // Vérifier si la valeur commence par un caractère dangereux
    $firstChar = substr($value, 0, 1);
    if (in_array($firstChar, $dangerousChars)) {
        // Préfixer avec une apostrophe pour forcer le traitement comme texte
        $value = "'" . $value;
    }
    
    // Nettoyer les caractères de contrôle problématiques pour CSV
    $value = str_replace(["\t", "\r\n", "\r", "\n"], [' ', ' ', ' ', ' '], $value);
    
    // Supprimer les caractères de contrôle invisibles potentiellement dangereux
    $value = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $value);
    
    return $value;
}

/**
 * Sanitise un tableau de données pour export CSV sécurisé
 * @param array $data Données à sanitiser
 * @return array Données sanitisées
 */
function sanitizeCSVData($data) {
    $sanitizedData = [];
    foreach ($data as $row) {
        if (is_array($row)) {
            $sanitizedRow = [];
            foreach ($row as $value) {
                $sanitizedRow[] = sanitizeCSVValue($value);
            }
            $sanitizedData[] = $sanitizedRow;
        } else {
            $sanitizedData[] = sanitizeCSVValue($row);
        }
    }
    return $sanitizedData;
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

// ==========================================
// FONCTIONS D'AMÉLIORATION DES STATUTS
// ==========================================

/**
 * Met à jour automatiquement le statut d'une commande en fonction des données de paiement
 * @param string $csvFile Fichier CSV des commandes
 * @return array Résultats de la mise à jour
 */
function updateOrderStatusFromPayments($csvFile = null) {
    if (!$csvFile) {
        $csvFile = COMMANDES_DIR . 'commandes.csv';
    }
    
    if (!file_exists($csvFile)) {
        return ['error' => 'Fichier CSV non trouvé', 'updated' => 0];
    }
    
    $updatedCount = 0;
    $errors = [];
    $tempFile = $csvFile . '.tmp';
    $statusChanges = []; // Pour tracker les changements de statuts
    
    // Lire le fichier CSV
    $handle = fopen($csvFile, 'r');
    $tempHandle = fopen($tempFile, 'w');
    
    if (!$handle || !$tempHandle) {
        return ['error' => 'Impossible d\'ouvrir les fichiers', 'updated' => 0];
    }
    
    // Copier l'en-tête
    $header = fgetcsv($handle, 0, ';');
    fputcsv($tempHandle, $header, ';');
    
    // Trouver les indices des colonnes importantes
    $statusIndex = array_search('Statut commande', $header);
    $paymentMethodIndex = array_search('Mode de paiement', $header);
    $paymentDateIndex = array_search('Date encaissement', $header);
    $refIndex = array_search('REF', $header);
    
    if ($statusIndex === false || $paymentMethodIndex === false) {
        fclose($handle);
        fclose($tempHandle);
        unlink($tempFile);
        return ['error' => 'Colonnes requises non trouvées', 'updated' => 0];
    }
    
    // Traiter chaque ligne
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $currentStatus = $row[$statusIndex];
        $paymentMethod = $row[$paymentMethodIndex];
        $paymentDate = $row[$paymentDateIndex] ?? '';
        $reference = $row[$refIndex] ?? '';
        
        // Logique de mise à jour automatique
        $newStatus = $currentStatus;
        
        if ($currentStatus === 'validated') {
            // Si paiement enregistré et date présente, passer à 'paid'
            if (!empty($paymentDate) && $paymentMethod !== 'unpaid') {
                if (isValidStatusTransition($currentStatus, 'paid')) {
                    $newStatus = 'paid';
                    $updatedCount++;
                    error_log("Status auto-updated: $reference validated → paid (payment recorded: $paymentDate)");
                    
                    // Tracker le changement de statut pour les hooks
                    if (!isset($statusChanges[$reference])) {
                        $statusChanges[$reference] = ['from' => $currentStatus, 'to' => $newStatus];
                    }
                }
            }
        } elseif ($currentStatus === 'paid') {
            // Logique future : si photos préparées, passer à 'prepared'
            // Pour l'instant, on garde 'paid'
        }
        
        // Mettre à jour la ligne
        $row[$statusIndex] = $newStatus;
        
        // Écrire la ligne (avec sanitisation)
        $sanitizedRow = array_map('sanitizeCSVValue', $row);
        fputcsv($tempHandle, $sanitizedRow, ';');
    }
    
    fclose($handle);
    fclose($tempHandle);
    
    // Remplacer le fichier original
    if (!rename($tempFile, $csvFile)) {
        unlink($tempFile);
        return ['error' => 'Impossible de sauvegarder les modifications', 'updated' => $updatedCount];
    }
    
    // Appliquer les hooks après mise à jour réussie du CSV
    foreach ($statusChanges as $reference => $change) {
        if ($change['to'] === 'validated') {
            addOrderToPreparerFile($reference);
        } elseif ($change['to'] === 'retrieved') {
            removeOrderFromPreparerFile($reference);
        }
    }
    
    return [
        'success' => true,
        'updated' => $updatedCount,
        'errors' => $errors,
        'status_changes' => $statusChanges
    ];
}

/**
 * Vérifie et corrige les incohérences de statuts dans le fichier CSV
 * @param string $csvFile Fichier CSV à vérifier
 * @return array Rapport de vérification et corrections
 */
function checkAndFixStatusInconsistencies($csvFile = null) {
    if (!$csvFile) {
        $csvFile = COMMANDES_DIR . 'commandes.csv';
    }
    
    $report = [
        'total_rows' => 0,
        'inconsistencies' => [],
        'corrections' => [],
        'errors' => []
    ];
    
    if (!file_exists($csvFile)) {
        $report['errors'][] = 'Fichier CSV non trouvé';
        return $report;
    }
    
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        $report['errors'][] = 'Impossible d\'ouvrir le fichier CSV';
        return $report;
    }
    
    // Lire l'en-tête
    $header = fgetcsv($handle, 0, ';');
    $statusIndex = array_search('Statut commande', $header);
    $paymentMethodIndex = array_search('Mode de paiement', $header);
    $paymentDateIndex = array_search('Date encaissement', $header);
    $refIndex = array_search('REF', $header);
    
    $lineNumber = 1;
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $lineNumber++;
        $report['total_rows']++;
        
        $currentStatus = $row[$statusIndex] ?? '';
        $paymentMethod = $row[$paymentMethodIndex] ?? '';
        $paymentDate = $row[$paymentDateIndex] ?? '';
        $reference = $row[$refIndex] ?? '';
        
        // Vérifications des incohérences
        
        // 1. Statut 'validated' mais paiement enregistré
        if ($currentStatus === 'validated' && !empty($paymentDate) && $paymentMethod !== 'unpaid') {
            $report['inconsistencies'][] = [
                'line' => $lineNumber,
                'reference' => $reference,
                'issue' => 'Status validated but payment recorded',
                'current_status' => $currentStatus,
                'payment_date' => $paymentDate,
                'payment_method' => $paymentMethod,
                'suggested_status' => 'paid'
            ];
        }
        
        // 2. Statut 'paid' mais pas de date de paiement
        if ($currentStatus === 'paid' && empty($paymentDate)) {
            $report['inconsistencies'][] = [
                'line' => $lineNumber,
                'reference' => $reference,
                'issue' => 'Status paid but no payment date',
                'current_status' => $currentStatus,
                'payment_date' => $paymentDate,
                'suggested_action' => 'Add payment date or change status to validated'
            ];
        }
        
        // 3. Méthode de paiement incohérente
        if ($currentStatus === 'paid' && $paymentMethod === 'unpaid') {
            $report['inconsistencies'][] = [
                'line' => $lineNumber,
                'reference' => $reference,
                'issue' => 'Status paid but payment method is unpaid',
                'current_status' => $currentStatus,
                'payment_method' => $paymentMethod,
                'suggested_action' => 'Fix payment method or change status'
            ];
        }
    }
    
    fclose($handle);
    
    return $report;
}

/**
 * Met à jour le statut d'une commande spécifique
 * @param string $reference Référence de la commande
 * @param string $newStatus Nouveau statut
 * @param string $csvFile Fichier CSV (optionnel)
 * @return bool Succès de la mise à jour
 */
function updateOrderStatus($reference, $newStatus, $csvFile = null) {
    if (!$csvFile) {
        $csvFile = COMMANDES_DIR . 'commandes.csv';
    }
    
    if (!file_exists($csvFile)) {
        error_log("updateOrderStatus: Fichier CSV non trouvé: $csvFile");
        return false;
    }
    
    $tempFile = $csvFile . '.tmp';
    $handle = fopen($csvFile, 'r');
    $tempHandle = fopen($tempFile, 'w');
    
    if (!$handle || !$tempHandle) {
        error_log("updateOrderStatus: Impossible d'ouvrir les fichiers");
        return false;
    }
    
    // Copier l'en-tête
    $header = fgetcsv($handle, 0, ';');
    fputcsv($tempHandle, $header, ';');
    
    $statusIndex = array_search('Statut commande', $header);
    $refIndex = array_search('REF', $header);
    $updated = false;
    
    // Traiter chaque ligne
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $currentRef = $row[$refIndex] ?? '';
        $currentStatus = $row[$statusIndex] ?? '';
        
        if ($currentRef === $reference) {
            // Vérifier si la transition est valide
            if (isValidStatusTransition($currentStatus, $newStatus)) {
                $row[$statusIndex] = $newStatus;
                $updated = true;
                error_log("updateOrderStatus: $reference - $currentStatus → $newStatus");
            } else {
                error_log("updateOrderStatus: Transition invalide pour $reference: $currentStatus → $newStatus");
            }
        }
        
        // Écrire la ligne (avec sanitisation)
        $sanitizedRow = array_map('sanitizeCSVValue', $row);
        fputcsv($tempHandle, $sanitizedRow, ';');
    }
    
    fclose($handle);
    fclose($tempHandle);
    
    if ($updated && rename($tempFile, $csvFile)) {
        // Hook automatique : ajouter au fichier préparateur si statut devient 'validated'
        if ($newStatus === 'validated') {
            addOrderToPreparerFile($reference);
        }
        // Hook automatique : supprimer du fichier préparateur si statut devient 'retrieved'
        elseif ($newStatus === 'retrieved') {
            removeOrderFromPreparerFile($reference);
        }
        
        return true;
    } else {
        unlink($tempFile);
        return false;
    }
}

/**
 * Obtient le statut actuel d'une commande
 * @param string $reference Référence de la commande
 * @param string $csvFile Fichier CSV (optionnel)
 * @return string|null Statut actuel ou null si non trouvé
 */
function getOrderStatus($reference, $csvFile = null) {
    if (!$csvFile) {
        $csvFile = COMMANDES_DIR . 'commandes.csv';
    }
    
    if (!file_exists($csvFile)) {
        return null;
    }
    
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        return null;
    }
    
    // Lire l'en-tête
    $header = fgetcsv($handle, 0, ';');
    $statusIndex = array_search('Statut commande', $header);
    $refIndex = array_search('REF', $header);
    
    // Chercher la commande
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        $currentRef = $row[$refIndex] ?? '';
        if ($currentRef === $reference) {
            fclose($handle);
            return $row[$statusIndex] ?? null;
        }
    }
    
    fclose($handle);
    return null;
}

/**
 * Ajoute automatiquement une commande au fichier commandes_a_preparer.csv
 * Appelée automatiquement quand une commande passe au statut 'validated'
 * @param string $reference Référence de la commande
 * @return bool Succès de l'ajout
 */
function addOrderToPreparerFile($reference) {
    $csvFile = COMMANDES_DIR . 'commandes.csv';
    $preparerFile = COMMANDES_DIR . 'commandes_a_preparer.csv';
    
    if (!file_exists($csvFile)) {
        error_log("addOrderToPreparerFile: Fichier CSV principal non trouvé");
        return false;
    }
    
    // Lire les données de la commande depuis le CSV principal
    $handle = fopen($csvFile, 'r');
    if (!$handle) {
        error_log("addOrderToPreparerFile: Impossible d'ouvrir le fichier CSV");
        return false;
    }
    
    // Lire l'en-tête
    $header = fgetcsv($handle, 0, ';');
    $refIndex = array_search('REF', $header);
    $statusIndex = array_search('Statut commande', $header);
    
    if ($refIndex === false || $statusIndex === false) {
        fclose($handle);
        error_log("addOrderToPreparerFile: Colonnes requises non trouvées");
        return false;
    }
    
    // Chercher toutes les lignes de cette commande
    $orderLines = [];
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (($row[$refIndex] ?? '') === $reference) {
            // Vérifier que le statut est bien 'validated'
            if (($row[$statusIndex] ?? '') === 'validated') {
                $orderLines[] = $row;
            }
        }
    }
    fclose($handle);
    
    if (empty($orderLines)) {
        error_log("addOrderToPreparerFile: Aucune ligne trouvée pour $reference avec statut 'validated'");
        return false;
    }
    
    // Créer ou compléter le fichier préparateur
    $isNewFile = !file_exists($preparerFile);
    
    // Créer l'en-tête si nouveau fichier
    if ($isNewFile) {
        $bom = "\xEF\xBB\xBF";
        $preparerHeader = $bom . "Ref;Nom;Prenom;Email;Tel;Nom du dossier;Nom de la photo;Quantite;Date de preparation;Date de recuperation\n";
        if (file_put_contents($preparerFile, $preparerHeader) === false) {
            error_log("addOrderToPreparerFile: Impossible de créer le fichier préparateur");
            return false;
        }
    }
    
    // Vérifier si la commande n'est pas déjà dans le fichier préparateur
    if (!$isNewFile && isOrderInPreparerFile($reference, $preparerFile)) {
        error_log("addOrderToPreparerFile: Commande $reference déjà dans le fichier préparateur");
        return true; // Pas une erreur, juste déjà présent
    }
    
    // Convertir et ajouter les lignes
    $preparerLines = '';
    foreach ($orderLines as $row) {
        // Mapping des colonnes CSV principal vers préparateur
        $preparerLine = [
            $row[0] ?? '',  // REF
            $row[1] ?? '',  // Nom
            $row[2] ?? '',  // Prenom
            $row[3] ?? '',  // Email
            $row[4] ?? '',  // Telephone
            $row[6] ?? '',  // Dossier (activité)
            $row[7] ?? '',  // N de la photo
            $row[8] ?? '',  // Quantite
            '',             // Date de preparation (vide)
            ''              // Date de recuperation (vide)
        ];
        
        // Sanitiser les données
        $sanitizedLine = array_map('sanitizeCSVValue', $preparerLine);
        $preparerLines .= implode(';', $sanitizedLine) . "\n";
    }
    
    // Ajouter au fichier
    $result = file_put_contents($preparerFile, $preparerLines, FILE_APPEND);
    
    if ($result !== false) {
        $photoCount = count($orderLines);
        error_log("addOrderToPreparerFile: Commande $reference ajoutée au fichier préparateur ($photoCount photos)");
        return true;
    } else {
        error_log("addOrderToPreparerFile: Impossible d'écrire dans le fichier préparateur");
        return false;
    }
}

/**
 * Vérifie si une commande est déjà présente dans le fichier préparateur
 * @param string $reference Référence de la commande
 * @param string $preparerFile Chemin vers le fichier préparateur
 * @return bool True si déjà présent
 */
function isOrderInPreparerFile($reference, $preparerFile) {
    if (!file_exists($preparerFile)) {
        return false;
    }
    
    $handle = fopen($preparerFile, 'r');
    if (!$handle) {
        return false;
    }
    
    // Ignorer l'en-tête
    fgetcsv($handle, 0, ';');
    
    // Chercher la référence
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (($row[0] ?? '') === $reference) {
            fclose($handle);
            return true;
        }
    }
    
    fclose($handle);
    return false;
}

/**
 * Supprime une commande du fichier préparateur (si elle passe à retrieved par exemple)
 * @param string $reference Référence de la commande
 * @return bool Succès de la suppression
 */
function removeOrderFromPreparerFile($reference) {
    $preparerFile = COMMANDES_DIR . 'commandes_a_preparer.csv';
    
    if (!file_exists($preparerFile)) {
        return true; // Rien à supprimer
    }
    
    $tempFile = $preparerFile . '.tmp';
    $handle = fopen($preparerFile, 'r');
    $tempHandle = fopen($tempFile, 'w');
    
    if (!$handle || !$tempHandle) {
        error_log("removeOrderFromPreparerFile: Impossible d'ouvrir les fichiers");
        return false;
    }
    
    // Copier l'en-tête
    $header = fgetcsv($handle, 0, ';');
    fputcsv($tempHandle, $header, ';');
    
    $removed = false;
    
    // Copier toutes les lignes sauf celles de cette référence
    while (($row = fgetcsv($handle, 0, ';')) !== false) {
        if (($row[0] ?? '') !== $reference) {
            fputcsv($tempHandle, $row, ';');
        } else {
            $removed = true;
        }
    }
    
    fclose($handle);
    fclose($tempHandle);
    
    if (rename($tempFile, $preparerFile)) {
        if ($removed) {
            error_log("removeOrderFromPreparerFile: Commande $reference supprimée du fichier préparateur");
        }
        return true;
    } else {
        unlink($tempFile);
        error_log("removeOrderFromPreparerFile: Impossible de sauvegarder les modifications");
        return false;
    }
}
?>