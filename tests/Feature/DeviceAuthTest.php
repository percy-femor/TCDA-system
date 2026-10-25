<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class DeviceAuthTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_field_officer_can_sign_in_and_sign_out(): void
    {
        $this->seed();
        $deviceId = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

        $login = $this->postJson('/api/auth/login', [
            'email' => 'officer@tcda.gov.gh',
            'password' => 'password',
            'device_id' => $deviceId,
            'device_name' => 'test-phone',
        ]);

        $login->assertOk()
            ->assertJsonPath('user.email', 'officer@tcda.gov.gh')
            ->assertJsonPath('user.role', 'field_officer')
            ->assertJsonPath('user.region', 'Western');

        $token = $login->json('token');
        $this->assertNotEmpty($token);
        $this->assertDatabaseCount('device_tokens', 1);

        $this->withToken($token)->postJson('/api/auth/logout')->assertOk();
        $this->assertDatabaseCount('device_tokens', 0);

        $this->withToken($token)->getJson('/api/sync/pull?device_id='.$deviceId)->assertStatus(401);
    }

    public function test_another_officer_can_sign_in_on_the_same_device(): void
    {
        $this->seed();
        $deviceId = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

        $western = $this->postJson('/api/auth/login', [
            'email' => 'officer@tcda.gov.gh',
            'password' => 'password',
            'device_id' => $deviceId,
        ])->assertOk();

        $this->withToken($western->json('token'))->postJson('/api/auth/logout')->assertOk();

        $bono = $this->postJson('/api/auth/login', [
            'email' => 'officer.bono@tcda.gov.gh',
            'password' => 'password',
            'device_id' => $deviceId,
        ]);

        $bono->assertOk()
            ->assertJsonPath('user.email', 'officer.bono@tcda.gov.gh')
            ->assertJsonPath('user.region', 'Bono');
    }

    public function test_mill_and_tcda_cannot_use_the_field_app(): void
    {
        $this->seed();
        $deviceId = '3fa85f64-5717-4562-b3fc-2c963f66afa6';

        $this->postJson('/api/auth/login', [
            'email' => 'mill@benso.palm.gh',
            'password' => 'password',
            'device_id' => $deviceId,
        ])->assertStatus(403)->assertJsonFragment(['message' => 'This phone app is for field officers. Sign in with your officer email.']);

        $this->postJson('/api/auth/login', [
            'email' => 'tcda@tcda.gov.gh',
            'password' => 'password',
            'device_id' => $deviceId,
        ])->assertStatus(403);
    }
}
