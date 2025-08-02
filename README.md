# 📸 Galerie Photos Gala 2025

Une application web PHP moderne pour la gestion de galeries photos d'événements avec interface d'administration complète.

## ✨ Fonctionnalités

- 🖼️ **Galerie photos** organisée par activités
- 🛒 **Système de commandes** avec panier et validation
- 💳 **Gestion des paiements** (CB, chèque, espèces)
- 📧 **Notifications email** automatiques
- 👨‍💼 **Interface d'administration** complète
- 📊 **Statistiques** et exports
- 🔍 **Système de recherche** et filtrage
- 💾 **Sauvegarde automatique** des données

## 🚀 Installation Rapide

### Prérequis
- PHP 7.4+ (8.0+ recommandé)
- Extensions PHP : `json`, `gd`, `session`, `zip`, `fileinfo`
- Serveur web (Apache/Nginx) avec `mod_rewrite`

### Étapes

1. **Cloner le repository**
```bash
git clone https://github.com/VOTRE_USERNAME/galerie.git
cd galerie
```

2. **Configurer l'application**
```bash
cp config.example.php config.php
# Éditer config.php avec vos paramètres
```

3. **Définir les permissions**
```bash
chmod 755 data/ logs/ exports/ commandes/ photos/
```

4. **Accéder à l'application**
```
http://votre-domaine.com/galerie
```

## 📖 Documentation Complète

Voir [INSTALL.md](INSTALL.md) pour une installation détaillée.

## 🏗️ Architecture

### Classes Principales
- **`CsvHandler`** - Gestion générique des fichiers CSV
- **`Order`** - Gestion des commandes individuelles (hérite de CsvHandler)
- **`OrdersList`** - Gestion des listes de commandes (hérite de CsvHandler)
- **`Logger`** - Système de logging avec niveaux

### Autoloader
Le système utilise un autoloader PSR-4 compatible pour le chargement automatique des classes.

## 🔧 Configuration

### Sécurité
```php
// Changer obligatoirement dans config.php
define('ADMIN_PASSWORD', 'votre_mot_de_passe_securise');
define('SECURITY_KEY', 'votre_cle_unique_ici');
```

### Types d'activités et tarifs
```php
$ACTIVITY_PRICING = [
    'PHOTO' => [
        'price' => 2,
        'display_name' => 'Photo',
        'description' => 'Tirage photo classique'
    ],
    'USB' => [
        'price' => 15,
        'display_name' => 'Clé USB', 
        'description' => 'Support USB avec toutes les vidéos'
    ]
];
```

## 📊 Interface d'Administration

Accès : `/admin.php`

### Fonctionnalités Admin
- 📋 Gestion des commandes (en attente, payées, préparées)
- 🖼️ Upload et organisation des photos
- 📈 Statistiques et rapports
- 💰 Suivi des paiements
- 📄 Exports (CSV, listes de picking)
- ⚙️ Configuration du système

## 🔄 Workflow des Commandes

1. **Client** : Sélection photos → Panier → Validation
2. **Admin** : Réception → Traitement paiement → Export préparation
3. **Imprimeur** : Réception liste → Impression
4. **Récupération** : Marquage commande récupérée

## 🛠️ Développement

### Structure des données
- **Photos** : Organisées en dossiers par activité
- **Commandes** : Stockage JSON avec backup automatique
- **Configuration** : Fichiers PHP avec constantes
- **Logs** : Rotation mensuelle automatique

### Tests
```bash
php test_integrite_complete.php      # Test général
php test_fonctionnalites_admin.php   # Test interface admin
```

## 🔒 Sécurité

- ✅ Protection contre l'accès direct aux fichiers
- ✅ Validation et sanitisation des entrées
- ✅ Sessions sécurisées
- ✅ Protection CSRF sur les formulaires critiques
- ✅ Gestion des permissions fichiers

## 📝 Changelog

### Version 1.1.0 (Août 2025)
- ✨ Refactoring vers héritage CSV
- ✨ Système d'autoloader PSR-4
- ✨ Classes Order/OrdersList optimisées
- ✨ Performance améliorée
- 🐛 Corrections diverses

### Version 1.0.0 (Juillet 2025)
- 🎉 Version initiale
- 📸 Galerie photos par activités
- 🛒 Système de commandes
- 👨‍💼 Interface d'administration

## 🤝 Contribution

1. Fork le projet
2. Créer une branche feature (`git checkout -b feature/AmazingFeature`)
3. Commit les changements (`git commit -m 'Add AmazingFeature'`)
4. Push la branche (`git push origin feature/AmazingFeature`)
5. Ouvrir une Pull Request

## 📄 Licence

Distribué sous licence GPL-3.0. Voir `LICENSE` pour plus d'informations.

## 📞 Support - Todo


## 🙏 Remerciements

- Foyer Culturel de Sciez
- Communauté PHP
- Contributors du projet

---

⭐ **N'hésitez pas à mettre une étoile si ce projet vous a aidé !**
