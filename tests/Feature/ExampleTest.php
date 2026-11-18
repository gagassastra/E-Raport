<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_to_login_page(): void
    {
        $this->seed();
        $response = $this->get('/');
        $response->assertRedirect(route('login'));

        $loginPage = $this->get(route('login'));
        $loginPage->assertStatus(200);
        $loginPage->assertSee('SISTEM INFORMASI E-RAPORT');
    }
}
