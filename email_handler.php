<?php
/**
 * Gestionnaire d'emails pour les commandes - Version avec templates
 */

// Sécurité
if (!defined('GALLERY_ACCESS')) {
    die('Accès direct non autorisé');
}

class EmailHandler {
    
    private $logger;
    
    public function __construct() {
        $this->logger = Logger::getInstance();
    }
    
    /**
     * Charger un template email et remplacer les variables
     */
    private function loadTemplate($templateName, $order, $isUpdate = false) {
        $templatePath = 'templates/emails/' . $templateName;
        
        if (!file_exists($templatePath)) {
            $this->logger->error('Template email non trouvé: ' . $templatePath);
            throw new Exception('Template email non trouvé: ' . $templateName);
        }
        
        $template = file_get_contents($templatePath);
        
        // Préparer les variables pour le template
        $customerName = $order['customer']['firstname'] . ' ' . $order['customer']['lastname'];
        $totalPhotos = array_sum(array_column($order['items'], 'quantity'));
        $totalPrice = calculateOrderTotal($order);
        
        $variables = [
            'ORDER_REFERENCE' => htmlspecialchars($order['reference']),
            'CUSTOMER_NAME' => htmlspecialchars($customerName),
            'CUSTOMER_EMAIL' => htmlspecialchars($order['customer']['email']),
            'CUSTOMER_PHONE' => htmlspecialchars($order['customer']['phone']),
            'ORDER_DATE' => date('d/m/Y à H:i', strtotime($order['created_at'])),
            'TOTAL_PHOTOS' => $totalPhotos,
            'TOTAL_PRICE' => number_format($totalPrice, 2, ',', ' '),
            'GENERATION_DATE' => date('d/m/Y à H:i'),
        ];
        
        // Variables spécifiques au format
        if (strpos($templateName, '.html') !== false) {
            // Template HTML
            $variables['UPDATE_WARNING'] = $isUpdate ? '<div class="warning">⚠️ Cette commande annule et remplace une commande précédente.</div>' : '';
            $variables['ORDER_ITEMS'] = $this->buildHtmlOrderItems($order);
        } else {
            // Template texte
            $variables['UPDATE_WARNING_TEXT'] = $isUpdate ? "⚠️ ATTENTION: Cette commande annule et remplace une commande précédente.\n" : '';
            $variables['ORDER_ITEMS_TEXT'] = $this->buildTextOrderItems($order);
        }
        
        // Remplacer les variables dans le template
        foreach ($variables as $key => $value) {
            $template = str_replace('{{' . $key . '}}', $value, $template);
        }
        
        return $template;
    }
    
    /**
     * Construire les lignes d'items HTML
     */
    private function buildHtmlOrderItems($order) {
        $html = '';
        foreach ($order['items'] as $item) {
            $unitPrice = number_format($item['unit_price'], 2, ',', ' ');
            $totalPrice = number_format($item['total_price'], 2, ',', ' ');
            
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['activity_key']) . '</td>
                        <td>' . htmlspecialchars($item['photo_name']) . '</td>
                        <td>' . $item['quantity'] . '</td>
                        <td>' . $unitPrice . '€</td>
                        <td class="price-column">' . $totalPrice . '€</td>
                    </tr>';
        }
        return $html;
    }
    
    /**
     * Construire les lignes d'items en texte
     */
    private function buildTextOrderItems($order) {
        $text = '';
        foreach ($order['items'] as $item) {
            $unitPrice = number_format($item['unit_price'], 2, ',', ' ');
            $totalPrice = number_format($item['total_price'], 2, ',', ' ');
            
            $text .= "- " . $item['activity_key'] . " / " . $item['photo_name'] . " (x" . $item['quantity'] . ") - " . $unitPrice . "€ = " . $totalPrice . "€\n";
        }
        return $text;
    }
    
    /**
     * Envoyer un email de confirmation de commande
     */
    public function sendOrderConfirmation($order, $isUpdate = false) {
        if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
            $this->logger->info('Envoi d\'email désactivé pour la commande: ' . $order['reference']);
            return true;
        }
        
        try {
            // Préparer les destinataires
            $recipients = $this->getRecipients($order);
            
            // Préparer le contenu de l'email
            $subject = $this->buildSubject($order, $isUpdate);
            $htmlBody = $this->buildHtmlBody($order, $isUpdate);
            $textBody = $this->buildTextBody($order, $isUpdate);
            
            // Envoyer à tous les destinataires
            $success = true;
            foreach ($recipients as $recipient) {
                if (!$this->sendEmail($recipient, $subject, $htmlBody, $textBody)) {
                    $success = false;
                }
            }
            
            if ($success) {
                $this->logger->info('Email de commande envoyé avec succès', [
                    'reference' => $order['reference'],
                    'recipients' => count($recipients),
                    'is_update' => $isUpdate
                ]);
            } else {
                $this->logger->error('Erreur lors de l\'envoi de l\'email de commande', [
                    'reference' => $order['reference']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi de l\'email: ' . $e->getMessage(), [
                'reference' => $order['reference'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Obtenir les destinataires de l'email
     */
    private function getRecipients($order) {
        $recipients = [];
        
        // Client principal
        if (!empty($order['customer']['email'])) {
            $recipients[] = [
                'email' => $order['customer']['email'],
                'name' => $order['customer']['firstname'] . ' ' . $order['customer']['lastname']
            ];
        }
        
        // Admin (si configuré)
        if (defined('ADMIN_EMAIL') && !empty(ADMIN_EMAIL)) {
            $recipients[] = [
                'email' => ADMIN_EMAIL,
                'name' => 'Administration'
            ];
        }
        
        return $recipients;
    }
    
    /**
     * Construire le sujet de l'email
     */
    private function buildSubject($order, $isUpdate) {
        $prefix = $isUpdate ? '[MISE À JOUR] ' : '';
        return $prefix . 'Confirmation commande ' . $order['reference'] . ' - Photos Gala';
    }
    
    /**
     * Construire le corps HTML de l'email à partir du template
     */
    private function buildHtmlBody($order, $isUpdate) {
        return $this->loadTemplate('order-confirmation.html', $order, $isUpdate);
    }
    
    /**
     * Construire le corps texte de l'email à partir du template
     */
    private function buildTextBody($order, $isUpdate) {
        return $this->loadTemplate('order-confirmation.txt', $order, $isUpdate);
    }
    
    /**
     * Envoyer l'email effectivement
     */
    private function sendEmail($recipient, $subject, $htmlBody, $textBody) {
        try {
            // En mode test/développement, juste logger
            if (defined('MAIL_DEBUG') && MAIL_DEBUG) {
                $this->logger->info('Email simulé envoyé', [
                    'to' => $recipient['email'],
                    'subject' => $subject
                ]);
                return true;
            }
            
            // Configuration des headers
            $headers = [
                'MIME-Version' => '1.0',
                'Content-Type' => 'multipart/alternative; boundary="boundary-' . md5(uniqid()) . '"',
                'From' => defined('MAIL_FROM') ? MAIL_FROM : 'noreply@example.com',
                'Reply-To' => defined('MAIL_REPLY_TO') ? MAIL_REPLY_TO : 'noreply@example.com',
                'X-Mailer' => 'PHP/' . phpversion()
            ];
            
            $boundary = 'boundary-' . md5(uniqid());
            
            // Corps multipart
            $body = "--{$boundary}\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\n";
            $body .= "Content-Transfer-Encoding: 8bit\n\n";
            $body .= $textBody . "\n\n";
            
            $body .= "--{$boundary}\n";
            $body .= "Content-Type: text/html; charset=UTF-8\n";
            $body .= "Content-Transfer-Encoding: 8bit\n\n";
            $body .= $htmlBody . "\n\n";
            
            $body .= "--{$boundary}--";
            
            // Convertir headers en string
            $headerString = '';
            foreach ($headers as $key => $value) {
                if ($key !== 'Content-Type') { // Content-Type sera dans le body
                    $headerString .= "{$key}: {$value}\r\n";
                }
            }
            $headerString .= "Content-Type: multipart/alternative; boundary=\"{$boundary}\"\r\n";
            
            // Envoyer l'email
            return mail($recipient['email'], $subject, $body, $headerString);
            
        } catch (Exception $e) {
            $this->logger->error('Erreur envoi email: ' . $e->getMessage());
            return false;
        }
    }
}
?>