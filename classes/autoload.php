<?php
/**
 * Système d'autoloading pour les classes du projet Gala
 * Charge automatiquement les fichiers de classes quand elles sont instanciées
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}

/**
 * Autoloader principal pour les classes du projet
 * Recherche les classes dans différents formats de noms de fichiers
 * 
 * @param string $className Nom de la classe à charger
 * @return bool True si la classe a été chargée, false sinon
 */
function gallaAutoloader($className) {
    // Répertoire des classes
    $classDir = __DIR__ . '/';
    
    // Formats de fichiers à essayer (par ordre de priorité)
    $fileFormats = [
        // Format principal : NomClasse -> nomclasse.class.php
        strtolower($className) . '.class.php',
        
        // Format alternatif : NomClasse -> NomClasse.class.php
        $className . '.class.php',
        
        // Format simple : NomClasse -> nomclasse.php
        strtolower($className) . '.php',
        
        // Format simple avec majuscules : NomClasse -> NomClasse.php
        $className . '.php',
        
        // Format avec underscores : MyClass -> my_class.class.php
        strtolower(preg_replace('/(?<!^)[A-Z]/', '_$0', $className)) . '.class.php',
        
        // Format pour classes avec points : Order.Liste -> orders.liste.class.php
        str_replace('.', '.', strtolower($className)) . '.class.php'
    ];
    
    // Essayer chaque format
    foreach ($fileFormats as $filename) {
        $filepath = $classDir . $filename;
        
        if (file_exists($filepath)) {
            require_once $filepath;
            
            // Vérifier que la classe a bien été définie
            if (class_exists($className, false)) {
                // Log du chargement en mode debug
                if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('logDebug')) {
                    logDebug("Classe auto-chargée: $className depuis $filename");
                }
                return true;
            }
        }
    }
    
    // Essayer dans des sous-dossiers si la classe contient des namespace
    if (strpos($className, '\\') !== false) {
        $parts = explode('\\', $className);
        $actualClassName = array_pop($parts);
        $namespace = implode('/', $parts);
        
        foreach ($fileFormats as $filename) {
            $filepath = $classDir . strtolower($namespace) . '/' . str_replace($className, $actualClassName, $filename);
            
            if (file_exists($filepath)) {
                require_once $filepath;
                
                if (class_exists($className, false)) {
                    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('logDebug')) {
                        logDebug("Classe avec namespace auto-chargée: $className depuis $filepath");
                    }
                    return true;
                }
            }
        }
    }
    
    // Classe non trouvée
    if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('logDebug')) {
        logDebug("Impossible de charger la classe: $className");
    }
    
    return false;
}

/**
 * Autoloader spécialisé pour les classes spécifiques du projet
 * Gère les cas particuliers et les alias de classes
 * 
 * @param string $className Nom de la classe
 * @return bool True si chargée, false sinon
 */
function gallaSpecialAutoloader($className) {
    $classDir = __DIR__ . '/';
    
    // Mapping spécial pour les classes avec noms particuliers
    $specialMappings = [
        'OrdersList' => 'orders.liste.class.php',
        'CsvHandler' => 'csv.class.php',
        'Logger' => 'logger.class.php',
        'Order' => 'order.class.php',
        
        // Aliases pour compatibilité
        'CSV' => 'csv.class.php',
        'Log' => 'logger.class.php',
        'Orders' => 'orders.liste.class.php',
        'Command' => 'order.class.php',
        'Commande' => 'order.class.php',
        'Liste' => 'orders.liste.class.php'
    ];
    
    // Vérifier les mappings spéciaux
    if (isset($specialMappings[$className])) {
        $filepath = $classDir . $specialMappings[$className];
        
        if (file_exists($filepath)) {
            require_once $filepath;
            
            // Pour les alias, vérifier la classe réelle
            $realClassName = $className;
            if (in_array($className, ['CSV'])) $realClassName = 'CsvHandler';
            if (in_array($className, ['Log'])) $realClassName = 'Logger';
            if (in_array($className, ['Orders', 'Liste'])) $realClassName = 'OrdersList';
            if (in_array($className, ['Command', 'Commande'])) $realClassName = 'Order';
            
            if (class_exists($realClassName, false)) {
                // Créer un alias si nécessaire
                if ($className !== $realClassName && !class_exists($className, false)) {
                    class_alias($realClassName, $className);
                }
                
                if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('logDebug')) {
                    logDebug("Classe spéciale auto-chargée: $className -> $realClassName depuis " . basename($filepath));
                }
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Autoloader pour les interfaces et traits
 * 
 * @param string $name Nom de l'interface ou trait
 * @return bool True si chargé, false sinon
 */
function gallaInterfaceAutoloader($name) {
    $classDir = __DIR__ . '/';
    
    // Formats pour interfaces et traits
    $formats = [
        'interfaces/' . strtolower($name) . '.interface.php',
        'traits/' . strtolower($name) . '.trait.php',
        strtolower($name) . '.interface.php',
        strtolower($name) . '.trait.php'
    ];
    
    foreach ($formats as $filename) {
        $filepath = $classDir . $filename;
        
        if (file_exists($filepath)) {
            require_once $filepath;
            
            if (interface_exists($name, false) || trait_exists($name, false)) {
                if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('logDebug')) {
                    logDebug("Interface/Trait auto-chargé: $name depuis $filename");
                }
                return true;
            }
        }
    }
    
    return false;
}

/**
 * Fonction principale d'autoloading qui combine tous les autoloaders
 * 
 * @param string $className Nom de la classe à charger
 * @return bool True si chargée, false sinon
 */
function gallaMainAutoloader($className) {
    // Éviter les tentatives de chargement en boucle
    static $loading = [];
    
    if (isset($loading[$className])) {
        return false;
    }
    
    $loading[$className] = true;
    
    try {
        // Essayer l'autoloader spécialisé en premier
        if (gallaSpecialAutoloader($className)) {
            unset($loading[$className]);
            return true;
        }
        
        // Essayer l'autoloader principal
        if (gallaAutoloader($className)) {
            unset($loading[$className]);
            return true;
        }
        
        // Essayer l'autoloader pour interfaces/traits
        if (gallaInterfaceAutoloader($className)) {
            unset($loading[$className]);
            return true;
        }
        
    } catch (Exception $e) {
        // Log de l'erreur si possible
        if (function_exists('logError')) {
            logError("Erreur lors du chargement de la classe $className: " . $e->getMessage());
        }
    }
    
    unset($loading[$className]);
    return false;
}

/**
 * Enregistrer l'autoloader avec gestion d'erreurs
 */
function registerGallaAutoloader() {
    // Vérifier si spl_autoload_register est disponible
    if (!function_exists('spl_autoload_register')) {
        if (function_exists('logError')) {
            logError("spl_autoload_register n'est pas disponible");
        }
        return false;
    }
    
    // Enregistrer notre autoloader
    $registered = spl_autoload_register('gallaMainAutoloader');
    
    if ($registered) {
        if (defined('DEBUG_MODE') && DEBUG_MODE && function_exists('logInfo')) {
            logInfo("Autoloader Gala enregistré avec succès");
        }
    } else {
        if (function_exists('logError')) {
            logError("Impossible d'enregistrer l'autoloader Gala");
        }
    }
    
    return $registered;
}

/**
 * Désactiver l'autoloader
 */
function unregisterGallaAutoloader() {
    return spl_autoload_unregister('gallaMainAutoloader');
}

/**
 * Obtenir la liste des classes disponibles dans le dossier classes/
 * 
 * @return array Liste des classes disponibles
 */
function getAvailableClasses() {
    $classDir = __DIR__ . '/';
    $classes = [];
    
    $files = glob($classDir . '*.class.php');
    
    foreach ($files as $file) {
        $filename = basename($file, '.class.php');
        
        // Convertir le nom de fichier en nom de classe probable
        $className = str_replace(['.', '_'], ['', ''], ucwords($filename, '._'));
        
        $classes[] = [
            'file' => basename($file),
            'probable_class' => $className,
            'size' => filesize($file),
            'modified' => date('Y-m-d H:i:s', filemtime($file))
        ];
    }
    
    return $classes;
}

/**
 * Vérifier l'état de l'autoloader
 * 
 * @return array Informations sur l'autoloader
 */
function getAutoloaderStatus() {
    $autoloaders = spl_autoload_functions();
    $gallaRegistered = false;
    
    if ($autoloaders) {
        foreach ($autoloaders as $autoloader) {
            if ($autoloader === 'gallaMainAutoloader') {
                $gallaRegistered = true;
                break;
            }
        }
    }
    
    return [
        'galla_registered' => $gallaRegistered,
        'total_autoloaders' => count($autoloaders ?? []),
        'available_classes' => count(getAvailableClasses()),
        'autoloaders' => $autoloaders
    ];
}

// Enregistrer automatiquement l'autoloader quand ce fichier est inclus
registerGallaAutoloader();

?>