<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Farmer;
use App\Models\Plot;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(): View|RedirectResponse
    {
        $user = request()->user();
        if (! $user->isTcda()) {
            return redirect()->route($user->homeRoute());
        }

        $farmers = Farmer::query()->visibleTo($user);
        $plots = Plot::query()->whereHas('farmer', fn ($q) => $q->visibleTo($user));

        return view('dashboard', [
            'farmerCount' => (clone $farmers)->count(),
            'palmFarmers' => (clone $farmers)->where('chain', 'palm')->count(),
            'cashewFarmers' => (clone $farmers)->where('chain', 'cashew')->count(),
            'plotCount' => (clone $plots)->count(),
            'unmappedPlots' => (clone $plots)->whereNull('latitude')->whereNull('geojson')->count(),
            'openBatches' => Batch::query()->where('status', 'open')->count(),
            'gapBatches' => Batch::query()->whereHas('compliance', fn ($q) => $q->where('status', 'gaps'))->count(),
            'recentBatches' => Batch::query()->with(['aggregator', 'compliance'])->latest()->limit(8)->get(),
            'assignedRegion' => $user->operatingRegion(),
            'seesAllRegions' => $user->canAccessAllRegions(),
        ]);
    }
}
