<?php
/**
 * Configuration d'exemple - Galerie Photos Gala
 * 
 * Copiez ce fichier vers config.php et adaptez les valeurs
 * 
 * @author Votre Nom
 * @license GPL-3.0
 * @version 1.1.0
 */

// ==========================================
// SÉCURITÉ - À CHANGER OBLIGATOIREMENT
// ==========================================

// Empêcher l'accès direct
if (!defined('GALLERY_ACCESS')) {
    die('Accès direct non autorisé');
}

// Mode debug (DÉSACTIVER EN PRODUCTION)
define('DEBUG_MODE', false);

if (DEBUG_MODE) {
    ini_set('display_errors', 1);
    ini_set('display_startup_errors', 1);
    error_reporting(E_ALL);
}

// ==========================================
// CONFIGURATION GÉNÉRALE
// ==========================================

// Informations du site
define('SITE_NAME', 'Galerie Photos');
define('SITE_DESCRIPTION', 'Galerie photos organisée par activités');
define('SITE_VERSION', '1.1.0');

// Devise
define('CURRENCY_SYMBOL', '€');
define('CURRENCY_FORMAT', 'fr');

// ==========================================
// SÉCURITÉ
// ==========================================

// OBLIGATOIRE : Changer ce mot de passe !
define('ADMIN_PASSWORD', 'CHANGEZ_MOI_IMMEDIATEMENT');

// OBLIGATOIRE : Générer une clé unique !
// Utilisez : bin2hex(random_bytes(32))
define('SECURITY_KEY', 'CHANGEZ_CETTE_CLE_UNIQUE_32_CARACTERES_MINIMUM');

// ==========================================
// TARIFICATION DES ACTIVITÉS
// ==========================================

$ACTIVITY_PRICING = [
    'PHOTO' => [
        'price' => 2,
        'display_name' => 'Photo',
        'description' => 'Tirage photo classique'
    ],
    'USB' => [
        'price' => 15,
        'display_name' => 'Clé USB',
        'description' => 'Support USB avec toutes les vidéos'
    ]
    // Ajoutez d'autres types selon vos besoins
];

// Type par défaut pour les nouvelles activités
define('DEFAULT_ACTIVITY_TYPE', 'PHOTO');

// ==========================================
// CONFIGURATION DES STATUTS
// ==========================================

$ORDER_STATUT = [
    'COMMAND_STATUS' => ['pending', 'validated', 'cancelled'],
    'PAYMENT_STATUS' => ['unpaid', 'paid', 'refunded'],
    'RETRIEVAL_STATUS' => ['not_retrieved', 'retrieved'],
    'PAYMENT_METHODS' => ['cash', 'check', 'card', 'transfer'],
    'EXPORT_STATUS' => ['', 'exported']
];

// ==========================================
// CHEMINS DES DOSSIERS
// ==========================================

define('COMMANDES_DIR', 'commandes/');
define('PHOTOS_DIR', 'photos/');
define('DATA_DIR', 'data/');
define('CSS_DIR', 'css/');
define('JS_DIR', 'js/');
define('LOGS_DIR', 'logs/');
define('EXPORTS_DIR', 'exports/');
define('ARCHIVES_DIR', 'archives/');

// Constantes pour les filtres de commandes
define('ORDERSLIST_TEMP', 0);
define('ORDERSLIST_UNPAID', 1);
define('ORDERSLIST_TOPREPARE', 2);
define('ORDERSLIST_CLOSED', 3);

// ==========================================
// CONFIGURATION DES IMAGES
// ==========================================

// Tailles maximales des images
define('MAX_IMAGE_WIDTH', 2048);
define('MAX_IMAGE_HEIGHT', 2048);
define('JPEG_QUALITY', 85);

// Taille des miniatures
define('THUMBNAIL_WIDTH', 200);
define('THUMBNAIL_HEIGHT', 200);

// Formats d'images supportés
define('ALLOWED_IMAGE_TYPES', ['jpg', 'jpeg', 'png', 'gif', 'webp']);

// ==========================================
// CONFIGURATION DES LOGS
// ==========================================

define('LOGS_ENABLED', true);
define('LOG_LEVEL', 'INFO'); // DEBUG, INFO, WARNING, ERROR
define('LOG_MAX_SIZE', 10 * 1024 * 1024); // 10MB
define('LOG_ROTATION', true);

// ==========================================
// CONFIGURATION EMAIL (OPTIONNEL)
// ==========================================

// Paramètres SMTP (à configurer dans email_handler.php)
define('MAIL_ENABLED', true);
define('MAIL_FRONT', false); //true pour activer l'envoi d'email de confirmation à la validation de la commande par un utilisateur
define('SMTP_ENABLED', false);
define('SMTP_HOST', 'smtp.exemple.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre@email.com');
define('SMTP_PASSWORD', 'votre_mot_de_passe_email');
define('SMTP_FROM_EMAIL', 'noreply@votre-domaine.com');
define('SMTP_FROM_NAME', 'Galerie Photos');

// ==========================================
// CONFIGURATION AVANCÉE
// ==========================================

// Limite de temps d'exécution pour les scripts lourds
define('SCRIPT_TIME_LIMIT', 300); // 5 minutes

// Limite mémoire
define('MEMORY_LIMIT', '256M');

// Nettoyage automatique des commandes temporaires
define('TEMP_ORDER_LIFETIME', 20); // heures

// Cache des images
define('IMAGE_CACHE_ENABLED', true);
define('IMAGE_CACHE_LIFETIME', 7 * 24 * 3600); // 7 jours

// ==========================================
// FONCTIONS UTILITAIRES
// ==========================================

/**
 * Obtient le prix d'une activité
 */
function getActivityPrice($activityKey) {
    global $ACTIVITY_PRICING;
    
    // Chercher dans les types définis
    foreach ($ACTIVITY_PRICING as $type => $config) {
        if ($activityKey === $type) {
            return $config['price'];
        }
    }
    
    // Utiliser le type par défaut
    return $ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE]['price'] ?? 0;
}

/**
 * Obtient les informations d'un type d'activité
 */
function getActivityTypeInfo($activityKey) {
    global $ACTIVITY_PRICING;
    
    foreach ($ACTIVITY_PRICING as $type => $config) {
        if ($activityKey === $type) {
            return $config;
        }
    }
    
    return $ACTIVITY_PRICING[DEFAULT_ACTIVITY_TYPE] ?? [
        'price' => 0,
        'display_name' => 'Inconnu',
        'description' => 'Type d\'activité non défini'
    ];
}

/**
 * Vérifie si l'utilisateur est administrateur
 */
function is_admin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

/**
 * Crée les dossiers requis s'ils n'existent pas
 */
function createRequiredDirectories() {
    $dirs = [
        DATA_DIR,
        LOGS_DIR,
        EXPORTS_DIR,
        ARCHIVES_DIR,
        COMMANDES_DIR,
        COMMANDES_DIR . 'temp/',
        PHOTOS_DIR,
        PHOTOS_DIR . 'cache/',
        PHOTOS_DIR . 'cache/thumbnails/',
        PHOTOS_DIR . 'cache/resized/'
    ];
    
    foreach ($dirs as $dir) {
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
    }
}

/**
 * Valide la configuration
 */
function validateConfig() {
    $errors = [];
    
    // Vérifier les paramètres de sécurité
    if (ADMIN_PASSWORD === 'CHANGEZ_MOI_IMMEDIATEMENT') {
        $errors[] = 'Mot de passe administrateur non changé';
    }
    
    if (SECURITY_KEY === 'CHANGEZ_CETTE_CLE_UNIQUE_32_CARACTERES_MINIMUM') {
        $errors[] = 'Clé de sécurité non changée';
    }
    
    if (strlen(SECURITY_KEY) < 32) {
        $errors[] = 'Clé de sécurité trop courte (minimum 32 caractères)';
    }
    
    // Vérifier les extensions PHP
    $requiredExtensions = ['json', 'gd', 'session'];
    foreach ($requiredExtensions as $ext) {
        if (!extension_loaded($ext)) {
            $errors[] = "Extension PHP manquante : $ext";
        }
    }
    
    // Vérifier les dossiers
    $requiredDirs = [DATA_DIR, LOGS_DIR, PHOTOS_DIR, COMMANDES_DIR];
    foreach ($requiredDirs as $dir) {
        if (!is_dir($dir)) {
            $errors[] = "Dossier manquant : $dir";
        } elseif (!is_writable($dir)) {
            $errors[] = "Dossier non accessible en écriture : $dir";
        }
    }
    
    return empty($errors) ? true : $errors;
}

// ==========================================
// INITIALISATION
// ==========================================

// Créer les dossiers requis
createRequiredDirectories();

// Définir les limites PHP
if (defined('SCRIPT_TIME_LIMIT')) {
    set_time_limit(SCRIPT_TIME_LIMIT);
}

if (defined('MEMORY_LIMIT')) {
    ini_set('memory_limit', MEMORY_LIMIT);
}

// Configuration de la timezone
date_default_timezone_set('Europe/Paris');

// Démarrer la session si pas déjà fait
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

// ==========================================
// NOTES IMPORTANTES
// ==========================================

/*
AVANT LA MISE EN PRODUCTION :

1. Changez ADMIN_PASSWORD avec un mot de passe fort
2. Changez SECURITY_KEY avec une clé aléatoire unique
3. Définissez DEBUG_MODE à false
4. Configurez SMTP_* si vous voulez les emails
5. Vérifiez les permissions des dossiers
6. Testez l'application complètement

COMMANDES UTILES :

# Générer un mot de passe hash
php -r "echo password_hash('votre_mot_de_passe', PASSWORD_DEFAULT);"

# Générer une clé de sécurité
php -r "echo bin2hex(random_bytes(32));"

# Vérifier la configuration
php -r "require 'config.php'; var_dump(validateConfig());"
*/

?>