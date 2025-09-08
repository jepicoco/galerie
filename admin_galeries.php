<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';

session_start();

require_once 'functions.php';

// Vérifier l'authentification admin
$is_admin = is_admin();

if (!$is_admin) {
    header('Location: index.php');
    exit;
}

$logger = Logger::getInstance();

// Gestion des actions POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (isset($_POST['action'])) {
        switch ($_POST['action']) {
            case 'scan_folders':
                $scannedActivities = scanPhotosDirectories();
                $success_message = "Scan terminé. " . count($scannedActivities) . " activité(s) trouvée(s).";
                $logger->adminAction('Scan des dossiers photos', ['count' => count($scannedActivities)]);
                break;
                
            case 'update_activity':
                $activityKey = $_POST['activity_key'] ?? '';
                $newData = [
                    'name' => $_POST['activity_name'] ?? '',
                    'description' => $_POST['activity_description'] ?? '',
                    'tags' => array_filter(array_map('trim', explode(',', $_POST['activity_tags'] ?? ''))),
                    'featured' => isset($_POST['activity_featured']),
                    'visibility' => $_POST['activity_visibility'] ?? 'public',
                    'pricing_type' => $_POST['activity_pricing_type'] ?? DEFAULT_ACTIVITY_TYPE, // NOUVEAU
                    'updated_at' => date('Y-m-d H:i:s')
                ];
                
                if (updateActivityData($activityKey, $newData)) {
                    $success_message = "Activité mise à jour avec succès.";
                    $logger->adminAction('Mise à jour activité', ['activity' => $activityKey, 'pricing_type' => $newData['pricing_type']]);
                } else {
                    $error_message = "Erreur lors de la mise à jour.";
                }
                break;
                
            case 'delete_activity':
                $activityKey = $_POST['activity_key'] ?? '';
                if (deleteActivity($activityKey)) {
                    $success_message = "Activité supprimée avec succès.";
                    $logger->adminAction('Suppression activité', ['activity' => $activityKey]);
                } else {
                    $error_message = "Erreur lors de la suppression.";
                }
                break;
        }
    }
}

// Charger les données
$activities = loadActivitiesData();

function updateActivityData($activityKey, $newData) {
    $activities = loadActivitiesData();
    
    if (!isset($activities[$activityKey])) {
        return false;
    }
    
    $activities[$activityKey] = array_merge($activities[$activityKey], $newData);
    $activities[$activityKey]['updated_at'] = date('Y-m-d H:i:s');
    
    return saveActivitiesData($activities);
}

function deleteActivity($activityKey) {
    $activities = loadActivitiesData();
    
    if (!isset($activities[$activityKey])) {
        return false;
    }
    
    unset($activities[$activityKey]);
    return saveActivitiesData($activities);
}

// Statistiques
$stats = [
    'total_activities' => count($activities),
    'total_photos' => array_sum(array_map(function($a) { return count($a['photos']); }, $activities)),
    'featured_activities' => count(array_filter($activities, function($a) { return isset($a['featured']) && $a['featured']; })),
    'total_tags' => 0
];

$allTags = [];
foreach ($activities as $activity) {
    if (isset($activity['tags']) && is_array($activity['tags'])) {
        $allTags = array_merge($allTags, $activity['tags']);
    }
}
$stats['total_tags'] = count(array_unique($allTags));

?>

<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Gestion de la galerie - <?php echo(SITE_NAME); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/admin.css">
    <link rel="stylesheet" href="css/admin.galerie.css">
    <link rel="icon" href="favicon.png" />
</head>
<body>
    
    <?php include('include.header.php'); ?>

    <!-- Modal d'édition -->
    <div id="editModal" class="modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 class="modal-title">Modifier l'activité</h2>
                <button class="modal-close" onclick="closeEditModal()">&times;</button>
            </div>
            
            <div class="modal-body">
                <form method="POST" class="activity-form" id="editForm">
                    <input type="hidden" name="action" value="update_activity">
                    <input type="hidden" name="activity_key" id="edit_activity_key" value="">
                    
                    <div class="form-row">
                        <div class="form-group">
                            <label for="edit_activity_name">Nom de l'activité</label>
                            <input type="text" 
                                id="edit_activity_name" 
                                name="activity_name" 
                                required>
                        </div>
                        
                        <div class="form-group">
                            <label for="edit_activity_visibility">Visibilité</label>
                            <select id="edit_activity_visibility" name="activity_visibility">
                                <option value="public">🌐 Public</option>
                                <option value="private">🔒 Privé</option>
                            </select>
                        </div>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_activity_description">Description</label>
                        <textarea id="edit_activity_description" 
                                name="activity_description" 
                                rows="3"></textarea>
                    </div>
                    
                    <div class="form-group">
                        <label for="edit_activity_tags">Tags</label>
    
                        <!-- Input avec système de blocs -->
                        <div class="tags-input-container">
                            <div id="selected-tags" class="selected-tags"></div>
                            <input type="text" 
                                id="edit_activity_tags_hidden" 
                                name="activity_tags" 
                                style="display: none;">
                            <input type="text" 
                                id="edit_activity_tags_input" 
                                placeholder="Taper un tag et appuyer sur Entrée...">
                        </div>
                        
                        <!-- Nuage de tags disponibles -->
                        <div class="tags-cloud-section">
                            <small>Cliquer pour ajouter :</small>
                            <div id="modal-tags-cloud" class="tags-cloud small">
                                <!-- Les tags seront générés dynamiquement -->
                            </div>
                        </div>
                    </div>
                    
                    <div class="checkbox-group">
                        <label>
                            <input type="checkbox" 
                                id="edit_activity_featured"
                                name="activity_featured">
                            Mettre en avant (à la une)
                        </label>
                    </div>

                    <!-- Champ pour le type de tarification -->
                    <div class="form-group">
                        <label for="edit_activity_pricing_type">Type de tarification :</label>
                        <select name="activity_pricing_type" id="edit_activity_pricing_type" class="form-control">
                            <?php
                            foreach ($ACTIVITY_PRICING as $type => $info): 
                            ?>
                                <option value="<?php echo $type; ?>">
                                    <?php echo htmlspecialchars($info['display_name']); ?> 
                                    (<?php echo $info['price']; ?><?php echo CURRENCY_SYMBOL; ?>)
                                </option>
                            <?php endforeach; ?>
                        </select>
                        <small class="form-text text-muted">
                            Définit le prix applicable pour cette activité
                        </small>
                    </div>
                </form>
            </div>
            
            <div class="modal-footer">
                <button type="button" class="btn btn-secondary" onclick="closeEditModal()">
                    Annuler
                </button>
                <button type="submit" form="editForm" class="btn btn-primary">
                    💾 Sauvegarder
                </button>
            </div>
        </div>
    </div>

    <main >
        <div class="container">


        <?php if (isset($success_message)): ?>
            <div class="alert alert-success">
                ✅ <?php echo htmlspecialchars($success_message); ?>
            </div>
        <?php endif; ?>

        <?php if (isset($error_message)): ?>
            <div class="alert alert-error">
                ❌ <?php echo htmlspecialchars($error_message); ?>
            </div>
        <?php endif; ?>

            <!-- Statistiques -->
            <section class="stats-section">
                <h2>📊 Statistiques</h2>
                <div class="stats-grid">
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_activities']; ?></div>
                        <div class="stat-label">Activités</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_photos']; ?></div>
                        <div class="stat-label">Photos</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['featured_activities']; ?></div>
                        <div class="stat-label">À la une</div>
                    </div>
                    <div class="stat-card">
                        <div class="stat-number"><?php echo $stats['total_tags']; ?></div>
                        <div class="stat-label">Tags uniques</div>
                    </div>
                </div>
            </section>

            

            <!-- Recherche et filtres -->
            <section class="search-section">
                <h2>🔎 Recherche et filtres</h2>
                
                <!-- Barre de recherche principale -->
                <div class="main-search">
                    <div class="search-input-wrapper">
                        <input type="text" id="search-activities" placeholder="Rechercher par nom, description ou tags..." class="search-input">
                        <span class="search-icon">🔍</span>
                        <button type="button" id="clear-search" class="clear-search-btn" title="Effacer la recherche">&times;</button>
                    </div>
                </div>

                <!-- Nuage de tags pour filtrage rapide -->
                <div class="tags-cloud-section">
                    <div id="search-tags-cloud" class="tags-cloud">
                        <!-- Les tags seront générés dynamiquement -->
                    </div>
                </div>

                <!-- Filtres rapides en cartes -->
                <div class="quick-filters">
                    <h3>⚡ Filtres rapides</h3>
                    <div class="filter-toggles">
                        <!-- Card 1: Visibilité -->
                        <div class="filter-group">
                            <label class="filter-label">🌐 Visibilité</label>
                            <div class="toggle-buttons">
                                <button type="button" class="filter-toggle active" data-filter="visibility" data-value="">
                                    <span class="toggle-text">📋 Toutes</span>
                                    <span class="toggle-count" id="count-visibility-all">0</span>
                                </button>
                                <button type="button" class="filter-toggle" data-filter="visibility" data-value="public">
                                    <span class="toggle-text">🌐 Public</span>
                                    <span class="toggle-count" id="count-visibility-public">0</span>
                                </button>
                                <button type="button" class="filter-toggle" data-filter="visibility" data-value="private">
                                    <span class="toggle-text">🔒 Privé</span>
                                    <span class="toggle-count" id="count-visibility-private">0</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Card 2: À la une -->
                        <div class="filter-group">
                            <label class="filter-label">⭐ À la une</label>
                            <div class="toggle-buttons">
                                <button type="button" class="filter-toggle active" data-filter="featured" data-value="">
                                    <span class="toggle-text">📋 Toutes</span>
                                    <span class="toggle-count" id="count-featured-all">0</span>
                                </button>
                                <button type="button" class="filter-toggle" data-filter="featured" data-value="true">
                                    <span class="toggle-text">⭐ À la une</span>
                                    <span class="toggle-count" id="count-featured-true">0</span>
                                </button>
                                <button type="button" class="filter-toggle" data-filter="featured" data-value="false">
                                    <span class="toggle-text">📄 Standard</span>
                                    <span class="toggle-count" id="count-featured-false">0</span>
                                </button>
                            </div>
                        </div>
                        
                        <!-- Card 3: Tags -->
                        <div class="filter-group">
                            <label class="filter-label">🏷️ Tags</label>
                            <div class="toggle-buttons">
                                <button type="button" class="filter-toggle active" data-filter="tags" data-value="">
                                    <span class="toggle-text">📋 Toutes</span>
                                    <span class="toggle-count" id="count-tags-all">0</span>
                                </button>
                                <button type="button" class="filter-toggle" data-filter="tags" data-value="has-tags">
                                    <span class="toggle-text">🏷️ Avec tags</span>
                                    <span class="toggle-count" id="count-tags-with">0</span>
                                </button>
                                <button type="button" class="filter-toggle warning" data-filter="tags" data-value="no-tags">
                                    <span class="toggle-text">⚠️ Sans tags</span>
                                    <span class="toggle-count" id="count-tags-without">0</span>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="filter-actions">
                    <button type="button" id="clear-all-filters" class="btn btn-secondary">
                        🔄 Réinitialiser tous les filtres
                    </button>
                    <div class="active-filters-summary" id="active-filters-summary"></div>
                </div>
            </section>

            <!-- Liste des activités -->
            <section class="activities-section">
                <h2 id="activities-title">📂 Activités (<span id="filtered-count"><?php echo count($activities); ?></span> / <?php echo count($activities); ?>)</h2>
                
                <?php if (empty($activities)): ?>
                    <div class="empty-state">
                        <p>Aucune activité trouvée.</p>
                        <p>Utilisez le bouton "Scanner les dossiers photos" pour détecter automatiquement les activités.</p>
                    </div>
                <?php else: ?>
                    <div class="activities-grid">
                        <?php foreach ($activities as $activityKey => $activity): ?>
                            <div class="activity-card <?php echo (empty($activity['tags']) || count($activity['tags']) == 0) ? 'no-tags-card' : ''; ?>" onclick="openEditModal('<?php echo htmlspecialchars($activityKey); ?>')">
                                <!-- Icône d'édition -->
                                <div class="edit-icon" title="Modifier l'activité">✏️</div>
                                <div class="activity-card-header">
                                    <h3 class="activity-title"><?php echo htmlspecialchars($activity['name']); ?></h3>
                                    
                                    <div class="activity-badges">
                                        <span class="badge photo-count">📸 <?php echo count($activity['photos']); ?> photos</span>
                                        <?php if(isset($activity['featured']) && $activity['featured']): ?>
                                            <span class="badge featured">⭐ À la une</span>
                                        <?php endif; ?>
                                        <span class="badge visibility <?php echo isset($activity['visibility']) && $activity['visibility'] === 'public' ? 'public' : 'private' ?>">
                                            <?php echo isset($activity['visibility']) && $activity['visibility'] === 'public' ? '🌐 Public' : '🔒 Privé'; ?>
                                        </span>
                                    </div>
                                    
                                    <div class="activity-tags">
                                        <?php if (!empty($activity['tags']) && count($activity['tags']) > 0): ?>
                                            <?php foreach($activity['tags'] as $tag): ?>
                                                <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                            <?php endforeach; ?>
                                        <?php else: ?>
                                            <span class="no-tags">Tags à saisir</span>
                                        <?php endif; ?>
                                    </div>

                                    <div class="pricing-info">
                                        <span class="pricing-badge pricing-<?php echo strtolower($activity['pricing_type'] ?? DEFAULT_ACTIVITY_TYPE); ?>">
                                            <?php 
                                            $pricingInfo = getActivityTypeInfo($activity['name'] ?? null);
                                            echo $pricingInfo['display_name'] . ' - ' . $pricingInfo['price'] . CURRENCY_SYMBOL; 
                                            ?>
                                        </span>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php endif; ?>
            </section>
        </div>
    </main>
    <script>
        // Recherche et filtres
        const searchInput = document.getElementById('search-activities');
        const visibilityFilter = document.getElementById('filter-visibility');
        const featuredFilter = document.getElementById('filter-featured');
        const clearFilters = document.getElementById('clear-filters');
    </script>
    <script src="js/admin.js"></script>
    <script src="js/admin.galerie.js"></script>
    <?php include('include.footer.php'); ?>
</body>
</html>