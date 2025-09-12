# PROCÉDURE DE DÉPLOIEMENT INFOMANIAK

## Vue d'ensemble

Ce guide détaille la procédure complète pour publier le système de galerie photo Gala sur l'hébergement Infomaniak.

## Prérequis

### Compte Infomaniak
- Compte d'hébergement web actif
- Accès au Manager Infomaniak (manager.infomaniak.com)
- Nom de domaine configuré (optionnel)

### Fichiers locaux
- Code source complet du projet
- Base de données (si applicable)
- Fichiers de configuration personnalisés

## ÉTAPE 1 : Préparation des fichiers

### 1.1 Configuration de production

**Fichier : `config.php`**
```php
// Paramètres de sécurité - OBLIGATOIRE À CHANGER
define('ADMIN_PASSWORD', 'VOTRE-MOT-DE-PASSE-SECURISE');
define('SECURITY_KEY', 'votre-cle-unique-32-caracteres-min');

// Mode debug - DÉSACTIVER EN PRODUCTION
define('DEBUG_MODE', false);

// Email - Configuration SMTP Infomaniak
define('MAIL_FRONT', true);
define('SMTP_HOST', 'mail.infomaniak.com');
define('SMTP_PORT', 587);
define('SMTP_USERNAME', 'votre-email@votredomaine.com');
define('SMTP_PASSWORD', 'votre-mot-de-passe-email');
define('SMTP_ENCRYPTION', 'tls');
define('MAIL_FROM', 'votre-email@votredomaine.com');
define('MAIL_FROM_NAME', 'Gala Photos 2025');
```

### 1.2 Vérification des permissions

**Structure des dossiers à créer :**
```
/
├── photos/
│   └── cache/               # 755 (lecture/écriture serveur)
│       ├── thumbnails/      # 755
│       └── resized/         # 755
├── commandes/               # 755 (écriture CSV)
│   └── temp/               # 755
├── exports/                 # 755 (fichiers générés)
├── logs/                   # 755 (logs système)
├── data/                   # 755 (fichiers JSON)
└── photos/[activités]/     # 755 (dossiers photos)
```

### 1.3 Fichiers sensibles à exclure

**Créer `.gitignore` ou exclure manuellement :**
```gitignore
# Données sensibles
commandes/*.json
commandes/*.csv
logs/*.log
.claude/

# Fichiers temporaires
debug_*.php
test_*.php
validate_*.php
fix_*.php

# Configuration locale
config.local.php
```

## ÉTAPE 2 : Accès à l'hébergement Infomaniak

### 2.1 Connexion FTP/SFTP

**Méthode 1 : Via Manager Infomaniak**
1. Connexion sur https://manager.infomaniak.com
2. Produits → Hébergement Web
3. Sélectionner votre hébergement
4. Onglet "Fichiers" → "Gestionnaire de fichiers"

**Méthode 2 : Client FTP (FileZilla, WinSCP)**
```
Serveur : ftp.votre-site.com (ou SFTP)
Utilisateur : votre-login-ftp
Mot de passe : votre-mot-de-passe-ftp
Port : 21 (FTP) ou 22 (SFTP)
```

**Informations disponibles dans :**
Manager → Hébergement → Accès FTP/SSH

### 2.2 Répertoire de destination

**Racine web :** `/web/` ou `/public_html/`
- Tous les fichiers doivent être placés dans ce répertoire
- L'URL publique correspondra à : `https://votre-domaine.com/`

## ÉTAPE 3 : Upload des fichiers

### 3.1 Ordre d'upload recommandé

1. **Fichiers système de base**
   ```
   config.php
   functions.php
   index.php
   admin.php
   admin_orders.php
   ```

2. **Classes et librairies**
   ```
   classes/
   vendor/ (si Composer utilisé)
   ```

3. **Ressources statiques**
   ```
   css/
   js/
   images/
   ```

4. **Dossiers de données**
   ```
   data/
   photos/
   ```

5. **Création des répertoires vides**
   ```
   commandes/
   commandes/temp/
   exports/
   logs/
   photos/cache/
   photos/cache/thumbnails/
   photos/cache/resized/
   ```

### 3.2 Permissions à définir

**Via FTP ou Manager :**
- Dossiers : 755 (rwxr-xr-x)
- Fichiers PHP : 644 (rw-r--r--)
- Dossiers d'écriture : 755

**Vérification avec `admin.php` :**
Une fois uploadé, accéder à `https://votre-domaine.com/admin.php` pour vérifier les permissions.

## ÉTAPE 4 : Configuration serveur

### 4.1 Fichier .htaccess

**Créer à la racine :**
```apache
# Protection des fichiers sensibles
<Files "config.php">
    Order Allow,Deny
    Deny from all
</Files>

<Files "functions.php">
    Order Allow,Deny
    Deny from all
</Files>

<FilesMatch "\.(log|json)$">
    Order Allow,Deny
    Deny from all
</FilesMatch>

# Protection dossiers sensibles
<Directory "commandes">
    Order Allow,Deny
    Deny from all
</Directory>

<Directory "logs">
    Order Allow,Deny
    Deny from all
</Directory>

<Directory "classes">
    Order Allow,Deny
    Deny from all
</Directory>

# Redirection d'erreur
ErrorDocument 403 /index.php
ErrorDocument 404 /index.php

# Performance - Cache statique
<IfModule mod_expires.c>
    ExpiresActive On
    ExpiresByType image/jpeg "access plus 1 month"
    ExpiresByType image/png "access plus 1 month"
    ExpiresByType text/css "access plus 1 week"
    ExpiresByType application/javascript "access plus 1 week"
</IfModule>

# Compression GZIP
<IfModule mod_deflate.c>
    AddOutputFilterByType DEFLATE text/plain
    AddOutputFilterByType DEFLATE text/html
    AddOutputFilterByType DEFLATE text/xml
    AddOutputFilterByType DEFLATE text/css
    AddOutputFilterByType DEFLATE application/xml
    AddOutputFilterByType DEFLATE application/xhtml+xml
    AddOutputFilterByType DEFLATE application/rss+xml
    AddOutputFilterByType DEFLATE application/javascript
    AddOutputFilterByType DEFLATE application/x-javascript
</IfModule>
```

### 4.2 Configuration PHP (php.ini ou .user.ini)

**Créer `.user.ini` à la racine :**
```ini
# Limites d'upload pour photos
upload_max_filesize = 10M
post_max_size = 50M
max_execution_time = 300
memory_limit = 256M

# Sessions
session.gc_maxlifetime = 3600
session.cookie_httponly = 1
session.cookie_secure = 1

# Erreurs (production)
display_errors = Off
log_errors = On
error_log = logs/php_errors.log

# Sécurité
expose_php = Off
```

## ÉTAPE 5 : Test et vérification

### 5.1 Tests fonctionnels

**1. Page d'accueil**
- URL : `https://votre-domaine.com/`
- Vérifier : Chargement galerie, images, navigation

**2. Interface admin**
- URL : `https://votre-domaine.com/admin.php`
- Vérifier : Connexion, permissions, statistiques

**3. Système de commande**
- Tester : Ajout panier, validation commande, email

**4. Traitement des images**
- Vérifier : Thumbnails, redimensionnement, cache

### 5.2 Diagnostic système

**Outil intégré :**
```
https://votre-domaine.com/diagnostic_tool.php?diagnostic_key=system_check_2024
```

**Points de contrôle :**
- ✅ Extensions PHP (GD, JSON, Session)
- ✅ Permissions dossiers d'écriture
- ✅ Configuration email SMTP
- ✅ Génération thumbnails
- ✅ Écriture logs et CSV

### 5.3 Tests de performance

**PageSpeed Insights :**
https://pagespeed.web.dev/

**Points d'optimisation :**
- Cache images activé
- Compression GZIP
- Lazy loading images
- Minification CSS/JS

## ÉTAPE 6 : Configuration domaine (optionnel)

### 6.1 Domaine principal

**Dans Manager Infomaniak :**
1. Domaines → Votre domaine
2. DNS → Redirection
3. Configurer redirection vers hébergement

### 6.2 Sous-domaine

**Exemple pour `gala.monsite.com` :**
1. DNS → Sous-domaines
2. Créer : `gala` → Pointer vers hébergement
3. Attendre propagation DNS (24-48h)

### 6.3 HTTPS/SSL

**Activation SSL :**
1. Hébergement → SSL/TLS
2. Let's Encrypt : Gratuit et automatique
3. Forcer HTTPS : Redirection HTTP → HTTPS

## ÉTAPE 7 : Maintenance post-déploiement

### 7.1 Sauvegarde automatique

**Via Manager Infomaniak :**
- Sauvegardes quotidiennes incluses
- Restauration possible jusqu'à 30 jours

**Sauvegarde manuelle :**
```bash
# Export base de données (si applicable)
# Téléchargement FTP des fichiers critiques
```

### 7.2 Monitoring

**Logs à surveiller :**
- `logs/gallery_YYYY-MM.log` : Logs applicatifs
- `logs/php_errors.log` : Erreurs PHP
- Manager → Statistiques : Traffic, erreurs HTTP

### 7.3 Mises à jour

**Procédure de mise à jour :**
1. Test local des modifications
2. Upload des fichiers modifiés uniquement
3. Vérification fonctionnelle
4. Sauvegarde avant modification majeure

## ÉTAPE 8 : Configuration email avancée

### 8.1 Email dédié Infomaniak

**Création compte email :**
1. Manager → Adresses email
2. Créer : `gala@votre-domaine.com`
3. Configuration dans `config.php`

### 8.2 Templates d'email

**Personnalisation :**
```php
// Dans functions.php - fonction sendOrderConfirmation()
$emailTemplate = "
<h2>🎉 Confirmation de commande - Gala 2025</h2>
<p>Merci pour votre commande {REFERENCE}</p>
<p><strong>Site:</strong> https://votre-domaine.com</p>
";
```

## DÉPANNAGE COURANT

### Erreur 500 - Internal Server Error
- Vérifier `.htaccess` (renommer temporairement)
- Contrôler permissions fichiers/dossiers
- Consulter logs d'erreur

### Images ne s'affichent pas
- Permissions `photos/cache/` : 755
- Extension PHP GD installée
- Vérifier chemin `PHOTOS_DIR` dans config

### Emails non envoyés
- Tester config SMTP Infomaniak
- Vérifier authentification email
- Contrôler logs d'erreur

### Performance lente
- Activer cache `.htaccess`
- Optimiser images (compression)
- Vérifier ressources hébergement

## SÉCURITÉ PRODUCTION

### Checklist finale
- [ ] Mots de passe changés (admin, BDD)
- [ ] `DEBUG_MODE = false`
- [ ] `.htaccess` protège fichiers sensibles
- [ ] SSL/HTTPS activé et forcé
- [ ] Permissions restrictives
- [ ] Logs d'accès surveillés

## CONTACTS SUPPORT

**Infomaniak :**
- Support : https://www.infomaniak.com/support
- Documentation : https://docs.infomaniak.com
- Téléphone : +41 22 820 35 44

**Urgences :**
- Manager Infomaniak → Tickets de support
- Chat en ligne (heures ouvrables)

---

*Procédure de déploiement Infomaniak - Version Septembre 2025*
*Pour le système de galerie photo Gala - PHP/JSON/CSV*