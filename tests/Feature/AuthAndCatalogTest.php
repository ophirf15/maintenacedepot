<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class AuthAndCatalogTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_and_catalog(): void
    {
        $this->seed();

        $response = $this->postJson('/api/auth/login', [
            'email' => 'joe@depotborrow.test',
            'password' => 'password',
        ]);

        $response->assertOk()->assertJsonPath('user.email', 'joe@depotborrow.test');
        $token = $response->json('token');

        $this->withToken($token)
            ->getJson('/api/catalog/categories')
            ->assertOk();

        $this->getJson('/api/auth/config')
            ->assertOk()
            ->assertJsonPath('installed', true);
    }
}
