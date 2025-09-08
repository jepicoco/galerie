# 🚀 Guide d'Installation - Galerie Photos Gala

Guide complet pour installer et configurer l'application de galerie photos.

## 📋 Prérequis Système

### Serveur Web
- **Apache 2.4+** ou **Nginx 1.18+**
- Module `mod_rewrite` activé (Apache)
- Support des fichiers `.htaccess`

### PHP
- **Version** : PHP 7.4+ (PHP 8.0+ recommandé)
- **Extensions requises** :
  - `json` - Manipulation des données JSON
  - `gd` - Traitement des images
  - `session` - Gestion des sessions
  - `zip` - Création d'archives
  - `fileinfo` - Détection des types de fichiers
  - `mbstring` - Gestion des chaînes multi-octets

### Vérification des prérequis
```bash
php -v                          # Version PHP
php -m | grep -E "(json|gd|session|zip|fileinfo|mbstring)"  # Extensions
```

## 📦 Installation

### 1. Téléchargement

#### Via Git (recommandé)
```bash
git clone https://github.com/VOTRE_USERNAME/galerie.git
cd galerie
```

#### Via téléchargement direct
```bash
wget https://github.com/VOTRE_USERNAME/galerie/archive/main.zip
unzip main.zip
mv galerie-main galerie
cd galerie
```

### 2. Structure des dossiers

Créer les dossiers nécessaires :
```bash
mkdir -p data logs exports commandes/temp archives photos/cache/thumbnails photos/cache/resized
```

### 3. Permissions

Définir les permissions appropriées :
```bash
# Dossiers de données (lecture/écriture)
chmod 755 data/ logs/ exports/ commandes/ archives/ photos/
chmod 755 photos/cache/ photos/cache/thumbnails/ photos/cache/resized/

# Fichiers PHP (lecture seule)
chmod 644 *.php
chmod 644 classes/*.php

# Fichier de configuration (lecture seule)
chmod 600 config.php
```

## ⚙️ Configuration

### 1. Configuration principale

Copier et éditer le fichier de configuration :
```bash
cp config.example.php config.php
```

### 2. Paramètres essentiels

Éditer `config.php` :

```php
// SÉCURITÉ - OBLIGATOIRE À CHANGER
define('ADMIN_PASSWORD', 'votre_mot_de_passe_securise');
define('SECURITY_KEY', 'votre_cle_unique_32_caracteres_mini');

// MODE DEBUG (désactiver en production)
define('DEBUG_MODE', false);

// INFORMATIONS DU SITE
define('SITE_NAME', 'Galerie Gala 2025');
define('SITE_DESCRIPTION', 'Galerie photos du gala');
```

### 3. Configuration des tarifs

Adapter les tarifs selon vos besoins :
```php
$ACTIVITY_PRICING = [
    'PHOTO' => [
        'price' => 2,                      // Prix en euros
        'display_name' => 'Photo',
        'description' => 'Tirage photo classique'
    ],
    'USB' => [
        'price' => 15,
        'display_name' => 'Clé USB',
        'description' => 'Support USB avec vidéos'
    ]
];
```

### 4. Configuration email (optionnel)

Pour les notifications automatiques, configurer PHPMailer dans `email_handler.php`.

## 🌐 Configuration Serveur Web

### Apache (.htaccess)

Le fichier `.htaccess` est inclus avec les règles :
```apache
RewriteEngine On

# Protection des fichiers sensibles
<Files "config.php">
    Require all denied
</Files>

<Files "*.log">
    Require all denied
</Files>

# Redirection des erreurs
ErrorDocument 404 /galerie/index.php
```

### Nginx

Configuration Nginx équivalente :
```nginx
server {
    listen 80;
    server_name votre-domaine.com;
    root /var/www/galerie;
    index index.php;

    # Protection des fichiers
    location ~ ^/(data|logs|exports|commandes)/ {
        deny all;
        return 403;
    }

    location ~ \.php$ {
        fastcgi_pass php-fpm;
        fastcgi_param SCRIPT_FILENAME $document_root$fastcgi_script_name;
        include fastcgi_params;
    }
}
```

## 📁 Organisation des Photos

### Structure recommandée
```
photos/
├── Activité 1/
│   ├── photo1.jpg
│   ├── photo2.jpg
│   └── ...
├── Activité 2/
│   ├── photo1.jpg
│   └── ...
└── cache/
    ├── thumbnails/
    └── resized/
```

### Upload des photos

1. **Via interface admin** : `admin.php` → Gestion des galeries
2. **Via FTP/SFTP** : Upload direct dans `photos/nom-activite/`
3. **Scan automatique** : L'admin peut scanner les nouveaux dossiers

### Formats supportés
- **Images** : JPG, JPEG, PNG, GIF, WEBP
- **Taille max** : Configurable dans `config.php`
- **Optimisation** : Redimensionnement automatique

## 🔐 Sécurité

### 1. Mots de passe

```php
// Générer un mot de passe sécurisé
define('ADMIN_PASSWORD', password_hash('votre_mot_de_passe', PASSWORD_DEFAULT));

// Ou utiliser un hash existant
define('ADMIN_PASSWORD', '$2y$10$...');
```

### 2. Clé de sécurité

```php
// Générer une clé aléatoire
define('SECURITY_KEY', bin2hex(random_bytes(32)));
```

### 3. Protection des dossiers

Vérifier que les dossiers sensibles ne sont pas accessibles :
```bash
curl -I http://votre-domaine.com/galerie/data/
curl -I http://votre-domaine.com/galerie/logs/
# Doivent retourner 403 Forbidden
```

## 🧪 Tests et Validation

### 1. Tests automatiques

```bash
# Test de l'intégrité générale
php test_integrite_complete.php

# Test des fonctionnalités admin
php test_fonctionnalites_admin.php

# Test de compatibilité
php test_compatibility.php
```

### 2. Validation manuelle

1. **Page d'accueil** : `http://votre-domaine.com/galerie/`
2. **Interface admin** : `http://votre-domaine.com/galerie/admin.php`
3. **Diagnostic** : `http://votre-domaine.com/galerie/diagnostic_tool.php?diagnostic_key=system_check_2024`

### 3. Vérifications post-installation

- [ ] Page d'accueil accessible
- [ ] Connexion admin fonctionnelle
- [ ] Upload de photos possible
- [ ] Création de commandes
- [ ] Envoi d'emails (si configuré)
- [ ] Exports CSV
- [ ] Logs générés

## 🔄 Maintenance

### Sauvegardes

```bash
# Sauvegarde complète
tar -czf galerie_backup_$(date +%Y%m%d).tar.gz \
    --exclude='photos/cache' \
    --exclude='logs' \
    galerie/

# Sauvegarde données uniquement
tar -czf galerie_data_$(date +%Y%m%d).tar.gz \
    galerie/data/ \
    galerie/commandes/ \
    galerie/config.php
```

### Nettoyage automatique

```bash
# Nettoyer les anciennes commandes temporaires
find commandes/temp/ -name "*.json" -mtime +1 -delete

# Nettoyer les anciens logs
find logs/ -name "*.log" -mtime +30 -delete

# Optimiser le cache images
php -r "
$cache = glob('photos/cache/*/*');
foreach($cache as $file) {
    if(filemtime($file) < strtotime('-7 days')) unlink($file);
}
"
```

### Mises à jour

```bash
# Sauvegarder avant mise à jour
cp config.php config.php.backup

# Mettre à jour le code
git pull origin main

# Restaurer la configuration
cp config.php.backup config.php

# Exécuter les migrations si nécessaire
php update_script.php
```

## 🆘 Dépannage

### Problèmes courants

#### Erreur 500
```bash
# Vérifier les logs
tail -f /var/log/apache2/error.log
tail -f logs/gallery_$(date +%Y-%m).log
```

#### Permissions
```bash
# Rétablir les permissions
find . -type d -exec chmod 755 {} \;
find . -type f -exec chmod 644 {} \;
chmod 600 config.php
```

#### Images ne s'affichent pas
```bash
# Vérifier l'extension GD
php -m | grep gd

# Vérifier les permissions du cache
ls -la photos/cache/
```

### Logs de diagnostic

L'application génère des logs détaillés dans `logs/gallery_YYYY-MM.log`.

## 📞 Support

- **Documentation** : README.md
- **Issues** : GitHub Issues
- **Email** : [votre-support@example.com]

## ✅ Checklist Post-Installation

- [ ] Configuration sécurisée (mots de passe changés)
- [ ] Permissions correctes
- [ ] Tests automatiques passés
- [ ] Interface admin accessible
- [ ] Upload de photos fonctionnel
- [ ] Emails configurés (optionnel)
- [ ] Sauvegardes planifiées
- [ ] Monitoring configuré

---

🎉 **Installation terminée ! Votre galerie photos est prête à l'emploi.**