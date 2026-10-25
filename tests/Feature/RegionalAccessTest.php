<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class RegionalAccessTest extends TestCase
{
    use RefreshDatabase;

    public function test_western_officer_cannot_open_a_bono_farmer(): void
    {
        $this->seed();

        $officer = User::query()->where('email', 'officer@tcda.gov.gh')->firstOrFail();
        $bonoFarmer = Farmer::query()->where('public_id', 'FRM-00003')->firstOrFail();

        $this->assertSame('Western', $officer->region);
        $this->assertSame('Bono', $bonoFarmer->region);

        $this->actingAs($officer)
            ->get(route('farmers.show', $bonoFarmer))
            ->assertForbidden();

        $this->actingAs($officer)
            ->get(route('officer.home'))
            ->assertOk()
            ->assertSee('Kofi Adjei')
            ->assertDontSee('Seidu Abdulai');
    }

    public function test_officer_cannot_enrol_outside_assigned_region(): void
    {
        $this->seed();
        $officer = User::query()->where('email', 'officer@tcda.gov.gh')->firstOrFail();

        $this->actingAs($officer)
            ->post(route('farmers.store'), [
                'name' => 'Outside',
                'community' => 'Tamale',
                'district' => 'Tamale Metro',
                'region' => 'Northern',
                'chain' => 'cashew',
                'consent' => '1',
                'land_status' => 'customary',
                'geojson' => json_encode([
                    'type' => 'Polygon',
                    'coordinates' => [[
                        [-0.85, 9.40],
                        [-0.84, 9.40],
                        [-0.84, 9.41],
                        [-0.85, 9.41],
                        [-0.85, 9.40],
                    ]],
                ]),
            ])
            ->assertForbidden();
    }

    public function test_tcda_can_see_every_region(): void
    {
        $this->seed();
        $tcda = User::query()->where('email', 'tcda@tcda.gov.gh')->firstOrFail();

        $this->actingAs($tcda)
            ->get(route('farmers.index'))
            ->assertOk()
            ->assertSee('Kofi Adjei')
            ->assertSee('Seidu Abdulai');
    }
}
