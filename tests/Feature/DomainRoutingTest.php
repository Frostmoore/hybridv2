<?php

namespace Tests\Feature;

use Tests\TestCase;

class DomainRoutingTest extends TestCase
{
    public function test_main_domain_serves_main_routes(): void
    {
        // La root del dominio principale è la login admin
        $this->get('http://'.config('hybrid.domain_main').'/')
            ->assertOk();
    }

    public function test_agencies_domain_serves_agencies_routes(): void
    {
        // La root del dominio agencies redirige alla login operatori
        $this->get('http://'.config('hybrid.domain_agencies').'/')
            ->assertRedirect('http://'.config('hybrid.domain_agencies').'/login.php');
    }

    public function test_health_check(): void
    {
        $this->get('/up')->assertOk();
    }
}
