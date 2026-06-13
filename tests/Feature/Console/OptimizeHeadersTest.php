<?php

namespace Tests\Feature\Console;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class OptimizeHeadersTest extends TestCase
{
    private string $base;

    protected function setUp(): void
    {
        parent::setUp();
        // Path isolato: non toccare le immagini reali di sviluppo
        config(['hybrid.agency_assets_path' => storage_path('app/test-agency-assets')]);
        $this->base = config('hybrid.agency_assets_path').'/img';
        File::deleteDirectory(storage_path('app/test-agency-assets'));
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/test-agency-assets'));
        parent::tearDown();
    }

    /** Crea un PNG grande come testata di un'agenzia. */
    private function makeHeader(int $agencyId, int $w, int $h): string
    {
        $dir = $this->base.'/'.$agencyId;
        File::ensureDirectoryExists($dir);
        $path = $dir.'/header_agenzia.png';
        $im = imagecreatetruecolor($w, $h);
        // un po' di rumore così il PNG non è banale da comprimere
        for ($i = 0; $i < 2000; $i++) {
            imagesetpixel($im, random_int(0, $w - 1), random_int(0, $h - 1), random_int(0, 0xFFFFFF));
        }
        imagepng($im, $path);
        imagedestroy($im);

        return $path;
    }

    public function test_command_downscales_and_compresses_headers(): void
    {
        $a = $this->makeHeader(101, 2400, 1256);
        $b = $this->makeHeader(102, 1000, 500);   // già stretto: non viene ingrandito

        $this->artisan('hybrid:optimize-headers')
            ->expectsOutputToContain('Ottimizzate 2 testate')
            ->assertSuccessful();

        // 101: ridimensionato a max 1200 e JPEG (il guadagno di peso reale è sui
        // PNG fotografici; qui basta verificare resize + ricompressione)
        $infoA = getimagesize($a);
        $this->assertSame(1200, $infoA[0]);
        $this->assertSame(IMAGETYPE_JPEG, $infoA[2]);

        // 102: larghezza invariata (solo downscale), comunque JPEG
        $infoB = getimagesize($b);
        $this->assertSame(1000, $infoB[0]);
        $this->assertSame(IMAGETYPE_JPEG, $infoB[2]);
    }

    public function test_command_dry_run_does_not_modify(): void
    {
        $a = $this->makeHeader(103, 2000, 1000);
        $type = getimagesize($a)[2];   // IMAGETYPE_PNG

        $this->artisan('hybrid:optimize-headers --dry-run')->assertSuccessful();

        $this->assertSame($type, getimagesize($a)[2]);   // ancora PNG, non toccato
    }

    public function test_command_no_files_is_noop(): void
    {
        $this->artisan('hybrid:optimize-headers')->assertSuccessful();
    }
}
