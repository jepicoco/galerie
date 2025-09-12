<?php
/**
 * Utilitaires pour écriture CSV sans accumulation de BOM
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct interdit');
}

/**
 * Assure qu'un contenu a exactement un BOM UTF-8 au début
 * @param string $content Contenu à traiter
 * @return string Contenu avec exactement un BOM
 */
function ensureSingleBOM($content) {
    // Supprimer tous les BOM existants
    $clean = str_replace("\xEF\xBB\xBF", "", $content);
    
    // Ajouter UN seul BOM au début
    return "\xEF\xBB\xBF" . $clean;
}

/**
 * Écrit un fichier CSV avec gestion sécurisée du BOM
 * @param string $filePath Chemin du fichier
 * @param string $content Contenu à écrire
 * @param bool $addBOM Ajouter un BOM (par défaut true)
 * @return bool|int Résultat de file_put_contents
 */
function writeBOMSafeCSV($filePath, $content, $addBOM = true) {
    if ($addBOM) {
        $finalContent = ensureSingleBOM($content);
    } else {
        // Supprimer tous les BOM si pas voulu
        $finalContent = str_replace("\xEF\xBB\xBF", "", $content);
    }
    
    return file_put_contents($filePath, $finalContent);
}

/**
 * Vérifie l'état des BOM dans un fichier
 * @param string $filePath Chemin du fichier
 * @return array Informations sur l'état du fichier
 */
function checkBOMStatus($filePath) {
    if (!file_exists($filePath)) {
        return ['exists' => false];
    }
    
    $content = file_get_contents($filePath);
    $bomCount = substr_count($content, "\xEF\xBB\xBF");
    
    return [
        'exists' => true,
        'size' => strlen($content),
        'bom_count' => $bomCount,
        'is_valid' => $bomCount === 1,
        'first_chars' => bin2hex(substr($content, 0, 10))
    ];
}

?>