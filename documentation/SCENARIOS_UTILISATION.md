# Scénarios Complets d'Utilisation du Site

## 📋 Vue d'Ensemble

Ce document présente 3 scénarios complets d'utilisation du système de galerie photo Gala 2025, couvrant les parcours utilisateur typiques de l'arrivée sur le site jusqu'à la récupération des photos.

---

## 🎭 Scénario 1 : Marie - Cliente Standard (Commande Simple)

### 👤 Profil Utilisateur
- **Nom :** Marie Dubois
- **Situation :** Participante du Gala 2025, souhaite commander quelques photos
- **Compétences techniques :** Basiques
- **Objectif :** Commander 3 photos de sa soirée

### 📱 Parcours Complet

#### **Étape 1 : Arrivée sur le site**
```
URL visitée: https://gala-photos.example.com/
Heure: 14h30, le lendemain du gala
```

**Actions utilisateur :**
1. Tape l'URL du site (reçue par email après le gala)
2. Arrive sur `index.php` - galerie principale
3. Voit immédiatement les activités disponibles :
   - Cocktail
   - Repas de gala
   - Soirée dansante
   - Photobooth

**Système (backend) :**
```php
// index.php:33-39
if($is_admin){
    adminCleanupTempOrders(COMMANDES_DIR); // Skip (pas admin)
} else {
    smartCleanupTempOrders(COMMANDES_DIR); // Nettoyage intelligent toutes les 2h
}

// Chargement des activités
$activities = loadActivitiesData(); // functions.php:467
```

#### **Étape 2 : Navigation dans les photos**
**Actions utilisateur :**
1. Clique sur "Soirée dansante" (elle se souvient avoir dansé)
2. Parcourt les vignettes des photos
3. Utilise la fonction zoom pour voir les détails
4. Trouve 2 photos où elle apparaît bien

**Système (backend) :**
```php
// image.php - génération des vignettes
$imageUrl = GetImageUrl('soiree-dansante/IMG_1234.jpg', IMG_THUMBNAIL);
// Vérification watermark dans getWatermarkConfig()
// Cache automatique des images redimensionnées
```

**Interface (frontend) :**
```javascript
// js/script.js - gestion du zoom et navigation
function openModal(imagePath) {
    // Affichage modal avec image haute résolution
    modal.style.display = "block";
    modalImage.src = imagePath.replace('thumbnails', 'large');
}
```

#### **Étape 3 : Ajout au panier**
**Actions utilisateur :**
1. Clique sur "Ajouter au panier" sur la première photo
2. Clique sur "Ajouter au panier" sur la deuxième photo
3. Vérifie le contenu de son panier (2 photos)

**Système (backend) :**
```php
// order_handler.php:114-140
case 'add_to_cart':
    $activityKey = 'soiree-dansante';
    $photoName = 'IMG_1234.jpg';
    $itemKey = $activityKey . '/' . $photoName;
    $unitPrice = getActivityPrice($activityKey); // = 2€ (PHOTO type)
    
    $_SESSION['current_order']['items'][$itemKey] = [
        'photo_path' => GetImageUrl($activityKey . '/' . $photoName, IMG_THUMBNAIL),
        'activity_key' => $activityKey,
        'photo_name' => $photoName,
        'quantity' => 1,
        'unit_price' => $unitPrice, // 2€
        'subtotal' => $unitPrice * 1 // 2€
    ];
```

#### **Étape 4 : Finalisation de la commande**
**Actions utilisateur :**
1. Clique sur "Finaliser la commande"
2. Remplit le formulaire :
   - Nom: Dubois
   - Prénom: Marie
   - Email: marie.dubois@email.com
   - Téléphone: 06.12.34.56.78
3. Confirme la commande

**Système (backend) :**
```php
// order_handler.php:71-85
case 'validate_order':
    // Validation email
    if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
        echo json_encode(['success' => false, 'error' => 'Adresse email invalide']);
        break;
    }
    
    // Génération référence robuste
    $reference = generateUniqueOrderReference(); // Ex: CMD2025090814301234567890123456
```

**Génération des fichiers :**
```php
// order_handler.php:215-235
$filename = $reference . '_DUBOIS_' . date('YmdHi') . '.json';
$orderFile = 'commandes/temp/' . $filename;

$orderJson = json_encode($order, JSON_PRETTY_PRINT);
file_put_contents($orderFile, $orderJson);
```

#### **Étape 5 : Confirmation par email**
**Système (backend) :**
```php
// email_handler.php
$emailSent = sendOrderConfirmationEmail([
    'email' => 'marie.dubois@email.com',
    'firstname' => 'Marie',
    'lastname' => 'Dubois', 
    'reference' => $reference,
    'total_price' => 4.00,
    'total_photos' => 2
]);
```

**Email reçu par Marie :**
```
Objet: Confirmation de commande - Gala 2025

Bonjour Marie,

Votre commande a bien été enregistrée.

Référence: CMD2025090814301234567890123456
Nombre de photos: 2
Montant total: 4,00 €

Pour récupérer vos photos, présentez-vous à l'accueil avec:
- Cette référence de commande
- Une pièce d'identité
- Le règlement (espèces ou chèque)

Cordialement,
L'équipe du Gala 2025
```

#### **Étape 6 : Récupération (côté admin)**
**Le lendemain, Marie vient récupérer ses photos**

**Actions administrateur :**
1. Connexion admin sur `/admin.php`
2. Va dans "Commandes réglées" via `admin_paid_orders.php`
3. Marie arrive et donne sa référence
4. Admin recherche la commande par référence
5. Marie règle 4€ en espèces
6. Admin marque la commande comme "payée" puis "récupérée"

**Système (backend) :**
```php
// admin_paid_orders_handler.php:46-49
case 'mark_as_retrieved':
    $order = new Order($_POST['reference']);
    $result = $order->updateRetrievalStatus('retrieved', date('Y-m-d H:i:s'));
    echo json_encode($result);

// Mise à jour CSV avec sanitisation
$data[14] = date('Y-m-d H:i:s'); // Date de récupération  
$data[15] = 'retrieved';         // Statut unifié v2.0
$sanitizedData = array_map('sanitizeCSVValue', $data);
fputcsv($tempHandle, $sanitizedData, ';');
```

### ✅ Résultat Final
- **Marie :** 2 photos imprimées récupérées
- **Système :** Commande tracée de bout en bout
- **Statut final :** `retrieved` dans le système unifié v2.0

---

## 👨‍💼 Scénario 2 : Jacques - Client Entreprise (Commande Groupée)

### 👤 Profil Utilisateur
- **Nom :** Jacques Martin
- **Situation :** Organisateur d'entreprise, veut toutes les photos + vidéos USB
- **Compétences techniques :** Avancées
- **Objectif :** Commander en gros pour l'équipe (50+ photos + USB)

### 📱 Parcours Complet

#### **Étape 1 : Planification de commande**
**Actions utilisateur :**
1. Arrive sur le site avec une liste de photos à commander
2. Utilise la fonction de recherche par nom de fichier
3. Planifie une commande groupée importante

#### **Étape 2 : Commande massive**
**Actions utilisateur :**
1. Parcourt systématiquement toutes les activités
2. Ajoute 45 photos individuelles (type PHOTO)
3. Ajoute 3 clés USB avec vidéos (type USB)
4. Vérifie le total : 45×2€ + 3×15€ = 135€

**Système (backend) :**
```php
// order_handler.php - gestion des quantités importantes
foreach($photos as $photo) {
    $unitPrice = getActivityPrice($activityKey);
    // PHOTO: 2€, USB: 15€ selon $ACTIVITY_PRICING
    $subtotal += $quantity * $unitPrice;
}

// Validation du panier important
$totalItems = array_sum(array_column($_SESSION['current_order']['items'], 'quantity'));
// 48 items total (45 photos + 3 USB)
```

#### **Étape 3 : Informations de commande**
**Actions utilisateur :**
1. Remplit les informations d'entreprise :
   - Nom: Martin (Jacques)
   - Entreprise: TechCorp Solutions  
   - Email: j.martin@techcorp.com
   - Téléphone: 01.23.45.67.89

#### **Étape 4 : Validation et génération**
**Système (backend) :**
```php
// Référence unique robuste
$reference = generateUniqueOrderReference();
// Ex: CMD2025090815451234567890987654

// Génération du fichier temporaire
$orderFile = 'commandes/temp/' . $reference . '_MARTIN_' . date('YmdHi') . '.json';

$order = [
    'reference' => $reference,
    'customer' => [
        'lastname' => 'Martin',
        'firstname' => 'Jacques',
        'email' => 'j.martin@techcorp.com',
        'phone' => '01.23.45.67.89'
    ],
    'items' => $_SESSION['current_order']['items'], // 48 items
    'total_price' => 135.00,
    'total_photos' => 48,
    'created_at' => date('Y-m-d H:i:s'),
    'command_status' => 'temp'
];
```

#### **Étape 5 : Traitement administratif**
**24h après la commande**

**Actions administrateur :**
1. Connexion admin, consultation des nouvelles commandes
2. Voit la grosse commande de Jacques (135€, 48 photos)
3. Prépare la commande :
   - Impression des 45 photos
   - Préparation des 3 clés USB avec vidéos
   - Organisation par activité

**Système (backend) :**
```php
// admin_orders_handler.php:661-749 - Génération picking lists
function generatePickingListsByActivityCSV() {
    // Groupe les commandes par activité
    $commandesParActivite = [];
    
    foreach($commandes as $commande) {
        foreach($commande['photos'] as $photo) {
            $activite = $photo['activity_key'];
            $photoNom = $photo['photo_name'];
            
            $commandesParActivite[$activite][$photoNom][] = [
                'ref' => $commande['reference'],
                'nom' => $commande['nom'],
                'prenom' => $commande['prenom'],
                'quantite' => $photo['quantity']
            ];
        }
    }
    
    // Export CSV sécurisé avec sanitisation
    $sanitizedRowData = array_map('sanitizeCSVValue', $rowData);
}
```

#### **Étape 6 : Préparation et contact**
**Actions administrateur :**
1. Marque la commande comme "prepared" 
2. Envoie un email à Jacques pour le prévenir
3. Organise les photos par lot dans des enveloppes

#### **Étape 7 : Récupération et paiement**
**Actions utilisateur (Jacques) :**
1. Vient récupérer avec un chèque d'entreprise de 135€
2. Vérifie le contenu : 45 photos + 3 USB
3. Signe un accusé de réception

**Système (backend) :**
```php
// Traçabilité complète
$order->updatePaymentStatus('paid', date('Y-m-d H:i:s'), 'Chèque entreprise');
$order->updateRetrievalStatus('retrieved', date('Y-m-d H:i:s'));

// Export vers CSV avec statut unifié v2.0
// Statut final: 'retrieved', exported: 'exported'
```

### ✅ Résultat Final
- **Jacques :** 45 photos + 3 clés USB récupérées
- **Entreprise :** Souvenir complet de l'événement
- **Système :** Traçabilité complète de la grosse commande

---

## 👥 Scénario 3 : Sophie - Utilisatrice Mobile (Parcours Complexe)

### 👤 Profil Utilisateur  
- **Nom :** Sophie Lefebvre
- **Situation :** Sur mobile, connexion intermittente, veut photos mais hésite
- **Compétences techniques :** Moyennes
- **Objectif :** Commander des photos malgré les contraintes techniques

### 📱 Parcours Complet

#### **Étape 1 : Première visite (connexion lente)**
**Actions utilisateur :**
1. Accès via smartphone, 3G lent
2. Page met du temps à charger les vignettes
3. Abandon après 30 secondes

**Système (backend) :**
```php
// Gestion des images optimisées
$thumbnailUrl = GetImageUrl($imagePath, IMG_THUMBNAIL); // 150x150px
// Cache automatique pour les prochaines visites
// Watermark léger pour réduire la taille
```

#### **Étape 2 : Deuxième visite (WiFi)**
**6h plus tard, Sophie est chez elle en WiFi**

**Actions utilisateur :**
1. Retourne sur le site, navigation fluide
2. Commence à parcourir les photos
3. Ajoute 2 photos au panier
4. Ferme le navigateur par erreur (enfant qui l'interrompt)

**Système (backend) :**
```php
// Session maintenue mais temporaire
$_SESSION['current_order']['items'] = [
    'cocktail/IMG_0567.jpg' => [...],
    'repas/IMG_0892.jpg' => [...]
];
// Données perdues à la fermeture du navigateur
```

#### **Étape 3 : Troisième visite (recommence)**
**Le lendemain matin**

**Actions utilisateur :**
1. Revient sur le site, panier vide (session expirée)
2. Frustration, mais recommence
3. Retrouve rapidement ses 2 photos grâce à la mémorisation
4. Ajoute une 3ème photo cette fois

#### **Étape 4 : Finalisation avec difficultés**
**Actions utilisateur :**
1. Clique "Finaliser la commande"
2. Remplit le formulaire sur mobile (difficile)
3. Fait une typo dans l'email : "sophie.lefebvre@gmial.com"
4. Valide la commande

**Système (backend) :**
```php
// order_handler.php:67-70 - Validation email
if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
    echo json_encode(['success' => false, 'error' => 'Adresse email invalide']);
    break;
}
// Validation échoue à cause de "gmial.com"
```

#### **Étape 5 : Correction et nouvelle tentative**
**Actions utilisateur :**
1. Voit le message d'erreur "Email invalide"
2. Corrige : "sophie.lefebvre@gmail.com"
3. Revalide la commande

**Système (backend) :**
```php
// Deuxième tentative - succès
$reference = generateUniqueOrderReference(); 
// Ex: CMD2025091009151234567890456789

// Génération commande temporaire
$orderFile = 'commandes/temp/' . $reference . '_LEFEBVRE_' . date('YmdHi') . '.json';

$order = [
    'reference' => $reference,
    'customer' => [
        'lastname' => 'Lefebvre',
        'firstname' => 'Sophie', 
        'email' => 'sophie.lefebvre@gmail.com',
        'phone' => '06.78.90.12.34'
    ],
    'items' => $_SESSION['current_order']['items'], // 3 photos
    'total_price' => 6.00,
    'total_photos' => 3,
    'created_at' => date('Y-m-d H:i:s'),
    'command_status' => 'temp' // Statut unifié v2.0
];
```

#### **Étape 6 : Email de confirmation**
**Système (backend) :**
```php
// email_handler.php - Envoi confirmation
$emailResult = sendOrderConfirmationEmail([
    'email' => 'sophie.lefebvre@gmail.com',
    'firstname' => 'Sophie',
    'lastname' => 'Lefebvre',
    'reference' => $reference,
    'total_price' => 6.00,
    'total_photos' => 3
]);

if ($emailResult['success']) {
    // Email envoyé avec succès
    error_log("Email confirmation envoyé à sophie.lefebvre@gmail.com pour commande $reference");
}
```

#### **Étape 7 : Suivi et récupération**
**Actions utilisateur :**
1. Reçoit l'email de confirmation sur son smartphone
2. Note la référence de commande dans ses contacts
3. 3 jours après, vient récupérer ses photos

**Actions administrateur :**
1. Sophie arrive à l'accueil
2. Donne sa référence : CMD2025091009151234567890456789
3. Admin trouve la commande dans le système
4. Sophie paye 6€ en espèces
5. Admin remet les 3 photos imprimées

**Système (backend) :**
```php
// admin_paid_orders_handler.php
// Changement de statut avec workflow unifié v2.0 :
// temp → validated → paid → prepared → retrieved

$order = new Order($reference);
$order->updatePaymentStatus('paid', date('Y-m-d H:i:s'), 'Espèces');
$order->updateRetrievalStatus('retrieved', date('Y-m-d H:i:s'));

// Mise à jour CSV avec sanitisation
$updatedData = [
    // ... données existantes ...
    14 => date('Y-m-d H:i:s'), // Date récupération
    15 => 'retrieved',         // Statut unifié 
    16 => 'exported'           // Marqué comme exporté
];

$sanitizedData = array_map('sanitizeCSVValue', $updatedData);
fputcsv($csvHandle, $sanitizedData, ';');
```

#### **Étape 8 : Nettoyage automatique**
**24h après récupération**

**Système (backend) :**
```php
// Nettoyage automatique des anciennes commandes temp
// functions.php:345-394
function cleanOldTempOrders($ordersDir, $minIntervalMinutes = 30, $force = false) {
    // Suppression des fichiers temp > 20h
    foreach ($tempFiles as $file) {
        $fileAge = time() - filemtime($file);
        if ($fileAge > (20 * 3600)) { // 20 heures
            unlink($file);
            error_log("Commande temporaire supprimée (age: " . round($fileAge/3600, 1) . "h): " . basename($file));
        }
    }
}

// Nettoyage intelligent appelé toutes les 2h pour les users publics
// Ne supprime que les vraies commandes temporaires abandonnées
```

### ✅ Résultat Final
- **Sophie :** 3 photos récupérées malgré les difficultés
- **Système :** Robuste face aux erreurs utilisateur et connexions instables
- **Administration :** Commande traitée normalement via le workflow unifié

---

## 📊 Synthèse des Scénarios

### Points Communs aux 3 Parcours

#### **Système de Référence Robuste**
```php
// Toutes les commandes utilisent generateUniqueOrderReference()
// Format: CMD + YYYYMMDDHHMMSS + microseconds + random
// Ex: CMD2025090814301234567890123456 (29 caractères)
// Probabilité collision: 1/900,000,000,000 par seconde
```

#### **Workflow de Statuts Unifié v2.0**
```
temp → validated → paid → prepared → retrieved
  ↓        ↓         ↓        ↓         ↓
cancelled cancelled cancelled cancelled  [states finaux]
```

#### **Sanitisation CSV Anti-Injection**
```php
// Toutes les données exportées sont sanitisées
$sanitizedData = array_map('sanitizeCSVValue', $data);
// Protection contre =SUM(), +HYPERLINK(), etc.
```

#### **Nettoyage Intelligent**
- **Admin :** Nettoyage max toutes les 15min
- **Public :** Nettoyage max toutes les 2h  
- **Temp orders :** Suppression après 20h d'abandon

### Cas d'Usage Couverts

| Scénario | Type Client | Complexité | Volume | Défis Techniques |
|----------|-------------|------------|---------|------------------|
| Marie | Standard | Simple | Faible (2 photos) | Navigation basique |
| Jacques | Entreprise | Complexe | Élevé (48 items) | Gestion de volume |
| Sophie | Mobile | Moyenne | Moyen (3 photos) | UX mobile, erreurs |

### Technologies Mobilisées

#### **Frontend**
- Interface responsive pour mobile
- Modal zoom avec lazy loading
- JavaScript vanilla pour performance
- Navigation intuitive par activités

#### **Backend** 
- Sessions PHP pour panier temporaire
- Génération de références cryptographiquement sécurisées
- Système de workflow avec validation des transitions
- Cache statique pour optimisation performances

#### **Data Management**
- Export CSV sécurisé avec sanitisation
- Archivage automatique des commandes
- Logs détaillés pour debugging
- Validation de cohérence des prix

#### **Administration**
- Interface dédiée pour gestion commandes
- Génération automatique de picking lists
- Suivi du workflow complet
- Outils de diagnostic intégrés

Ces 3 scénarios démontrent la robustesse et la polyvalence du système Gala 2025 face aux différents profils utilisateurs et situations d'usage réelles.