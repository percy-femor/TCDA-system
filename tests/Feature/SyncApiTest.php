<?php

namespace Tests\Feature;

use App\Models\Farmer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SyncApiTest extends TestCase
{
    use RefreshDatabase;

    public function test_field_device_can_push_farmer_and_plot_idempotently(): void
    {
        $this->seed();
        $deviceId = '3fa85f64-5717-4562-b3fc-2c963f66afa6';
        $farmerUuid = '3fa85f64-5717-4562-b3fc-2c963f66afa7';
        $plotUuid = '3fa85f64-5717-4562-b3fc-2c963f66afa8';
        $opFarmer = '3fa85f64-5717-4562-b3fc-2c963f66afb1';
        $opPlot = '3fa85f64-5717-4562-b3fc-2c963f66afb2';

        $login = $this->postJson('/api/auth/login', [
            'email' => 'officer@tcda.gov.gh',
            'password' => 'password',
            'device_id' => $deviceId,
            'device_name' => 'test-phone',
        ]);
        $login->assertOk()->assertJsonStructure(['token']);
        $token = $login->json('token');

        $payload = [
            'device_id' => $deviceId,
            'operations' => [
                [
                    'op_id' => $opFarmer,
                    'type' => 'farmer.upsert',
                    'client_uuid' => $farmerUuid,
                    'happened_at' => now()->subMinutes(5)->toIso8601String(),
                    'payload' => [
                        'name' => 'Mensah Offline',
                        'community' => 'Busua',
                        'district' => 'Ahanta West',
                        'region' => 'Western',
                        'chain' => 'palm',
                        'aggregator_public_id' => 'ORG-00002',
                        'consent' => true,
                    ],
                ],
                [
                    'op_id' => $opPlot,
                    'type' => 'plot.upsert',
                    'client_uuid' => $plotUuid,
                    'happened_at' => now()->subMinutes(4)->toIso8601String(),
                    'payload' => [
                        'farmer_client_uuid' => $farmerUuid,
                        'latitude' => 4.8011,
                        'longitude' => -2.2011,
                        'land_status' => 'customary',
                        'area_hectares' => 0.9,
                    ],
                ],
            ],
        ];

        $first = $this->withToken($token)->postJson('/api/sync/push', $payload);
        $first->assertOk();
        $this->assertCount(2, $first->json('accepted'));
        $this->assertSame([], $first->json('rejected'));

        $second = $this->withToken($token)->postJson('/api/sync/push', $payload);
        $second->assertOk();
        $this->assertTrue($second->json('accepted.0.duplicate'));
        $this->assertSame(1, Farmer::query()->where('name', 'Mensah Offline')->count());
        $this->assertDatabaseHas('plots', ['client_uuid' => $plotUuid]);

        $pull = $this->withToken($token)->getJson('/api/sync/pull?device_id='.$deviceId);
        $pull->assertOk();
        $names = collect($pull->json('farmers'))->pluck('name');
        $this->assertTrue($names->contains('Mensah Offline'));
    }

    public function test_sync_requires_a_device_token(): void
    {
        $this->postJson('/api/sync/push', [
            'device_id' => '3fa85f64-5717-4562-b3fc-2c963f66afa6',
            'operations' => [],
        ])->assertStatus(401);
    }
}
