<?php
/**
 * Header mutualisé du site de commande de photos
 * Version: 1.1
 * Gère l'affichage conditionnel des boutons admin selon la page courante
 * Ajoute des badges avec compteurs pour les boutons Retraits et Commandes
 */

// Déterminer la page courante pour l'affichage conditionnel
$current_page = basename($_SERVER['PHP_SELF']);
$is_admin_page = in_array($current_page, ['admin_orders.php', 'admin_galeries.php', 'admin_parameters.php', 'admin_paid_orders.php']);

// Compter les commandes pour les badges (seulement si admin)
$retrieval_count = 0;
$orders_count = 0;
if ($is_admin) {
    try {
        $ordersList = new OrdersList();
        $retrieval_count = $ordersList->countPendingRetrievals();
        $orders_count = $ordersList->countPendingPayments();

    } catch (Exception $e) {
        // En cas d'erreur, ne pas afficher de badge
        $retrieval_count = 0;
        $orders_count = 0;
    }
}
?>

<header>
    <div class="container">
        <h1><a href="/index.php"><?php echo(SITE_NAME); ?></a></h1>
        <nav>
            <?php if ($is_admin): ?>
                <?php if (!$is_admin_page): ?>
                    <!-- Boutons admin standard si on n'est pas sur une page admin -->
                    <a href="admin_paid_orders.php" class="btn btn-secondary">
                        📦 Retraits
                        <?php if ($retrieval_count > 0): ?>
                            <span class="badge"><?php echo $retrieval_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="admin_orders.php" class="btn btn-secondary">
                        📋 Commandes
                        <?php if ($orders_count > 0): ?>
                            <span class="badge"><?php echo $orders_count; ?></span>
                        <?php endif; ?>
                    </a>
                    <a href="admin_galeries.php" class="btn btn-secondary">🖼️ Gestion de la galerie</a>
                    <a href="admin_parameters.php" class="btn btn-secondary">⚙️ Paramètres</a>
                <?php else: ?>
                    <!-- Navigation admin conditionnelle -->
                    <?php if ($current_page !== 'admin_paid_orders.php'): ?>
                        <a href="admin_paid_orders.php" class="btn btn-secondary">
                            📦 Retraits
                            <?php if ($retrieval_count > 0): ?>
                                <span class="badge"><?php echo $retrieval_count; ?></span>
                            <?php endif; ?>
                        </a>
                    <?php endif; ?>
                    
                    <?php if ($current_page !== 'admin_orders.php'): ?>
                        <a href="admin_orders.php" class="btn btn-secondary">
                            📋 Commandes
                            <?php if ($orders_count > 0): ?>
                                <span class="badge"><?php echo $orders_count; ?></span>
                            <?php endif; ?>
                        </a>
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