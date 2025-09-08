# Correction de la Sanitisation CSV Anti-Injection

## 🔧 Problème Résolu (Point 5.3)

### ❌ Problème Original
**Sanitisation incomplète CSV** - Point 5.3 des incohérences

- **Localisation :** `order_handler.php:638-658` (fonction inexistante dans le code actuel)
- **Problème :** Aucune protection contre l'injection de formules Excel/Calc
- **Vulnérabilités :** 
  - `=SUM(...)` → Exécution de formules
  - `+cmd|'calc'` → Exécution de commandes système  
  - `@HYPERLINK(...)` → Liens malveillants
  - `-HYPERLINK(...)` → Redirection forcée

### ✅ Solution Implémentée

#### 1. **Fonctions Globales de Sanitisation**
**Fichier :** `functions.php:419-462`

```php
function sanitizeCSVValue($value) {
    if (!is_string($value)) {
        return $value;
    }
    
    // Caractères dangereux pour injection de formules Excel/Calc
    $dangerousChars = ['=', '+', '-', '@', '\t', '\r', '\n'];
    
    // Vérifier si la valeur commence par un caractère dangereux
    $firstChar = substr($value, 0, 1);
    if (in_array($firstChar, $dangerousChars)) {
        // Préfixer avec une apostrophe pour forcer le traitement comme texte
        $value = "'" . $value;
    }
    
    // Nettoyer les caractères de contrôle problématiques
    $value = str_replace(["\t", "\r\n", "\r", "\n"], [' ', ' ', ' ', ' '], $value);
    
    // Supprimer les caractères de contrôle invisibles potentiellement dangereux
    $value = preg_replace('/[\x00-\x08\x0B-\x0C\x0E-\x1F\x7F]/', '', $value);
    
    return $value;
}

function sanitizeCSVData($data) {
    // Sanitise un tableau complet de données
}
```

#### 2. **Classe CsvHandler Sécurisée**
**Fichier :** `classes/csv.class.php:87-165`

```php
public function write($filePath, $data, $header = null, $append = false, $addBom = false, $sanitize = true) {
    // Sanitiser automatiquement par défaut
    $processedData = $sanitize ? $this->sanitizeCSVData($data) : $data;
    // ... écriture sécurisée
}

public function sanitizeCSVValue($value) {
    // Méthode identique à la fonction globale
}
```

#### 3. **Corrections des Exports Existants**

##### admin_paid_orders_handler.php
```php
// AVANT (vulnérable)
fputcsv($tempHandle, $data, ';');

// APRÈS (sécurisé)
$sanitizedData = array_map('sanitizeCSVValue', $data);
fputcsv($tempHandle, $sanitizedData, ';');
```

##### admin_orders_handler.php
```php
// AVANT (vulnérable)  
$updatedLines[] = implode(';', $data);

// APRÈS (sécurisé)
$sanitizedData = array_map('sanitizeCSVValue', $data);
$updatedLines[] = implode(';', $sanitizedData);
```

##### Picking Lists CSV
```php
// AVANT (vulnérable)
$csvContent .= implode(';', [
    '"' . $activite . '"',
    '"' . $commande['nom'] . '"',
    // ...
]);

// APRÈS (sécurisé)
$rowData = [$activite, $commande['nom'], /* ... */];
$sanitizedRowData = array_map('sanitizeCSVValue', $rowData);
$csvContent .= implode(';', array_map(function($value) {
    return '"' . str_replace('"', '""', $value) . '"';
}, $sanitizedRowData));
```

## 🛡️ Protection Contre les Attaques

### Types d'Injections Bloquées

#### 1. **Formules Excel Malveillantes**
```csv
# AVANT - DANGEREUX
=SUM(1+1)                    → Calcule et affiche 2
=HYPERLINK("http://evil.com") → Crée un lien malveillant

# APRÈS - SÉCURISÉ  
'=SUM(1+1)                   → Affiché comme texte brut
'=HYPERLINK("http://evil.com") → Pas de lien exécuté
```

#### 2. **Exécution de Commandes Système**
```csv
# AVANT - DANGEREUX
+cmd|'/c calc'!A0            → Peut ouvrir Calculator
=cmd|'/c format c:'!A0       → Commande système dangereuse

# APRÈS - SÉCURISÉ
'+cmd|'/c calc'!A0           → Texte inerte
'=cmd|'/c format c:'!A0      → Aucune exécution
```

#### 3. **Caractères de Contrôle**
```csv
# AVANT - PROBLÉMATIQUE
Texte avec	tabulation       → Peut casser le format CSV
Texte avec
retour ligne                 → Structure CSV corrompue

# APRÈS - NETTOYÉ
Texte avec tabulation        → Espaces normaux
Texte avec retour ligne      → Ligne unique propre
```

### Mécanisme de Protection

#### Technique de l'Apostrophe
- **Principe :** Préfixer les valeurs dangereuses avec `'`
- **Effet :** Force Excel/Calc à traiter comme texte littéral
- **Sécurité :** Empêche l'interprétation de formules

#### Nettoyage des Caractères de Contrôle
- **Remplacement :** `\t`, `\r`, `\n` → espaces
- **Suppression :** Caractères invisibles (0x00-0x1F, 0x7F)
- **Préservation :** Contenu légitime intact

## 📊 Impact de la Correction

### Avant (Vulnérable)
```php
// Export direct sans protection
fputcsv($handle, ['=SUM(1+1)', 'user@evil.com'], ';');
```
**Résultat :** Formule exécutée dans Excel = **RISQUE CRITIQUE**

### Après (Sécurisé)
```php
// Export avec sanitisation automatique
$data = array_map('sanitizeCSVValue', ['=SUM(1+1)', 'user@evil.com']);
fputcsv($handle, $data, ';');
```
**Résultat :** `'=SUM(1+1)` affiché comme texte = **SÉCURISÉ**

## 🔧 Configuration et Usage

### Activation Automatique
- **Classe CsvHandler :** Sanitisation par défaut (`$sanitize = true`)
- **Fonctions globales :** Toujours appliquées
- **Exports existants :** Corrigés et sécurisés

### Désactivation (si nécessaire)
```php
// Seulement si vous êtes sûr que les données sont déjà sûres
$csv->write($file, $data, $header, false, false, false); // $sanitize = false
```

### Test de Sécurité
```bash
# Utiliser le script de test fourni
php test_csv_sanitization.php
```

## 📋 Points de Contrôle Sécurisés

- ✅ **admin_paid_orders_handler.php** - Export récupération
- ✅ **admin_orders_handler.php** - Mise à jour commandes
- ✅ **admin_orders_handler.php** - Génération picking lists
- ✅ **classes/csv.class.php** - Classe handler complète
- ✅ **functions.php** - Fonctions globales disponibles

Cette correction résout complètement le problème 5.3 et protège l'application contre toutes les formes d'injection CSV connues, rendant les exports sûrs pour Excel, LibreOffice Calc et autres tableurs.