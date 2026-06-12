<?php

namespace Tests\Feature;

use Tests\TestCase;

class DomainRoutingTest extends TestCase
{
    public function test_main_domain_serves_main_routes(): void
    {
        $this->get('http://'.config('hybrid.domain_main').'/')
            ->assertOk();
    }

    public function test_agencies_domain_serves_agencies_routes(): void
    {
        $this->get('http://'.config('hybrid.domain_agencies').'/')
            ->assertOk();
    }

    public function test_health_check(): void
    {
        $this->get('/up')->assertOk();
    }
}
