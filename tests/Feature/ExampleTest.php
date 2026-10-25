<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;
    public function test_root_redirects_to_login(): void
    {
        $this->get('/')->assertRedirect(route('login'));
    }

    public function test_demo_user_can_sign_in(): void
    {
        $this->seed();

        $this->post('/login', [
            'email' => 'tcda@tcda.gov.gh',
            'password' => 'password',
        ])->assertRedirect(route('dashboard'));

        $this->get('/tcda')->assertOk()->assertSee('National command');
    }
}
