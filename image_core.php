<?php
/**
 * Fonctions de traitement d'images
 */

if (!defined('GALLERY_ACCESS')) {
    die('Accès direct non autorisé');
}

/**
 * Lire l'orientation EXIF d'une image
 */
function getImageOrientation($imagePath) {
    if (!extension_loaded('exif') || !function_exists('exif_read_data')) {
        return 1; // Orientation normale par défaut
    }
    
    $imageType = exif_imagetype($imagePath);
    if ($imageType !== IMAGETYPE_JPEG) {
        return 1; // EXIF uniquement supporté pour JPEG
    }
    
    $exifData = @exif_read_data($imagePath);
    if ($exifData === false || !isset($exifData['Orientation'])) {
        return 1; // Orientation normale par défaut
    }
    
    return $exifData['Orientation'];
}

/**
 * Corriger l'orientation d'une image selon les données EXIF
 */
function fixImageOrientation($image, $orientation) {
    if (!$image || $orientation <= 1 || $orientation > 8) {
        return $image;
    }
    
    switch ($orientation) {
        case 2:
            // Miroir horizontal
            imageflip($image, IMG_FLIP_HORIZONTAL);
            break;
        case 3:
            // Rotation 180°
            $image = imagerotate($image, 180, 0);
            break;
        case 4:
            // Miroir vertical
            imageflip($image, IMG_FLIP_VERTICAL);
            break;
        case 5:
            // Miroir horizontal + rotation 90° sens horaire
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $image = imagerotate($image, -90, 0);
            break;
        case 6:
            // Rotation 90° sens horaire
            $image = imagerotate($image, -90, 0);
            break;
        case 7:
            // Miroir horizontal + rotation 90° sens anti-horaire
            imageflip($image, IMG_FLIP_HORIZONTAL);
            $image = imagerotate($image, 90, 0);
            break;
        case 8:
            // Rotation 90° sens anti-horaire
            $image = imagerotate($image, 90, 0);
            break;
    }
    
    return $image;
}

/**
 * Vérifier si l'image est en cache et si elle est valide
 */
function isCacheValid($cacheFilePath, $originalFilePath) {
    if (!file_exists($cacheFilePath)) {
        return false;
    }
    
    if (!file_exists($originalFilePath)) {
        return false;
    }
    
    $cacheTime = filemtime($cacheFilePath);
    $originalTime = filemtime($originalFilePath);
    
    if ($originalTime > $cacheTime) {
        return false;
    }
    
    $maxAge = IMAGE_CACHE_DURATION;
    if ((time() - $cacheTime) > $maxAge) {
        return false;
    }
    
    return true;
}

/**
 * Générer une miniature optimisée
 */
function generateThumbnail($sourcePath, $thumbnailPath, $width = null, $height = null) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $width = $width ?: THUMBNAIL_WIDTH;
    $height = $height ?: THUMBNAIL_HEIGHT;
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $sourceType = $imageInfo[2];
    
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $sourceImage = imagecreatefromwebp($sourcePath);
            } else {
                return false;
            }
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Correction de l'orientation EXIF
    $orientation = getImageOrientation($sourcePath);
    $sourceImage = fixImageOrientation($sourceImage, $orientation);
    
    // Recalculer les dimensions après rotation éventuelle
    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    
    $ratio = min($width / $sourceWidth, $height / $sourceHeight);
    $thumbWidth = intval($sourceWidth * $ratio);
    $thumbHeight = intval($sourceHeight * $ratio);
    
    $thumbImage = imagecreatetruecolor($thumbWidth, $thumbHeight);
    
    if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
        imagealphablending($thumbImage, false);
        imagesavealpha($thumbImage, true);
        $transparent = imagecolorallocatealpha($thumbImage, 255, 255, 255, 127);
        imagefilledrectangle($thumbImage, 0, 0, $thumbWidth, $thumbHeight, $transparent);
    }
    
    imagecopyresampled(
        $thumbImage, $sourceImage,
        0, 0, 0, 0,
        $thumbWidth, $thumbHeight,
        $sourceWidth, $sourceHeight
    );
    
    $thumbnailDir = dirname($thumbnailPath);
    if (!is_dir($thumbnailDir)) {
        mkdir($thumbnailDir, 0755, true);
    }
    
    $success = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($thumbImage, $thumbnailPath, JPEG_QUALITY);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($thumbImage, $thumbnailPath, 9);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($thumbImage, $thumbnailPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $success = imagewebp($thumbImage, $thumbnailPath, JPEG_QUALITY);
            }
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($thumbImage);
    
    return $success;
}

/**
 * Redimensionner une image avec contraintes
 */
function resizeImage($sourcePath, $outputPath, $maxWidth, $maxHeight, $quality = null) {
    if (!extension_loaded('gd')) {
        return false;
    }
    
    $quality = $quality ?: JPEG_QUALITY;
    
    $imageInfo = getimagesize($sourcePath);
    if (!$imageInfo) {
        return false;
    }
    
    $sourceWidth = $imageInfo[0];
    $sourceHeight = $imageInfo[1];
    $sourceType = $imageInfo[2];
    
    if ($sourceWidth <= $maxWidth && $sourceHeight <= $maxHeight) {
        return copy($sourcePath, $outputPath);
    }
    
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $sourceImage = imagecreatefromjpeg($sourcePath);
            break;
        case IMAGETYPE_PNG:
            $sourceImage = imagecreatefrompng($sourcePath);
            break;
        case IMAGETYPE_GIF:
            $sourceImage = imagecreatefromgif($sourcePath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagecreatefromwebp')) {
                $sourceImage = imagecreatefromwebp($sourcePath);
            } else {
                return false;
            }
            break;
        default:
            return false;
    }
    
    if (!$sourceImage) {
        return false;
    }
    
    // Correction de l'orientation EXIF
    $orientation = getImageOrientation($sourcePath);
    $sourceImage = fixImageOrientation($sourceImage, $orientation);
    
    // Recalculer les dimensions après rotation éventuelle
    $sourceWidth = imagesx($sourceImage);
    $sourceHeight = imagesy($sourceImage);
    
    // Recalculer le ratio et les nouvelles dimensions
    $ratio = min($maxWidth / $sourceWidth, $maxHeight / $sourceHeight);
    $newWidth = intval($sourceWidth * $ratio);
    $newHeight = intval($sourceHeight * $ratio);
    
    $resizedImage = imagecreatetruecolor($newWidth, $newHeight);
    
    if ($sourceType == IMAGETYPE_PNG || $sourceType == IMAGETYPE_GIF) {
        imagealphablending($resizedImage, false);
        imagesavealpha($resizedImage, true);
        $transparent = imagecolorallocatealpha($resizedImage, 255, 255, 255, 127);
        imagefilledrectangle($resizedImage, 0, 0, $newWidth, $newHeight, $transparent);
    }
    
    imagecopyresampled(
        $resizedImage, $sourceImage,
        0, 0, 0, 0,
        $newWidth, $newHeight,
        $sourceWidth, $sourceHeight
    );
    
    $outputDir = dirname($outputPath);
    if (!is_dir($outputDir)) {
        mkdir($outputDir, 0755, true);
    }
    
    $success = false;
    switch ($sourceType) {
        case IMAGETYPE_JPEG:
            $success = imagejpeg($resizedImage, $outputPath, $quality);
            break;
        case IMAGETYPE_PNG:
            $success = imagepng($resizedImage, $outputPath, 9);
            break;
        case IMAGETYPE_GIF:
            $success = imagegif($resizedImage, $outputPath);
            break;
        case IMAGETYPE_WEBP:
            if (function_exists('imagewebp')) {
                $success = imagewebp($resizedImage, $outputPath, $quality);
            }
            break;
    }
    
    imagedestroy($sourceImage);
    imagedestroy($resizedImage);
    
    return $success;
}
?>