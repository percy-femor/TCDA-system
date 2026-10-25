<?php

namespace Tests\Feature;

use Tests\TestCase;

class FieldPwaTest extends TestCase
{
    public function test_field_app_is_installable(): void
    {
        $this->get('/field')
            ->assertOk()
            ->assertSee('field-manifest.json', false)
            ->assertSee('apple-touch-icon', false)
            ->assertSee('Add to Home screen')
            ->assertSee('Download for this PC')
            ->assertSee('Install status')
            ->assertSee('Outbox')
            ->assertSee('Sign out')
            ->assertSee('fisc-logo.jpg', false)
            ->assertSee('mobile-web-app-capable', false);

        $manifest = json_decode(file_get_contents(public_path('field-manifest.json')), true);
        $this->assertSame('standalone', $manifest['display']);
        $this->assertArrayNotHasKey('orientation', $manifest);
        $this->assertSame('/field?source=homescreen', $manifest['start_url']);
        $this->assertSame('TCDA Field', $manifest['short_name']);
        $this->assertFileExists(public_path('icons/field-192.png'));
        $this->assertFileExists(public_path('icons/field-512.png'));
        $this->assertFileExists(public_path('icons/field-180.png'));
        $this->assertFileExists(public_path('images/fisc-logo.jpg'));
    }
}
