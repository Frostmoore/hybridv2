<?php

namespace Tests\Feature\ApiV2;

use Illuminate\Support\Facades\File;
use Tests\TestCase;

class AssetTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        File::ensureDirectoryExists(storage_path('app/agency-assets/img/6'));
        file_put_contents(storage_path('app/agency-assets/img/6/logo_agenzia.png'), 'fake-png');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory(storage_path('app/agency-assets'));
        parent::tearDown();
    }

    public function test_serves_agency_image_at_legacy_path(): void
    {
        $this->get('/res/img/6/logo_agenzia.png')->assertOk();
    }

    public function test_missing_image_404(): void
    {
        $this->get('/res/img/6/inesistente.png')->assertNotFound();
    }

    public function test_path_traversal_blocked(): void
    {
        $this->get('/res/img/../../../.env')->assertNotFound();
    }

    public function test_does_not_shadow_api_routes(): void
    {
        // res/api/* deve restare gestito dalle route API (404 JSON, non asset)
        $this->get('/res/api/v2/inesistente.php')
            ->assertNotFound()
            ->assertJsonPath('code', 'NOT_FOUND');
    }
}
