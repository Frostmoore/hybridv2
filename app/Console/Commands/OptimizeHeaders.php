<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Support\ImageOptimizer;
use Illuminate\Console\Command;

/**
 * Comprime e ridimensiona le immagini di testata delle agenzie già esistenti.
 * Riusabile sia sullo storage locale sia sull'albero immagini del legacy prima
 * dell'rsync in produzione (`--path`).
 */
class OptimizeHeaders extends Command
{
    protected $signature = 'hybrid:optimize-headers
        {--path= : Cartella base immagini agenzia (default: storage/app/agency-assets/img)}
        {--name=header_agenzia.png : Nome file da ottimizzare per ogni agenzia}
        {--max-width=1200 : Larghezza massima (solo downscale)}
        {--quality=80 : Qualità JPEG}
        {--dry-run : Mostra cosa farebbe senza scrivere}';

    protected $description = 'Comprime/ridimensiona le testate agenzia (resize a max-width + JPEG)';

    public function handle(): int
    {
        $base = (string) ($this->option('path') ?: config('hybrid.agency_assets_path').'/img');
        $name = (string) $this->option('name');
        $maxWidth = (int) $this->option('max-width');
        $quality = (int) $this->option('quality');
        $dry = (bool) $this->option('dry-run');

        // Slash normalizzati: su Windows glob() tratta `\` come escape.
        $pattern = str_replace('\\', '/', rtrim($base, '/\\')).'/*/'.$name;
        $files = glob($pattern) ?: [];

        if ($files === []) {
            $this->warn("Nessun file «{$name}» trovato in {$base}");

            return self::SUCCESS;
        }

        $totBefore = 0;
        $totAfter = 0;
        $done = 0;

        foreach ($files as $file) {
            $label = basename(\dirname($file)).'/'.basename($file);

            if ($dry) {
                $size = (int) filesize($file);
                $dim = getimagesize($file) ?: [0, 0];
                $this->line(sprintf('[dry] %s — %d KB (%dx%d)', $label, intdiv($size, 1024), $dim[0], $dim[1]));

                continue;
            }

            try {
                $r = ImageOptimizer::optimize($file, $file, $maxWidth, $quality);
            } catch (\Throwable $e) {
                $this->error("Salto $label: ".$e->getMessage());

                continue;
            }

            $totBefore += $r['before'];
            $totAfter += $r['after'];
            $done++;
            $this->line(sprintf(
                '%s — %d KB → %d KB (%dx%d)',
                $label, intdiv($r['before'], 1024), intdiv($r['after'], 1024), $r['width'], $r['height']
            ));
        }

        if (! $dry) {
            $saved = $totBefore > 0 ? (int) round(100 * ($totBefore - $totAfter) / $totBefore) : 0;
            $this->info(sprintf(
                'Ottimizzate %d testate: %d KB → %d KB (-%d%%)',
                $done, intdiv($totBefore, 1024), intdiv($totAfter, 1024), $saved
            ));
        }

        return self::SUCCESS;
    }
}
