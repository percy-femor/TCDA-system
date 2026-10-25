@extends('layouts.app')
@section('title', 'Batches')
@section('content')
<div class="page-head">
    <div>
        <h1>All farm-sets</h1>
        <p class="lede">National batch ledger. Aggregators open these; mills and exporters work from their own desks.</p>
    </div>
    <a class="btn" href="{{ route('supervision') }}">Review exceptions</a>
</div>
<form class="filters" method="get">
    <select name="chain" onchange="this.form.submit()">
        <option value="">All chains</option>
        <option value="palm" @selected(request('chain')==='palm')>Palm</option>
        <option value="cashew" @selected(request('chain')==='cashew')>Cashew</option>
    </select>
    <select name="status" onchange="this.form.submit()">
        <option value="">All statuses</option>
        @foreach(['open','closed','mill_received','exported'] as $st)
            <option value="{{ $st }}" @selected(request('status')===$st)>{{ str_replace('_',' ',$st) }}</option>
        @endforeach
    </select>
</form>
<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Batch</th>
                <th>Chain</th>
                <th>Aggregator</th>
                <th>Farms</th>
                <th>Kg</th>
                <th>Status</th>
                <th>Compliance</th>
            </tr>
            </thead>
            <tbody>
            @foreach($batches as $batch)
                <tr>
                    <td><a href="{{ route('batches.show', $batch) }}">{{ $batch->public_id }}</a></td>
                    <td><span class="badge {{ $batch->chain }}">{{ $batch->chain }}</span></td>
                    <td>{{ $batch->aggregator->name }}</td>
                    <td>{{ $batch->farmCount() }}</td>
                    <td>{{ number_format($batch->totalVolumeKg(), 1) }}</td>
                    <td><span class="badge {{ $batch->status }}">{{ str_replace('_',' ',$batch->status) }}</span></td>
                    <td>
                        @if($batch->compliance)
                            <span class="badge {{ $batch->compliance->status }}">{{ $batch->compliance->status }}</span>
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $batches->links() }}</div>
</div>
@endsection
