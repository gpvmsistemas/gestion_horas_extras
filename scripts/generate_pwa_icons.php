<?php
/**
 * Genera íconos cuadrados para PWA a partir de public/img/pwa/logo-pm.png (o .jpg).
 */
$outDir = dirname(__DIR__) . '/public/img/pwa';
$candidates = [
    $outDir . '/logo-pm.png',
    $outDir . '/logo-pm.jpg',
    dirname(__DIR__) . '/public/img/pym.png',
];

$src = null;
foreach ($candidates as $path) {
    if (is_file($path)) {
        $src = $path;
        break;
    }
}
if ($src === null) {
    fwrite(STDERR, "No se encontró logo PWA en public/img/pwa/logo-pm.*\n");
    exit(1);
}
if (!is_dir($outDir)) {
    mkdir($outDir, 0755, true);
}

function pwa_load_image($path) {
    $info = @getimagesize($path);
    if (!$info) {
        return null;
    }
    switch ($info[2]) {
        case IMAGETYPE_PNG:
            $im = imagecreatefrompng($path);
            if ($im) {
                imagealphablending($im, true);
                imagesavealpha($im, true);
            }
            return $im;
        case IMAGETYPE_JPEG:
            return imagecreatefromjpeg($path);
        case IMAGETYPE_WEBP:
            return function_exists('imagecreatefromwebp') ? imagecreatefromwebp($path) : null;
        default:
            return null;
    }
}

$im = pwa_load_image($src);
if (!$im) {
    fwrite(STDERR, "No se pudo leer la imagen fuente: $src\n");
    exit(1);
}

$srcW = imagesx($im);
$srcH = imagesy($im);
$side = min($srcW, $srcH);
$srcX = (int)(($srcW - $side) / 2);
$srcY = (int)(($srcH - $side) / 2);

foreach ([192, 512] as $size) {
    $dst = imagecreatetruecolor($size, $size);
    $white = imagecolorallocate($dst, 255, 255, 255);
    imagefilledrectangle($dst, 0, 0, $size, $size, $white);
    imagecopyresampled($dst, $im, 0, 0, $srcX, $srcY, $size, $size, $side, $side);
    $path = $outDir . '/icon-' . $size . '.png';
    imagepng($dst, $path, 6);
    imagedestroy($dst);
    echo "Generado: $path (desde " . basename($src) . ")\n";
}
imagedestroy($im);
