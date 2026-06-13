<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Ottimizzazione immagini (in particolare le testate agenzia): ridimensiona a
 * una larghezza massima (solo downscale) e ricomprime in JPEG. Le testate
 * arrivano spesso a 2400px / più MB: inutili su mobile e problematiche da
 * servire in locale. Vedi critics.md.
 *
 * Il file di destinazione mantiene il nome originale (es. header_agenzia.png)
 * anche se il contenuto è JPEG: il path è referenziato dal DB e il MIME viene
 * comunque riconosciuto dal contenuto (server + Flutter).
 */
final class ImageOptimizer
{
    /**
     * Ottimizza l'immagine di $srcPath e scrive il risultato JPEG in $destPath
     * (può coincidere con $srcPath per l'ottimizzazione in-place).
     *
     * @return array{before:int, after:int, width:int, height:int}
     */
    public static function optimize(string $srcPath, string $destPath, int $maxWidth = 1200, int $quality = 80): array
    {
        $bytes = @file_get_contents($srcPath);
        if ($bytes === false) {
            throw new \RuntimeException("Impossibile leggere l'immagine: $srcPath");
        }
        $before = strlen($bytes);

        $src = @imagecreatefromstring($bytes);
        if ($src === false) {
            throw new \RuntimeException("Formato immagine non valido: $srcPath");
        }

        $w = imagesx($src);
        $h = imagesy($src);
        $nw = $w > $maxWidth ? $maxWidth : $w;
        $nh = max(1, (int) round($h * $nw / $w));

        $dst = imagecreatetruecolor($nw, $nh);
        // JPEG non ha canale alpha: appiattisco l'eventuale trasparenza su bianco.
        $white = imagecolorallocate($dst, 255, 255, 255);
        imagefilledrectangle($dst, 0, 0, $nw, $nh, $white);
        imagecopyresampled($dst, $src, 0, 0, 0, 0, $nw, $nh, $w, $h);

        $dir = \dirname($destPath);
        if (! is_dir($dir)) {
            mkdir($dir, 0775, true);
        }

        imagejpeg($dst, $destPath, $quality);
        imagedestroy($src);
        imagedestroy($dst);
        clearstatcache();

        return [
            'before' => $before,
            'after' => (int) filesize($destPath),
            'width' => $nw,
            'height' => $nh,
        ];
    }
}
