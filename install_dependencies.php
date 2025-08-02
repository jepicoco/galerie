<?php
define('GALLERY_ACCESS', true);

echo "<h2>Installation des dépendances</h2>\n";

// Vérifier si PHPMailer existe déjà
if (file_exists(__DIR__ . '/phpmailer/src/PHPMailer.php')) {
    echo "<p>✅ PHPMailer déjà installé</p>\n";
} else {
    echo "<p>📥 Téléchargement de PHPMailer...</p>\n";
    
    // Créer le dossier phpmailer
    $phpmailerDir = __DIR__ . '/phpmailer';
    if (!is_dir($phpmailerDir)) {
        mkdir($phpmailerDir, 0755, true);
    }
    
    // URLs des fichiers PHPMailer essentiels
    $files = [
        'src/PHPMailer.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/PHPMailer.php',
        'src/SMTP.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/SMTP.php',
        'src/Exception.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/Exception.php',
        'src/OAuth.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/OAuth.php',
        'src/POP3.php' => 'https://raw.githubusercontent.com/PHPMailer/PHPMailer/master/src/POP3.php'
    ];
    
    foreach ($files as $localFile => $url) {
        $fullPath = $phpmailerDir . '/' . $localFile;
        $dir = dirname($fullPath);
        
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }
        
        $content = file_get_contents($url);
        if ($content !== false) {
            file_put_contents($fullPath, $content);
            echo "<p>✅ Téléchargé: $localFile</p>\n";
        } else {
            echo "<p>❌ Échec: $localFile</p>\n";
        }
    }
    
    echo "<p>✅ PHPMailer installé avec succès!</p>\n";
}

echo "<p><a href='admin.php'>← Retour à l'administration</a></p>\n";
?>