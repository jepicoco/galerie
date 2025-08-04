<?php
define('GALLERY_ACCESS', true);

require_once 'config.php';

session_start();

require_once 'functions.php';

// Créer les dossiers de données s'ils n'existent pas
if (!is_dir(DATA_DIR)) {
    mkdir(DATA_DIR, 0755, true);
}

// Gestion de la connexion admin
if (isset($_POST['admin_login'])) {
    if ($_POST['password'] === ADMIN_PASSWORD) {
        $_SESSION['admin'] = true;
        header('Location: index.php');
        exit;
    } else {
        $error_message = "Mot de passe incorrect";
    }
}

// Gestion de la déconnexion
if (isset($_GET['logout'])) {
    session_destroy();
    header('Location: index.php');
    exit;
}

if($is_admin){
    cleanOldTempOrders(COMMANDES_DIR);
}

// Si aucun fichier d'activités n'existe, le créer à partir des dossiers
if (empty($activities)) {
    $activities = scanPhotosDirectories();
    file_put_contents($activities_file, json_encode($activities, JSON_PRETTY_PRINT));
}

// Trier les activités : featured en premier, puis les autres
$sortedActivities = [];
$featuredActivities = [];
$normalActivities = [];

foreach ($activities as $activity_key => $activity) {
    if (isset($activity['featured']) && $activity['featured']) {
        $featuredActivities[$activity_key] = $activity;
    } else {
        $normalActivities[$activity_key] = $activity;
    }
}

// Fusionner : featured d'abord, puis les normales
$sortedActivities = array_merge($featuredActivities, $normalActivities);
?>
<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title><?php echo(SITE_NAME); ?></title>
    <link rel="stylesheet" href="css/style.css">
    <link rel="stylesheet" href="css/print.css">
    <link rel="stylesheet" href="css/cloud.css">
    <link rel="icon" href="favicon.png" />
</head>
<body>
    
    <?php include('include.header.php'); ?>

    <!-- Boutons de gestion des commandes -->
    <div class="order-controls">
        <button id="new-order-btn" class="btn btn-primary">📋 Nouvelle commande</button>
        <button id="resume-order-btn" class="btn btn-secondary">🔄 Reprendre une commande</button>
    </div>

    <!-- Panier de commande (côté droit) -->
    <div id="order-cart" class="order-cart collapsed">
        <div class="cart-header">
            <div class="cart-header-content">
                <span id="cart-icon" class="cart-icon">🛒</span>
                <h3 class="cart-title">Ma commande</h3>
            </div>
            <button id="toggle-cart" class="cart-toggle">◀</button>
        </div>
        
        <div class="cart-content">

            <div id="order-info" class="order-info" style="display: none;">
                <div class="order-reference">
                    <strong>📋 Référence:</strong> <span id="order-reference"></span>
                </div>
            </div>        

            <div id="customer-info" class="customer-info" style="display: none;">
                <p><strong>Adhérent:</strong> <span id="customer-name"></span></p>
                <p><strong>Email:</strong> <span id="customer-email"></span></p>
                <p><strong>Tél:</strong> <span id="customer-phone"></span></p>
            </div>
            
            <div id="cart-items" class="cart-items">
                <p class="empty-cart">Panier vide</p>
            </div>
            
            <div class="cart-actions">
                <div class="cart-summary">
                    <div class="cart-quantity">
                        <strong><span id="cart-count">0</span> photo(s)</strong>-<span id="cart-total">0</span>€
                    </div>
                </div>
                <div class="cart-buttons">
                    <button id="clear-cart" class="btn btn-clear-cart" style="display: none;" title="Vider le panier">
                        🗑️
                    </button>
                    <button id="validate-order" class="btn btn-primary" disabled>
                        ✅ Valider la commande
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Modal de confirmation pour vider le panier -->
    <div id="clear-cart-modal" class="modal">
        <div class="modal-content">
            <h2>⚠️ Confirmation</h2>
            <p>Êtes-vous sûr de vouloir vider complètement votre panier ?</p>
            <p><strong>Cette action supprimera toutes les photos sélectionnées.</strong></p>
            <div class="modal-actions">
                <button id="cancel-clear-cart" class="btn btn-secondary">❌ Annuler</button>
                <button id="confirm-clear-cart" class="btn btn-danger">🗑️ Vider le panier</button>
            </div>
        </div>
    </div>

    <!-- Modal nouvelle commande -->
    <div id="new-order-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <h2>📋 Nouvelle commande</h2>
            <form id="new-order-form">
                <div class="form-group">
                    <label for="customer_lastname">Nom *</label>
                    <input type="text" id="customer_lastname" name="lastname" required>
                </div>
                <div class="form-group">
                    <label for="customer_firstname">Prénom *</label>
                    <input type="text" id="customer_firstname" name="firstname" required>
                </div>
                <div class="form-group">
                    <label for="customer_phone">N° de téléphone *</label>
                    <input type="tel" id="customer_phone" name="phone" required>
                </div>
                <div class="form-group">
                    <label for="customer_email">Adresse email *</label>
                    <input type="email" id="customer_email" name="email" required>
                </div>
                <button type="submit" class="btn btn-primary">Créer la commande</button>
            </form>
        </div>
    </div>

    <!-- Modal reprendre commande -->
    <div id="resume-order-modal" class="modal">
        <div class="modal-content modal-large">
            <span class="close">&times;</span>
            <h2>🔄 Reprendre une commande</h2>
            
            <form id="resume-order-form">
                <div class="form-group">
                    <label for="order_reference">Référence de commande</label>
                    <input type="text" id="order_reference" placeholder="Ex: CMD202412241630" required>
                </div>
                <button type="submit" class="btn btn-primary">Charger la commande</button>
            </form>

            <?php if ($is_admin): ?>

            <div id="admin-only-section" style="display: none;">
                <hr style="margin: 2rem 0;">
                
                <div class="search-section">
                    <h3>🔍 Rechercher une commande</h3>
                    <div class="search-controls">
                        <input type="text" id="search-orders" placeholder="Rechercher par nom, prénom ou email..." class="search-input">
                        <button id="clear-search" class="btn btn-secondary">✖ Effacer</button>
                    </div>
                </div>
                
                <div class="orders-section">
                    <div class="orders-header">
                        <h3>Commandes récentes</h3>
                        <span id="orders-count" class="orders-count"></span>
                    </div>
                    <div id="recent-orders-list" class="orders-grid">
                        <p class="loading">Chargement...</p>
                    </div>
                </div>
            </div>

            <?php endif; ?>
        </div>
    </div>

    <!-- Modal de validation de commande -->
    <div id="validate-order-modal" class="modal">
        <div class="modal-content validate-modal">
            <h2>📋 Validation de la commande</h2>
            
            <div id="validation-confirm" class="validation-step active">
                <div class="validation-icon">⚠️</div>
                <h3>Confirmer la validation</h3>
                <p>Êtes-vous sûr de vouloir valider cette commande ?</p>
                <div class="order-summary">
                    <div class="summary-item">
                        <strong>Référence :</strong> <span id="confirm-reference"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Client :</strong> <span id="confirm-customer"></span>
                    </div>
                    <div class="summary-item">
                        <strong>Total :</strong> <span id="confirm-total"></span> photo(s)
                    </div>
                </div>
                <div class="modal-actions">
                    <button id="cancel-validation" class="btn btn-secondary">❌ Annuler</button>
                    <button id="confirm-validation" class="btn btn-primary">✅ Valider</button>
                </div>
            </div>
            
            <div id="validation-progress" class="validation-step">
                <div class="validation-icon">
                    <div class="spinner"></div>
                </div>
                <h3>Traitement en cours...</h3>
                <div class="progress-steps">
                    <div class="progress-step" id="step-save">
                        <div class="step-icon">💾</div>
                        <div class="step-text">Sauvegarde de la commande</div>
                        <div class="step-status">⏳</div>
                    </div>
                    <div class="progress-step" id="step-excel">
                        <div class="step-icon">📊</div>
                        <div class="step-text">Mise à jour du fichier Excel</div>
                        <div class="step-status">⏳</div>
                    </div>
                    <?php if (MAIL_FRONT): ?>
                    <div class="progress-step" id="step-email">
                        <div class="step-icon">📧</div>
                        <div class="step-text">Envoi de l'email de confirmation</div>
                        <div class="step-status">⏳</div>
                    </div>
                    <?php endif; ?>
                </div>
                <?php if (MAIL_FRONT): ?>
                <div class="progress-note">
                    <p>⏱️ Cette opération peut prendre quelques secondes en raison de l'envoi d'email...</p>
                </div>
                <?php else: ?>
                <div class="progress-note">
                    <p>⏱️ Cette opération peut prendre quelques secondes...</p>
                </div>
                <?php endif; ?>
            </div>
            
            <div id="validation-success" class="validation-step">
                <div class="validation-icon">✅</div>
                <h3>Commande validée avec succès !</h3>
                <div class="success-details">
                    <div class="success-item">
                        <strong>Référence :</strong> <span id="success-reference"></span>
                    </div>
                    <div class="success-item" id="success-update-info" style="display: none;">
                        <strong>Type :</strong> <span class="update-badge">🔄 Mise à jour (remplace la précédente)</span>
                    </div>
                    <?php if (MAIL_FRONT): ?>
                    <div class="success-item">
                        <strong>Email :</strong> <span id="success-email-status"></span>
                    </div>
                    <?php endif; ?>
                </div>
                <div class="modal-actions">
                    <button id="print-order" class="btn btn-secondary">🖨️ Imprimer</button>
                    <button id="close-validation" class="btn btn-primary" onclick="closeAllModalsAndResetSearch()">🎉 Parfait !</button>
                </div>
            </div>
            
            <div id="validation-error" class="validation-step">
                <div class="validation-icon">❌</div>
                <h3>Erreur lors de la validation</h3>
                <div class="error-message" id="error-details"></div>
                <div class="modal-actions">
                    <button id="retry-validation" class="btn btn-primary">🔄 Réessayer</button>
                    <button id="close-error" class="btn btn-secondary">Fermer</button>
                </div>
            </div>
        </div>
    </div>

    <main>
        <div class="container">
            <!-- Formulaire de connexion admin (modal) -->
            <?php if (!$is_admin): ?>
            <div id="admin-modal" class="modal">
                <div class="modal-content">
                    <span class="close">&times;</span>
                    <h2>Connexion Administrateur</h2>
                    <form method="POST">
                        <input type="password" name="password" placeholder="Mot de passe" required>
                        <button type="submit" name="admin_login" class="btn btn-primary">Se connecter</button>
                    </form>
                    <?php if (isset($error_message)): ?>
                        <p class="error"><?php echo htmlspecialchars($error_message); ?></p>
                    <?php endif; ?>
                </div>
            </div>
            <?php endif; ?>

            <!-- Section de recherche -->
            <section class="search-section">
                <div class="search-container">
                    <input type="text" id="search-input" placeholder="🔍 Rechercher une photo..." />
                </div>

                <!-- Section des filtres par tags (réduite par défaut) -->
                <div class="tags-filter-section" id="tags-filter-section">
                    <button class="tags-filter-toggle" id="tags-filter-toggle">
                        <span>
                            🏷️ Filtrer par tags
                            <span class="selected-tags-badge" id="selected-tags-badge" style="display: none;">0</span>
                        </span>
                        <span class="toggle-icon">▼</span>
                    </button>

                    <div class="tags-filter-content" id="tags-filter-content">
                        <div class="tags-cloud" id="tags-cloud">
                            <!-- Les tags seront générés dynamiquement -->
                        </div>

                        <div class="filter-actions">
                            <button class="clear-filters-btn" id="clear-tags-btn" disabled>
                                🔄 Effacer les filtres
                            </button>
                            <div class="active-filters-info" id="active-filters-info"></div>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Section des activités à la une -->
            <section id="featured-section" class="featured-section" style="display: none;">
                <h2>⭐ À la une</h2>
                <div id="featured-activities" class="featured-grid"></div>
            </section>

            <!-- Liste des activités -->
            <div class="activities-grid" id="activities-list">
                <?php foreach ($sortedActivities as $activity_key => $activity): ?>
                <?php 
                // Vérifications de sécurité
                $activity_name = htmlspecialchars($activity['name'] ?? 'Activité sans nom');
                $activity_description = htmlspecialchars($activity['description'] ?? '');
                $activity_tags = $activity['tags'] ?? [];
                $activity_photos = $activity['photos'] ?? [];
                $is_featured = isset($activity['featured']) && $activity['featured'];
                
                // Préparer les tags pour l'attribut data-tags
                $tags_string = !empty($activity_tags) ? htmlspecialchars(implode(' ', $activity_tags)) : '';
                ?>
                
                <div class="activity-card<?php echo $is_featured ? ' featured' : ''; ?>" 
                    data-activity="<?php echo htmlspecialchars($activity_key); ?>"
                    data-tags="<?php echo $tags_string; ?>">
                    
                    <div class="activity-image">
                        <?php if (!empty($activity_photos)): ?>
                            <img src="<?php echo GetImageUrl(htmlspecialchars($activity_key) . '/' . htmlspecialchars($activity_photos[0]),IMG_THUMBNAIL); ?>" 
                                alt="<?php echo $activity_name; ?>"
                                loading="lazy">
                        <?php else: ?>
                            <div class="no-image">Aucune image</div>
                        <?php endif; ?>
                    </div>
                    
                    <div class="activity-info">
                        <h3><?php echo $activity_name; ?></h3>
                        <p class="photo-count"><?php echo count($activity_photos); ?> photo(s)</p>
                        
                        <?php if (!empty($activity_tags)): ?>
                            <div class="tags">
                                <?php foreach ($activity_tags as $tag): ?>
                                    <span class="tag"><?php echo htmlspecialchars($tag); ?></span>
                                <?php endforeach; ?>
                            </div>
                        <?php endif; ?>
                        
                        <?php if (!empty($activity_description)): ?>
                            <p class="description"><?php echo $activity_description; ?></p>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
            </div>
        </div>
    </main>

    <?php include('order_print.php'); ?>

    <!-- Modal de galerie -->
    <div id="gallery-modal" class="modal gallery-modal">
        <div class="modal-content gallery-content">
            <span class="close gallery-close">&times;</span>
            <h2 id="gallery-title"></h2>
            <div class="gallery-grid" id="gallery-images"></div>
        </div>
    </div>

    <!-- Modal de visualisation d'image -->
    <div id="image-modal" class="modal image-modal">
        <div class="modal-content image-content">
            <span class="close image-close">&times;</span>
            <div class="image-container">
                <img id="modal-image" src="" alt="">
                
                <!-- Bouton d'ajout au panier dans la modal -->
                <button id="modal-add-cart" class="modal-add-cart-btn">
                    🛒 Ajouter au panier
                </button>
            </div>
        </div>
        
        <!-- Contrôles fixes en bas à droite -->
        <div class="image-controls-fixed">
            <button id="prev-photo">‹</button>
            <button id="zoom-out">-</button>
            <button id="zoom-reset">Reset</button>
            <button id="zoom-in">+</button>
            <button id="next-photo">›</button>
        </div>
    </div>
    <?php include('include.footer.php'); ?>
</body>
</html>