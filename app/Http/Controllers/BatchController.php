<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\Batch;
use App\Models\Farmer;
use App\Models\Organization;
use App\Services\CodeGenerator;
use App\Services\ComplianceEngine;
use App\Services\CustodyLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class BatchController extends Controller
{
    public function __construct(
        private ComplianceEngine $compliance,
        private CustodyLogger $custody,
    ) {}

    public function index(Request $request): View
    {
        $user = $request->user();

        if ($user->role === UserRole::Aggregator) {
            $mine = Batch::query()
                ->with(['aggregator', 'members.farmer', 'compliance'])
                ->when($user->organization_id, fn ($q) => $q->where('aggregator_id', $user->organization_id));

            return view('desks.aggregator', [
                'openBatches' => (clone $mine)->where('status', 'open')->latest()->get(),
                'closedBatches' => (clone $mine)->where('status', '!=', 'open')->latest()->limit(8)->get(),
                'farmers' => Farmer::query()->visibleTo($user)->orderBy('name')->get(),
            ]);
        }

        $batches = Batch::query()
            ->with(['aggregator', 'compliance', 'members'])
            ->when($request->chain, fn ($q, $chain) => $q->where('chain', $chain))
            ->when($request->status, fn ($q, $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('batches.index', compact('batches'));
    }

    public function create(): View
    {
        $user = request()->user();
        $aggregators = Organization::query()->where('type', 'aggregator')->orderBy('name')->get();

        return view('batches.create', compact('aggregators', 'user'));
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'chain' => ['required', 'in:palm,cashew'],
            'aggregator_id' => ['required', 'exists:organizations,id'],
            'capture_channel' => ['required', 'in:paper,mobile,desk'],
            'notes' => ['nullable', 'string'],
        ]);

        $batch = Batch::create([
            'public_id' => CodeGenerator::next('BAT-', 'batches'),
            'chain' => $data['chain'],
            'status' => 'open',
            'capture_channel' => $data['capture_channel'],
            'aggregator_id' => $data['aggregator_id'],
            'opened_by' => $request->user()->id,
            'notes' => $data['notes'] ?? null,
        ]);

        $this->custody->record($batch, 'opened', $request->user(), [
            'channel' => $batch->capture_channel,
        ]);

        return redirect()->route('batches.show', $batch)->with('status', 'Batch opened. Add farm deliveries before closing.');
    }

    public function show(Request $request, Batch $batch): View
    {
        $batch->load(['aggregator', 'mill', 'exporter', 'members.farmer.plots', 'members.plot', 'custodyEvents.actor', 'compliance']);
        $assessment = $this->compliance->evaluate($batch);
        $farmers = Farmer::query()
            ->visibleTo($request->user())
            ->where('chain', $batch->chain)
            ->orderBy('name')
            ->get();

        return view('batches.show', compact('batch', 'assessment', 'farmers'));
    }

    public function addDelivery(Request $request, Batch $batch): RedirectResponse
    {
        abort_unless($batch->status === 'open', 422, 'Deliveries can only be added to an open batch.');

        $data = $request->validate([
            'farmer_id' => ['required', 'exists:farmers,id'],
            'volume_kg' => ['required', 'numeric', 'min:0.1'],
            'paper_stub_no' => ['nullable', 'string', 'max:60'],
            'capture_channel' => ['required', 'in:paper,mobile,desk'],
            'delivered_at' => ['required', 'date'],
        ]);

        $farmer = Farmer::with('plots')->findOrFail($data['farmer_id']);
        abort_unless($request->user()->canAccessRegion($farmer->region), 403, 'That farmer is outside your assigned region.');
        abort_if($farmer->chain !== $batch->chain, 422, 'Farmer chain does not match this batch.');

        $batch->members()->create([
            'farmer_id' => $farmer->id,
            'plot_id' => $farmer->plots->first()?->id,
            'volume_kg' => $data['volume_kg'],
            'paper_stub_no' => $data['paper_stub_no'] ?? null,
            'capture_channel' => $data['capture_channel'],
            'delivered_at' => $data['delivered_at'],
            'captured_by' => $request->user()->id,
        ]);

        $this->custody->record($batch, 'delivery_added', $request->user(), [
            'farmer' => $farmer->public_id,
            'volume_kg' => $data['volume_kg'],
            'channel' => $data['capture_channel'],
        ]);

        return back()->with('status', 'Delivery recorded against this batch.');
    }

    public function close(Request $request, Batch $batch): RedirectResponse
    {
        abort_unless($batch->status === 'open', 422, 'Batch is already closed.');
        abort_if($batch->members()->count() === 0, 422, 'Close is blocked until at least one delivery is recorded.');

        $batch->update([
            'status' => 'closed',
            'closed_at' => now(),
        ]);

        $assessment = $this->compliance->persist($batch->fresh('members.farmer.plots', 'members.plot'));
        $this->custody->record($batch, 'closed', $request->user(), [
            'compliance' => $assessment->status,
            'gaps' => $assessment->gaps,
        ]);

        return back()->with('status', 'Batch closed. Compliance gaps stay visible — they are not filled in.');
    }
}
