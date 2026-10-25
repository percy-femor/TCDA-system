@extends('layouts.app')
@section('title', 'National farmer roll')
@section('content')
<div class="page-head">
    <div>
        <h1>National farmer roll</h1>
        <p class="lede">
            Every enrolled farmer across Ghana’s 16 regions. Field officers enrol; you supervise.
        </p>
    </div>
</div>

<form class="filters" method="get">
    <select name="chain" onchange="this.form.submit()">
        <option value="">All chains</option>
        <option value="palm" @selected(request('chain')==='palm')>Palm</option>
        <option value="cashew" @selected(request('chain')==='cashew')>Cashew</option>
    </select>
    <input type="search" name="q" value="{{ request('q') }}" placeholder="Name, ID, community, card no.">
    <button class="btn secondary" type="submit">Search</button>
</form>

<div class="card">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Chain</th>
                <th>Location</th>
                <th>Consent</th>
                <th>GPS</th>
            </tr>
            </thead>
            <tbody>
            @foreach($farmers as $farmer)
                <tr>
                    <td><a href="{{ route('farmers.show', $farmer) }}">{{ $farmer->public_id }}</a></td>
                    <td>{{ $farmer->name }}</td>
                    <td><span class="badge {{ $farmer->chain }}">{{ $farmer->chain }}</span></td>
                    <td>{{ $farmer->community }}, {{ $farmer->district }}, {{ $farmer->region }}</td>
                    <td>{{ $farmer->consent_at ? 'Yes' : 'Missing' }}</td>
                    <td>{{ $farmer->hasGeolocation() ? 'Mapped' : 'Gap' }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $farmers->links() }}</div>
</div>
@endsection
