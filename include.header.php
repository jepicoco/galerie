<?php
/**
 * Header mutualisé du site de commande de photos
 * Version: 2.0
 * Navigation fixe avec indicateur d'état pour améliorer l'UX
 */

// Déterminer la page courante pour l'affichage conditionnel
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin_page = in_array($current_page, ['admin_orders.php', 'admin_galeries.php', 'admin_parameters.php', 'admin_paid_orders.php']);

// Titres des pages pour breadcrumb
$page_titles = [
    'admin_paid_orders.php' => 'Retraits',
    'admin_orders.php' => 'Commandes',
    'admin_galeries.php' => 'Gestion galerie',
    'admin_parameters.php' => 'Paramètres'
];
?>

<header>
    <div class="container">
        <h1><a href="/index.php"><?php echo(SITE_NAME); ?></a></h1>
        <nav>
            <?php if ($is_admin): ?>
                <!-- Breadcrumb pour pages admin -->
                <?php if ($is_admin_page): ?>
                    <div class="breadcrumb">
                        <span>Admin</span> › <span class="current-page"><?= $page_titles[$current_page] ?? 'Page' ?></span>
                    </div>
                <?php endif; ?>

                <!-- Navigation admin toujours complète avec indicateurs d'état -->
                <a href="admin_paid_orders.php"
                   class="btn btn-secondary btn-with-badge <?= $current_page === 'admin_paid_orders.php' ? 'active' : '' ?>"
                   data-badge-type="pickup"
                   <?= $current_page === 'admin_paid_orders.php' ? 'aria-current="page"' : '' ?>>
                    📦 Retraits
                    <span class="notification-badge" id="pickup-badge" style="display: none;"></span>
                </a>

                <a href="admin_orders.php"
                   class="btn btn-secondary btn-with-badge <?= $current_page === 'admin_orders.php' ? 'active' : '' ?>"
                   data-badge-type="orders"
                   <?= $current_page === 'admin_orders.php' ? 'aria-current="page"' : '' ?>>
                    📋 Commandes
                    <span class="notification-badge" id="orders-badge" style="display: none;"></span>
                </a>

                <a href="admin_galeries.php"
                   class="btn btn-secondary <?= $current_page === 'admin_galeries.php' ? 'active' : '' ?>"
                   <?= $current_page === 'admin_galeries.php' ? 'aria-current="page"' : '' ?>>
                    🖼️ Gestion de la galerie
                </a>

                <a href="admin_parameters.php"
                   class="btn btn-secondary <?= $current_page === 'admin_parameters.php' ? 'active' : '' ?>"
                   <?= $current_page === 'admin_parameters.php' ? 'aria-current="page"' : '' ?>>
                    ⚙️ Paramètres
                </a>

                <!-- Séparateur et boutons secondaires -->
                <div class="nav-separator"></div>
                <a href="index.php" class="btn btn-outline">← Retour à la galerie</a>
                <a href="?logout=1" class="btn btn-outline">🚪 Déconnexion</a>
            <?php else: ?>
                <a href="#" id="admin-login-btn" class="btn btn-outline">🚪 Connexion Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</header>

<?php if ($is_admin): ?>
<!-- Script pour les badges de notification des commandes -->
<script src="js/order-badges.js"></script>
<?php endif; ?>