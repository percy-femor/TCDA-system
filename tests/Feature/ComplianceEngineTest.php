<?php

use App\Models\Batch;
use App\Models\Farmer;
use App\Services\ComplianceEngine;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ComplianceEngineTest extends TestCase
{
    use RefreshDatabase;

    public function test_palm_batch_without_gps_is_a_gap(): void
    {
        $this->seed();

        $batch = Batch::query()->where('public_id', 'BAT-00002')->firstOrFail();
        $result = app(ComplianceEngine::class)->evaluate($batch);

        $this->assertSame('gaps', $result['status']);
        $this->assertTrue(collect($result['gaps'])->contains(fn ($g) => str_contains($g, 'geolocation')));
    }

    public function test_mapped_consented_palm_farmer_can_be_ready(): void
    {
        $this->seed();

        $farmer = Farmer::query()->where('public_id', 'FRM-00001')->firstOrFail();
        $this->assertTrue($farmer->hasGeolocation());
        $this->assertNotNull($farmer->consent_at);

        $batch = Batch::query()->where('public_id', 'BAT-00001')->firstOrFail();
        $result = app(ComplianceEngine::class)->evaluate($batch);

        $this->assertSame('ready', $result['status']);
        $this->assertSame([], $result['gaps']);
    }
}
