<?php
/**
 * Classe générique pour la gestion des fichiers CSV
 * Fournit des méthodes de lecture et écriture pour tous les fichiers CSV du projet
 * @version 1.0
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}

class CsvHandler {
    
    private $delimiter;
    private $enclosure;
    private $escape;
    
    /**
     * Constructeur
     * @param string $delimiter Séparateur (par défaut ';')
     * @param string $enclosure Caractère d'encadrement (par défaut '"')
     * @param string $escape Caractère d'échappement (par défaut '\')
     */
    public function __construct($delimiter = ';', $enclosure = '"', $escape = '\\') {
        $this->delimiter = $delimiter;
        $this->enclosure = $enclosure;
        $this->escape = $escape;
    }
    
    /**
     * Lit un fichier CSV et retourne les données
     * @param string $filePath Chemin vers le fichier CSV
     * @param bool $hasHeader Indique si le fichier a un en-tête (par défaut true)
     * @param array $requiredColumns Nombre minimum de colonnes requis (par défaut null)
     * @return array|false Données du CSV ou false en cas d'erreur
     */
    public function read($filePath, $hasHeader = true, $requiredColumns = null) {
        if (!file_exists($filePath)) {
            return false;
        }
        
        $lines = file($filePath, FILE_IGNORE_NEW_LINES);
        if ($lines === false || count($lines) < 1) {
            return false;
        }
        
        $data = [];
        $header = null;
        
        // Traiter l'en-tête si présent
        if ($hasHeader && count($lines) > 0) {
            $header = str_getcsv(array_shift($lines), $this->delimiter, $this->enclosure, $this->escape);
        }
        
        // Traiter chaque ligne
        foreach ($lines as $lineNumber => $line) {
            if (empty(trim($line))) {
                continue;
            }
            
            $rowData = str_getcsv($line, $this->delimiter, $this->enclosure, $this->escape);
            
            // Vérifier le nombre minimum de colonnes si spécifié
            if ($requiredColumns !== null && count($rowData) < $requiredColumns) {
                continue;
            }
            
            $data[] = [
                'line_number' => $lineNumber + ($hasHeader ? 2 : 1), // +2 car array_shift et index commençant à 0
                'data' => $rowData,
                'raw_line' => $line
            ];
        }
        
        return [
            'header' => $header,
            'data' => $data,
            'total_lines' => count($data)
        ];
    }
    
    /**
     * Écrit des données dans un fichier CSV
     * @param string $filePath Chemin vers le fichier CSV
     * @param array $data Données à écrire
     * @param array $header En-tête du fichier (optionnel)
     * @param bool $append Mode d'ajout (par défaut false = écrasement)
     * @param bool $addBom Ajouter le BOM UTF-8 (par défaut false)
     * @return bool Succès de l'opération
     */
    public function write($filePath, $data, $header = null, $append = false, $addBom = false) {
        $mode = $append ? 'a' : 'w';
        $handle = fopen($filePath, $mode);
        
        if ($handle === false) {
            return false;
        }
        
        // Ajouter le BOM UTF-8 si demandé et en mode écriture
        if ($addBom && !$append) {
            fwrite($handle, "\xEF\xBB\xBF");
        }
        
        // Écrire l'en-tête si fourni et en mode écriture
        if ($header !== null && !$append) {
            fputcsv($handle, $header, $this->delimiter, $this->enclosure, $this->escape);
        }
        
        // Écrire les données
        foreach ($data as $row) {
            fputcsv($handle, $row, $this->delimiter, $this->enclosure, $this->escape);
        }
        
        fclose($handle);
        return true;
    }
    
    /**
     * Ajoute une ligne à un fichier CSV existant
     * @param string $filePath Chemin vers le fichier CSV
     * @param array $row Ligne à ajouter
     * @param array $header En-tête à créer si le fichier n'existe pas
     * @param bool $addBom Ajouter le BOM UTF-8 si nouveau fichier
     * @return bool Succès de l'opération
     */
    public function appendRow($filePath, $row, $header = null, $addBom = false) {
        $isNewFile = !file_exists($filePath);
        
        if ($isNewFile && $header !== null) {
            return $this->write($filePath, [$row], $header, false, $addBom);
        } else {
            return $this->write($filePath, [$row], null, true, false);
        }
    }
    
    /**
     * Met à jour une ligne spécifique dans un fichier CSV
     * @param string $filePath Chemin vers le fichier CSV
     * @param int $lineNumber Numéro de ligne à modifier (1-indexed, sans compter l'en-tête)
     * @param array $newData Nouvelles données pour la ligne
     * @param bool $hasHeader Indique si le fichier a un en-tête
     * @return bool Succès de l'opération
     */
    public function updateLine($filePath, $lineNumber, $newData, $hasHeader = true) {
        $csvData = $this->read($filePath, $hasHeader);
        if ($csvData === false) {
            return false;
        }
        
        $found = false;
        foreach ($csvData['data'] as &$row) {
            if ($row['line_number'] == $lineNumber) {
                $row['data'] = $newData;
                $found = true;
                break;
            }
        }
        
        if (!$found) {
            return false;
        }
        
        // Reconstituer les données pour l'écriture
        $dataToWrite = [];
        foreach ($csvData['data'] as $row) {
            $dataToWrite[] = $row['data'];
        }
        
        return $this->write($filePath, $dataToWrite, $csvData['header'], false, true);
    }
    
    /**
     * Met à jour toutes les lignes correspondant à un critère
     * @param string $filePath Chemin vers le fichier CSV
     * @param int $searchColumn Index de la colonne de recherche (0-indexed)
     * @param string $searchValue Valeur à rechercher
     * @param array $updates Tableau associatif [index_colonne => nouvelle_valeur]
     * @param bool $hasHeader Indique si le fichier a un en-tête
     * @return array Résultat avec nombre de lignes modifiées
     */
    public function updateByValue($filePath, $searchColumn, $searchValue, $updates, $hasHeader = true) {
        $csvData = $this->read($filePath, $hasHeader);
        if ($csvData === false) {
            return ['success' => false, 'error' => 'Impossible de lire le fichier'];
        }
        
        $updatedCount = 0;
        
        foreach ($csvData['data'] as &$row) {
            if (isset($row['data'][$searchColumn]) && $row['data'][$searchColumn] === $searchValue) {
                // Étendre le tableau si nécessaire
                $maxIndex = max(array_keys($updates));
                while (count($row['data']) <= $maxIndex) {
                    $row['data'][] = '';
                }
                
                // Appliquer les mises à jour
                foreach ($updates as $columnIndex => $newValue) {
                    $row['data'][$columnIndex] = $newValue;
                }
                $updatedCount++;
            }
        }
        
        if ($updatedCount === 0) {
            return ['success' => false, 'error' => 'Aucune ligne trouvée'];
        }
        
        // Reconstituer les données pour l'écriture
        $dataToWrite = [];
        foreach ($csvData['data'] as $row) {
            $dataToWrite[] = $row['data'];
        }
        
        $writeSuccess = $this->write($filePath, $dataToWrite, $csvData['header'], false, true);
        
        return [
            'success' => $writeSuccess,
            'updated_count' => $updatedCount,
            'error' => $writeSuccess ? null : 'Erreur lors de l\'écriture'
        ];
    }
    
    /**
     * Filtre les données CSV selon des critères
     * @param array $csvData Données CSV (résultat de read())
     * @param array $filters Filtres à appliquer [colonne => valeur] ou [colonne => [valeurs]]
     * @param string $operator Opérateur logique ('AND' ou 'OR')
     * @return array Données filtrées
     */
    public function filter($csvData, $filters, $operator = 'AND') {
        if (!isset($csvData['data']) || empty($filters)) {
            return $csvData;
        }
        
        $filteredData = [];
        
        foreach ($csvData['data'] as $row) {
            $matches = [];
            
            foreach ($filters as $column => $value) {
                if (!isset($row['data'][$column])) {
                    $matches[] = false;
                    continue;
                }
                
                $cellValue = $row['data'][$column];
                
                if (is_array($value)) {
                    $matches[] = in_array($cellValue, $value);
                } else {
                    $matches[] = ($cellValue === $value);
                }
            }
            
            $shouldInclude = ($operator === 'AND') ? !in_array(false, $matches) : in_array(true, $matches);
            
            if ($shouldInclude) {
                $filteredData[] = $row;
            }
        }
        
        return [
            'header' => $csvData['header'],
            'data' => $filteredData,
            'total_lines' => count($filteredData)
        ];
    }
    
    /**
     * Crée un fichier de sauvegarde avant modification
     * @param string $filePath Chemin du fichier original
     * @param string $backupDir Répertoire de sauvegarde (par défaut 'archives/')
     * @return string|false Chemin du fichier de sauvegarde ou false en cas d'erreur
     */
    public function createBackup($filePath, $backupDir = 'archives/') {
        if (!file_exists($filePath)) {
            return false;
        }
        
        // Créer le répertoire de sauvegarde s'il n'existe pas
        if (!is_dir($backupDir)) {
            mkdir($backupDir, 0755, true);
        }
        
        $filename = basename($filePath);
        $extension = pathinfo($filename, PATHINFO_EXTENSION);
        $basename = pathinfo($filename, PATHINFO_FILENAME);
        
        $backupPath = $backupDir . $basename . '_' . date('Y-m-d_H-i-s') . '.' . $extension;
        
        return copy($filePath, $backupPath) ? $backupPath : false;
    }
}

?>