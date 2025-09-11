<?php
/**
 * Classe pour la gestion des commandes individuelles
 * Gère les opérations sur une commande spécifique (création, mise à jour, export)
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}

require_once 'config.php';
require_once 'csv.class.php';

class Order extends CsvHandler {
    
    private $reference;
    private $data;
    private $csvFile;
    
    /**
     * Constructeur
     * @param string $reference Référence de la commande (optionnel pour nouvelle commande)
     */
    public function __construct($reference = null) {
        parent::__construct(';', '"', '\\');
        $this->reference = $reference;
        $this->data = [];
        $this->csvFile = 'commandes/commandes.csv';
    }
    
    /**
     * Génère une nouvelle référence de commande unique et robuste
     * @return string Nouvelle référence
     */
    public function generateReference() {
        $this->reference = generateUniqueOrderReference();
        return $this->reference;
    }
    
    /**
     * Charge les données de la commande depuis le CSV
     * @return bool Succès du chargement
     */
    public function load() {
        if (!$this->reference) {
            return false;
        }
        
        $csvData = $this->read($this->csvFile, true, 17);

        if ($csvData === false) {
            return false;
        }
        
        foreach ($csvData['data'] as $row) {
            if ($row['data'][0] === $this->reference) {
                // S'assurer qu'on a toutes les colonnes (padding si nécessaire)
                while (count($row['data']) < 18) {
                    $row['data'][] = '';
                }
                
                $this->data = [
                    'reference' => $row['data'][0],
                    'lastname' => $row['data'][1],
                    'firstname' => $row['data'][2],
                    'email' => $row['data'][3],
                    'phone' => $row['data'][4],
                    'order_date' => $row['data'][5],
                    'activity_key' => $row['data'][6],
                    'photo_name' => $row['data'][7],
                    'quantity' => intval($row['data'][8]),
                    'total_price' => floatval(str_replace(',', '.', $row['data'][9])),
                    'payment_mode' => $row['data'][10],
                    'desired_payment_date' => $row['data'][11],
                    'payment_date' => $row['data'][12],
                    'deposite_payment_date' => $row['data'][13],
                    'actual_retrieval_date' => $row['data'][14], // Date réelle de récupération
                    'command_status' => $row['data'][15],
                    'exported' => $row['data'][16] ?? '',
                    'expected_retrieval_date' => $row['data'][17] ?? '', // Nouvelle colonne: date prévue
                    'line_number' => $row['line_number']
                ];
                
                // Compatibilité: si pas de date prévue mais date réelle, utiliser la date réelle
                if (empty($this->data['expected_retrieval_date']) && !empty($this->data['actual_retrieval_date'])) {
                    $this->data['expected_retrieval_date'] = $this->data['actual_retrieval_date'];
                }
                
                // Pour compatibilité avec l'ancien code
                $this->data['retrieval_date'] = $this->data['expected_retrieval_date'];
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Extrait la date de création de la référence de commande
     * @return string Date formatée Y-m-d H:i:s
     */
    public function getCreationDate() {
        if (!$this->reference) {
            return date('Y-m-d H:i:s');
        }
        
        if (preg_match('/CMD(\d{8})(\d{6})/', $this->reference, $matches)) {
            $date = $matches[1]; // 20250725
            $time = $matches[2]; // 161243
            
            $year = substr($date, 0, 4);
            $month = substr($date, 4, 2);
            $day = substr($date, 6, 2);
            $hour = substr($time, 0, 2);
            $minute = substr($time, 2, 2);
            $second = substr($time, 4, 2);
            
            return "$year-$month-$day $hour:$minute:$second";
        }
        
        return date('Y-m-d H:i:s');
    }
    
    /**
     * Met à jour le statut de paiement de la commande
     * @param array $paymentData Données de paiement
     * @return array Résultat de l'opération
     */
    public function updatePaymentStatus($paymentData) {
        global $ORDER_STATUT;
        
        if (!$this->reference) {
            return ['success' => false, 'error' => 'Référence de commande manquante'];
        }
        
        $payMode = $paymentData['payment_mode'] ?? '';
        $payDesiredDate = $paymentData['desired_deposit_date'] ?? '';
        $payDate = date('Y-m-d H:i:s');
        $actualDepositDate = $paymentData['actual_deposit_date'] ?? '';
        
        // Si ce n'est pas un chèque, les dates sont identiques
        if ($payMode !== $ORDER_STATUT['PAYMENT_METHODS'][1]) {
            $payDesiredDate = $payDate;
            $actualDepositDate = $payDate;
        }
        
        $updates = [
            10 => $payMode ?? '',
            11 => $payDesiredDate ?? '',
            12 => $payDate ?? '',
            13 => $actualDepositDate ?? '',
            15 => $ORDER_STATUT['COMMAND_STATUS'][2] // paid (au lieu de validated)
        ];
        
        return $this->updateByValue(
            $this->csvFile,
            0, // Colonne de référence
            $this->reference,
            $updates
        );
    }
    
    /**
     * Marque la commande comme exportée
     * @return array Résultat de l'opération
     */
    public function markAsExported() {
        if (!$this->reference) {
            return ['success' => false, 'error' => 'Référence de commande manquante'];
        }
        
        $updates = [16 => 'exported'];
        
        return $this->updateByValue(
            $this->csvFile,
            0, // Colonne de référence
            $this->reference,
            $updates
        );
    }
    
    /**
     * Met à jour le statut de récupération
     * @param string $status Nouveau statut ('retrieved' ou 'not_retrieved')
     * @param string $actualRetrievalDate Date réelle de récupération (optionnel)
     * @return array Résultat de l'opération
     */
    public function updateRetrievalStatus($status, $actualRetrievalDate = '') {
        if (!$this->reference) {
            return ['success' => false, 'error' => 'Référence de commande manquante'];
        }
        
        $updates = [15 => $status]; // Statut de commande
        if ($status === 'retrieved' && $actualRetrievalDate) {
            $updates[14] = $actualRetrievalDate; // Date réelle de récupération (colonne 14)
        }
        
        return $this->updateByValue(
            $this->csvFile,
            0, // Colonne de référence
            $this->reference,
            $updates
        );
    }
    
    /**
     * Met à jour la date prévue de récupération
     * @param string $expectedDate Date prévue de récupération
     * @return array Résultat de l'opération
     */
    public function updateExpectedRetrievalDate($expectedDate) {
        if (!$this->reference) {
            return ['success' => false, 'error' => 'Référence de commande manquante'];
        }
        
        $updates = [17 => $expectedDate]; // Nouvelle colonne: date prévue
        
        return $this->updateByValue(
            $this->csvFile,
            0, // Colonne de référence
            $this->reference,
            $updates
        );
    }
    
    /**
     * Exporte la commande vers le fichier des commandes réglées
     * @param string $paymentMode Mode de paiement
     * @param string $paymentDate Date de paiement
     * @param string $desiredDepositDate Date encaissement souhaitée
     * @param string $actualDepositDate Date encaissement réelle
     * @return array Résultat de l'opération
     */
    public function exportToReglees($paymentMode, $paymentDate, $desiredDepositDate, $actualDepositDate) {
        if (!$this->data) {
            return ['success' => false, 'error' => 'Données de commande non chargées'];
        }
        
        $regleesFile = 'commandes/commandes_reglees.csv';
        $header = [
            'Ref', 'Nom', 'Prenom', 'Email', 'Tel', 'Nb photos', 'Nb USB', 
            'Montant', 'Reglement', 'Date reglement', 'Date encaissement souhaitee', 
            'Date encaissement reelle'
        ];
        
        // Calculer le nombre d'USB (pour l'instant 0)
        $nbUSB = 0;
        
        $rowData = [
            $this->data['reference'],
            $this->data['lastname'],
            $this->data['firstname'],
            $this->data['email'],
            $this->data['phone'],
            $this->data['quantity'],
            $nbUSB,
            $this->data['total_price'],
            $paymentMode,
            $paymentDate,
            $desiredDepositDate,
            $actualDepositDate
        ];
        
        return $this->appendRow($regleesFile, $rowData, $header, true) ?
            ['success' => true] :
            ['success' => false, 'error' => 'Impossible d\'écrire dans le fichier des commandes réglées'];
    }
    
    /**
     * Exporte la commande vers le fichier de préparation
     * @return array Résultat de l'opération
     */
    public function exportToPreparer() {
        if (!$this->data) {
            return ['success' => false, 'error' => 'Données de commande non chargées'];
        }
        
        $preparerFile = 'commandes/commandes_a_preparer.csv';
        $header = [
            'Ref', 'Nom', 'Prenom', 'Email', 'Tel', 'Nom du dossier', 
            'Nom de la photo', 'Quantite', 'Date de preparation', 'Date de recuperation'
        ];
        
        $activityInfo = getActivityTypeInfo($this->data['activity_key']);
        $activityName = $activityInfo['display_name'] ?? $this->data['activity_key'];
        
        $rowData = [
            $this->data['reference'],
            $this->data['lastname'],
            $this->data['firstname'],
            $this->data['email'],
            $this->data['phone'],
            $activityName,
            $this->data['photo_name'],
            $this->data['quantity'],
            '', // Date de préparation (vide)
            $this->data['retrieval_date'] ?? ''
        ];
        
        return $this->appendRow($preparerFile, $rowData, $header, true) ?
            ['success' => true] :
            ['success' => false, 'error' => 'Impossible d\'écrire dans le fichier de préparation'];
    }
    
    /**
     * Calcule le prix unitaire pour l'activité de la commande
     * @return float Prix unitaire
     */
    public function getUnitPrice() {
        if (!$this->data || !isset($this->data['activity_key'])) {
            return 0.0;
        }
        
        return getActivityPrice($this->data['activity_key']);
    }
    
    /**
     * Calcule le sous-total de la commande
     * @return float Sous-total
     */
    public function getSubtotal() {
        if (!$this->data) {
            return 0.0;
        }
        
        return $this->data['quantity'] * $this->getUnitPrice();
    }
    
    /**
     * Retourne toutes les données de la commande
     * @return array Données complètes
     */
    public function getData() {
        return $this->data;
    }
    
    /**
     * Retourne la référence de la commande
     * @return string|null Référence
     */
    public function getReference() {
        return $this->reference;
    }
    
    /**
     * Définit la référence de la commande
     * @param string $reference Nouvelle référence
     */
    public function setReference($reference) {
        $this->reference = $reference;
    }
    
    /**
     * Définit les données de la commande
     * @param array $data Données de la commande
     */
    public function setData($data) {
        $this->data = $data;
        if (isset($data['reference'])) {
            $this->reference = $data['reference'];
        }
    }
    
    /**
     * Vérifie si la commande existe dans le CSV
     * @return bool True si la commande existe
     */
    public function exists() {
        return $this->load();
    }
    
    /**
     * Supprime la commande du CSV (archive)
     * @return array Résultat de l'opération
     */
    public function archive() {
        if (!$this->reference) {
            return ['success' => false, 'error' => 'Référence de commande manquante'];
        }
        
        // Créer une sauvegarde avant suppression
        $backupPath = $this->createBackup($this->csvFile);
        if (!$backupPath) {
            return ['success' => false, 'error' => 'Impossible de créer la sauvegarde'];
        }
        
        // Lire toutes les données
        $csvData = $this->read($this->csvFile, true);
        if ($csvData === false) {
            return ['success' => false, 'error' => 'Impossible de lire le fichier'];
        }
        
        // Filtrer les données pour exclure cette commande
        $filteredData = [];
        $found = false;
        
        foreach ($csvData['data'] as $row) {
            if ($row['data'][0] !== $this->reference) {
                $filteredData[] = $row['data'];
            } else {
                $found = true;
            }
        }
        
        if (!$found) {
            return ['success' => false, 'error' => 'Commande introuvable'];
        }
        
        // Réécrire le fichier sans cette commande
        $writeSuccess = $this->write($this->csvFile, $filteredData, $csvData['header'], false, true);
        
        return [
            'success' => $writeSuccess,
            'backup_file' => $backupPath,
            'error' => $writeSuccess ? null : 'Erreur lors de l\'écriture'
        ];
    }
}

?>