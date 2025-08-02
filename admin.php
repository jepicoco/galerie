<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';

session_start();

require_once 'functions.php';

// Vérifier si l'utilisateur est connecté en tant qu'admin
if (!isset($_SESSION['admin']) || $_SESSION['admin'] !== true) {
    header('Location: index.php');
    exit;
}

// Message de succès après redirection
if (isset($_GET['watermark_updated'])) {
    $success_message = "Configuration du watermark mise à jour avec succès.";
}

$watermarkConfig = getWatermarkConfig();

// Charger les données
$activities_file = DATA_DIR . 'activities.json';
$photos_file = DATA_DIR . 'photos_list.json';

$activities = [];
if (file_exists($activities_file)) {
    $activities = json_decode(file_get_contents($activities_file), true) ?: [];
}

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'scan_directories':
                // Scanner les dossiers et mettre à jour
                $scanned_activities = scanPhotosDirectories();
                
                foreach ($scanned_activities as $key => $scanned_activity) {
                    if (!isset($activities[$key])) {
                        $activities[$key] = [
                            'name' => $scanned_activity['name'],
                            'photos' => $scanned_activity['photos'],
                            'tags' => [],
                            'description' => ''
                        ];
                    } else {
                        // Mettre à jour la liste des photos
                        $activities[$key]['photos'] = $scanned_activity['photos'];
                    }
                }
                
                file_put_contents($activities_file, json_encode($activities, JSON_PRETTY_PRINT));
                $success_message = "Scan des dossiers terminé. " . count($scanned_activities) . " activité(s) trouvée(s).";
                break;
                
            case 'update_activity':
                $activity_key = $_POST['activity_key'];
                if (isset($activities[$activity_key])) {
                    $activities[$activity_key]['tags'] = array_filter(array_map('trim', explode(',', $_POST['tags'])));
                    $activities[$activity_key]['description'] = trim($_POST['description']);
                    
                    file_put_contents($activities_file, json_encode($activities, JSON_PRETTY_PRINT));
                    $success_message = "Activité mise à jour avec succès.";
                }
                break;
                
            case 'generate_photos_list':
                // Générer la liste de toutes les photos
                $all_photos = [];
                foreach ($activities as $activity_key => $activity) {
                    foreach ($activity['photos'] as $photo) {
                        $all_photos[] = [
                            'filename' => $photo,
                            'activity' => $activity['name'],
                            'path' => PHOTOS_DIR . $activity_key . '/' . $photo,
                            'tags' => $activity['tags']
                        ];
                    }
                }
                
                file_put_contents($photos_file, json_encode($all_photos, JSON_PRETTY_PRINT));
                $success_message = "Liste des photos générée. " . count($all_photos) . " photo(s) répertoriée(s).";
                break;
                
            case 'export_activities':
                // Exporter la liste des activités
                $export_file = DATA_DIR . 'activities_export.json';
                $export_data = [];
                foreach ($activities as $key => $activity) {
                    $export_data[] = [
                        'name' => $activity['name'],
                        'photo_count' => count($activity['photos']),
                        'tags' => $activity['tags'],
                        'description' => $activity['description']
                    ];
                }
                
                file_put_contents($export_file, json_encode($export_data, JSON_PRETTY_PRINT));
                $success_message = "Export des activités créé dans " . $export_file;
                break;

            case 'update_watermark':
            $watermarkEnabled = isset($_POST['watermark_enabled']) ? 'true' : 'false';
            $watermarkText = addslashes($_POST['watermark_text'] ?? 'Gala de danse');
            $watermarkOpacity = floatval($_POST['watermark_opacity'] ?? 0.3);
            $watermarkSize = intval($_POST['watermark_size'] ?? 24) . 'px';
            $watermarkColor = $_POST['watermark_color'] ?? '#FFFFFF';
            
            // Lire le fichier config actuel
            $configFile = 'config_watermark.php';
            if (file_exists($configFile)) {
                $configContent = file_get_contents($configFile);

                // Remplacer ou ajouter les constantes watermark
                $watermarkConfig = "
// WATERMARK
define('WATERMARK_ENABLED', $watermarkEnabled);
define('WATERMARK_TEXT', '$watermarkText');
define('WATERMARK_OPACITY', $watermarkOpacity);
define('WATERMARK_SIZE', '$watermarkSize');
define('WATERMARK_COLOR', '$watermarkColor');
define('WATERMARK_ANGLE', -45);";

                // Supprimer l'ancienne section watermark si elle existe
                $configContent = preg_replace('/\/\/ WATERMARK.*?define\(\'WATERMARK_ANGLE\'[^;]*;/s', '', $configContent);
                
                // Ajouter la nouvelle configuration avant la fermeture PHP
                $configContent = str_replace('?>', $watermarkConfig . "\n\n?>", $configContent);
                
                // Sauvegarder
                if (file_put_contents($configFile, $watermarkConfig)) {
                    // Vider le cache PHP si disponible
                    if (function_exists('opcache_reset')) {
                        opcache_reset();
                    }
                    
                    // Redirection pour recharger les constantes
                    header('Location: admin.php?watermark_updated=1');
                    exit;
                } else {
                    $success_message = "Erreur lors de la sauvegarde de la configuration.";
                }
            }
            break;
        }
    }
}

// Recharger les activités après modification
if (file_exists($activities_file)) {
    $activities = json_decode(file_get_contents($activities_file), true) ?: [];
}

?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Administration - <?php echo(SITE_NAME); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="icon" href="favicon.png" />
</head>
<body>
    <header>
        <div class="container">
            <h1>Administration - Galerie Photos</h1>
            <nav>
                <a href="index.php" class="btn btn-secondary">Galerie</a>
                <a href="?logout=1" class="btn btn-outline">Déconnexion</a>
            </nav>
        </div>
    </header>

    <main>
        <div class="container">
            <?php if (isset($success_message)): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($success_message); ?></div>
            <?php endif; ?>

            <!-- Actions principales -->
            <section class="admin-actions">
                <h2>Actions</h2>
                <div class="actions-grid">
                    <form method="POST" class="action-form">
                        <input type="hidden" name="action" value="scan_directories">
                        <h3>Scanner les dossiers</h3>
                        <p>Parcourt le dossier photos/ et met à jour la liste des activités</p>
                        <button type="submit" class="btn btn-primary">Lancer le scan</button>
                    </form>

                    <form method="POST" class="action-form">
                        <input type="hidden" name="action" value="generate_photos_list">
                        <h3>Générer la liste des photos</h3>
                        <p>Crée un fichier JSON avec toutes les photos et leurs activités</p>
                        <button type="submit" class="btn btn-primary">Générer</button>
                    </form>

                    <form method="POST" class="action-form">
                        <input type="hidden" name="action" value="export_activities">
                        <h3>Exporter les activités</h3>
                        <p>Crée un fichier d'export avec toutes les activités et leurs informations</p>
                        <button type="submit" class="btn btn-primary">Exporter</button>
                    </form>
                </div>
            </section>

            <!-- Statistiques -->
            <section class="stats">
                <h2>Statistiques</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <h3><?php echo count($activities); ?></h3>
                        <p>Activités</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php echo array_sum(array_map(function($a) { return count($a['photos']); }, $activities)); ?></h3>
                        <p>Photos total</p>
                    </div>
                    <div class="stat-card">
                        <h3><?php 
                        $allTags = [];
                        foreach ($activities as $activity) {
                            if (isset($activity['tags']) && is_array($activity['tags'])) {
                                $allTags = array_merge($allTags, $activity['tags']);
                            }
                        }
                        echo count(array_unique($allTags)); 
                        ?></h3>
                        <p>Tags uniques</p>
                    </div>
                </div>
            </section>

            <!-- Gestion du Watermark -->
            <section class="watermark-management">
                <h2>Configuration</h2>
                
                <div class="accordion-section">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h3>⚙️ Paramètres du Watermark</h3>
                        <span class="accordion-toggle">▼</span>
                    </div>
                    
                    <div class="accordion-content">
                        <div class="accordion-inner">
                            <form method="POST" class="activity-form">
                                <input type="hidden" name="action" value="update_watermark">
                                
                                <div class="checkbox-group">
                                    <input type="checkbox" 
                                        id="watermark_enabled"
                                        name="watermark_enabled" 
                                        <?php echo $watermarkConfig['WATERMARK_ENABLED'] ? 'checked' : ''; ?>>
                                    <label for="watermark_enabled">Activer le watermark</label>
                                </div>

                                <div class="form-group">
                                    <label for="watermark_text">Texte du watermark</label>
                                    <input type="text" 
                                        id="watermark_text" 
                                        name="watermark_text" 
                                        value="<?php echo htmlspecialchars($watermarkConfig['WATERMARK_TEXT']); ?>"
                                        placeholder="Votre texte de watermark">
                                </div>

                                <div class="form-group">
                                    <label for="watermark_opacity">Opacité (0.1 à 1.0)</label>
                                    <input type="number" 
                                        id="watermark_opacity" 
                                        name="watermark_opacity" 
                                        value="<?php echo $watermarkConfig['WATERMARK_OPACITY']; ?>"
                                        min="0.1" max="1.0" step="0.1">
                                </div>

                                <div class="form-group">
                                    <label for="watermark_size">Taille de police (px)</label>
                                    <input type="number" 
                                        id="watermark_size" 
                                        name="watermark_size" 
                                        value="<?php echo str_replace('px', '', $watermarkConfig['WATERMARK_SIZE']); ?>"
                                        min="12" max="72">
                                </div>

                                <div class="form-group">
                                    <label for="watermark_color">Couleur</label>
                                    <input type="color" 
                                        id="watermark_color" 
                                        name="watermark_color" 
                                        value="<?php echo $watermarkConfig['WATERMARK_COLOR']; ?>">
                                </div>
                                
                                <button type="submit" class="btn btn-primary">💾 Sauvegarder Watermark</button>
                            </form>
                            
                            <!-- Aperçu -->
                            <div class="watermark-preview" style="margin-top: 2rem;">
                                <h4>Aperçu :</h4>
                                <div class="watermark-container" data-watermark="<?php echo htmlspecialchars(defined('WATERMARK_TEXT') ? WATERMARK_TEXT : 'Gala de danse'); ?>" 
                                    style="width: 200px; height: 150px; background: #f0f0f0; display: inline-block; border-radius: 8px;">
                                    <div class="watermark-pattern">
                                        <?php for ($i = 0; $i < 4; $i++): ?>
                                            <div class="watermark-text" style="left: <?php echo $i * 100; ?>px; top: 50px;">
                                                <?php echo htmlspecialchars(defined('WATERMARK_TEXT') ? WATERMARK_TEXT : 'Gala de danse'); ?>
                                            </div>
                                        <?php endfor; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Configuration Email -->
            <section class="email-management">
                <div class="accordion-section">
                    <div class="accordion-header" onclick="toggleAccordion(this)">
                        <h3>📧 Configuration Email</h3>
                        <span class="accordion-toggle">▼</span>
                    </div>
                    
                    <div class="accordion-content">
                        <div class="accordion-inner">
                            <div class="email-config-info">
                                <h4>Paramètres actuels</h4>
                                <div class="config-grid">
                                    <div class="config-item">
                                        <strong>Envoi activé :</strong> 
                                        <span class="<?php echo (defined('MAIL_ENABLED') && MAIL_ENABLED) ? 'status-ok' : 'status-warning'; ?>">
                                            <?php echo (defined('MAIL_ENABLED') && MAIL_ENABLED) ? '✅ Oui' : '⚠️ Non'; ?>
                                        </span>
                                    </div>
                                    <div class="config-item">
                                        <strong>Méthode :</strong> 
                                        <span class="<?php echo (defined('SMTP_ENABLED') && SMTP_ENABLED) ? 'status-ok' : 'status-info'; ?>">
                                            <?php echo (defined('SMTP_ENABLED') && SMTP_ENABLED) ? '📡 SMTP' : '📬 mail()'; ?>
                                        </span>
                                    </div>
                                    <?php if (defined('SMTP_ENABLED') && SMTP_ENABLED): ?>
                                    <div class="config-item">
                                        <strong>Serveur SMTP :</strong> 
                                        <code><?php echo defined('SMTP_HOST') ? SMTP_HOST . ':' . (defined('SMTP_PORT') ? SMTP_PORT : '25') : 'Non configuré'; ?></code>
                                    </div>
                                    <div class="config-item">
                                        <strong>Sécurité :</strong> 
                                        <code><?php echo defined('SMTP_SECURE') ? strtoupper(SMTP_SECURE) : 'Aucune'; ?></code>
                                    </div>
                                    <div class="config-item">
                                        <strong>Utilisateur SMTP :</strong> 
                                        <code><?php echo defined('SMTP_USERNAME') ? SMTP_USERNAME : 'Non configuré'; ?></code>
                                    </div>
                                    <?php endif; ?>
                                    <div class="config-item">
                                        <strong>Email expéditeur :</strong> 
                                        <code><?php echo defined('MAIL_FROM_EMAIL') ? MAIL_FROM_EMAIL : 'Non configuré'; ?></code>
                                    </div>
                                    <div class="config-item">
                                        <strong>Destinataires admin :</strong> 
                                        <code><?php echo defined('MAIL_ADMIN_RECIPIENTS') ? MAIL_ADMIN_RECIPIENTS : 'Non configuré'; ?></code>
                                    </div>
                                </div>
                            </div>
                            
                            <div class="email-actions">
                                <button id="test-email-config" class="btn btn-secondary">🧪 Tester la configuration</button>
                                <button id="send-test-email" class="btn btn-primary">📧 Envoyer un email de test</button>
                                <div id="email-test-result" class="test-result" style="display: none;"></div>
                            </div>
                            
                            <div class="email-help">
                                <h4>💡 Configuration pour Free Pro</h4>
                                <p>Pour utiliser SMTP, configurez dans <code>config.php</code> :</p>
                                <pre><code>// Configuration SMTP
define('SMTP_ENABLED', true);
define('SMTP_HOST', 'smtp.exemple.fr');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@free.fr');
define('SMTP_PASSWORD', 'votre-mot-de-passe-free');
define('SMTP_SECURE', 'tls');</code></pre>
                                
                                <div class="email-notes">
                                    <h5>📋 Notes importantes :</h5>
                                    <ul>
                                        <li>Utilisez votre adresse @free.fr complète comme username</li>
                                        <li>Le mot de passe est celui de votre compte Free</li>
                                        <li>Port 587 avec TLS est recommandé</li>
                                        <li>Assurez-vous que le SMTP est activé dans votre espace Free</li>
                                    </ul>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>

<script>
// Test de configuration email
document.getElementById('test-email-config')?.addEventListener('click', async function() {
    this.disabled = true;
    this.textContent = '🔄 Test en cours...';
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=test_email_config'
        });
        
        const result = await response.json();
        const resultDiv = document.getElementById('email-test-result');
        
        resultDiv.style.display = 'block';
        if (result.success) {
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = '✅ ' + result.message;
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = '❌ ' + result.error;
        }
        
    } catch (error) {
        const resultDiv = document.getElementById('email-test-result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = '❌ Erreur de communication: ' + error.message;
    }
    
    this.disabled = false;
    this.textContent = '🧪 Tester la configuration';
});

// Test d'envoi d'email
document.getElementById('send-test-email')?.addEventListener('click', async function() {
    this.disabled = true;
    this.textContent = '📤 Envoi en cours...';
    
    try {
        const response = await fetch('order_handler.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/x-www-form-urlencoded'},
            body: 'action=send_test_email'
        });
        
        const result = await response.json();
        const resultDiv = document.getElementById('email-test-result');
        
        resultDiv.style.display = 'block';
        if (result.success) {
            resultDiv.className = 'test-result success';
            resultDiv.innerHTML = '✅ ' + result.message + '<br><small>Référence: ' + result.reference + '</small>';
        } else {
            resultDiv.className = 'test-result error';
            resultDiv.innerHTML = '❌ ' + result.error;
        }
        
    } catch (error) {
        const resultDiv = document.getElementById('email-test-result');
        resultDiv.style.display = 'block';
        resultDiv.className = 'test-result error';
        resultDiv.innerHTML = '❌ Erreur de communication: ' + error.message;
    }
    
    this.disabled = false;
    this.textContent = '📧 Envoyer un email de test';
});
</script>

            <!-- Gestion des activités -->
            <section class="activities-management">
                <h2>Gestion des activités</h2>
                
                <?php if (empty($activities)): ?>
                    <p class="no-activities">Aucune activité trouvée. Lancez un scan des dossiers pour commencer.</p>
                <?php else: ?>
                    <div class="activities-list">
                        <?php foreach ($activities as $activity_key => $activity): ?>
                            <div class="activity-item">
                                <div class="activity-header">
                                    <h3><?php echo htmlspecialchars($activity['name']); ?></h3>
                                    <span class="photo-count"><?php echo count($activity['photos']); ?> photo(s)</span>
                                </div>
                                
                                <form method="POST" class="activity-form">
                                    <input type="hidden" name="action" value="update_activity">
                                    <input type="hidden" name="activity_key" value="<?php echo htmlspecialchars($activity_key); ?>">
                                    
                                    <div class="form-group">
                                        <label for="tags_<?php echo $activity_key; ?>">Tags (séparés par des virgules)</label>
                                        <input type="text" 
                                               id="tags_<?php echo $activity_key; ?>" 
                                               name="tags" 
                                               value="<?php echo htmlspecialchars(implode(', ', $activity['tags'])); ?>"
                                               placeholder="sport, extérieur, été...">
                                    </div>
                                    
                                    <div class="form-group">
                                        <label for="description_<?php echo $activity_key; ?>">Description</label>
                                        <textarea id="description_<?php echo $activity_key; ?>" 
                                                  name="description" 
                                                  rows="3"
                                                  placeholder="Description de l'activité..."><?php echo htmlspecialchars($activity['description']); ?></textarea>
                                    </div>
                                    
                                    <button type="submit" class="btn btn-primary">Sauvegarder</button>
                                </form>
                                
                                <!-- Aperçu des photos -->
                                <div class="photos-preview">
                                    <h4>Photos (<?php echo count($activity['photos']); ?>)</h4>
                                    <div class="photos-grid">
                                        <?php foreach (array_slice($activity['photos'], 0, 6) as $photo): ?>
                                            <img src="<?php echo PHOTOS_DIR . $activity_key . '/' . $photo; ?>" 
                                                 alt="<?php echo htmlspecialchars($photo); ?>"
                                                 class="photo-thumbnail">
                                        <?php endforeach; ?>
                                        <?php if (count($activity['photos']) > 6): ?>
                                            <div class="more-photos">+<?php echo count($activity['photos']) - 6; ?> autres</div>
                                        <?php endif; ?>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>

            <!-- Fichiers générés -->
            <section class="generated-files">
                <h2>Fichiers générés</h2>
                <div class="files-list">
                    <?php
                    $files_to_check = [
                        'activities.json' => 'Configuration des activités',
                        'photos_list.json' => 'Liste complète des photos',
                        'activities_export.json' => 'Export des activités'
                    ];
                    
                    foreach ($files_to_check as $filename => $description) {
                        $filepath = DATA_DIR . $filename;
                        if (file_exists($filepath)) {
                            $filesize = filesize($filepath);
                            $modified = date('d/m/Y H:i:s', filemtime($filepath));
                            echo "<div class='file-item'>";
                            echo "<strong>" . htmlspecialchars($filename) . "</strong> - " . htmlspecialchars($description);
                            echo "<br><small>Taille: " . number_format($filesize) . " octets - Modifié: " . $modified . "</small>";
                            echo "</div>";
                        }
                    }
                    ?>
                </div>
            </section>
        </div>
    </main>

    <script src="js/admin.js"></script>
</body>
</html>