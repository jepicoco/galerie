# PROCESSUS COMPLET DE GESTION DES FICHIERS CSV DE COMMANDES

## Vue d'ensemble

Le système de gestion photo gala utilise plusieurs fichiers CSV pour tracer l'état des commandes depuis leur création jusqu'à leur récupération. Cette documentation détaille le processus complet de gestion des CSV de commandes.

## Architecture des fichiers CSV

### 1. Fichier principal : `commandes/commandes.csv`

**Rôle** : Fichier central contenant toutes les commandes (une ligne par photo commandée)

**Structure** :
```csv
REF;Nom;Prenom;Email;Telephone;Date commande;Dossier;N de la photo;Quantite;Montant Total;Mode de paiement;Date encaissement souhaitee;Date encaissement;Date depot;Date de recuperation;Statut commande;Exported
```

**Colonnes clés** :
- `REF` : Référence unique de la commande (format: CMD+timestamp+hash)
- `Statut commande` : temp → validated → paid → prepared → retrieved
- `Exported` : Marque si la ligne a été exportée vers les fichiers de traitement

**⚠️ Workflow des statuts corrigé** :
- **temp** : Commande temporaire (panier)
- **validated** : Commande confirmée mais non payée
- **paid** : Commande payée (masquée de l'interface admin)
- **prepared** : Commande préparée pour récupération
- **retrieved** : Commande récupérée par le client

### 2. Fichier de règlements : `commandes/commandes_reglees.csv`

**Rôle** : Liste consolidée des commandes réglées (une ligne par commande complète)

**Structure** :
```csv
Ref;Nom;Prenom;Email;Tel;Nb photos;Nb USB;Montant;Reglement;Date reglement;Date encaissement souhaitee;Date encaissement reelle
```

**Utilisation** : Export comptable, suivi des encaissements

### 3. Fichier de préparation : `commandes/commandes_a_preparer.csv`

**Rôle** : Liste détaillée pour la préparation des photos (une ligne par photo)

**Structure** :
```csv
Ref;Nom;Prenom;Email;Tel;Nom du dossier;Nom de la photo;Quantite;Date de preparation;Date de recuperation
```

**Utilisation** : Instructions imprimeur, listes de picking

## Processus de traitement des commandes

### Phase 1 : Création de commande

1. **Source** : Interface publique (`index.php`)
2. **Action** : Ajout dans `commandes/commandes.csv`
3. **Statut initial** : `unpaid`
4. **Format ligne** :
   ```csv
   CMD20250908170840288845160639;DUPONT;JEAN;jean@email.com;0123456789;2025-09-08 17:09:06;SOIREE;photo001.jpg;2;4;unpaid;;;;;validated;
   ```

### Phase 2 : Traitement du règlement

**Déclencheur** : Bouton "Régler" dans `admin_orders.php`

**Processus** (`admin_orders_handler.php` - fonction `processOrderPayment`) :

1. **Chargement de la commande**
   ```php
   $order = new Order($reference);
   $order->load();
   ```

2. **Export vers règlements** (`exportToReglees()`)
   - Création/ajout dans `commandes/commandes_reglees.csv`
   - BOM UTF-8 pour compatibilité Excel
   - Une ligne par commande complète

3. **Export vers préparation** (`exportToPreparer()`)
   - Création/ajout dans `commandes/commandes_a_preparer.csv`
   - Une ligne par photo commandée
   - Détermination automatique de l'activité

4. **Mise à jour statut** (`updatePaymentStatus()`)
   - Statut : `validated` → `paid` (corrigé - ancien bug: restait `validated`)
   - Ajout des données de règlement
   - **Impact** : Commandes payées masquées de l'interface admin

5. **Marquage export** (`markAsExported()`)
   - Colonne `Exported` = `exported`

### Phase 3 : Génération des fichiers de travail

**Actions disponibles** dans `admin_orders.php` :

#### A. Résumé imprimeur (✅ corrigé)
- **Fonction** : `exportPrinterSummary()`
- **Fichier** : `exports/resume_imprimeur_TIMESTAMP.txt`
- **Contenu** : Toutes commandes "validated" indépendamment du paiement
- **Source** : Lit maintenant depuis `commandes.csv` principal
- **Usage** : Commande à passer à l'imprimeur

#### B. Guide de séparation (✅ corrigé)
- **Fonction** : `exportSeparationGuide()`
- **Fichier** : `exports/guide_separation_TIMESTAMP.txt`
- **Contenu** : Instructions de tri par activité (commandes "validated")
- **Source** : Lit maintenant depuis `commandes.csv` principal
- **Usage** : Organisation des photos reçues

#### C. Listes de picking
- **Fonction** : `generatePickingListsByActivityCSV()`
- **Fichier** : `exports/picking_lists_detaillees_TIMESTAMP.csv`
- **Contenu** : Détail par commande et photo
- **Usage** : Distribution des photos aux clients

#### D. Export classique
- **Fonction** : `exportPreparationList()`
- **Fichier** : `exports/preparation_complete_TIMESTAMP.csv`
- **Contenu** : Liste complète pour impression
- **Usage** : Méthode traditionnelle

### Phase 4 : Téléchargement des fichiers

**Mécanisme JavaScript** (`admin_orders.js`) :

```javascript
function downloadFile(filePath) {
    const link = document.createElement('a');
    link.href = filePath;
    link.download = filePath.split('/').pop();
    link.style.display = 'none';
    document.body.appendChild(link);
    link.click();
    document.body.removeChild(link);
}
```

**Workflow** :
1. Génération côté serveur (PHP)
2. Retour JSON avec chemin fichier
3. Déclenchement téléchargement navigateur
4. Notification utilisateur

## Sécurisation des données CSV

### Protection contre injection formule

**Fonction** `sanitizeCSVValue()` :
- Neutralise les caractères `= + - @`
- Préfixe par apostrophe si nécessaire
- Appliquée à tous les exports

### Encodage UTF-8 et gestion BOM-safe

**⚠️ IMPORTANT** : Gestion sécurisée du BOM UTF-8 pour éviter l'accumulation.

**Classe utilitaire** `classes/bom_safe_csv.php` :
```php
// Assure exactement un BOM UTF-8
function ensureSingleBOM($content);

// Écriture sécurisée des fichiers CSV
function writeBOMSafeCSV($filePath, $content, $addBOM = true);

// Vérification état BOM d'un fichier
function checkBOMStatus($filePath);
```

**Problème résolu** :
- **Symptôme** : Accumulation de BOM multiples (32 détectés)
- **Cause** : Écriture répétée sans vérification
- **Solution** : Nettoyage et prévention via fonctions BOM-safe
- **Impact** : CSV correctement parsé, commandes payées masquées

**Usage moderne** :
```php
// Remplace l'ancien :
file_put_contents($file, "\xEF\xBB\xBF" . $content);

// Par le nouveau :
writeBOMSafeCSV($file, $content, true);
```

**Avantage** : Ouverture correcte dans Excel/LibreOffice sans corruption

## Gestion des erreurs et logs

### Logging des opérations

**Logger utilisé** dans `admin_orders_handler.php` :
```php
$logger->info('Paiement traité', [
    'reference' => $reference,
    'payment_mode' => $paymentMode,
    'payment_date' => $paymentDate
]);
```

### Validation des données

**Contrôles** :
- Vérification existence fichiers
- Validation format CSV
- Test permissions écriture
- Cohérence des références

### Gestion des erreurs

**Format de réponse** :
```php
return [
    'success' => false,
    'error' => 'Message d\'erreur explicite'
];
```

## Structure des dossiers

```
/commandes/
├── commandes.csv              # Fichier principal
├── commandes_reglees.csv      # Règlements consolidés
├── commandes_a_preparer.csv   # Instructions préparation
└── temp/                      # Commandes temporaires

/exports/
├── preparation_*.csv          # Exports classiques
├── picking_lists_*.csv        # Listes de picking
├── resume_imprimeur_*.txt     # Résumés imprimeur
└── guide_separation_*.txt     # Guides de tri

/archives/
└── commandes_archive_*.csv    # Archives anciennes
```

## Flux de données complet

```mermaid
graph TD
    A[Commande publique] --> B[commandes.csv - unpaid]
    B --> C[Admin: Bouton Régler]
    C --> D[processOrderPayment()]
    D --> E[commandes_reglees.csv]
    D --> F[commandes_a_preparer.csv]
    D --> G[commandes.csv - paid]
    
    F --> H[Résumé imprimeur]
    F --> I[Guide séparation]
    F --> J[Listes picking]
    F --> K[Export classique]
    
    H --> L[Téléchargement]
    I --> L
    J --> L
    K --> L
```

## Maintenance et optimisation

### Diagnostic et correction CSV

**⚠️ NOUVEAUX OUTILS DE MAINTENANCE** :

**Script de diagnostic** : `validate_csv_system.php`
- Vérification intégrité BOM UTF-8
- Détection accumulation BOM multiples
- Test parsing des statuts de commandes
- Validation cohérence données

**Script de correction** : `fix_csv_bom.php`
- Nettoyage automatique BOM multiples
- Préservation structure CSV
- Sauvegarde automatique avant correction
- Rapport détaillé des modifications

**Utilisation** :
```bash
# Diagnostic
php validate_csv_system.php

# Correction si nécessaire
php fix_csv_bom.php
```

### Archivage automatique

**Fonction** : `archiveOldOrders($days)`
- Archive commandes anciennes
- Garde fichier principal léger
- Sauvegarde dans `/archives/`

### Nettoyage temporaire

**Fonction** : `cleanOldTempOrders()`
- Supprime commandes temp > 20h
- Exécution automatique admin

### Contrôle cohérence

**Fonction** : `checkActivityCoherence()`
- Vérification données par activité
- Détection incohérences
- Rapport détaillé

## Bonnes pratiques

### Performance
- Lecture/écriture fichiers optimisée
- Traitement par lots si volume important
- Cache des données fréquemment utilisées

### Fiabilité
- Validation systématique des données
- Sauvegarde avant modification
- Transactions atomiques

### Sécurité
- Sanitisation toutes les entrées CSV
- Vérification permissions fichiers
- Logging des actions sensibles
- **Protection BOM-safe** : Évite corruption par accumulation

### Utilisabilité
- Messages d'erreur explicites
- Notifications temps réel
- Téléchargements automatiques

## Historique des problèmes résolus

### 🐛 Problème #1 : Commandes payées toujours visibles (Sept 2025)

**Symptôme** : Les commandes avec statut "paid" apparaissaient encore dans l'interface admin

**Cause racine** : Accumulation de 32 BOM UTF-8 dans le fichier CSV principal
- Le parsing CSV était corrompu
- Les mises à jour de statut échouaient silencieusement
- Le filtre 'unpaid' ne fonctionnait pas correctement

**Solution appliquée** :
1. **Diagnostic** : Détection BOM multiples avec `od -c commandes.csv`
2. **Nettoyage** : Suppression BOM multiples + ajout BOM unique
3. **Prévention** : Implémentation classe `bom_safe_csv.php`
4. **Correction** : Fix bug `updatePaymentStatus()` (INDEX[1]→INDEX[2])

**Fichiers modifiés** :
- `classes/bom_safe_csv.php` (créé)
- `admin_orders_handler.php` (BOM-safe + exports corrigés)
- `classes/order.class.php` (bug statut corrigé)
- `classes/csv.class.php` (méthode BOM-safe ajoutée)

**Résultat** : ✅ Commandes payées maintenant correctement masquées

### 🐛 Problème #2 : Exports incomplets (Sept 2025)

**Symptôme** : Les exports n'incluaient que les commandes payées, pas toutes les commandes validées

**Cause** : Fonctions d'export lisaient depuis `commandes_a_preparer.csv` au lieu du fichier principal

**Solution** :
- Modification de toutes les fonctions d'export pour lire depuis `commandes.csv`
- Filtre par statut 'validated' au lieu de lire fichier préparer
- Logique : `if ($commandStatus !== 'validated' || !empty($dateRecuperation))`

**Fonctions corrigées** :
- `exportPrinterSummary()`
- `exportSeparationGuide()`
- `generatePickingListsByActivityCSV()`
- `checkActivityCoherence()`

**Résultat** : ✅ Exports incluent maintenant toutes les commandes validées

### 📋 Filtrage des commandes - Matrice des statuts

| Statut CSV | Filtre 'unpaid' | Filtre 'paid' | Interface admin | Exports |
|------------|----------------|---------------|-----------------|---------|
| temp       | ✅ Visible      | ❌ Masqué     | ✅ Visible      | ❌ Exclu |
| validated  | ✅ Visible      | ❌ Masqué     | ✅ Visible      | ✅ Inclus |
| paid       | ❌ Masqué       | ✅ Visible    | ❌ Masqué       | ✅ Inclus |
| prepared   | ❌ Masqué       | ✅ Visible    | ❌ Masqué       | ❌ Exclu |
| retrieved  | ❌ Masqué       | ❌ Masqué     | ❌ Masqué       | ❌ Exclu |

---

*Documentation mise à jour - Dernière version : Septembre 2025*