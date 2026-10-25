@extends('layouts.app')
@section('title', 'Supervision')
@section('content')
<div class="page-head">
    <div>
        <h1>TCDA supervision</h1>
        <p class="lede">Data-quality exceptions for the pilot. Completeness first — not a buyer-facing dashboard.</p>
    </div>
</div>

<form class="filters" method="get">
    <select name="chain" onchange="this.form.submit()">
        <option value="">All chains</option>
        <option value="palm" @selected(request('chain')==='palm')>Palm</option>
        <option value="cashew" @selected(request('chain')==='cashew')>Cashew</option>
    </select>
</form>

<div class="stats">
    <div class="stat"><b>{{ $batches->count() }}</b><span>Batches in view</span></div>
    <div class="stat"><b>{{ $batches->filter(fn ($b) => $b->compliance?->status === 'gaps')->count() }}</b><span>With gaps</span></div>
    <div class="stat"><b>{{ $unmapped->count() }}</b><span>Farmers without GPS</span></div>
    <div class="stat"><b>{{ $noConsent->count() }}</b><span>Missing consent</span></div>
</div>

<div class="card">
    <h2>Batch quality</h2>
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>Batch</th>
                <th>Chain</th>
                <th>Status</th>
                <th>Gaps</th>
            </tr>
            </thead>
            <tbody>
            @foreach($batches as $batch)
                <tr>
                    <td><a href="{{ route('batches.show', $batch) }}">{{ $batch->public_id }}</a></td>
                    <td><span class="badge {{ $batch->chain }}">{{ $batch->chain }}</span></td>
                    <td>{{ str_replace('_',' ',$batch->status) }}</td>
                    <td>
                        @if($batch->compliance?->gaps)
                            {{ count($batch->compliance->gaps) }} issues
                        @else
                            —
                        @endif
                    </td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>

<div class="card">
    <h2>Unmapped farmers</h2>
    <div class="table-wrap">
        <table>
            <thead><tr><th>ID</th><th>Name</th><th>Chain</th><th>Community</th></tr></thead>
            <tbody>
            @foreach($unmapped as $farmer)
                <tr>
                    <td><a href="{{ route('farmers.show', $farmer) }}">{{ $farmer->public_id }}</a></td>
                    <td>{{ $farmer->name }}</td>
                    <td><span class="badge {{ $farmer->chain }}">{{ $farmer->chain }}</span></td>
                    <td>{{ $farmer->community }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
</div>
@endsection
