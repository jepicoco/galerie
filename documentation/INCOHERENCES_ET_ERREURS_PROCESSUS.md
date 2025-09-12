# Incohérences et Erreurs dans le Processus de Commande

## Analyse Complète des Problèmes Identifiés

Après analyse approfondie du code source et du processus de commande, voici la liste détaillée des incohérences et erreurs identifiées.

---

## ❌ 1. INCOHÉRENCES CRITIQUES DES STATUTS

### 1.1 Conflit dans les définitions de statuts

**Localisation :** `config.php:60-73` vs utilisation pratique

**Problème :**
```php
// config.php définit:
$ORDER_STATUT = [
    'COMMAND_STATUS' => ['pending', 'validated', 'cancelled'],
    // ...
];

// Mais order_handler.php:80 utilise:
$order['status'] = 'temp'; // STATUT NON DÉFINI !

// Et order_handler.php:211 utilise:
$order['status'] = 'validated'; // OK, mais incohérent avec 'temp'
```

**Impact :** Le statut `'temp'` n'existe pas dans `ORDER_STATUT['COMMAND_STATUS']`, causant des incohérences dans le système.

### 1.2 Mélange ancien/nouveau système de statuts

**Localisation :** `config.php:67-72`
```php
// Anciens statuts pour compatibilité
'LEGACY' => [
    'STATUT' => ['CREEE', 'VALIDEE', 'PRETE', 'PAYEE', 'ANNULEE', 'REMBOURSEE']
]
```

**Problème :** Le système maintient deux ensembles de statuts sans logique de migration claire.

---

## ❌ 2. ERREURS DE VARIABLES NON DÉFINIES

### 2.1 Variable $pricing_type non définie

**Localisation :** `functions.php:90`
```php
'pricing_type' => $pricing_type ?? DEFAULT_ACTIVITY_TYPE,
```

**Problème :** `$pricing_type` n'est définie nulle part dans la portée de `scanPhotosDirectories()`.
**Conséquence :** Utilise toujours `DEFAULT_ACTIVITY_TYPE` par défaut.

### 2.2 Référence unique potentiellement non unique

**Localisation :** `order_handler.php:73`
```php
$reference = 'CMD' . date('YmdHi') . rand(10, 99);
```

**Problème :** 
- Format `YmdHi` (année+mois+jour+heure+minute) = 12 caractères
- `rand(10, 99)` = seulement 90 possibilités 
- **Collision possible** si 2 commandes créées la même minute

**Risque :** Écrasement de commandes avec même référence.

---

## ❌ 3. PROBLÈMES DE GESTION DE SESSION

### 3.1 Session non initialisée dans functions.php

**Localisation :** `functions.php:44`
```php
function is_admin(){
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}
```

**Problème :** `functions.php` utilise `$_SESSION` mais n'appelle jamais `session_start()`.
**Conséquence :** Peut causer des erreurs si `functions.php` est inclus avant initialisation session.

### 3.2 Double nettoyage de commandes temporaires

**Localisation :** `index.php:33-35` et `functions.php:277`
```php
// index.php
if($is_admin){
    cleanOldTempOrders(COMMANDES_DIR);
}
```

**Problème :** Nettoyage fait à chaque chargement d'`index.php` par admin = inefficace.
**Impact :** Performance dégradée, logs pollués.

---

## ❌ 4. INCOHÉRENCES DANS LA VALIDATION

### 4.1 Validation email incomplète

**Localisation :** `order_handler.php:67`
```php
if (!filter_var($customerData['email'], FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Adresse email invalide']);
    break;
}
```

**Problème manquant :** Pas de vérification anti-spam, longueur email, domaines temporaires.

### 4.2 Validation téléphone absente

**Localisation :** `order_handler.php:61-65`
```php
if (empty($customerData['phone'])) {
    echo json_encode(['success' => false, 'error' => 'Tous les champs sont requis']);
}
```

**Problème :** Aucune validation du format téléphone (peut contenir n'importe quoi).

---

## ❌ 5. ERREURS DE SÉCURITÉ

### 5.1 Génération de référence prévisible

**Localisation :** `order_handler.php:73`
```php
$reference = 'CMD' . date('YmdHi') . rand(10, 99);
```

**Problème :** Format prévisible permet énumération des commandes.
**Solution manquante :** Utiliser `bin2hex(random_bytes(8))` ou hash cryptographique.

### 5.2 Pas de protection CSRF

**Localisation :** Toutes les actions POST dans `order_handler.php`

**Problème :** Aucune vérification de token CSRF.
**Risque :** Attaques cross-site request forgery.

### 5.3 Sanitisation incomplète CSV

**Localisation :** `order_handler.php:638-658`
```php
function cleanCSVValue($value) {
    // Remplacer les caractères problématiques pour CSV
    $cleaned = str_replace([';', "\n", "\r", "\t"], ['_', ' ', ' ', ' '], $cleaned);
}
```

**Problème :** Ne protège pas contre injection de formules Excel (`=`, `+`, `-`, `@`).

---

## ❌ 6. PROBLÈMES DE CONCURRENCE

### 6.1 Race condition sur fichier CSV

**Localisation :** `order_handler.php:616`
```php
$result = file_put_contents($excelFile, $content, FILE_APPEND | LOCK_EX);
```

**Problème :** Lock seulement sur écriture, pas sur lecture CSV lors de `checkOrderExistsInCSV()`.
**Risque :** Corruption données si validation simultanées.

### 6.2 Pas de verrous sur fichiers JSON

**Localisation :** `order_handler.php:235`
```php
if (file_put_contents($orderFile, $orderJson) === false) {
```

**Problème :** Pas de `LOCK_EX` sur écriture fichiers JSON commandes.

---

## ❌ 7. INCOHÉRENCES DANS LES DONNÉES

### 7.1 Structure items incohérente

**Localisation :** `order_handler.php:122-130`
```php
$_SESSION['current_order']['items'][$itemKey] = [
    'photo_path' => GetImageUrl(..., IMG_THUMBNAIL), // URL générée
    'activity_key' => $activityKey,                   // Clé brute
    'photo_name' => $photoName,                       // Nom brut
    // ...
];
```

**Problème :** Mélange d'URLs générées et de données brutes dans même structure.
**Impact :** Confusion lors de reconstruction URLs dans autres contextes.

### 7.2 Calcul prix incohérent

**Localisation :** `order_handler.php:118` + `functions.php:171`
```php
// order_handler.php
$unitPrice = getActivityPrice($activityKey);

// functions.php:184 - peut retourner type différent de l'activité
$pricingType = $activitiesData[$activityKey]['pricing_type'];
```

**Problème :** `getActivityPrice()` lit le `pricing_type` mais peut retourner prix de type différent si données corrompues.

---

## ❌ 8. ERREURS DE NOMMAGE ET TYPOS

### 8.1 Nom de variable incorrect

**Localisation :** `email_handler.php:727`
```php
$princingType = getActivityTypeInfo($activityKey); // TYPO: "princing" au lieu de "pricing"
return $princingType['pricing_type'] ?? 'PHOTO';
```

### 8.2 Noms de constantes incohérents

**Localisation :** `config.php:107-110`
```php
define('ORDERSLIST_TEMP', 0);      // Style UNDERSCORE
define('ORDERSLIST_UNPAID', 1);    // Style UNDERSCORE
// vs
define('MAX_IMAGE_WIDTH', 2048);   // Style UNDERSCORE standard
```

**Problème :** Mélange conventions de nommage.

---

## ❌ 9. GESTION D'ERREURS DÉFAILLANTE

### 9.1 Rollback incomplet sur échec

**Localisation :** `order_handler.php:244-248`
```php
if (!addOrderToCSV($order, $ordersDir)) {
    $logger->error("Impossible de mettre à jour le fichier CSV pour: $reference");
    unlink($orderFile); // Supprime JSON mais pas de nettoyage session
    echo json_encode(['success' => false, 'error' => 'Erreur lors de la mise à jour du fichier Excel']);
    break;
}
```

**Problème :** En cas d'échec CSV, supprime le JSON mais laisse session et logs incohérents.

### 9.2 Pas de transaction atomique

**Problème général :** Le processus validation n'est pas atomique :
1. Créer JSON ✓
2. Mettre à jour CSV ✗ → Échec
3. État inconsistant : JSON existe mais pas dans CSV

---

## ❌ 10. PROBLÈMES DE PERFORMANCE

### 10.1 Lecture répétée du même fichier

**Localisation :** `functions.php:171-178`
```php
function getActivityPrice($activityKey) {
    static $activitiesData = null;
    
    // Charger les données une seule fois (cache statique)
    if ($activitiesData === null) {
        $activitiesData = loadActivitiesConfiguration(); // Lit le fichier JSON
    }
```

**Problème partiel :** Cache statique OK, mais si appelé dans contextes différents, relit quand même.

### 10.2 Scan inutile des dossiers

**Localisation :** `index.php:37-41`
```php
// Si aucun fichier d'activités n'existe, le créer à partir des dossiers
if (empty($activities)) {
    $activities = scanPhotosDirectories(); // Scan file system
    file_put_contents($activities_file, json_encode($activities, JSON_PRETTY_PRINT));
}
```

**Problème :** Scan fait à chaque chargement si `activities.json` vide, pas de cache.

---

## 🔧 RECOMMANDATIONS DE CORRECTIONS

### Priorité 1 - Critiques (Sécurité/Intégrité)
1. **Fixer génération référence unique** : Utiliser `uniqid()` + hash cryptographique
2. **Ajouter protection CSRF** : Token dans toutes les actions POST
3. **Atomicité des transactions** : Système de rollback complet
4. **Corriger statuts** : Définir clairement `['temp', 'validated', 'paid', 'retrieved']`

### Priorité 2 - Fonctionnelles
5. **Validation complète** : Format téléphone, anti-spam email
6. **Verrous fichiers** : `LOCK_EX` sur tous les accès JSON
7. **Variables non définies** : Corriger `$pricing_type` dans `scanPhotosDirectories()`

### Priorité 3 - Qualité code
8. **Conventions nommage** : Uniformiser style variables/constantes
9. **Typos** : Corriger `$princingType` → `$pricingType`
10. **Performance** : Cache global activités, scan conditionnel

### Priorité 4 - Maintenance
11. **Logs structure** : Format uniforme, niveaux cohérents
12. **Documentation** : Commenter fonctions critiques
13. **Tests unitaires** : Couvrir fonctions validation/sauvegarde

---

## 📊 RÉSUMÉ PAR GRAVITÉ

| Niveau | Nombre | Impact |
|--------|---------|---------|
| 🔴 **Critique** | 4 | Sécurité, perte données possibles |
| 🟡 **Important** | 8 | Bugs fonctionnels, incohérences |
| 🟢 **Mineur** | 8 | Qualité code, maintenance |

**Total identifié :** 20 problèmes dans le processus de commande.

Ces problèmes nécessitent une attention immédiate pour assurer la robustesse et la sécurité du système de commande.