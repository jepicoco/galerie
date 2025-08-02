<?php
// Script d'installation de PHPMailer
echo "Installation de PHPMailer...\n";

$phpmailerUrl = 'https://github.com/PHPMailer/PHPMailer/archive/refs/tags/v6.9.1.zip';
$zipFile = 'phpmailer.zip';
$extractDir = 'phpmailer/';

// Télécharger PHPMailer
if (file_put_contents($zipFile, file_get_contents($phpmailerUrl))) {
    echo "PHPMailer téléchargé.\n";
    
    $zip = new ZipArchive;
    if ($zip->open($zipFile) === TRUE) {
        $zip->extractTo('./');
        $zip->close();
        
        // Renommer le dossier
        if (is_dir('PHPMailer-6.9.1')) {
            rename('PHPMailer-6.9.1', 'phpmailer');
        }
        
        unlink($zipFile);
        echo "PHPMailer installé avec succès dans le dossier phpmailer/\n";
    } else {
        echo "Erreur lors de l'extraction.\n";
    }
} else {
    echo "Erreur lors du téléchargement.\n";
}
?>