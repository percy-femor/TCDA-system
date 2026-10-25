<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Farmer;
use App\Models\Organization;
use App\Services\CodeGenerator;
use App\Support\FarmPolygon;
use App\Support\GhanaRegions;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class FarmerController extends Controller
{
    public function index(Request $request): View
    {
        $user = $request->user();
        $farmers = Farmer::query()
            ->visibleTo($user)
            ->with(['plots', 'aggregator'])
            ->when($request->chain, fn ($q, $chain) => $q->where('chain', $chain))
            ->when($request->q, function ($q, $term) {
                $q->where(function ($inner) use ($term) {
                    $inner->where('name', 'like', "%{$term}%")
                        ->orWhere('public_id', 'like', "%{$term}%")
                        ->orWhere('community', 'like', "%{$term}%")
                        ->orWhere('paper_card_no', 'like', "%{$term}%");
                });
            })
            ->latest()
            ->paginate(20)
            ->withQueryString();

        if ($user->role === UserRole::FieldOfficer) {
            $roster = Farmer::query()->visibleTo($user)->with('plots')->latest()->get();

            return view('desks.officer', [
                'farmers' => $farmers,
                'needsConsent' => $roster->whereNull('consent_at')->take(8),
                'needsGps' => $roster->filter(fn (Farmer $farmer) => ! $farmer->hasGeolocation())->take(8),
                'assignedRegion' => $user->operatingRegion(),
                'lockedRegion' => $user->operatingRegion(),
            ]);
        }

        if ($user->role === UserRole::Aggregator) {
            return view('desks.buying-farmers', compact('farmers'));
        }

        return view('farmers.index', [
            'farmers' => $farmers,
            'assignedRegion' => $user->operatingRegion(),
            'seesAllRegions' => $user->canAccessAllRegions(),
        ]);
    }

    public function create(Request $request): View
    {
        abort_unless($request->user()->canEnrolFarmers(), 403, 'This role cannot enrol farmers.');
        abort_unless(
            $request->user()->canAccessAllRegions() || $request->user()->operatingRegion(),
            403,
            'This officer has no region assigned. Ask TCDA to assign one.'
        );

        return view('farmers.create', [
            'aggregators' => Organization::query()
                ->where('type', 'aggregator')
                ->when(
                    ! $request->user()->canAccessAllRegions(),
                    fn ($q) => $q->where('region', $request->user()->operatingRegion())
                )
                ->orderBy('name')
                ->get(),
            'regions' => GhanaRegions::all(),
            'lockedRegion' => $request->user()->canAccessAllRegions() ? null : $request->user()->operatingRegion(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        abort_unless($request->user()->canEnrolFarmers(), 403);

        $data = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'phone' => ['nullable', 'string', 'max:40'],
            'community' => ['required', 'string', 'max:120'],
            'district' => ['required', 'string', 'max:120'],
            'region' => ['required', GhanaRegions::rule()],
            'chain' => ['required', 'in:palm,cashew'],
            'paper_card_no' => ['nullable', 'string', 'max:60'],
            'aggregator_id' => ['nullable', 'exists:organizations,id'],
            'consent' => ['nullable', 'boolean'],
            'geojson' => ['required', 'string'],
            'land_status' => ['required', 'in:documented,customary,unknown'],
            'area_hectares' => ['nullable', 'numeric', 'min:0'],
        ]);

        abort_unless(
            $request->user()->canAccessRegion($data['region']),
            403,
            'You can only enrol farmers in your assigned region.'
        );

        $fence = FarmPolygon::fromGeoJson($data['geojson']);

        $farmer = DB::transaction(function () use ($request, $data, $fence) {
            $farmer = Farmer::create([
                'public_id' => CodeGenerator::next('FRM-', 'farmers'),
                'name' => $data['name'],
                'phone' => $data['phone'] ?? null,
                'community' => $data['community'],
                'district' => $data['district'],
                'region' => $data['region'],
                'chain' => $data['chain'],
                'paper_card_no' => $data['paper_card_no'] ?? null,
                'aggregator_id' => $data['aggregator_id'] ?? $request->user()->organization_id,
                'enrolled_by' => $request->user()->id,
                'consent_at' => $request->boolean('consent') ? now() : null,
            ]);

            $farmer->plots()->create([
                'public_id' => CodeGenerator::next('PLT-', 'plots'),
                'latitude' => $fence['latitude'],
                'longitude' => $fence['longitude'],
                'geojson' => $fence['geojson'],
                'area_hectares' => filled($data['area_hectares'] ?? null) ? $data['area_hectares'] : $fence['area_hectares'],
                'land_status' => $data['land_status'],
                'mapped_at' => now(),
                'mapped_by' => $request->user()->id,
            ]);

            return $farmer;
        });

        return redirect()->route('farmers.show', $farmer)->with('status', 'Farmer enrolled with a farm-land geofence.');
    }

    public function show(Request $request, Farmer $farmer): View
    {
        abort_unless($request->user()->canAccessRegion($farmer->region), 403, 'This farmer is outside your assigned region.');
        $farmer->load(['plots.mappedBy', 'aggregator', 'enrolledBy']);

        return view('farmers.show', compact('farmer'));
    }
}
