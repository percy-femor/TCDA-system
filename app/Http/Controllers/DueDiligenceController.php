<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Services\ComplianceEngine;
use App\Services\CustodyLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DueDiligenceController extends Controller
{
    public function __construct(
        private ComplianceEngine $compliance,
        private CustodyLogger $custody,
    ) {}

    public function index(): View
    {
        $batches = Batch::query()
            ->with(['aggregator', 'mill', 'compliance', 'members'])
            ->whereIn('status', ['mill_received', 'exported'])
            ->latest()
            ->get();

        $waiting = $batches->where('status', 'mill_received');

        return view('dds.index', [
            'ready' => $waiting->filter(fn ($batch) => $batch->compliance?->status === 'ready')->values(),
            'gaps' => $waiting->filter(fn ($batch) => $batch->compliance?->status !== 'ready')->values(),
            'shipped' => $batches->where('status', 'exported')->values(),
        ]);
    }

    public function show(Batch $batch): View
    {
        $batch->load(['aggregator', 'mill', 'exporter', 'members.farmer.plots', 'members.plot', 'custodyEvents.actor', 'compliance']);
        $assessment = $this->compliance->evaluate($batch);

        return view('dds.show', compact('batch', 'assessment'));
    }

    public function export(Request $request, Batch $batch): RedirectResponse
    {
        abort_unless($batch->status === 'mill_received', 422, 'Only mill-received lots can be packed for export.');

        $data = $request->validate([
            'shipment_ref' => ['required', 'string', 'max:80'],
        ]);

        $batch->update([
            'status' => 'exported',
            'exporter_id' => $request->user()->organization_id,
            'shipment_ref' => $data['shipment_ref'],
        ]);

        $this->custody->record($batch, 'export_packed', $request->user(), [
            'shipment_ref' => $data['shipment_ref'],
        ]);

        return back()->with('status', 'Shipment linked. The due-diligence pack still lists any gaps.');
    }
}
