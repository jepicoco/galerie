<?php

require_once '../../config.php';
require_once '../../email_handler.php';
require_once 'order_summary.class.php';
require_once 'email_template.php';

class DailyOrderSummaryCron {
    private $config;
    private $orderSummary;
    private $logger;
    private $emailRecipients;

    public function __construct() {
        try {
            $this->orderSummary = new OrderSummary('./cron_config.json');
            $this->config = $this->orderSummary->getConfig();
            $this->logger = $this->orderSummary->getLogger();
            $this->loadEmailRecipients();
            
            $timezone = $this->config['email_settings']['timezone'];
            date_default_timezone_set($timezone);
            
            $this->logger->info("Daily order summary cron initialized");
        } catch (Exception $e) {
            error_log("Fatal error initializing cron: " . $e->getMessage());
            die("Initialization failed: " . $e->getMessage());
        }
    }

    private function loadEmailRecipients() {
        $recipientsFile = $this->config['paths']['email_recipients_file'];
        
        if (!file_exists($recipientsFile)) {
            throw new Exception("Email recipients file not found: $recipientsFile");
        }
        
        $content = file_get_contents($recipientsFile);
        $data = json_decode($content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in recipients file: " . json_last_error_msg());
        }
        
        $this->emailRecipients = array_filter($data['recipients'], function($recipient) {
            return isset($recipient['active']) && $recipient['active'] === true;
        });
        
        $this->logger->info("Loaded " . count($this->emailRecipients) . " active email recipients");
    }

    public function run() {
        try {
            $startTime = microtime(true);
            $this->logger->info("Starting daily order summary cron");
            
            $todayOrders = $this->orderSummary->getTodayOrders();
            $summaryData = $this->orderSummary->generateSummaryData($todayOrders);
            
            if (empty($this->emailRecipients)) {
                $this->logger->warning("No active email recipients found, skipping email sending");
                return;
            }
            
            $emailContent = generateEmailTemplate($summaryData, $this->config);
            $subject = $this->config['email_settings']['subject'];
            
            $successCount = 0;
            $errorCount = 0;
            
            foreach ($this->emailRecipients as $recipient) {
                $success = $this->sendEmailWithRetry($recipient, $subject, $emailContent);
                
                if ($success) {
                    $successCount++;
                    $this->logger->info("Email sent successfully to: " . $recipient['email']);
                } else {
                    $errorCount++;
                    $this->logger->error("Failed to send email to: " . $recipient['email']);
                }
            }
            
            $executionTime = round(microtime(true) - $startTime, 2);
            
            $this->logger->info("Daily summary completed - " . 
                               "Orders: " . $summaryData['total_orders'] . ", " .
                               "Revenue: " . number_format($summaryData['total_amount'], 2) . "€, " .
                               "Emails sent: $successCount/$successCount+$errorCount, " .
                               "Execution time: {$executionTime}s");
            
            return [
                'success' => true,
                'orders_count' => $summaryData['total_orders'],
                'total_amount' => $summaryData['total_amount'],
                'emails_sent' => $successCount,
                'emails_failed' => $errorCount,
                'execution_time' => $executionTime
            ];
            
        } catch (Exception $e) {
            $this->logger->error("Cron execution failed: " . $e->getMessage());
            return [
                'success' => false,
                'error' => $e->getMessage()
            ];
        }
    }

    private function sendEmailWithRetry($recipient, $subject, $content) {
        $maxAttempts = $this->config['system_settings']['max_retry_attempts'];
        $retryDelay = $this->config['system_settings']['retry_delay_minutes'] * 60;
        
        for ($attempt = 1; $attempt <= $maxAttempts; $attempt++) {
            try {
                $success = $this->sendEmail($recipient, $subject, $content);
                
                if ($success) {
                    if ($attempt > 1) {
                        $this->logger->info("Email sent to " . $recipient['email'] . " on attempt $attempt");
                    }
                    return true;
                }
                
            } catch (Exception $e) {
                $this->logger->warning("Email attempt $attempt failed for " . $recipient['email'] . ": " . $e->getMessage());
            }
            
            if ($attempt < $maxAttempts && $this->config['system_settings']['retry_failed_emails']) {
                $this->logger->info("Retrying email to " . $recipient['email'] . " in " . ($retryDelay/60) . " minutes");
                sleep($retryDelay);
            }
        }
        
        return false;
    }

    private function sendEmail($recipient, $subject, $content) {
        try {
            $mail = new PHPMailer\PHPMailer\PHPMailer(true);
            
            $mail->isSMTP();
            $mail->Host = SMTP_HOST;
            $mail->SMTPAuth = true;
            $mail->Username = SMTP_USER;
            $mail->Password = SMTP_PASS;
            $mail->SMTPSecure = PHPMailer\PHPMailer\PHPMailer::ENCRYPTION_STARTTLS;
            $mail->Port = SMTP_PORT;
            $mail->CharSet = 'UTF-8';
            
            $mail->setFrom(SMTP_FROM_EMAIL, $this->config['email_settings']['from_name']);
            $mail->addAddress($recipient['email'], $recipient['name']);
            
            $mail->isHTML(true);
            $mail->Subject = $subject;
            $mail->Body = $content;
            
            $alternativeContent = $this->generateTextAlternative($content);
            $mail->AltBody = $alternativeContent;
            
            return $mail->send();
            
        } catch (Exception $e) {
            throw new Exception("Failed to send email: " . $e->getMessage());
        }
    }
    
    private function generateTextAlternative($htmlContent) {
        $text = strip_tags($htmlContent);
        $text = html_entity_decode($text, ENT_QUOTES, 'UTF-8');
        $text = preg_replace('/\s+/', ' ', $text);
        return trim($text);
    }

    public static function shouldRun() {
        $config = json_decode(file_get_contents('./cron_config.json'), true);
        $currentHour = date('H');
        $currentMinute = date('i');
        $targetHour = $config['email_settings']['send_hour'];
        $targetMinute = $config['email_settings']['send_minute'];
        
        return ($currentHour == $targetHour && $currentMinute == $targetMinute);
    }
}

if (php_sapi_name() === 'cli' || (isset($_GET['run_cron']) && $_GET['run_cron'] === 'true')) {
    $cron = new DailyOrderSummaryCron();
    $result = $cron->run();
    
    if (php_sapi_name() === 'cli') {
        echo "Daily Order Summary Cron Results:\n";
        echo "Success: " . ($result['success'] ? 'Yes' : 'No') . "\n";
        if ($result['success']) {
            echo "Orders processed: " . $result['orders_count'] . "\n";
            echo "Total amount: " . number_format($result['total_amount'], 2) . "€\n";
            echo "Emails sent: " . $result['emails_sent'] . "\n";
            echo "Emails failed: " . $result['emails_failed'] . "\n";
            echo "Execution time: " . $result['execution_time'] . "s\n";
        } else {
            echo "Error: " . $result['error'] . "\n";
        }
    } else {
        header('Content-Type: application/json');
        echo json_encode($result, JSON_PRETTY_PRINT);
    }
} else {
    echo "Direct access not allowed. Use CLI or add ?run_cron=true parameter for testing.";
}