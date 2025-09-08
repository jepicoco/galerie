# Processus Complet de Commande - Du Client à la Récupération

## Vue d'ensemble du processus

Ce document détaille le cycle de vie complet d'une commande photo, depuis l'arrivée de l'utilisateur sur le site jusqu'à la récupération physique des photos, en précisant tous les fichiers et fonctions impliquées.

---

## Phase 1 : Arrivée et Navigation de l'Utilisateur

### 1.1 Accès initial au site

**Fichier :** `index.php`
**Point d'entrée :** Chargement de la page principale

```php
// Initialisation système
define('GALLERY_ACCESS', true);
require_once 'config.php';
session_start();
require_once 'functions.php';
```

**Actions automatiques :**
- Vérification session admin : `is_admin()` dans `functions.php:44`
- Nettoyage commandes temporaires anciennes : `cleanOldTempOrders(COMMANDES_DIR)` dans `functions.php:277`
- Chargement activités : lecture `data/activities.json` dans `index.php:226-230`

### 1.2 Affichage de la galerie

**Traitement des activités :**
```php
// index.php:232-269 - Enrichissement des données
foreach ($activities as $activityKey => $activity) {
    $enrichedActivity = $activity;
    // Génération URLs pour chaque photo
    $photoPath = $activityKey . '/' . $photoName;
    $enrichedPhoto = [
        'name' => $photoName,
        'path' => $photoPath,
        'originalUrl' => GetImageUrl($photoPath, IMG_ORIGINAL),    // config.php:452
        'thumbPath' => GetImageUrl($photoPath, IMG_THUMBNAIL),     // config.php:452
        'resizedUrl' => GetImageUrl($photoPath, IMG_RESIZED)       // config.php:452
    ];
}
```

**Fonction clé :** `GetImageUrl()` dans `config.php:452-473`
- Génère les URLs sécurisées vers `image.php`
- Sanitise les chemins : supprime `../`, `.\`, `\\`

### 1.3 Navigation et recherche

**Frontend JavaScript :** `js/script.js`
- Filtrage par tags et recherche textuelle
- Lazy loading des images
- Gestion responsive de la grille

---

## Phase 2 : Création de Commande

### 2.1 Initiation d'une nouvelle commande

**Interface :** Modal "Nouvelle commande" dans `index.php:139-164`
**Handler JavaScript :** Soumission vers `order_handler.php`

**Appel API :**
```javascript
POST order_handler.php
action=create_order
lastname, firstname, phone, email
```

### 2.2 Traitement côté serveur

**Fichier :** `order_handler.php:52-95`
**Action :** `create_order`

```php
// Validation des données
if (empty($customerData['lastname']) || empty($customerData['firstname']) || 
    empty($customerData['phone']) || empty($customerData['email'])) {
    echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis']);
    break;
}

// Validation email
if (!filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Adresse email invalide']);
    break;
}

// Génération référence unique
$reference = 'CMD' . date('YmdHi') . rand(10, 99);

// Création structure commande
$order = [
    'reference' => $reference,
    'customer' => $customerData,
    'items' => [],
    'created_at' => date('Y-m-d H:i:s'),
    'status' => 'temp'
];

// Sauvegarde en session et fichier temporaire
$_SESSION['current_order'] = $order;
saveTempOrder($order, $ordersDir);  // order_handler.php:742
```

**Fonction support :** `saveTempOrder()` dans `order_handler.php:742-747`
```php
function saveTempOrder($order, $ordersDir) {
    $tempDir = ensureTempDirExists($ordersDir);           // order_handler.php:788
    $tempFile = $tempDir . $order['reference'] . '.json';
    $orderJson = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
    return file_put_contents($tempFile, $orderJson) !== false;
}
```

---

## Phase 3 : Sélection et Ajout de Photos

### 3.1 Affichage des images

**Système de cache intelligent :**
**Fichier :** `image.php:15-95`

```php
function serveImage() {
    // Récupération paramètres
    $src = $_GET['src'] ?? '';
    $type = $_GET['type'] ?? 'original';
    
    // Sécurisation chemin
    $src = str_replace(['../', '.\\', '\\'], '', $src);
    $originalPath = PHOTOS_DIR . $src;
    
    // Vérification cache
    if (!isCacheValid($cacheFile, $originalPath)) {
        // Génération selon type demandé
        switch ($type) {
            case 'thumbnail':
                $success = generateThumbnail($originalPath, $cacheFile);  // image_core.php
                break;
            case 'resized':
                $success = resizeImage($originalPath, $cacheFile);        // image_core.php
                break;
        }
    }
}
```

### 3.2 Ajout au panier

**Interface :** Boutons "🛒 Ajouter au panier" sur chaque photo
**Handler JavaScript :** Communication AJAX avec backend

**Appel API :**
```javascript
POST order_handler.php
action=add_item
photo_path, activity_key, photo_name
```

### 3.3 Traitement ajout côté serveur

**Fichier :** `order_handler.php:102-146`
**Action :** `add_item`

```php
// Vérification session active
if (!isset($_SESSION['current_order'])) {
    echo json_encode(['success' => false, 'error' => 'Aucune commande active']);
    break;
}

// Récupération données photo
$itemKey = $activityKey . '/' . $photoName;
$unitPrice = getActivityPrice($activityKey);  // functions.php:171

// Logique d'ajout/incrémentation
if (!isset($_SESSION['current_order']['items'][$itemKey])) {
    // Nouveau item
    $_SESSION['current_order']['items'][$itemKey] = [
        'photo_path' => GetImageUrl($activityKey . '/' . $photoName, IMG_THUMBNAIL),
        'activity_key' => $activityKey,
        'photo_name' => $photoName,
        'quantity' => 1,
        'unit_price' => $unitPrice,
        'total_price' => $unitPrice,
        'pricing_type' => getActivityTypeInfo($activityKey)['display_name']  // functions.php:211
    ];
} else {
    // Incrémentation existant
    $_SESSION['current_order']['items'][$itemKey]['quantity']++;
    $_SESSION['current_order']['items'][$itemKey]['total_price'] = 
        $_SESSION['current_order']['items'][$itemKey]['quantity'] * $unitPrice;
}

// Mise à jour fichier temporaire
saveTempOrder($_SESSION['current_order'], $ordersDir);
```

**Fonctions de tarification :**
- `getActivityPrice($activityKey)` dans `functions.php:171-196`
- `getActivityTypeInfo($activityKey)` dans `functions.php:211-223`

---

## Phase 4 : Gestion du Panier

### 4.1 Modification quantités

**Actions supportées :**
- `update_quantity` : `order_handler.php:148-177`
- `remove_item` : `order_handler.php:179-202`
- `clear_cart` : `order_handler.php:444-466`

### 4.2 Persistance des modifications

**Toutes les actions panier :**
1. Modifient `$_SESSION['current_order']`
2. Appellent `saveTempOrder()` pour persistance fichier
3. Retournent le nouveau `cart_count` au frontend

---

## Phase 5 : Validation de Commande

### 5.1 Processus de validation

**Interface :** Modal de validation dans `index.php:208-302`
**Appel API :**
```javascript
POST order_handler.php
action=validate_order
```

### 5.2 Traitement validation côté serveur

**Fichier :** `order_handler.php:204-289`
**Action :** `validate_order`

**Étapes de validation :**

#### 5.2.1 Vérifications préliminaires
```php
if (!isset($_SESSION['current_order']) || empty($_SESSION['current_order']['items'])) {
    echo json_encode(['success' => false, 'error' => 'Panier vide']);
    break;
}
```

#### 5.2.2 Préparation données
```php
$order = $_SESSION['current_order'];
$order['status'] = 'validated';
$order['validated_at'] = date('Y-m-d H:i:s');

$reference = $order['reference'];
$filename = $reference . '_' . 
    strtoupper($order['customer']['lastname']) . '_' . 
    date('YmdHi') . '.json';
```

#### 5.2.3 Gestion déduplication
```php
// Étape 1 : Vérifier commande existante
$isUpdate = checkOrderExistsInCSV($reference, $ordersDir . 'commandes.csv');  // order_handler.php:672

if ($isUpdate) {
    $logger->info("Mise à jour de la commande existante: $reference");
    removeOrderFromCSV($reference, $ordersDir . 'commandes.csv');    // order_handler.php:694
    removeOldOrderFile($reference, $ordersDir);                      // order_handler.php:830
}
```

#### 5.2.4 Sauvegarde définitive
```php
// Étape 2 : Créer fichier JSON
$orderFile = $ordersDir . $filename;
$orderJson = json_encode($order, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE);
file_put_contents($orderFile, $orderJson);

// Étape 3 : Mise à jour CSV
addOrderToCSV($order, $ordersDir);  // order_handler.php:558
```

#### 5.2.5 Notification email (optionnelle)
```php
if (MAIL_FRONT) {
    $emailHandler = new EmailHandler();                              // email_handler.php:11
    $emailSent = $emailHandler->sendOrderConfirmation($order, $isUpdate);  // email_handler.php:22
}
```

#### 5.2.6 Nettoyage final
```php
// Supprimer fichier temporaire
removeTempOrder($reference, $ordersDir);  // order_handler.php:818

// Vider session
unset($_SESSION['current_order']);

// Logging action
$logger->adminAction('Commande validée', [...]);  // classes/logger.class.php:171
```

---

## Phase 6 : Gestion Administrative

### 6.1 Interface de gestion commandes

**Fichier :** `admin_orders.php`
**Classe :** `OrdersList` dans `classes/orders.list.class.php`

```php
// Chargement données commandes
$ordersList = new OrdersList();
$ordersData = $ordersList->loadOrdersData('unpaid');  // Filtre commandes non payées
$stats = $ordersList->calculateStats($ordersData['orders']);
```

### 6.2 Traitement des règlements

**Interface :** Modal règlement dans `admin_orders.php:274-327`
**Handler :** `admin_orders_handler.php`

**Workflow règlement :**
1. Sélection mode paiement (espèces, chèque, carte, virement)
2. Saisie date règlement
3. Pour chèques : dates d'encaissement souhaitée/réelle
4. Mise à jour statuts dans CSV et JSON

### 6.3 Génération exports

**Fonctions d'export multiples :**
- **Liste imprimeur :** `exportPrinterSummary()` - Résumé optimisé commandes groupées
- **Préparation :** `generatePickingListsCSV()` - Listes de répartition par activité
- **Comptabilité :** `exportDailyPayments()` - Règlements du jour

---

## Phase 7 : Préparation Physique

### 7.1 Traitement des commandes réglées

**Interface :** `admin_paid_orders.php`
**Statuts gérés :**
- `paid` → `prepared` → `retrieved`

### 7.2 Workflow préparation

1. **Impression listes :** Via exports CSV optimisés imprimeur
2. **Tri physique :** Organisation par client/commande
3. **Vérification :** Contrôle cohérence quantités/références
4. **Emballage :** Préparation paquets individuels
5. **Mise à jour statut :** Passage en `prepared`

---

## Phase 8 : Récupération Client

### 8.1 Interface de retrait

**Gestion :** `admin_paid_orders.php`
**Workflow retrait :**

1. **Identification client :** Recherche par nom/référence
2. **Vérification commande :** Contrôle statut `prepared`
3. **Remise physique :** Distribution photos/supports
4. **Confirmation retrait :** 
   - Mise à jour statut → `retrieved`
   - Ajout `retrieval_date` dans données commande
   - Mise à jour CSV avec date récupération

### 8.2 Traçabilité complète

**Logging automatique :**
```php
$logger->adminAction('Commande récupérée', [
    'reference' => $reference,
    'customer' => $customer_name,
    'retrieval_date' => date('Y-m-d H:i:s'),
    'admin_user' => $_SESSION['admin_user'] ?? 'admin'
]);
```

---

## Phase 9 : Archivage et Suivi

### 9.1 Archivage automatique

**Fonction :** `archiveOldOrders()` dans système admin
**Critères :** Commandes > 3 mois avec statut `retrieved`
**Action :** Déplacement vers `archives/` avec conservation traçabilité

### 9.2 Rapports et statistiques

**Données disponibles :**
- Nombre commandes par période
- Chiffre d'affaires par type produit
- Temps moyen de traitement
- Taux de récupération clients

---

## Fichiers et Fonctions - Récapitulatif

### Fichiers principaux
| Fichier | Rôle | Phase |
|---------|------|-------|
| `index.php` | Interface publique galerie | 1-5 |
| `order_handler.php` | API gestion commandes | 2-5 |
| `image.php` | Serveur d'images avec cache | 3 |
| `admin_orders.php` | Interface admin commandes | 6 |
| `admin_paid_orders.php` | Gestion préparation/retrait | 7-8 |
| `email_handler.php` | Notifications email | 5 |

### Fonctions critiques
| Fonction | Fichier | Description |
|----------|---------|-------------|
| `GetImageUrl()` | config.php:452 | Génération URLs sécurisées images |
| `getActivityPrice()` | functions.php:171 | Calcul tarification différentielle |
| `saveTempOrder()` | order_handler.php:742 | Persistance commandes temporaires |
| `addOrderToCSV()` | order_handler.php:558 | Export CSV compatible Excel |
| `sendOrderConfirmation()` | email_handler.php:22 | Envoi emails confirmation |
| `cleanOldTempOrders()` | functions.php:277 | Nettoyage automatique |

### Structure de données
| Type | Localisation | Format |
|------|--------------|--------|
| Commandes temporaires | `commandes/temp/REF.json` | JSON |
| Commandes validées | `commandes/REF_NOM_DATE.json` | JSON |
| Export Excel | `commandes/commandes.csv` | CSV avec BOM UTF-8 |
| Logs système | `logs/gallery_YYYY-MM.log` | Texte structuré |
| Configuration | `data/activities.json` | JSON |

---

## Points de Contrôle Qualité

### Intégrité des données
- **Unicité références :** Vérification via `checkOrderExistsInCSV()`
- **Cohérence JSON/CSV :** Synchronisation automatique lors validation
- **Sauvegarde redondante :** Session + fichier temporaire + fichier définitif

### Sécurité
- **Sanitisation entrées :** `htmlspecialchars()`, `cleanCSVValue()`
- **Validation email :** `filter_var(..., FILTER_VALIDATE_EMAIL)`
- **Protection chemins :** Suppression `../`, `.\`, `\\` dans URLs images

### Performance
- **Cache images :** Génération à la demande avec persistance
- **Sessions optimisées :** Structure minimale, nettoyage automatique
- **Logs rotatifs :** Gestion automatique taille et ancienneté

Ce processus garantit une traçabilité complète et une sécurité maximale du cycle de vie des commandes, depuis la navigation initiale jusqu'à la récupération finale des photos par le client.