<?php
/**
 * Serveur d'images avec cache automatique
 */

if (!defined('GALLERY_ACCESS')) {
    define('GALLERY_ACCESS', true);
}
require_once 'config.php';
require_once 'image_core.php';

/**
 * Serveur d'images avec cache automatique
 */
function serveImage() {
    if (!IMAGE_CACHE_ENABLED) {
        serveOriginalImage();
        return;
    }
    
    $src = $_GET['src'] ?? '';
    $type = $_GET['type'] ?? 'original';
    $width = intval($_GET['width'] ?? 0);
    $height = intval($_GET['height'] ?? 0);
    
    if (empty($src)) {
        http_response_code(400);
        die('Paramètre src manquant');
    }
    
    $src = str_replace(['../', '.\\', '\\'], '', $src);
    if (strpos($src, '/') === 0) {
        $src = substr($src, 1);
    }
    
    $originalPath = PHOTOS_DIR . $src;
    
    if (!file_exists($originalPath)) {
        http_response_code(404);
        die('Image non trouvée');
    }
    
    $extension = strtolower(pathinfo($originalPath, PATHINFO_EXTENSION));
    if (!in_array($extension, ALLOWED_IMAGE_EXTENSIONS)) {
        http_response_code(403);
        die('Type de fichier non autorisé');
    }
    
    $cacheFile = '';
    $cacheDir = '';
    
    switch ($type) {
        case 'thumbnail':
            $cacheDir = THUMBNAILS_CACHE_DIR;
            $cacheFile = $cacheDir . str_replace('/', '_', $src);
            break;
            
        case 'resized':
            $cacheDir = RESIZED_CACHE_DIR;
            $maxWidth = $width > 0 ? min($width, MAX_IMAGE_WIDTH) : MAX_IMAGE_WIDTH;
            $maxHeight = $height > 0 ? min($height, MAX_IMAGE_HEIGHT) : MAX_IMAGE_HEIGHT;
            $cacheFile = $cacheDir . $maxWidth . 'x' . $maxHeight . '_' . str_replace('/', '_', $src);
            break;
            
        case 'original':
        default:
            serveOriginalImage($originalPath);
            return;
    }
    
    if (!is_dir($cacheDir)) {
        mkdir($cacheDir, 0755, true);
    }
    
    if (!isCacheValid($cacheFile, $originalPath)) {
        $success = false;
        
        switch ($type) {
            case 'thumbnail':
                $success = generateThumbnail($originalPath, $cacheFile, $width, $height);
                break;
                
            case 'resized':
                $success = resizeImage($originalPath, $cacheFile, $maxWidth, $maxHeight);
                break;
        }
        
        if (!$success) {
            serveOriginalImage($originalPath);
            return;
        }
    }
    
    serveOriginalImage($cacheFile);
}

/**
 * Servir une image avec les headers appropriés
 */
function serveOriginalImage($imagePath = null) {
    if ($imagePath === null) {
        $src = $_GET['src'] ?? '';
        $imagePath = PHOTOS_DIR . str_replace(['../', '.\\', '\\'], '', $src);
    }
    
    if (!file_exists($imagePath)) {
        http_response_code(404);
        die('Image non trouvée');
    }
    
    $imageInfo = getimagesize($imagePath);
    $mimeType = $imageInfo['mime'] ?? 'application/octet-stream';
    
    $etag = md5_file($imagePath);
    $lastModified = filemtime($imagePath);
    
    header('Content-Type: ' . $mimeType);
    header('Content-Length: ' . filesize($imagePath));
    header('Cache-Control: public, max-age=' . IMAGE_CACHE_DURATION);
    header('ETag: "' . $etag . '"');
    header('Last-Modified: ' . gmdate('D, d M Y H:i:s', $lastModified) . ' GMT');
    
    $clientEtag = $_SERVER['HTTP_IF_NONE_MATCH'] ?? '';
    $clientLastModified = $_SERVER['HTTP_IF_MODIFIED_SINCE'] ?? '';
    
    if (($clientEtag && $clientEtag === '"' . $etag . '"') ||
        ($clientLastModified && strtotime($clientLastModified) >= $lastModified)) {
        http_response_code(304);
        return;
    }
    
    readfile($imagePath);
}

// Constantes pour les types d'images
if (!defined('IMG_THUMBNAIL')) {
    define('IMG_THUMBNAIL', 'thumbnail');
    define('IMG_RESIZED', 'resized');
    define('IMG_ORIGINAL', 'original');
}

// Point d'entrée principal
if (isset($_GET['src']) || isset($_GET['cached'])) {
    serveImage();
} else {
    http_response_code(400);
    die('Paramètres manquants');
}
?>