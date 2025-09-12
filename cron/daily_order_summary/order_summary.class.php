<?php

class OrderSummary {
    private $config;
    private $logger;
    private $ordersDirectory;
    
    public function __construct($configPath = './cron_config.json') {
        $this->loadConfig($configPath);
        $this->initializeLogger();
        $this->ordersDirectory = $this->config['paths']['orders_directory'];
    }
    
    private function loadConfig($configPath) {
        if (!file_exists($configPath)) {
            throw new Exception("Configuration file not found: $configPath");
        }
        
        $configContent = file_get_contents($configPath);
        $this->config = json_decode($configContent, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new Exception("Invalid JSON in configuration file: " . json_last_error_msg());
        }
    }
    
    private function initializeLogger() {
        require_once $this->config['paths']['logs_directory'] . '../logger_class.php';
        $this->logger = new Logger('cron_order_summary');
    }
    
    public function getTodayOrders() {
        $today = date('Y-m-d');
        $orders = [];
        
        if (!is_dir($this->ordersDirectory)) {
            $this->logger->warning("Orders directory not found: " . $this->ordersDirectory);
            return $orders;
        }
        
        $files = glob($this->ordersDirectory . '*.json');
        
        foreach ($files as $file) {
            if (strpos(basename($file), 'temp') !== false) {
                continue;
            }
            
            $fileModTime = date('Y-m-d', filemtime($file));
            
            if ($fileModTime === $today) {
                $orderData = $this->loadOrderFile($file);
                if ($orderData) {
                    $orderData['filename'] = basename($file);
                    $orderData['order_time'] = date('H:i:s', filemtime($file));
                    $orders[] = $orderData;
                }
            }
        }
        
        usort($orders, function($a, $b) {
            return strcmp($a['order_time'], $b['order_time']);
        });
        
        $this->logger->info("Found " . count($orders) . " orders for today ($today)");
        return $orders;
    }
    
    private function loadOrderFile($filePath) {
        try {
            $content = file_get_contents($filePath);
            $orderData = json_decode($content, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->logger->error("Invalid JSON in order file: " . basename($filePath));
                return null;
            }
            
            return $orderData;
        } catch (Exception $e) {
            $this->logger->error("Error reading order file " . basename($filePath) . ": " . $e->getMessage());
            return null;
        }
    }
    
    public function generateSummaryData($orders) {
        $summary = [
            'date' => date('Y-m-d'),
            'total_orders' => count($orders),
            'total_amount' => 0,
            'statistics' => [
                'photos' => 0,
                'usb' => 0,
                'activities' => []
            ],
            'orders' => []
        ];
        
        foreach ($orders as $order) {
            $orderTotal = 0;
            $orderDetails = [
                'filename' => $order['filename'],
                'time' => $order['order_time'],
                'client_name' => isset($order['client_name']) ? $order['client_name'] : 'Non spécifié',
                'client_email' => isset($order['client_email']) ? $order['client_email'] : 'Non spécifié',
                'items' => [],
                'total' => 0
            ];
            
            if (isset($order['commande']) && is_array($order['commande'])) {
                foreach ($order['commande'] as $activity => $items) {
                    if (is_array($items)) {
                        foreach ($items as $item) {
                            if (isset($item['type']) && isset($item['price'])) {
                                $orderTotal += floatval($item['price']);
                                
                                if ($item['type'] === 'PHOTO') {
                                    $summary['statistics']['photos']++;
                                } elseif ($item['type'] === 'USB') {
                                    $summary['statistics']['usb']++;
                                }
                                
                                if (!isset($summary['statistics']['activities'][$activity])) {
                                    $summary['statistics']['activities'][$activity] = 0;
                                }
                                $summary['statistics']['activities'][$activity]++;
                                
                                $orderDetails['items'][] = [
                                    'activity' => $activity,
                                    'type' => $item['type'],
                                    'filename' => isset($item['filename']) ? $item['filename'] : 'N/A',
                                    'price' => floatval($item['price'])
                                ];
                            }
                        }
                    }
                }
            }
            
            $orderDetails['total'] = $orderTotal;
            $summary['total_amount'] += $orderTotal;
            $summary['orders'][] = $orderDetails;
        }
        
        return $summary;
    }
    
    public function getConfig() {
        return $this->config;
    }
    
    public function getLogger() {
        return $this->logger;
    }
}