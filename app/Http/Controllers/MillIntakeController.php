<?php

namespace App\Http\Controllers;

use App\Models\Batch;
use App\Models\Organization;
use App\Services\ComplianceEngine;
use App\Services\CustodyLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class MillIntakeController extends Controller
{
    public function __construct(
        private ComplianceEngine $compliance,
        private CustodyLogger $custody,
    ) {}

    public function index(): View
    {
        $user = request()->user();
        $closed = Batch::query()
            ->with(['aggregator', 'compliance', 'members'])
            ->whereIn('status', ['closed', 'mill_received'])
            ->latest()
            ->get();

        $received = $closed->where('status', 'mill_received');
        if ($user->organization_id && ! $user->isTcda()) {
            $received = $received->where('mill_id', $user->organization_id);
        }

        return view('mill.index', [
            'waiting' => $closed->where('status', 'closed')->values(),
            'received' => $received->values(),
            'mills' => Organization::query()->where('type', 'mill')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request, Batch $batch): RedirectResponse
    {
        abort_unless(in_array($batch->status, ['closed', 'mill_received'], true), 422, 'Mill intake needs a closed aggregator batch.');

        $data = $request->validate([
            'mill_id' => ['required', 'exists:organizations,id'],
            'mill_weight_kg' => ['required', 'numeric', 'min:0.1'],
        ]);

        if ($request->user()->organization_id && ! $request->user()->isTcda()) {
            $data['mill_id'] = $request->user()->organization_id;
        }

        $batch->update([
            'status' => 'mill_received',
            'mill_id' => $data['mill_id'],
            'mill_weight_kg' => $data['mill_weight_kg'],
        ]);

        $this->compliance->persist($batch->fresh(['members.farmer.plots', 'members.plot']));
        $this->custody->record($batch, 'mill_intake', $request->user(), [
            'weight_kg' => $data['mill_weight_kg'],
        ]);

        return back()->with('status', 'Mill intake recorded. Farm-set on the batch is unchanged (mixing is expected).');
    }
}
