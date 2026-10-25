@extends('layouts.app')
@section('title', 'Buying-point floor')
@section('content')
<div class="page-head">
    <div>
        <h1>Buying-point floor</h1>
        <p class="lede">
            Open a farm-set, add kg from farmers you buy from, then close it for the mill.
            This desk does not weigh trucks or write a buyer pack.
        </p>
    </div>
    <a class="btn" href="{{ route('batches.create') }}">Open a farm-set</a>
</div>

@forelse($openBatches as $batch)
    <div class="job-card">
        <h2>{{ $batch->public_id }} · open · {{ number_format($batch->totalVolumeKg(), 1) }} kg</h2>
        <p class="muted">{{ strtoupper($batch->chain) }} · {{ $batch->capture_channel }} · {{ $batch->farmCount() }} farms so far</p>
        <form method="post" action="{{ route('batches.deliveries', $batch) }}" class="stack" style="max-width:none;margin-top:12px">
            @csrf
            <div class="grid-2">
                <label>Farmer
                    <select name="farmer_id" required>
                        @foreach($farmers->where('chain', $batch->chain) as $farmer)
                            <option value="{{ $farmer->id }}">{{ $farmer->public_id }} — {{ $farmer->name }}</option>
                        @endforeach
                    </select>
                </label>
                <label>Volume (kg)
                    <input type="number" step="0.1" name="volume_kg" required>
                </label>
            </div>
            <div class="grid-2">
                <label>Channel
                    <select name="capture_channel">
                        <option value="desk">Desk</option>
                        <option value="paper">Paper stub</option>
                        <option value="mobile">Mobile</option>
                    </select>
                </label>
                <label>Paper stub
                    <input name="paper_stub_no">
                </label>
            </div>
            <input type="hidden" name="delivered_at" value="{{ now()->toDateString() }}">
            <div class="row-actions">
                <button class="btn" type="submit">Add delivery</button>
            </div>
        </form>
        <form method="post" action="{{ route('batches.close', $batch) }}" style="margin-top:10px">
            @csrf
            <button class="btn secondary" type="submit">Close farm-set for the mill</button>
        </form>
    </div>
@empty
    <div class="job-card">
        <h2>No open farm-set</h2>
        <p class="muted">Open one before farmers start arriving.</p>
        <a class="btn" href="{{ route('batches.create') }}">Open a farm-set</a>
    </div>
@endforelse

@if($closedBatches->isNotEmpty())
<div class="job-card">
    <h2>Already sent toward the mill</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Batch</th>
                <th>Kg</th>
                <th>Status</th>
            </tr>
            </thead>
            <tbody>
            @foreach($closedBatches as $batch)
                <tr>
                    <td><a href="{{ route('batches.show', $batch) }}">{{ $batch->public_id }}</a></td>
                    <td>{{ number_format($batch->totalVolumeKg(), 1) }}</td>
                    <td>{{ str_replace('_', ' ', $batch->status) }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endif
@endsection
