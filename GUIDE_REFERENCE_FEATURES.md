# GUIDE DE RÉFÉRENCE RAPIDE - FONCTIONNALITÉS

## STRUCTURE GÉNÉRALE

### Configuration Centrale
- **Fichier** : `config.php`
- **Sécurité** : `ADMIN_PASSWORD`, `SECURITY_KEY` (lignes 15-16)
- **Images** : `MAX_IMAGE_WIDTH/HEIGHT`, `JPEG_QUALITY` (lignes 24-26) 
- **Email** : `MAIL_FRONT`, `SMTP_*` (lignes 32-45)
- **Tarifs** : `$ACTIVITY_PRICING` (lignes 58-71)

### Fonctions Centrales
- **Fichier** : `functions.php`
- **URLs images** : `GetImageUrl()` (ligne 452)
- **Tarifs** : `getActivityPrice()` (ligne 171)
- **Nettoyage** : `cleanOldTempOrders()` (ligne 277)

### 📋 **CSV BOM-Safe (NOUVEAU)**
- **Fichier** : `classes/bom_safe_csv.php`
- **Fonctions** : `ensureSingleBOM()`, `writeBOMSafeCSV()`, `checkBOMStatus()`
- **Usage** : Évite l'accumulation de BOM UTF-8 dans les CSV

## FONCTIONNALITÉS PRINCIPALES

### 1. GALERIE PHOTOS
**Fichier principal** : `index.php`

| Feature | Localisation | Description |
|---------|--------------|-------------|
| Affichage galerie | `index.php:226-269` | Chargement activités + enrichissement URLs |
| Recherche/filtrage | `js/script.js` | Filtres tags + recherche textuelle |
| Modal zoom | `index.php:174-207` | Visualiseur avec navigation |
| Lazy loading | CSS/JS | Chargement progressif images |

### 2. SYSTÈME DE COMMANDE
**Fichiers** : `order_handler.php`, session PHP

| Action | Endpoint | Fonction | Description |
|--------|----------|----------|-------------|
| Créer commande | `POST action=create_order` | `order_handler.php:52-95` | Génère référence + session |
| Ajouter photo | `POST action=add_item` | `order_handler.php:102-146` | Ajout panier + persistance |
| Modifier quantité | `POST action=update_quantity` | `order_handler.php:148-177` | Mise à jour quantités |
| Supprimer item | `POST action=remove_item` | `order_handler.php:179-202` | Suppression du panier |
| Valider commande | `POST action=validate_order` | `order_handler.php:204-289` | JSON + CSV + email |
| Vider panier | `POST action=clear_cart` | `order_handler.php:444-466` | Reset complet |

### 3. GESTION DES IMAGES
**Fichier principal** : `image.php` + `image_core.php`

| Type | URL | Cache | Description |
|------|-----|-------|-------------|
| Original | `image.php?src=path&type=original` | Headers optimisés | Image non modifiée |
| Thumbnail | `image.php?src=path&type=thumbnail` | `photos/cache/thumbnails/` | 900x600px fixe |
| Resized | `image.php?src=path&type=resized&width=X&height=Y` | `photos/cache/resized/` | Dimensions variables |

**Watermarking** : Configuration dans `config.php:185-190`

### 4. INTERFACE ADMIN
**Fichier principal** : `admin.php`

| Section | Localisation | Fonctionnalité |
|---------|--------------|----------------|
| Connexion | `admin.php:17-39` | Auth par mot de passe |
| Scanner photos | `admin.php:122-145` | Détection automatique dossiers |
| Config watermark | `admin.php:89-121` | Paramètres + aperçu |
| Stats système | `admin.php:65-88` | Nombre photos, commandes |

### 5. GESTION COMMANDES
**Fichier principal** : `admin_orders.php` + classe `OrdersList`

| Fonctionnalité | Classe/Fichier | Description |
|----------------|----------------|-------------|
| Liste commandes | `OrdersList::loadOrdersData()` | Chargement avec filtres |
| Statistiques | `OrdersList::calculateStats()` | Calculs temps réel |
| Filtrage | `OrdersList::filterOrdersByStatus()` | Filtres par statut |
| Export CSV | `admin_orders_handler.php` | Multiples formats |

**⚠️ Statuts des commandes** (config.php:62) - **Workflow corrigé** :
- `temp` → `validated` → `paid` → `prepared` → `retrieved`
- **Interface admin** : Filtre 'unpaid' affiche uniquement ['temp', 'validated']
- **Commandes payées** : Masquées automatiquement de l'interface

### 6. SYSTÈME DE RÈGLEMENT
**Fichier** : `admin_orders_handler.php`

| Action | Fonction | Description |
|--------|----------|-------------|
| Traiter règlement | `processOrderPayment()` | Workflow complet |
| Export règlements | `exportToReglees()` | Vers `commandes_reglees.csv` |
| Export préparation | `exportToPreparer()` | Vers `commandes_a_preparer.csv` |
| Mise à jour statut | `updatePaymentStatus()` | `validated` → `paid` ✅ **CORRIGÉ** |

## DONNÉES ET STOCKAGE

### Fichiers JSON
| Fichier | Rôle | Structure |
|---------|------|-----------|
| `data/activities.json` | Configuration activités | Nom, photos, tags, prix |
| `commandes/REF_*.json` | Commandes validées | Client, items, statuts |
| `commandes/temp/REF.json` | Commandes temporaires | En cours de création |

### Fichiers CSV
| Fichier | Colonnes | Usage |
|---------|----------|-------|
| `commandes/commandes.csv` | 17 colonnes | Base principale |
| `commandes/commandes_reglees.csv` | 12 colonnes | Comptabilité |
| `commandes/commandes_a_preparer.csv` | 9 colonnes | Instructions imprimeur |

### Structure CSV principale
```csv
REF;Nom;Prenom;Email;Telephone;Date commande;Dossier;N de la photo;Quantite;Montant Total;Mode de paiement;Date encaissement souhaitee;Date encaissement;Date depot;Date de recuperation;Statut commande;Exported
```

## EXPORTS ET RAPPORTS

### Types d'exports (admin_orders.php)
| Export | Fonction | Fichier généré | Usage |
|--------|----------|----------------|-------|
| Résumé imprimeur ✅ | `exportPrinterSummary()` | `exports/resume_imprimeur_*.txt` | Commande imprimeur - **toutes commandes validées** |
| Guide séparation ✅ | `exportSeparationGuide()` | `exports/guide_separation_*.txt` | Tri par activité - **toutes commandes validées** |
| Listes picking ✅ | `generatePickingListsByActivityCSV()` | `exports/picking_lists_*.csv` | Distribution - **toutes commandes validées** |
| Export classique | `exportPreparationList()` | `exports/preparation_*.csv` | Méthode traditionnelle |

## SÉCURITÉ

### Authentification
- **Admin** : Mot de passe en dur (`config.php:15`)
- **Sessions** : Durée configurable (`ADMIN_SESSION_DURATION`)
- **Validation** : `is_admin()` dans `functions.php:44`

### Protection données
- **Sanitisation** : `htmlspecialchars()` sur tous affichages
- **CSV** : `sanitizeCSVValue()` contre injection formule
- **Chemins** : Suppression `../`, `.\`, `\\` dans URLs
- **Email** : `filter_var(..., FILTER_VALIDATE_EMAIL)`

## LOGGING
- **Classe** : `Logger` (singleton)
- **Fichiers** : `logs/gallery_YYYY-MM.log`
- **Niveaux** : ERROR, WARNING, INFO, DEBUG
- **Actions admin** : `adminAction()` pour traçabilité

## MAINTENANCE

### Scripts utilitaires
| Script | Usage |
|--------|-------|
| `diagnostic_tool.php` | Santé système |
| `sample_data.php` | Données de test |
| `update_script.php` | Migration |
| `install.php` | Installation |
| `validate_csv_system.php` | **NOUVEAU** - Diagnostic BOM UTF-8 |
| `fix_csv_bom.php` | **NOUVEAU** - Correction BOM multiples |

### Nettoyage automatique
- **Commandes temp** : >20h supprimées automatiquement
- **Logs** : Rotation si >taille max
- **Cache images** : Régénération si source modifiée

## WORKFLOW TYPIQUE

### Client public
1. **Navigation** → `index.php` charge galerie
2. **Sélection** → Ajout panier via `order_handler.php`
3. **Commande** → Modal saisie coordonnées
4. **Validation** → Sauvegarde JSON + CSV + email

### Admin
1. **Connexion** → `admin.php` 
2. **Gestion photos** → Scanner dossiers
3. **Commandes** → `admin_orders.php` liste + filtres
4. **Règlements** → Modal traitement
5. **Exports** → Génération fichiers CSV/TXT

### Préparation
1. **Export imprimeur** → Résumé consolidé
2. **Réception photos** → Guide séparation
3. **Distribution** → Listes picking détaillées
4. **Récupération** → Interface `admin_paid_orders.php`

## POINTS D'EXTENSION

### Nouveaux types produits
1. Ajouter dans `$ACTIVITY_PRICING` (config.php:58)
2. Modifier `pricing_type` activités
3. Adapter templates email si nécessaire

### Nouveaux statuts
1. Étendre `$ORDER_WORKFLOW` (config.php:109)
2. Ajouter libellés `$ORDER_STATUT_PRINT` (config.php:76)
3. Modifier logique filtrage

### Nouvelles fonctionnalités
1. **API** → Étendre `order_handler.php`
2. **Interface** → Modifier `admin_*.php` 
3. **Exports** → Nouvelles fonctions dans handlers
4. **Images** → Extensions `image_core.php`

## DÉPANNAGE RAPIDE

### Images ne s'affichent pas
- Vérifier permissions `photos/cache/` (755)
- Extension GD installée : `php -m | grep -i gd`
- Logs : `logs/gallery_*.log`

### Commandes perdues
- Session PHP active ?
- Permissions `commandes/` (755)
- Console navigateur (F12) erreurs AJAX

### Emails non envoyés
- Test config : `admin.php` → bouton test
- SMTP paramètres corrects
- `MAIL_FRONT = true` dans config

### ⚠️ Commandes payées toujours visibles
- **Symptôme** : Commandes paid visibles dans admin_orders.php
- **Diagnostic** : `php validate_csv_system.php`
- **Cause probable** : BOM UTF-8 multiples dans CSV
- **Solution** : `php fix_csv_bom.php`
- **Vérification** : `od -c commandes/commandes.csv | head -1`

### Exports incomplets
- **Symptôme** : Exports ne contiennent que commandes payées
- **Cause** : Lecture depuis mauvaise source (commandes_a_preparer.csv)
- **Solution** : Vérifier que exports lisent depuis commandes.csv principal

---

*Guide généré automatiquement - Référence rapide pour modifications système*