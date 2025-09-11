<?php
/**
 * Handler pour la gestion des commandes réglées
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}

require_once 'config.php';

try {
    require_once 'functions.php';
    require_once 'email_handler.php';
    require_once 'classes/autoload.php'; // Pour les classes Order, OrdersList, etc.

} catch (Exception $e) {
    // Nettoyer la sortie et retourner une erreur JSON
    ob_clean();
    header('Content-Type: application/json');
    echo json_encode(['success' => false, 'error' => 'Erreur de configuration: ' . $e->getMessage()]);
    exit;
}

// Initialiser le logger si non défini
if (!isset($logger)) {
    // Exemple d'initialisation simple, à adapter selon votre application
    class SimpleLogger {
        public function adminAction($action, $data = []) {
            // Vous pouvez écrire dans un fichier ou simplement ignorer pour éviter l'erreur
            // file_put_contents('admin_actions.log', date('Y-m-d H:i:s') . " $action: " . json_encode($data) . "\n", FILE_APPEND);
        }
    }
    $logger = new SimpleLogger();
}

// Traitement des actions AJAX
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action'])) {
    switch ($_POST['action']) {
        case 'mark_as_retrieved':
            if (!isset($_POST['reference'])) {
                echo json_encode(['success' => false, 'message' => 'Référence manquante']);
                exit;
            }
            
            try {
                $order = new Order($_POST['reference']);
                $result = $order->updateRetrievalStatus('retrieved', date('Y-m-d H:i:s'));
                
                // Log de l'action pour traçabilité
                $logger->adminAction('mark_as_retrieved', [
                    'reference' => $_POST['reference'],
                    'success' => $result['success'] ?? false
                ]);
                
                echo json_encode($result);
            } catch (Exception $e) {
                error_log('Erreur mark_as_retrieved: ' . $e->getMessage());
                echo json_encode(['success' => false, 'message' => 'Erreur technique']);
            }
            exit;
            
        case 'resend_confirmation':
            if (!isset($_POST['reference'])) {
                echo json_encode(['success' => false, 'error' => 'Référence manquante']);
                exit;
            }
            
            try {
                $order = new Order($_POST['reference']);
                $loaded = $order->load();
                
                if (!$loaded) {
                    echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                    exit;
                }
                
                $orderData = $order->getData();
                
                // Utiliser l'email handler existant pour renvoyer la confirmation
                $emailHandler = new EmailHandler();
                $emailSent = $emailHandler->sendOrderConfirmation($orderData, false);
                
                $emailResult = [
                    'success' => $emailSent,
                    'error' => $emailSent ? null : 'Erreur lors de l\'envoi'
                ];
                
                // Log de l'action
                $logger->adminAction('resend_confirmation', [
                    'reference' => $_POST['reference'],
                    'email' => $orderData['email'] ?? 'unknown',
                    'success' => $emailResult['success'] ?? false
                ]);
                
                if ($emailResult['success'] ?? false) {
                    echo json_encode(['success' => true, 'message' => 'Email envoyé avec succès']);
                } else {
                    echo json_encode(['success' => false, 'error' => $emailResult['error'] ?? 'Erreur d\'envoi']);
                }
                
            } catch (Exception $e) {
                error_log('Erreur resend_confirmation: ' . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Erreur technique: ' . $e->getMessage()]);
            }
            exit;
            
        case 'get_contact':
            if (!isset($_POST['reference'])) {
                echo json_encode(['success' => false, 'error' => 'Référence manquante']);
                exit;
            }
            
            try {
                $order = new Order($_POST['reference']);
                $loaded = $order->load();
                
                if (!$loaded) {
                    echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                    exit;
                }
                
                $orderData = $order->getData();
                
                // Retourner les informations de contact (pas de manipulation CSV ici)
                echo json_encode([
                    'success' => true,
                    'contact' => [
                        'reference' => $orderData['reference'],
                        'firstname' => $orderData['firstname'],
                        'lastname' => $orderData['lastname'],
                        'email' => $orderData['email'],
                        'phone' => $orderData['phone'],
                        'amount' => $orderData['total_price'],
                        'total_photos' => $orderData['quantity'],
                        'order_date' => $orderData['order_date'],
                        'command_status' => $orderData['command_status'],
                        'payment_mode' => $orderData['payment_mode'] ?? 'unknown',
                        'photos' => $orderData['photos'] ?? []
                    ]
                ]);
                
            } catch (Exception $e) {
                error_log('Erreur get_contact: ' . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Erreur technique']);
            }
            exit;
            
        case 'update_expected_retrieval_date':
            if (!isset($_POST['reference']) || !isset($_POST['expected_date'])) {
                echo json_encode(['success' => false, 'error' => 'Paramètres manquants']);
                exit;
            }
            
            try {
                $order = new Order($_POST['reference']);
                $loaded = $order->load();
                
                if (!$loaded) {
                    echo json_encode(['success' => false, 'error' => 'Commande introuvable']);
                    exit;
                }
                
                $expectedDate = $_POST['expected_date'];
                
                // Valider le format de date
                if (!empty($expectedDate) && !strtotime($expectedDate)) {
                    echo json_encode(['success' => false, 'error' => 'Format de date invalide']);
                    exit;
                }
                
                // Convertir au format Y-m-d H:i:s si nécessaire
                if (!empty($expectedDate)) {
                    $expectedDate = date('Y-m-d H:i:s', strtotime($expectedDate));
                }
                
                $result = $order->updateExpectedRetrievalDate($expectedDate);
                
                // Log de l'action
                $logger->adminAction('update_expected_retrieval_date', [
                    'reference' => $_POST['reference'],
                    'expected_date' => $expectedDate,
                    'success' => $result['success'] ?? false
                ]);
                
                echo json_encode($result);
                
            } catch (Exception $e) {
                error_log('Erreur update_expected_retrieval_date: ' . $e->getMessage());
                echo json_encode(['success' => false, 'error' => 'Erreur technique']);
            }
            exit;
    }
}

// FONCTION SUPPRIMÉE - Utiliser OrdersList::loadOrdersData('paid') à la place

// FONCTION SUPPRIMÉE - Utiliser OrdersList::calculateStats() à la place

// FONCTION SUPPRIMÉE - Utiliser Order::updateRetrievalStatus() à la place
?>