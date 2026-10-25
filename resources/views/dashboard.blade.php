@extends('layouts.app')
@section('title', 'National command')
@section('content')
<div class="page-head">
    <div>
        <h1>National command</h1>
        <p class="lede">
            Registry overview across Ghana’s 16 regions. You assign officers and watch gaps.
            You do not stand at a buying point, weighbridge, or export desk.
        </p>
    </div>
    <a class="btn" href="{{ route('supervision') }}">Review exceptions</a>
</div>

<div class="stats">
    <div class="stat"><b>{{ $farmerCount }}</b><span>Farmers on register</span></div>
    <div class="stat"><b>{{ $palmFarmers }} / {{ $cashewFarmers }}</b><span>Palm / cashew</span></div>
    <div class="stat"><b>{{ $plotCount }}</b><span>Mapped plots</span></div>
    <div class="stat"><b>{{ $gapBatches }}</b><span>Lots with compliance gaps</span></div>
</div>

<div class="card">
    <h2>Recent farm-sets</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Batch</th>
                <th>Chain</th>
                <th>Aggregator</th>
                <th>Status</th>
                <th>Compliance</th>
            </tr>
            </thead>
            <tbody>
            @forelse($recentBatches as $batch)
                <tr>
                    <td><a href="{{ route('batches.show', $batch) }}">{{ $batch->public_id }}</a></td>
                    <td><span class="badge {{ $batch->chain }}">{{ $batch->chain }}</span></td>
                    <td>{{ $batch->aggregator->name }}</td>
                    <td><span class="badge {{ $batch->status }}">{{ str_replace('_', ' ', $batch->status) }}</span></td>
                    <td>
                        @if($batch->compliance)
                            <span class="badge {{ $batch->compliance->status }}">{{ $batch->compliance->status }}</span>
                        @else
                            <span class="muted">Not assessed</span>
                        @endif
                    </td>
                </tr>
            @empty
                <tr><td colspan="5" class="muted">No batches yet.</td></tr>
            @endforelse
            </tbody>
        </table>
    </div>
</div>
@endsection
