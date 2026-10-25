<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Farmer;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SupervisionController extends Controller
{
    public function __invoke(Request $request): View
    {
        $user = $request->user();

        $batches = Batch::query()
            ->with(['aggregator', 'compliance', 'members.farmer.plots'])
            ->when($request->chain, fn ($q, $chain) => $q->where('chain', $chain))
            ->when(
                ! $user->canAccessAllRegions() && $user->operatingRegion(),
                fn ($q) => $q->whereHas('members.farmer', fn ($f) => $f->where('region', $user->operatingRegion()))
            )
            ->latest()
            ->get();

        $unmapped = Farmer::query()
            ->visibleTo($user)
            ->with('plots')
            ->whereDoesntHave('plots', function ($q) {
                $q->whereNotNull('latitude')->orWhereNotNull('geojson');
            })
            ->orderBy('chain')
            ->orderBy('name')
            ->get();

        $noConsent = Farmer::query()->visibleTo($user)->whereNull('consent_at')->orderBy('name')->get();

        return view('supervision.index', compact('batches', 'unmapped', 'noConsent'));
    }
}
