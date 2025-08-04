<?php
/**
 * Gestionnaire de commandes pour la galerie photos
 * 
 * Gère la création, modification et validation des commandes photos
 */
// Démarrer la capture de sortie pour éviter les sorties parasites
ob_start();

define('GALLERY_ACCESS', true);

require_once 'config.php';

session_start();

try {
    require_once 'functions.php';
    require_once 'classes/autoload.php';
} catch (Exception $e) {
    // Nettoyer la sortie et retourner une erreur JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

// Nettoyer toute sortie parasite
ob_clean();
header('Content-Type: application/json');

// Vérifier si c'est une connexion admin
$is_admin = is_admin();

// Créer le dossier commandes s'il n'existe pas
$ordersDir = 'commandes/';
if (!is_dir($ordersDir)) {
    mkdir($ordersDir, 0755, true);
}

$action = $_POST['action'] ?? '';

// Vérifier que l'action existe
if (empty($action)) {
    echo json_encode(['success' => false, 'error' => 'Aucune action spécifiée']);
    exit;
}

$logger = Logger::getInstance();

try {
    switch ($action) {
        case 'create_order':
        $customerData = [
            'lastname' => trim($_POST['lastname'] ?? ''),
            'firstname' => trim($_POST['firstname'] ?? ''),
            'phone' => trim($_POST['phone'] ?? ''),
            'email' => trim($_POST['email'] ?? '')
        ];
        
        // Validation des données
        if (empty($customerData['lastname']) || empty($customerData['firstname']) || 
            empty($customerData['phone']) || empty($customerData['email'])) {
            echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis']);
            break;
        }
        
        if (!filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
            echo json_encode(['success' => false, 'error' => 'Adresse email invalide']);
            break;
        }
        
        // Générer référence unique
        $reference = 'CMD' . date('YmdHi') . rand(10, 99);
        
        $order = [
            'reference' => $reference,
            'customer' => $customerData,
            'items' => [],
            'created_at' => date('Y-m-d H:i:s'),
            'status' => 'temp' // Marquer comme temporaire
        ];
        
        $_SESSION['current_order'] = $order;
        
        // Sauvegarder la commande temporaire
        saveTempOrder($order, $ordersDir);
        
        $logger->info("Nouvelle commande temporaire créée: $reference");
        
        echo json_encode([
            'success' => true,
            'reference' => $reference,
            'customer' => $customerData
        ]);
        break;

    /**
     * Ajouter un item au panier
     * Version: 2.0
     * Correction: suppression duplication causant doublement quantités
     */
    case 'add_item':
        if (!isset($_SESSION['current_order'])) {
            echo json_encode(['success' => false, 'error' => 'Aucune commande active']);
            break;
        }
        
        $photoPath = trim($_POST['photo_path'] ?? '');
        $activityKey = trim($_POST['activity_key'] ?? '');
        $photoName = trim($_POST['photo_name'] ?? '');
        
        if (empty($photoPath) || empty($activityKey) || empty($photoName)) {
            echo json_encode(['success' => false, 'error' => 'Données de photo manquantes']);
            break;
        }
        
        $itemKey = $activityKey . '/' . $photoName;
        $unitPrice = getActivityPrice($activityKey);

        // BLOC UNIQUE - sans duplication
        if (!isset($_SESSION['current_order']['items'][$itemKey])) {
            $_SESSION['current_order']['items'][$itemKey] = [
                'photo_path' => GetImageUrl(htmlspecialchars($activityKey) . '/' . htmlspecialchars($photoName),IMG_THUMBNAIL),
                'activity_key' => $activityKey,
                'photo_name' => $photoName,
                'quantity' => 1,
                'unit_price' => $unitPrice,
                'total_price' => $unitPrice,
                'pricing_type' => getActivityTypeInfo($activityKey)['display_name'] ?? 'Photo standard'
            ];
        } else {
            $_SESSION['current_order']['items'][$itemKey]['quantity']++;
            $_SESSION['current_order']['items'][$itemKey]['total_price'] = 
            $_SESSION['current_order']['items'][$itemKey]['quantity'] * $unitPrice;
        }
        
        // Mettre à jour la commande temporaire
        saveTempOrder($_SESSION['current_order'], $ordersDir);
        
        $totalQuantity = array_sum(array_column($_SESSION['current_order']['items'], 'quantity'));
        
        echo json_encode([
            'success' => true,
            'cart_count' => $totalQuantity
        ]);
        break;

    case 'update_quantity':
        if (!isset($_SESSION['current_order'])) {
            echo json_encode(['success' => false, 'error' => 'Aucune commande active']);
            break;
        }
        
        $itemKey = $_POST['item_key'] ?? '';
        $quantity = intval($_POST['quantity'] ?? 1);
        
        if ($quantity < 1) {
            echo json_encode(['success' => false, 'error' => 'Quantité invalide']);
            break;
        }
        
        if (isset($_SESSION['current_order']['items'][$itemKey])) {
            $_SESSION['current_order']['items'][$itemKey]['quantity'] = $quantity;
            
            // Mettre à jour la commande temporaire
            saveTempOrder($_SESSION['current_order'], $ordersDir);
            
            $totalQuantity = array_sum(array_column($_SESSION['current_order']['items'], 'quantity'));
            
            echo json_encode([
                'success' => true,
                'cart_count' => $totalQuantity
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Article non trouvé']);
        }
        break;

    case 'remove_item':
        if (!isset($_SESSION['current_order'])) {
            echo json_encode(['success' => false, 'error' => 'Aucune commande active']);
            break;
        }
        
        $itemKey = $_POST['item_key'] ?? '';
        
        if (isset($_SESSION['current_order']['items'][$itemKey])) {
            unset($_SESSION['current_order']['items'][$itemKey]);
            
            // Mettre à jour la commande temporaire
            saveTempOrder($_SESSION['current_order'], $ordersDir);
            
            $totalQuantity = array_sum(array_column($_SESSION['current_order']['items'], 'quantity'));
            
            echo json_encode([
                'success' => true,
                'cart_count' => $totalQuantity
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Article non trouvé']);
        }
        break;

    case 'validate_order':
        if (!isset($_SESSION['current_order']) || empty($_SESSION['current_order']['items'])) {
            echo json_encode(['success' => false, 'error' => 'Panier vide']);
            break;
        }
        
        $order = $_SESSION['current_order'];
        $order['status'] = 'validated';
        $order['validated_at'] = date('Y-m-d H:i:s');
        
        $reference = $order['reference'];
        $filename = $reference . '_' . 
                strtoupper($order['customer']['lastname']) . '_' . 
                date('YmdHi') . '.json';
        
        $orderFile = $ordersDir . $filename;
        
        // Étape 1 : Vérifier si c'est une mise à jour d'une commande existante
        $isUpdate = checkOrderExistsInCSV($reference, $ordersDir . 'commandes.csv');
        
        if ($isUpdate) {
            $logger->info("Mise à jour de la commande existante: $reference");
            removeOrderFromCSV($reference, $ordersDir . 'commandes.csv');
            removeOldOrderFile($reference, $ordersDir);
        } else {
            $logger->info("Nouvelle validation de commande: $reference");
        }
        
        // Étape 2 : Créer le nouveau fichier JSON
        $orderJson = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
        
        if (file_put_contents($orderFile, $orderJson) === false) {
            $logger->error("Impossible de créer le fichier JSON: $orderFile");
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la création du fichier de commande']);
            break;
        }
        
        $logger->info("Fichier JSON créé avec succès: $filename");
        
        // Étape 3 : Mettre à jour le fichier CSV
        if (!addOrderToCSV($order, $ordersDir)) {
            $logger->error("Impossible de mettre à jour le fichier CSV pour: $reference");
            unlink($orderFile);
            echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour du fichier Excel']);
            break;
        }
        
        $logger->info("Fichier CSV mis à jour avec succès pour: $reference");
        
        // Étape 4 : Envoyer l'email de confirmation
        $emailHandler = new EmailHandler();
        $emailSent = $emailHandler->sendOrderConfirmation($order, $isUpdate);
        
        if (!$emailSent) {
            $logger->warning("Échec envoi email pour la commande: $reference");
            // Ne pas faire échouer la validation pour un problème d'email
        }
        
        // Étape 5 : Supprimer la commande temporaire
        removeTempOrder($reference, $ordersDir);
        
        // Étape 6 : Logger l'action
        $logger->adminAction('Commande validée', [
            'reference' => $reference,
            'customer' => $order['customer']['lastname'] . ' ' . $order['customer']['firstname'],
            'items_count' => count($order['items']),
            'is_update' => $isUpdate,
            'filename' => $filename,
            'email_sent' => $emailSent
        ]);
        
        // Vider la session
        unset($_SESSION['current_order']);
        
        echo json_encode([
            'success' => true,
            'reference' => $reference,
            'filename' => $filename,
            'is_update' => $isUpdate,
            'email_sent' => $emailSent
        ]);
    break;

    case 'load_temp_order':
        
        $reference = trim($_POST['reference'] ?? '');
        
        if (empty($reference)) {
            echo json_encode(['success' => false, 'error' => 'Référence manquante']);
            break;
        }
        
        $tempOrder = loadTempOrder($reference, $ordersDir);
        
        if (!$tempOrder) {
            echo json_encode(['success' => false, 'error' => 'Commande temporaire introuvable']);
            break;
        }
        
        // Restaurer la commande en session
        $_SESSION['current_order'] = $tempOrder;
        
        $logger->info("Commande temporaire rechargée: $reference");
        
        echo json_encode([
            'success' => true,
            'order' => $tempOrder
        ]);
        break;

    case 'list_temp_orders':

        $tempDir = $ordersDir . 'temp/';
        $tempFiles = is_dir($tempDir) ? glob($tempDir . '*.json') : [];
        $tempOrders = [];
        
        foreach ($tempFiles as $file) {
            $content = file_get_contents($file);
            if ($content !== false) {
                $orderData = json_decode($content, true);
                if ($orderData && isset($orderData['reference'])) {
                    $tempOrders[] = [
                        'reference' => $orderData['reference'],
                        'customer_name' => ($orderData['customer']['firstname'] ?? '') . ' ' . ($orderData['customer']['lastname'] ?? ''),
                        'customer_email' => $orderData['customer']['email'] ?? '',
                        'created_at' => $orderData['created_at'] ?? 'Date inconnue',
                        'items_count' => count($orderData['items'] ?? []),
                        'is_temp' => true // Marquer explicitement comme temporaire
                    ];
                }
            }
        }
        
        // Trier par date de création (plus récent en premier)
        usort($tempOrders, function($a, $b) {
            return strtotime($b['created_at']) - strtotime($a['created_at']);
        });
        
        echo json_encode(['success' => true, 'orders' => $tempOrders]);
        break;
            
    case 'get_current_order':
        if (isset($_SESSION['current_order'])) {
            echo json_encode([
                'success' => true,
                'order' => $_SESSION['current_order']
            ]);
        } else {
            echo json_encode(['success' => false, 'error' => 'Aucune commande en cours']);
        }
        break;
            
    case 'load_order':
        $reference = trim($_POST['reference'] ?? '');
        
        if (empty($reference)) {
            echo json_encode(['success' => false, 'error' => 'Référence manquante']);
            break;
        }
        
        // Chercher le fichier de commande
        $orderFiles = glob($ordersDir . $reference . '_*.json');
        
        if (empty($orderFiles)) {
            echo json_encode(['success' => false, 'error' => 'Commande introuvable avec la référence: ' . $reference]);
            break;
        }
        
        // Charger la première commande trouvée
        $orderFile = $orderFiles[0];
        $orderContent = file_get_contents($orderFile);
        
        if ($orderContent === false) {
            echo json_encode(['success' => false, 'error' => 'Impossible de lire le fichier de commande']);
            break;
        }
        
        $orderData = json_decode($orderContent, true);
        
        if (!$orderData) {
            echo json_encode(['success' => false, 'error' => 'Fichier de commande corrompu']);
            break;
        }
        
        // Restaurer la commande en session
        $_SESSION['current_order'] = $orderData;
        
        $logger->info("Commande rechargée: $reference");
        
        echo json_encode([
            'success' => true,
            'order' => $orderData
        ]);
        break;
            
        case 'list_recent_orders':
        
        $limit = intval($_POST['limit'] ?? 10);
        $limit = min($limit, 100); // Maximum 100 commandes
        
        $orderFiles = glob($ordersDir . '*.json');
        $orders = [];
        
        if ($orderFiles) {
            // Trier par date de modification (plus récent en premier)
            usort($orderFiles, function($a, $b) {
                return filemtime($b) - filemtime($a);
            });
            
            // Prendre les X plus récents
            foreach (array_slice($orderFiles, 0, $limit) as $file) {
                $orderContent = file_get_contents($file);
                if ($orderContent !== false) {
                    $orderData = json_decode($orderContent, true);
                    if ($orderData && isset($orderData['reference'])) {
                        $orders[] = [
                            'reference' => $orderData['reference'],
                            'customer_name' => ($orderData['customer']['firstname'] ?? '') . ' ' . ($orderData['customer']['lastname'] ?? ''),
                            'customer_email' => $orderData['customer']['email'] ?? '',
                            'created_at' => $orderData['created_at'] ?? 'Date inconnue',
                            'items_count' => count($orderData['items'] ?? []),
                            'items' => $orderData['items']
                        ];
                    }
                }
            }
        }
        
        echo json_encode(['success' => true, 'orders' => $orders]);
        break;
            
        case 'clear_current_order':
            unset($_SESSION['current_order']);
            echo json_encode(['success' => true, 'message' => 'Commande en cours supprimée']);
            break;

        case 'clear_cart':
            if (!isset($_SESSION['current_order'])) {
                echo json_encode(['success' => false, 'error' => 'Aucune commande active']);
                break;
            }
            
            // Vider tous les articles du panier
            $_SESSION['current_order']['items'] = [];
            
            // Mettre à jour la commande temporaire (vide)
            saveTempOrder($_SESSION['current_order'], $ordersDir);
            
            $logger->info("Panier vidé pour la commande: " . $_SESSION['current_order']['reference']);

            // Vider la session
            unset($_SESSION['current_order']);
            
            echo json_encode([
                'success' => true,
                'message' => 'Panier vidé avec succès',
                'cart_count' => 0
            ]);
            break;

    case 'debug_order':
        if (!$is_admin) {
            echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
            break;
        }
        
        $reference = $_POST['reference'] ?? '';
        if (empty($reference)) {
            echo json_encode(['success' => false, 'error' => 'Référence manquante']);
            break;
        }
        
        //$debug = debugOrderFiles($reference, $ordersDir);
        echo json_encode(['success' => true, 'debug' => $debug]);
        break;
    
    case 'test_email_config':
        
        $emailHandler = new EmailHandler();
        $result = $emailHandler->testEmailConfiguration();
        
        echo json_encode($result);
        break;

    case 'send_test_email':
    if (!$is_admin) {
        echo json_encode(['success' => false, 'error' => 'Accès non autorisé']);
        break;
    }
    
    $emailHandler = new EmailHandler();
    
    // Créer une commande de test
    $testOrder = [
        'reference' => 'TEST' . date('YmdHi'),
        'customer' => [
            'firstname' => 'Test',
            'lastname' => 'Utilisateur',
            'email' => defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'test@example.com',
            'phone' => '01 23 45 67 89'
        ],
        'items' => [
            'test/photo1.jpg' => [
                'activity_key' => 'activite-test',
                'photo_name' => 'photo-test-1.jpg',
                'quantity' => 1
            ],
            'test/photo2.jpg' => [
                'activity_key' => 'activite-test',
                'photo_name' => 'photo-test-2.jpg', 
                'quantity' => 2
            ]
        ],
        'created_at' => date('Y-m-d H:i:s'),
        'status' => 'test'
    ];
    
    $success = $emailHandler->sendOrderConfirmation($testOrder, false);
    
    if ($success) {
        echo json_encode([
            'success' => true, 
            'message' => 'Email de test envoyé avec succès',
            'reference' => $testOrder['reference']
        ]);
    } else {
        echo json_encode([
            'success' => false, 
            'error' => 'Échec de l\'envoi de l\'email de test'
        ]);
    }
    break;
            
        default:
            echo json_encode(['success' => false, 'error' => 'Action non reconnue: ' . $action]);
    }

    
    
} catch (Exception $e) {
    $logger->error('Erreur dans order_handler: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur serveur: ' . $e->getMessage()]);
} catch (Error $e) {
    $logger->error('Erreur PHP dans order_handler: ' . $e->getMessage());
    echo json_encode(['success' => false, 'error' => 'Erreur PHP: ' . $e->getMessage()]);
}

/**
 * Ajouter une commande au fichier CSV avec la nouvelle structure complète
 */
function addOrderToCSV($order, $ordersDir) {
    global $ORDER_STATUT;

    try {
        $excelFile = $ordersDir . 'commandes.csv';
        $isNewFile = !file_exists($excelFile);
        
        // En-tête CSV complet avec BOM UTF-8 et la nouvelle colonne "exported"
        if ($isNewFile) {
            $bom = "\xEF\xBB\xBF";
            $header = $bom . "REF;Nom;Prenom;Email;Telephone;Date commande;Dossier;N de la photo;Quantite;Montant Total;Mode de paiement;Date encaissement souhaitee;Date encaissement;Date depot;Date de recuperation;Statut commande;Exported\n";
            if (file_put_contents($excelFile, $header) === false) {
                error_log("Impossible de créer le fichier CSV: $excelFile");
                return false;
            }
        }
        
        // Calculer le montant total de la commande avec tarification différentielle
        $totalAmount = 0;
        foreach ($order['items'] as $item) {
            $itemPrice = getActivityPrice($item['activity_key']);
            $totalAmount += $item['quantity'] * $itemPrice;
        }
        
            // Préparer les lignes de données (nettoyage des caractères problématiques)
        $lines = [];
        foreach ($order['items'] as $item) {
            // Nettoyer toutes les valeurs
            $cleanData = [
                cleanCSVValue($order['reference']), //REF                                                   - 1
                cleanCSVValue($order['customer']['lastname']), //Nom                                        - 2
                cleanCSVValue($order['customer']['firstname']), //Prenom                                    - 3
                cleanCSVValue($order['customer']['email']), //Email                                         - 4
                cleanCSVValue($order['customer']['phone']), //Telephone                                     - 5
                cleanCSVValue($order['validated_at'] ?? ''), //Date commande                                - 6
                cleanCSVValue($item['activity_key']), //Dossier                                             - 7
                cleanCSVValue($item['photo_name']), //N de la photo                                         - 8
                cleanCSVValue((string)$item['quantity']), //Quantite                                        - 9 
                cleanCSVValue((string)$item['total_price']), //Montant Total                                - 10
                cleanCSVValue($order['payment_method'] ?? $ORDER_STATUT['PAYMENT_STATUS'][0]), //Mode de paiement - 11
                cleanCSVValue($order['desired_payment_date'] ?? ''), //Date encaissement souhaitee          - 12   
                cleanCSVValue($order['actual_payment_date'] ?? ''), //Date encaissement                     - 13
                cleanCSVValue($order['deposit_date'] ?? ''), //Date depot                                   - 14
                cleanCSVValue($order['retrieval_date'] ?? ''), //Date de recuperation                       - 15
                cleanCSVValue($order['status'] ?? $ORDER_STATUT['COMMAND_STATUS'][0]),  //Statut commande   - 16
                cleanCSVValue($order['exported'] ?? $ORDER_STATUT['EXPORT_STATUS'][0]), //Exported        - 17
                cleanCSVValue('')


                
            ];
            
            $lines[] = implode(';', $cleanData);
        }

        // Écrire avec verrou exclusif et encodage UTF-8
        $content = implode("\n", $lines) . "\n";
        
        $result = file_put_contents($excelFile, $content, FILE_APPEND | LOCK_EX);
        
        if ($result === false) {
            error_log("Impossible d'écrire dans le fichier CSV: $excelFile");
            return false;
        }
        
        error_log("Ajout réussi au CSV: " . count($order['items']) . " ligne(s) pour " . $order['reference'] . " - Montant: " . $totalAmount . "€");
        return true;
        
    } catch (Exception $e) {
        error_log('Erreur addOrderToCSV: ' . $e->getMessage());
        return false;
    }
}

/**
 * Nettoyer une valeur pour l'insertion CSV
 * @param string $value Valeur à nettoyer
 * @return string Valeur nettoyée
 * @version 1.0
 */
function cleanCSVValue($value) {
    if ($value === null || $value === false) {
        return '';
    }
    
    // Convertir en string et nettoyer
    $cleaned = (string)$value;
    
    // Supprimer les caractères de contrôle et non-printables
    $cleaned = preg_replace('/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/', '', $cleaned);
    
    // Remplacer les caractères problématiques pour CSV
    $cleaned = str_replace([';', "\n", "\r", "\t"], ['_', ' ', ' ', ' '], $cleaned);
    
    // Assurer l'encodage UTF-8
    if (!mb_check_encoding($cleaned, 'UTF-8')) {
        $cleaned = mb_convert_encoding($cleaned, 'UTF-8', 'auto');
    }
    
    return trim($cleaned);
}

/**
 * Mettre à jour le fichier Excel avec gestion des doublons
 */
function updateExcelFile($order, $ordersDir) {
    
    return addOrderToCSV($order, $ordersDir);

}

/**
 * Vérifier si une commande existe déjà dans le CSV
 */
function checkOrderExistsInCSV($reference, $csvFile) {
    if (!file_exists($csvFile)) {
        return false;
    }
    
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }
    
    foreach ($lines as $line) {
        if (strpos($line, $reference . ';') === 0) {
            return true;
        }
    }
    
    return false;
}

/**
 * Supprimer les lignes d'une référence du fichier CSV
 */
function removeOrderFromCSV($reference, $csvFile) {
    if (!file_exists($csvFile)) {
        return true;
    }
    
    $lines = file($csvFile, FILE_IGNORE_NEW_LINES);
    if ($lines === false) {
        return false;
    }
    
    $filteredLines = [];
    $removedCount = 0;
    
    foreach ($lines as $line) {
        // Garder l'en-tête et les lignes qui ne correspondent pas à cette référence
        if (empty($line) || strpos($line, $reference . ';') !== 0) {
            $filteredLines[] = $line;
        } else {
            $removedCount++;
        }
    }
    
    // Log du nombre de lignes supprimées
    error_log("Suppression de $removedCount ligne(s) pour la référence $reference");
    
    return file_put_contents($csvFile, implode("\n", $filteredLines) . "\n") !== false;
}

/**
 * Obtenir des informations sur les fichiers de commandes
 */
function getOrderFilesInfo($ordersDir) {
    $allFiles = glob($ordersDir . '*.json');
    $tempFiles = glob($ordersDir . 'temp_*.json');
    $validatedFiles = array_diff($allFiles, $tempFiles);
    
    return [
        'total_files' => count($allFiles),
        'temp_files' => count($tempFiles),
        'validated_files' => count($validatedFiles),
        'temp_list' => array_map('basename', $tempFiles),
        'validated_list' => array_map('basename', $validatedFiles)
    ];
}

/**
 * Sauvegarder la commande temporaire
 */
function saveTempOrder($order, $ordersDir) {
    $tempDir = ensureTempDirExists($ordersDir);
    $tempFile = $tempDir . $order['reference'] . '.json';
    $orderJson = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($tempFile, $orderJson) !== false;
}

/**
 * Valider les données de commande
 */
function validateOrderData($data) {
    $errors = [];
    
    if (empty($data['lastname'])) {
        $errors[] = 'Le nom est requis';
    }
    
    if (empty($data['firstname'])) {
        $errors[] = 'Le prénom est requis';
    }
    
    if (empty($data['email']) || !filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
        $errors[] = 'Une adresse email valide est requise';
    }
    
    if (empty($data['phone'])) {
        $errors[] = 'Le numéro de téléphone est requis';
    }
    
    return $errors;
}

/**
 * Nettoyer les données d'entrée
 */
function sanitizeInput($data) {
    if (is_array($data)) {
        return array_map('sanitizeInput', $data);
    }
    
    return htmlspecialchars(trim($data), ENT_QUOTES, 'UTF-8');
}

/**
 * Créer le dossier temporaire s'il n'existe pas
 */
function ensureTempDirExists($ordersDir) {
    $tempDir = $ordersDir . 'temp/';
    if (!is_dir($tempDir)) {
        mkdir($tempDir, 0755, true);
    }
    return $tempDir;
}

/**
 * Charger la commande temporaire
 */
function loadTempOrder($reference, $ordersDir) {
    $tempDir = $ordersDir . 'temp/';
    $tempFile = $tempDir . $reference . '.json';
    
    if (!file_exists($tempFile)) {
        return null;
    }
    
    $content = file_get_contents($tempFile);
    if ($content === false) {
        return null;
    }
    
    return json_decode($content, true);
}

/**
 * Supprimer la commande temporaire
 */
function removeTempOrder($reference, $ordersDir) {
    $tempDir = $ordersDir . 'temp/';
    $tempFile = $tempDir . $reference . '.json';
    if (file_exists($tempFile)) {
        return unlink($tempFile);
    }
    return true;
}

/**
 * Supprimer l'ancien fichier JSON d'une commande (seulement les fichiers validés)
 */
function removeOldOrderFile($reference, $ordersDir) {
    // Chercher dans le dossier principal (pas dans temp/)
    $files = glob($ordersDir . $reference . '_*.json');
    $removedFiles = [];
    
    foreach ($files as $file) {
        $basename = basename($file);
        if (unlink($file)) {
            $removedFiles[] = $basename;
        }
    }
    
    // Log des fichiers supprimés
    if (!empty($removedFiles)) {
        error_log("Fichiers JSON supprimés pour $reference: " . implode(', ', $removedFiles));
    }
    
    return true;
}

?>