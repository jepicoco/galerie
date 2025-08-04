<?php
/**
 * Gestionnaire d'emails pour les commandes
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
                $this->logger->warning('Erreur partielle lors de l\'envoi d\'emails', [
                    'reference' => $order['reference']
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi d\'email', [
                'reference' => $order['reference'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Obtenir la liste des destinataires
     */
    private function getRecipients($order) {
        $recipients = [];
        
        // Email du client
        if (!empty($order['customer']['email'])) {
            $recipients[] = [
                'email' => $order['customer']['email'],
                'name' => $order['customer']['firstname'] . ' ' . $order['customer']['lastname'],
                'type' => 'customer'
            ];
        }
        
        // Emails des administrateurs
        if (defined('MAIL_ADMIN_RECIPIENTS') && !empty(MAIL_ADMIN_RECIPIENTS)) {
            $adminEmails = explode(',', MAIL_ADMIN_RECIPIENTS);
            foreach ($adminEmails as $adminEmail) {
                $adminEmail = trim($adminEmail);
                if (!empty($adminEmail) && filter_var($adminEmail, FILTER_VALIDATE_EMAIL)) {
                    $recipients[] = [
                        'email' => $adminEmail,
                        'name' => 'Administration',
                        'type' => 'admin'
                    ];
                }
            }
        }
        
        return $recipients;
    }
    
    /**
     * Construire le sujet de l'email
     */
    private function buildSubject($order, $isUpdate) {
        $prefix = defined('MAIL_SUBJECT_PREFIX') ? MAIL_SUBJECT_PREFIX : 'Commande Photos';
        $subject = $prefix . ' - ' . $order['reference'];
        
        if ($isUpdate) {
            $subject = '[Annule et remplace] ' . $subject;
        }
        
        return $subject;
    }
    
    /**
     * Construire le corps HTML de l'email
     */
    private function buildHtmlBody($order, $isUpdate) {
        $customerName = $order['customer']['firstname'] . ' ' . $order['customer']['lastname'];
        $totalsByType = $this->calculateTotalsByActivityType($order['items']);
        $totalPhotos = array_sum(array_column($totalsByType, 'quantity'));
        $updateText = $isUpdate ? '<p style="color: #d30420; font-weight: bold;">⚠️ Cette commande annule et remplace une commande précédente.</p>' : '';
        
        $html = '
<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Confirmation de commande</title>
    <style>
        body { font-family: Arial, sans-serif; line-height: 1.6; color: #333; margin: 0; padding: 20px; background-color: #f4f4f4; }
        .container { max-width: 600px; margin: 0 auto; background: white; border-radius: 10px; overflow: hidden; box-shadow: 0 0 20px rgba(0,0,0,0.1); }
        .header { background: linear-gradient(135deg, #0BABDF, #0A76B7); color: white; padding: 30px; text-align: center; }
        .content { padding: 30px; }
        .order-info { background: #f8f9fa; padding: 20px; border-radius: 8px; margin: 20px 0; }
        .customer-info { background: #e3f2fd; padding: 15px; border-radius: 8px; margin: 15px 0; }
        .items-table { width: 100%; border-collapse: collapse; margin: 20px 0; }
        .items-table th, .items-table td { padding: 12px; text-align: left; border-bottom: 1px solid #ddd; }
        .items-table th { background: #0BABDF; color: white; }
        .items-table tr:nth-child(even) { background: #f9f9f9; }
        .total-row { background: #e8f5e8 !important; font-weight: bold; }
        .footer { background: #f8f9fa; padding: 20px; text-align: center; font-size: 0.9em; color: #666; }
        .button { display: inline-block; background: #0BABDF; color: white; padding: 12px 25px; text-decoration: none; border-radius: 5px; margin: 10px 0; }
        @media (max-width: 600px) {
            .container { margin: 10px; }
            .header, .content { padding: 20px; }
            .items-table { font-size: 0.9em; }
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📸 Confirmation de Commande</h1>
            <h2>' . $order['reference'] . '</h2>
        </div>
        
        <div class="content">
            ' . $updateText . '
            
            <p>Bonjour <strong>' . htmlspecialchars($customerName) . '</strong>,</p>
            
            <p>Nous confirmons la réception de votre commande de photos du gala de danse.</p>
            
            <div class="order-info">
                <h3>📋 Informations de la commande</h3>
                <p><strong>Référence :</strong> ' . $order['reference'] . '</p>
                <p><strong>Date :</strong> ' . date('d/m/Y à H:i', strtotime($order['created_at'])) . '</p>
                <p><strong>Total :</strong> ' . $this->formatTotalsByType($totalsByType) . '</p>
            </div>
            
            <div class="customer-info">
                <h3>👤 Vos informations</h3>
                <p><strong>Nom :</strong> ' . htmlspecialchars($customerName) . '</p>
                <p><strong>Email :</strong> ' . htmlspecialchars($order['customer']['email']) . '</p>
                <p><strong>Téléphone :</strong> ' . htmlspecialchars($order['customer']['phone']) . '</p>
            </div>
            
            <h3>📷 Détail des photos commandées</h3>
            <table class="items-table">
                <thead>
                    <tr>
                        <th>Activité</th>
                        <th>Photo</th>
                        <th>Quantité</th>
                    </tr>
                </thead>
                <tbody>';
        
        foreach ($order['items'] as $item) {
            $html .= '
                    <tr>
                        <td>' . htmlspecialchars($item['activity_key']) . '</td>
                        <td>' . htmlspecialchars($item['photo_name']) . '</td>
                        <td>' . $item['quantity'] . '</td>
                    </tr>';
        }
        
        $html .= '
                    <tr class="total-row">
                        <td colspan="2"><strong>TOTAL</strong></td>
                        <td><strong>' . $this->formatTotalsByType($totalsByType) . '</strong></td>
                    </tr>
                </tbody>
            </table>
            
            <div style="background: #fff3cd; padding: 15px; border-radius: 8px; margin: 20px 0;">
                <h4>📝 Prochaines étapes :</h4>
                <ol>
                    <li>Nous préparons votre commande</li>
                    <li>Vous recevrez un email de confirmation quand elle sera prête</li>
                    <li>Récupération selon les modalités convenues</li>
                </ol>
            </div>
        </div>
        
        <div class="footer">
            <p>Cet email a été générée le ' . date('d/m/Y à H:i') . '</p>
            <p>Pour toute question, conservez précieusement cette référence : <strong>' . $order['reference'] . '</strong></p>
        </div>
    </div>
</body>
</html>';
        
        return $html;
    }
    
    /**
     * Construire le corps texte de l'email
     */
    private function buildTextBody($order, $isUpdate) {
        $customerName = $order['customer']['firstname'] . ' ' . $order['customer']['lastname'];
        $totalsByType = $this->calculateTotalsByActivityType($order['items']);
        $totalPhotos = array_sum(array_column($totalsByType, 'quantity'));
        $updateText = $isUpdate ? "\n⚠️ ATTENTION: Cette commande annule et remplace une commande précédente.\n" : '';
        
        $text = "CONFIRMATION DE COMMANDE - " . $order['reference'] . "\n";
        $text .= "=" . str_repeat("=", strlen($order['reference']) + 25) . "\n\n";
        $text .= $updateText;
        $text .= "Bonjour " . $customerName . ",\n\n";
        $text .= "Nous confirmons la réception de votre commande de photos du gala de danse.\n\n";
        
        $text .= "INFORMATIONS DE LA COMMANDE\n";
        $text .= "----------------------------\n";
        $text .= "Référence : " . $order['reference'] . "\n";
        $text .= "Date : " . date('d/m/Y à H:i', strtotime($order['created_at'])) . "\n";
        $text .= "Total : " . $this->formatTotalsByType($totalsByType, false) . "\n\n";
        
        $text .= "VOS INFORMATIONS\n";
        $text .= "----------------\n";
        $text .= "Nom : " . $customerName . "\n";
        $text .= "Email : " . $order['customer']['email'] . "\n";
        $text .= "Téléphone : " . $order['customer']['phone'] . "\n\n";
        
        $text .= "DÉTAIL DES PHOTOS COMMANDÉES\n";
        $text .= "----------------------------\n";
        foreach ($order['items'] as $item) {
            $text .= "- " . $item['activity_key'] . " / " . $item['photo_name'] . " (x" . $item['quantity'] . ")\n";
        }
        $text .= "\n" . $this->formatTotalsByType($totalsByType, false) . "\n\n";
        
        $text .= "PROCHAINES ÉTAPES\n";
        $text .= "-----------------\n";
        $text .= "1. Nous préparons votre commande\n";
        $text .= "2. Vous recevrez un email de confirmation quand elle sera prête\n";
        $text .= "3. Récupération selon les modalités convenues\n\n";
        
        $text .= "Pour toute question, conservez précieusement cette référence : " . $order['reference'] . "\n\n";
        $text .= "Email généré automatiquement le " . date('d/m/Y à H:i');
        
        return $text;
    }
    
    /**
     * Envoyer un email
     */
    private function sendEmail($recipient, $subject, $htmlBody, $textBody) {
        // Choisir la méthode d'envoi
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            return $this->sendEmailViaSMTP($recipient, $subject, $htmlBody, $textBody);
        } else {
            return $this->sendEmailViaMail($recipient, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Envoyer un email via PHPMailer SMTP
     */
    private function sendEmailViaSMTP($recipient, $subject, $htmlBody, $textBody) {
        try {
            // Vérifier la disponibilité de PHPMailer
            if (!$this->isPHPMailerAvailable()) {
                $this->logger->warning('PHPMailer non disponible, fallback vers mail()');
                return $this->sendEmailViaMail($recipient, $subject, $htmlBody, $textBody);
            }
            
            // Charger PHPMailer
            $this->loadPHPMailer();
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host       = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
            $mail->SMTPAuth   = true;
            $mail->Username   = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $mail->Password   = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            
            // Correction pour Gmail : utiliser TLS avec le port 587
            $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
            $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
            
            // Forcer TLS pour Gmail avec port 587
            if (defined('SMTP_HOST') && SMTP_HOST === 'smtp.gmail.com' && $smtpPort == 587) {
                $smtpSecure = 'tls';
            }
            
            $mail->SMTPSecure = $smtpSecure === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port       = $smtpPort;
            $mail->CharSet    = 'UTF-8';
            
            // Configuration debug en mode développement
            if (defined('DEBUG_MODE') && DEBUG_MODE) {
                $mail->SMTPDebug = 2;
                $mail->Debugoutput = function($str, $level) {
                    $this->logger->debug("SMTP Debug: $str");
                };
            }
            
            // Timeout
            $mail->Timeout = 30;
            
            // Expéditeur
            $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Galerie Photos';
            $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'noreply@localhost';
            $mail->setFrom($fromEmail, $fromName);
            
            // Reply-To
            if (defined('MAIL_REPLY_TO') && !empty(MAIL_REPLY_TO)) {
                $mail->addReplyTo(MAIL_REPLY_TO);
            }
            
            // Destinataire
            $mail->addAddress($recipient['email'], $recipient['name']);
            
            // Contenu
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $htmlBody;
            $mail->AltBody = $textBody;
            
            // Envoi
            $success = $mail->send();
            
            if ($success) {
                $this->logger->info('Email envoyé via SMTP', [
                    'to' => $recipient['email'],
                    'type' => $recipient['type'],
                    'subject' => $subject,
                    'method' => 'SMTP',
                    'host' => defined('SMTP_HOST') ? SMTP_HOST : 'localhost'
                ]);
            }
            
            return $success;
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            $this->logger->error('Erreur PHPMailer SMTP', [
                'to' => $recipient['email'],
                'error' => $e->getMessage(),
                'errorInfo' => method_exists($e, 'errorMessage') ? $e->errorMessage() : 'N/A'
            ]);
            
            // Fallback vers mail() en cas d'erreur SMTP
            $this->logger->warning('Fallback vers mail() après échec SMTP');
            return $this->sendEmailViaMail($recipient, $subject, $htmlBody, $textBody);
            
        } catch (Exception $e) {
            $this->logger->error('Erreur générale lors de l\'envoi SMTP', [
                'to' => $recipient['email'],
                'error' => $e->getMessage()
            ]);
            
            // Fallback vers mail()
            return $this->sendEmailViaMail($recipient, $subject, $htmlBody, $textBody);
        }
    }
    
    /**
     * Envoyer un email via la fonction mail() native
     */
    private function sendEmailViaMail($recipient, $subject, $htmlBody, $textBody) {
        try {
            // Configuration SMTP pour mail() si définie
            if (defined('SMTP_HOST') && !empty(SMTP_HOST)) {
                ini_set('SMTP', SMTP_HOST);
            }
            if (defined('SMTP_PORT') && !empty(SMTP_PORT)) {
                ini_set('smtp_port', SMTP_PORT);
            }
            
            // Headers de base
            $headers = [];
            $headers[] = 'MIME-Version: 1.0';
            $headers[] = 'Content-Type: multipart/alternative; boundary="' . $boundary = md5(time()) . '"';
            
            // From
            $fromName = defined('MAIL_FROM_NAME') ? MAIL_FROM_NAME : 'Galerie Photos';
            $fromEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'noreply@localhost';
            $headers[] = 'From: ' . $fromName . ' <' . $fromEmail . '>';
            
            // Reply-To
            if (defined('MAIL_REPLY_TO') && !empty(MAIL_REPLY_TO)) {
                $headers[] = 'Reply-To: ' . MAIL_REPLY_TO;
            }
            
            // Headers additionnels
            $headers[] = 'X-Mailer: PHP/' . phpversion();
            $headers[] = 'X-Priority: 3';
            $headers[] = 'Content-Transfer-Encoding: 8bit';
            
            // Configuration sendmail_from pour Windows
            if (defined('MAIL_FROM_EMAIL') && !empty(MAIL_FROM_EMAIL)) {
                ini_set('sendmail_from', MAIL_FROM_EMAIL);
            }
            
            // Corps multipart
            $body = "--$boundary\n";
            $body .= "Content-Type: text/plain; charset=UTF-8\n";
            $body .= "Content-Transfer-Encoding: 8bit\n\n";
            $body .= $textBody . "\n\n";
            
            $body .= "--$boundary\n";
            $body .= "Content-Type: text/html; charset=UTF-8\n";
            $body .= "Content-Transfer-Encoding: 8bit\n\n";
            $body .= $htmlBody . "\n\n";
            
            $body .= "--$boundary--";
            
            // Envoi
            $success = mail(
                $recipient['email'],
                $subject,
                $body,
                implode("\r\n", $headers)
            );
            
            if ($success) {
                $this->logger->info('Email envoyé via mail()', [
                    'to' => $recipient['email'],
                    'type' => $recipient['type'],
                    'subject' => $subject,
                    'method' => 'mail()'
                ]);
            } else {
                $this->logger->error('Échec envoi email via mail()', [
                    'to' => $recipient['email'],
                    'type' => $recipient['type'],
                    'subject' => $subject,
                    'smtp_host' => ini_get('SMTP'),
                    'smtp_port' => ini_get('smtp_port')
                ]);
            }
            
            return $success;
            
        } catch (Exception $e) {
            $this->logger->error('Erreur lors de l\'envoi via mail()', [
                'to' => $recipient['email'],
                'error' => $e->getMessage()
            ]);
            return false;
        }
    }
    
    /**
     * Vérifier si PHPMailer est disponible
     */
    private function isPHPMailerAvailable() {
        // Vérifier les différents emplacements possibles
        $possiblePaths = [
            __DIR__ . '/phpmailer/src/PHPMailer.php',
            __DIR__ . '/../vendor/phpmailer/phpmailer/src/PHPMailer.php',
            __DIR__ . '/../../vendor/phpmailer/phpmailer/src/PHPMailer.php'
        ];
        
        foreach ($possiblePaths as $path) {
            if (file_exists($path)) {
                return true;
            }
        }
        
        // Vérifier si la classe est déjà chargée
        return class_exists('\PHPMailer\PHPMailer\PHPMailer');
    }
    
    /**
     * Charger PHPMailer
     */
    private function loadPHPMailer() {
        if (class_exists('\PHPMailer\PHPMailer\PHPMailer')) {
            return; // Déjà chargé
        }
        
        $possiblePaths = [
            __DIR__ . '/phpmailer/src/',
            __DIR__ . '/../vendor/phpmailer/phpmailer/src/',
            __DIR__ . '/../../vendor/phpmailer/phpmailer/src/'
        ];
        
        foreach ($possiblePaths as $basePath) {
            if (file_exists($basePath . 'PHPMailer.php')) {
                require_once $basePath . 'PHPMailer.php';
                require_once $basePath . 'SMTP.php';
                require_once $basePath . 'Exception.php';
                return;
            }
        }
        
        throw new Exception('PHPMailer non trouvé dans les emplacements attendus');
    }

    /**
     * Tester la configuration email
     */
    public function testEmailConfiguration() {
        if (!defined('MAIL_ENABLED') || !MAIL_ENABLED) {
            return ['success' => false, 'error' => 'Envoi d\'email désactivé'];
        }
        
        // Vérifier la configuration de base
        $issues = [];
        
        if (!defined('MAIL_FROM_EMAIL') || empty(MAIL_FROM_EMAIL)) {
            $issues[] = 'MAIL_FROM_EMAIL non configuré';
        } elseif (!filter_var(MAIL_FROM_EMAIL, FILTER_VALIDATE_EMAIL)) {
            $issues[] = 'MAIL_FROM_EMAIL invalide';
        }
        
        if (!defined('MAIL_ADMIN_RECIPIENTS') || empty(MAIL_ADMIN_RECIPIENTS)) {
            $issues[] = 'MAIL_ADMIN_RECIPIENTS non configuré';
        }
        
        // Vérifier la configuration SMTP si activée
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            if (!defined('SMTP_HOST') || empty(SMTP_HOST)) {
                $issues[] = 'SMTP_HOST non configuré';
            }
            
            if (!defined('SMTP_USERNAME') || empty(SMTP_USERNAME)) {
                $issues[] = 'SMTP_USERNAME non configuré';
            }
            
            if (!defined('SMTP_PASSWORD') || empty(SMTP_PASSWORD)) {
                $issues[] = 'SMTP_PASSWORD non configuré';
            }
            
            // Vérifier la cohérence port/sécurité pour Gmail
            if (defined('SMTP_HOST') && SMTP_HOST === 'smtp.gmail.com') {
                if (defined('SMTP_PORT') && SMTP_PORT == 587 && defined('SMTP_SECURE') && SMTP_SECURE === 'ssl') {
                    $issues[] = 'Configuration Gmail incorrecte: utiliser TLS avec le port 587';
                }
            }
            
            // Vérifier que PHPMailer est disponible
            if (!$this->isPHPMailerAvailable()) {
                $issues[] = 'PHPMailer non trouvé (requis pour SMTP)';
            }
            
            // Test de connexion SMTP si pas d'erreurs jusqu'ici
            if (empty($issues) && $this->isPHPMailerAvailable()) {
                $smtpTest = $this->testSMTPConnection();
                if (!$smtpTest['success']) {
                    $issues[] = 'Connexion SMTP échouée: ' . $smtpTest['error'];
                }
            }
        } else {
            // Vérifier que PHP peut envoyer des emails via mail()
            if (!function_exists('mail')) {
                $issues[] = 'Fonction mail() non disponible';
            }
        }
        
        if (!empty($issues)) {
            return ['success' => false, 'error' => implode(', ', $issues)];
        }
        
        $method = (defined('SMTP_ENABLED') && SMTP_ENABLED) ? 'SMTP' : 'mail()';
        $config = [];
        if (defined('SMTP_ENABLED') && SMTP_ENABLED) {
            $config['host'] = defined('SMTP_HOST') ? SMTP_HOST : 'N/A';
            $config['port'] = defined('SMTP_PORT') ? SMTP_PORT : 'N/A';
            $config['secure'] = defined('SMTP_SECURE') ? SMTP_SECURE : 'N/A';
        }
        
        return [
            'success' => true, 
            'message' => "Configuration email OK (méthode: $method)",
            'method' => $method,
            'config' => $config
        ];
    }
    
    /**
     * Tester la connexion SMTP
     */
    private function testSMTPConnection() {
        try {
            if (!$this->isPHPMailerAvailable()) {
                return ['success' => false, 'error' => 'PHPMailer non disponible'];
            }
            
            $this->loadPHPMailer();
            
            $mail = new \PHPMailer\PHPMailer\PHPMailer(true);
            
            // Configuration SMTP
            $mail->isSMTP();
            $mail->Host = defined('SMTP_HOST') ? SMTP_HOST : 'localhost';
            $mail->SMTPAuth = true;
            $mail->Username = defined('SMTP_USERNAME') ? SMTP_USERNAME : '';
            $mail->Password = defined('SMTP_PASSWORD') ? SMTP_PASSWORD : '';
            
            // Correction automatique Gmail
            $smtpSecure = defined('SMTP_SECURE') ? SMTP_SECURE : 'tls';
            $smtpPort = defined('SMTP_PORT') ? SMTP_PORT : 587;
            
            if (defined('SMTP_HOST') && SMTP_HOST === 'smtp.gmail.com' && $smtpPort == 587) {
                $smtpSecure = 'tls';
            }
            
            $mail->SMTPSecure = $smtpSecure === 'ssl' ? \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_SMTPS : \PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = $smtpPort;
            
            // Test de connexion seulement
            $mail->SMTPDebug = 0;
            $mail->Timeout = 10;
            
            // Tenter la connexion
            if ($mail->smtpConnect()) {
                $mail->smtpClose();
                return ['success' => true, 'message' => 'Connexion SMTP réussie'];
            } else {
                return ['success' => false, 'error' => 'Impossible de se connecter au serveur SMTP'];
            }
            
        } catch (\PHPMailer\PHPMailer\Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        } catch (Exception $e) {
            return ['success' => false, 'error' => $e->getMessage()];
        }
    }
    
    /**
     * Envoyer un email de test
     */
    public function sendTestEmail($testEmail = null) {
        if (!$testEmail) {
            $testEmail = defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'test@localhost';
        }
        
        $recipient = [
            'email' => $testEmail,
            'name' => 'Test',
            'type' => 'test'
        ];
        
        $subject = 'Test de configuration email - ' . date('Y-m-d H:i:s');
        $htmlBody = '<h2>Test Email</h2><p>Si vous recevez cet email, la configuration fonctionne correctement.</p><p>Date: ' . date('Y-m-d H:i:s') . '</p>';
        $textBody = "Test Email\n\nSi vous recevez cet email, la configuration fonctionne correctement.\n\nDate: " . date('Y-m-d H:i:s');
        
        return $this->sendEmail($recipient, $subject, $htmlBody, $textBody);
    }
    
    /**
     * Calculer les totaux par type d'activité basé sur $ACTIVITY_PRICING
     */
    private function calculateTotalsByActivityType($items) {
        global $ACTIVITY_PRICING;
        
        $totalsByType = [];
        
        // Initialiser les types disponibles
        if (isset($ACTIVITY_PRICING) && is_array($ACTIVITY_PRICING)) {
            foreach ($ACTIVITY_PRICING as $type => $config) {
                $totalsByType[$type] = [
                    'quantity' => 0,
                    'display_name' => $config['display_name'] ?? $type,
                    'type' => $type
                ];
            }
        }
        
        // Compter les items par type d'activité
        foreach ($items as $item) {
            $activityKey = $item['activity_key'] ?? '';
            
            // Chercher le type de pricing pour cette activité
            $pricingType = $this->getActivityPricingType($activityKey);
            
            if (isset($totalsByType[$pricingType])) {
                $totalsByType[$pricingType]['quantity'] += intval($item['quantity'] ?? 1);
            } else {
                // Type inconnu, ajouter comme "PHOTO" par défaut
                if (!isset($totalsByType['PHOTO'])) {
                    $totalsByType['PHOTO'] = [
                        'quantity' => 0,
                        'display_name' => 'Photo',
                        'type' => 'PHOTO'
                    ];
                }
                $totalsByType['PHOTO']['quantity'] += intval($item['quantity'] ?? 1);
            }
        }
        
        // Retourner seulement les types avec des quantités > 0
        return array_filter($totalsByType, function($type) {
            return $type['quantity'] > 0;
        });
    }
    
    /**
     * Obtenir le type de pricing pour une activité donnée
     */
    private function getActivityPricingType($activityKey) {

        $princingType = getActivityTypeInfo($activityKey); // Par défaut
        return $princingType['pricing_type'] ?? 'PHOTO'; // Fallback à 'PHOTO' si non défini
        /*
        // Chercher l'activité dans les données
        if (function_exists('getActivityTypeInfo')) {
            $typeInfo = getActivityTypeInfo($activityKey);
            if (isset($typeInfo['pricing_type'])) {
                return $typeInfo['pricing_type'];
            }
        }
        
        // Fallback : déterminer le type par le nom de l'activité
        $activityKey = strtoupper($activityKey);
        
        if (strpos($activityKey, 'USB') !== false || strpos($activityKey, 'CLE') !== false) {
            return 'USB';
        }
        
        // Par défaut : PHOTO
        return 'PHOTO';
        */
    }
    
    /**
     * Formater les totaux par type pour affichage
     */
    private function formatTotalsByType($totalsByType, $htmlFormat = true) {
        if (empty($totalsByType)) {
            return $htmlFormat ? '0 photo' : '0 photo';
        }
        
        $parts = [];
        foreach ($totalsByType as $type) {
            if ($type['quantity'] > 0) {
                $label = $type['display_name'];
                $quantity = $type['quantity'];
                $parts[] = "$quantity $label" . ($quantity > 1 ? 's' : '');
            }
        }
        
        if (empty($parts)) {
            return $htmlFormat ? '0 photo' : '0 photo';
        }
        
        return implode($htmlFormat ? ', ' : ', ', $parts);
    }
}
?>