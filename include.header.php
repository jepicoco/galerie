<?php
/**
 * Header mutualisé du site de commande de photos
 * Version: 1.0
 * Gère l'affichage conditionnel des boutons admin selon la page courante
 */

// Déterminer la page courante pour l'affichage conditionnel
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin_page = in_array($current_page, ['admin_orders.php', 'admin_galeries.php', 'admin_parameters.php', 'admin_paid_orders.php']);
?>

<header>
    <div class="container">
        <h1><a href="/index.php"><?php echo(SITE_NAME); ?></a></h1>
        <nav>
            <?php if ($is_admin): ?>
                <?php if (!$is_admin_page): ?>
                    <!-- Boutons admin standard si on n'est pas sur une page admin -->
                    <a href="admin_paid_orders.php" class="btn btn-secondary">📦 Retraits</a>
                    <a href="admin_orders.php" class="btn btn-secondary">📋 Commandes</a>
                    <a href="admin_galeries.php" class="btn btn-secondary">🖼️ Gestion de la galerie</a>
                    <a href="admin_parameters.php" class="btn btn-secondary">⚙️ Paramètres</a>
                <?php else: ?>
                    <!-- Navigation admin conditionnelle -->
                    <?php if ($current_page !== 'admin_paid_orders.php'): ?>
                        <a href="admin_paid_orders.php" class="btn btn-secondary">📦 Retraits</a>
                    <?php endif; ?>
                    
                    <?php if ($current_page !== 'admin_orders.php'): ?>
                        <a href="admin_orders.php" class="btn btn-secondary">📋 Commandes</a>
                    <?php endif; ?>
                    
                    <?php if ($current_page !== 'admin_galeries.php'): ?>
                        <a href="admin_galeries.php" class="btn btn-secondary">🖼️ Gestion de la galerie</a>
                    <?php endif; ?>
                    
                    <?php if ($current_page !== 'admin_parameters.php'): ?>
                        <a href="admin_parameters.php" class="btn btn-secondary">⚙️ Paramètres</a>
                    <?php endif; ?>

                    
                    <!-- Bouton retour à la galerie -->
                    <a href="index.php" class="btn btn-outline">← Retour à la galerie</a>
                <?php endif; ?>
                
                <a href="?logout=1" class="btn btn-outline">🚪 Déconnexion</a>
            <?php else: ?>
                <a href="#" id="admin-login-btn" class="btn btn-outline">🚪 Connexion Admin</a>
            <?php endif; ?>
        </nav>
    </div>
</header>