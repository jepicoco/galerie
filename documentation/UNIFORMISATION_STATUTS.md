# Uniformisation des Statuts de Commandes

## ✅ Corrections Apportées

### 1. **Système de statuts unifié (v2.0)**

**Nouveau cycle de vie des commandes :**
```
temp → validated → paid → prepared → retrieved
  ↓        ↓         ↓        ↓         ↓
cancelled cancelled cancelled cancelled  [final]
```

**Fichier modifié :** `config.php:62`
```php
'COMMAND_STATUS' => ['temp', 'validated', 'paid', 'prepared', 'retrieved', 'cancelled']
```

### 2. **Mise à jour des libellés d'affichage**

**Fichier modifié :** `config.php:76-101`
```php
$ORDER_STATUT_PRINT = [
    'temp' => 'Temporaire',
    'validated' => 'Validée', 
    'paid' => 'Payée',
    'prepared' => 'Préparée',
    'retrieved' => 'Retirée',
    'cancelled' => 'Annulée',
    // ...
];
```

### 3. **Workflow et transitions contrôlées**

**Nouveau dans :** `config.php:109-117`
```php
$ORDER_WORKFLOW = [
    'temp' => ['validated', 'cancelled'],
    'validated' => ['paid', 'cancelled'], 
    'paid' => ['prepared', 'cancelled'],
    'prepared' => ['retrieved', 'cancelled'],
    'retrieved' => [], // Statut final
    'cancelled' => []  // Statut final
];
```

**Fonctions utilitaires ajoutées :**
- `isValidStatusTransition($currentStatus, $newStatus)` - `config.php:130`
- `getPossibleTransitions($currentStatus)` - `config.php:147`

### 4. **Correction de la logique de filtrage**

**Fichier modifié :** `classes/orders.list.class.php:164-181`

**Ancienne logique (incohérente) :**
```php
case 'temp':
    return ['unpaid', 'not_exported']; // ❌ Mélange de critères
```

**Nouvelle logique (cohérente) :**
```php
case 'temp':
    return ['temp']; // ✅ Statut direct
case 'unpaid':
    return ['temp', 'validated']; // ✅ Commandes non payées
```

### 5. **Correction des filtres de recherche**

**Fichier modifié :** `classes/orders.list.class.php:206-251`

**Nouveau mapping unifié :**
```php
case 'temp':
    $matches = ($commandStatus === 'temp');
case 'validated':
    $matches = ($commandStatus === 'validated');
case 'paid':
    $matches = ($commandStatus === 'paid');
case 'prepared':
    $matches = ($commandStatus === 'prepared');
case 'retrieved':
    $matches = ($commandStatus === 'retrieved');
case 'cancelled':
    $matches = ($commandStatus === 'cancelled');
```

### 6. **Correction interface commandes payées**

**Fichier modifié :** `admin_paid_orders_handler.php:82`

**Ancien problème :**
```php
if ($row['Statut paiement'] === 'paid' && $row['Statut retrait'] !== 'retrieved')
// ❌ Colonnes inexistantes dans le CSV réel
```

**Correction :**
```php
if ($commandStatus === 'paid' && empty($retrievalDate))
// ✅ Utilise les index CSV corrects (15 et 14)
```

### 7. **Correction processus de récupération**

**Fichier modifié :** `admin_paid_orders_handler.php:166-171`

**Correction du mapping CSV :**
```php
$data[14] = date('Y-m-d H:i:s'); // Date de recuperation
$data[15] = 'retrieved';         // Statut commande unifié
$data[16] = 'exported';          // Exported
```

## 🔧 Améliorations Techniques

### 1. **Constantes par défaut**
```php
define('DEFAULT_COMMAND_STATUS', 'temp');
define('DEFAULT_PAYMENT_STATUS', 'unpaid'); 
define('DEFAULT_RETRIEVAL_STATUS', 'not_retrieved');
```

### 2. **Validation des transitions**
Toutes les transitions de statuts peuvent maintenant être validées via `isValidStatusTransition()`.

### 3. **Documentation du workflow**
Le cycle de vie est clairement défini dans `$ORDER_WORKFLOW`.

## 📊 Impact des Corrections

### Problèmes résolus :
- ✅ **Incohérence critique** : Le statut `'temp'` est maintenant officiellement défini
- ✅ **Mapping CSV incorrect** : Utilisation des bons indices de colonnes
- ✅ **Filtres incohérents** : Logique de filtrage uniformisée
- ✅ **Transitions non contrôlées** : Workflow défini avec validations

### Compatibilité :
- 🔄 **Rétrocompatibilité maintenue** : Les anciens statuts sont dans `ORDER_STATUT['LEGACY']`
- 🔄 **Migration progressive** : Les filtres `'unpaid'` fonctionnent toujours
- 🔄 **Interfaces existantes** : Fonctionnent sans modification majeure

## 🚀 Bénéfices

1. **Cohérence système** : Un seul système de statuts dans toute l'application
2. **Workflow clair** : Cycle de vie de commande prévisible et contrôlé  
3. **Maintenance facilitée** : Plus de confusion entre statuts
4. **Extensibilité** : Facile d'ajouter de nouveaux statuts avec transitions
5. **Debugging amélioré** : Logs et états plus clairs

## 📋 Points de Vérification

### Statuts maintenant cohérents dans :
- [x] `config.php` - Définitions centrales
- [x] `order_handler.php` - Création et validation commandes
- [x] `classes/orders.list.class.php` - Filtrage et recherche
- [x] `admin_paid_orders_handler.php` - Interface récupération

### Workflow de test recommandé :
1. **Créer commande** → Statut `'temp'` ✓
2. **Valider commande** → Transition `'temp'` → `'validated'` ✓
3. **Règlement commande** → Transition `'validated'` → `'paid'` ✓
4. **Préparer commande** → Transition `'paid'` → `'prepared'` ✓
5. **Récupération** → Transition `'prepared'` → `'retrieved'` ✓

Cette uniformisation résout les **4 problèmes critiques** identifiés dans l'analyse initiale et établit une base solide pour l'évolution future du système.