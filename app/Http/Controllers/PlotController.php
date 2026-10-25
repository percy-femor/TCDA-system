<?php

namespace App\Http\Controllers;

use App\Models\Farmer;
use App\Models\Plot;
use App\Services\CodeGenerator;
use App\Support\FarmPolygon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class PlotController extends Controller
{
    public function store(Request $request, Farmer $farmer): RedirectResponse
    {
        abort_unless($request->user()->canHandleFarmer($farmer), 403, 'This farmer is outside your assigned region.');

        $data = $request->validate([
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
            'land_status' => ['required', 'in:documented,customary,unknown'],
            'geojson' => ['nullable', 'string'],
        ]);

        $fence = null;
        if (! empty($data['geojson'])) {
            $fence = FarmPolygon::fromGeoJson($data['geojson']);
        }

        if (! $fence && empty($data['latitude']) && empty($data['longitude'])) {
            return back()->withErrors([
                'geojson' => 'Draw a farm geofence or record a GPS point. Empty maps are stored as gaps, not as mapped plots.',
            ]);
        }

        Plot::create([
            'public_id' => CodeGenerator::next('PLT-', 'plots'),
            'farmer_id' => $farmer->id,
            'latitude' => $fence['latitude'] ?? $data['latitude'] ?? null,
            'longitude' => $fence['longitude'] ?? $data['longitude'] ?? null,
            'geojson' => $fence['geojson'] ?? null,
            'area_hectares' => filled($data['area_hectares'] ?? null) ? $data['area_hectares'] : ($fence['area_hectares'] ?? null),
            'land_status' => $data['land_status'],
            'mapped_at' => now(),
            'mapped_by' => $request->user()->id,
        ]);

        return back()->with('status', 'Plot mapped. This is the geolocation record used for palm EUDR checks.');
    }
}
