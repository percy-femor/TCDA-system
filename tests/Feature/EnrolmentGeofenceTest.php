<?php

namespace Tests\Feature;

use App\Models\Farmer;
use App\Models\User;
use App\Support\FarmPolygon;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EnrolmentGeofenceTest extends TestCase
{
    use RefreshDatabase;

    public function test_enrolment_requires_a_farm_polygon(): void
    {
        $this->seed();
        $officer = User::query()->where('email', 'officer@tcda.gov.gh')->firstOrFail();

        $this->actingAs($officer)
            ->post(route('farmers.store'), [
                'name' => 'No Fence',
                'community' => 'Dixcove',
                'district' => 'Ahanta West',
                'region' => 'Western',
                'chain' => 'palm',
                'consent' => '1',
                'land_status' => 'customary',
            ])
            ->assertSessionHasErrors('geojson');
    }

    public function test_enrolment_saves_the_farm_geofence(): void
    {
        $this->seed();
        $officer = User::query()->where('email', 'officer@tcda.gov.gh')->firstOrFail();
        $polygon = $this->westernPolygon();

        $this->actingAs($officer)
            ->post(route('farmers.store'), [
                'name' => 'Fence Farmer',
                'community' => 'Dixcove',
                'district' => 'Ahanta West',
                'region' => 'Western',
                'chain' => 'palm',
                'consent' => '1',
                'land_status' => 'customary',
                'geojson' => $polygon,
            ])
            ->assertRedirect();

        $farmer = Farmer::query()->where('name', 'Fence Farmer')->firstOrFail();
        $plot = $farmer->plots()->first();

        $this->assertNotNull($plot);
        $this->assertNotEmpty($plot->geojson);
        $this->assertSame('Polygon', json_decode($plot->geojson, true)['type']);
        $this->assertNotNull($plot->latitude);
        $this->assertGreaterThan(0, $plot->area_hectares);
    }

    public function test_polygon_outside_ghana_is_rejected(): void
    {
        $this->expectException(\Illuminate\Validation\ValidationException::class);
        FarmPolygon::fromGeoJson(json_encode([
            'type' => 'Polygon',
            'coordinates' => [[
                [2.3, 48.8],
                [2.4, 48.8],
                [2.4, 48.9],
                [2.3, 48.9],
                [2.3, 48.8],
            ]],
        ]));
    }

    private function westernPolygon(): string
    {
        return json_encode([
            'type' => 'Polygon',
            'coordinates' => [[
                [-2.2411, 4.8912],
                [-2.2400, 4.8912],
                [-2.2400, 4.8925],
                [-2.2411, 4.8925],
                [-2.2411, 4.8912],
            ]],
        ]);
    }
}
