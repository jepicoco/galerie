<?php

function generateEmailTemplate($summaryData, $config) {
    $currencySymbol = $config['report_settings']['currency_symbol'];
    $date = date('d/m/Y', strtotime($summaryData['date']));
    
    ob_start();
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Récapitulatif quotidien - Gala 2025</title>
    <style>
        body {
            font-family: 'Segoe UI', Tahoma, Geneva, Verdana, sans-serif;
            line-height: 1.6;
            color: #333;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
            background-color: #f5f5f5;
        }
        .container {
            background: white;
            border-radius: 10px;
            box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            overflow: hidden;
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 300;
        }
        .content {
            padding: 30px;
        }
        .stats-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 20px;
            margin: 30px 0;
        }
        .stat-card {
            background: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            text-align: center;
            border-left: 4px solid #667eea;
        }
        .stat-number {
            font-size: 36px;
            font-weight: bold;
            color: #667eea;
            margin: 0;
        }
        .stat-label {
            margin: 10px 0 0 0;
            color: #666;
            font-size: 14px;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        .orders-section {
            margin: 40px 0;
        }
        .section-title {
            font-size: 20px;
            margin-bottom: 20px;
            padding-bottom: 10px;
            border-bottom: 2px solid #667eea;
        }
        .order-item {
            background: #fff;
            border: 1px solid #e9ecef;
            border-radius: 8px;
            margin-bottom: 15px;
            padding: 20px;
        }
        .order-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
            font-weight: bold;
        }
        .order-time {
            color: #667eea;
            font-size: 14px;
        }
        .order-total {
            background: #28a745;
            color: white;
            padding: 5px 15px;
            border-radius: 20px;
            font-size: 14px;
        }
        .client-info {
            background: #f8f9fa;
            padding: 10px;
            border-radius: 4px;
            margin-bottom: 15px;
        }
        .items-list {
            margin-top: 10px;
        }
        .item-row {
            display: flex;
            justify-content: space-between;
            padding: 5px 0;
            border-bottom: 1px solid #eee;
        }
        .item-row:last-child {
            border-bottom: none;
        }
        .no-orders {
            text-align: center;
            padding: 40px;
            color: #666;
            font-style: italic;
        }
        .footer {
            background: #f8f9fa;
            padding: 20px 30px;
            text-align: center;
            color: #666;
            font-size: 12px;
        }
        .activities-breakdown {
            margin: 30px 0;
        }
        .activity-item {
            display: flex;
            justify-content: space-between;
            padding: 8px 0;
            border-bottom: 1px solid #eee;
        }
    </style>
</head>
<body>
    <div class="container">
        <div class="header">
            <h1>📊 Récapitulatif Quotidien</h1>
            <p>Commandes du <?php echo $date; ?></p>
        </div>
        
        <div class="content">
            <div class="stats-grid">
                <div class="stat-card">
                    <div class="stat-number"><?php echo $summaryData['total_orders']; ?></div>
                    <div class="stat-label">Commandes</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo number_format($summaryData['total_amount'], 2); ?><?php echo $currencySymbol; ?></div>
                    <div class="stat-label">Chiffre d'affaires</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $summaryData['statistics']['photos']; ?></div>
                    <div class="stat-label">Photos vendues</div>
                </div>
                <div class="stat-card">
                    <div class="stat-number"><?php echo $summaryData['statistics']['usb']; ?></div>
                    <div class="stat-label">Clés USB</div>
                </div>
            </div>

            <?php if (!empty($summaryData['statistics']['activities'])): ?>
            <div class="activities-breakdown">
                <h3 class="section-title">🎭 Répartition par activité</h3>
                <?php foreach ($summaryData['statistics']['activities'] as $activity => $count): ?>
                <div class="activity-item">
                    <span><strong><?php echo htmlspecialchars($activity); ?></strong></span>
                    <span><?php echo $count; ?> commande<?php echo $count > 1 ? 's' : ''; ?></span>
                </div>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <div class="orders-section">
                <h3 class="section-title">📋 Détail des commandes</h3>
                
                <?php if (empty($summaryData['orders'])): ?>
                <div class="no-orders">
                    <p>🌙 Aucune commande aujourd'hui</p>
                    <p>Bonne nuit et à demain pour de nouveaux résultats !</p>
                </div>
                <?php else: ?>
                
                <?php 
                $maxOrders = $config['report_settings']['max_orders_in_detail'];
                $ordersToShow = array_slice($summaryData['orders'], 0, $maxOrders);
                $remainingOrders = count($summaryData['orders']) - count($ordersToShow);
                ?>
                
                <?php foreach ($ordersToShow as $order): ?>
                <div class="order-item">
                    <div class="order-header">
                        <span class="order-time">⏰ <?php echo $order['time']; ?></span>
                        <span class="order-total"><?php echo number_format($order['total'], 2); ?><?php echo $currencySymbol; ?></span>
                    </div>
                    
                    <div class="client-info">
                        <strong>👤 Client:</strong> <?php echo htmlspecialchars($order['client_name']); ?><br>
                        <strong>📧 Email:</strong> <?php echo htmlspecialchars($order['client_email']); ?>
                    </div>
                    
                    <div class="items-list">
                        <?php foreach ($order['items'] as $item): ?>
                        <div class="item-row">
                            <span>
                                <strong><?php echo htmlspecialchars($item['activity']); ?></strong> - 
                                <?php echo $item['type']; ?>
                                <?php if ($item['filename'] !== 'N/A'): ?>
                                    <br><small><?php echo htmlspecialchars($item['filename']); ?></small>
                                <?php endif; ?>
                            </span>
                            <span><?php echo number_format($item['price'], 2); ?><?php echo $currencySymbol; ?></span>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endforeach; ?>
                
                <?php if ($remainingOrders > 0): ?>
                <div class="order-item" style="text-align: center; font-style: italic; color: #666;">
                    ... et <?php echo $remainingOrders; ?> autre<?php echo $remainingOrders > 1 ? 's' : ''; ?> commande<?php echo $remainingOrders > 1 ? 's' : ''; ?>
                </div>
                <?php endif; ?>
                
                <?php endif; ?>
            </div>
        </div>
        
        <div class="footer">
            <p>🤖 Rapport généré automatiquement le <?php echo date('d/m/Y à H:i'); ?></p>
            <p>Système de gestion Gala 2025 - Récapitulatif quotidien des commandes</p>
        </div>
    </div>
</body>
</html>
<?php
    return ob_get_clean();
}