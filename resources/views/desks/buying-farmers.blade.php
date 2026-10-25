@extends('layouts.app')
@section('title', 'Farmers I buy from')
@section('content')
<div class="page-head">
    <div>
        <h1>Farmers I buy from</h1>
        <p class="lede">A directory for adding deliveries. Enrolment is a field-officer job; you only need these names at the scale.</p>
    </div>
</div>
<div class="job-card">
    <div class="table-wrap">
        <table>
            <thead>
            <tr>
                <th>ID</th>
                <th>Name</th>
                <th>Chain</th>
                <th>Community</th>
            </tr>
            </thead>
            <tbody>
            @foreach($farmers as $farmer)
                <tr>
                    <td><a href="{{ route('farmers.show', $farmer) }}">{{ $farmer->public_id }}</a></td>
                    <td>{{ $farmer->name }}</td>
                    <td>{{ $farmer->chain }}</td>
                    <td>{{ $farmer->community }}, {{ $farmer->region }}</td>
                </tr>
            @endforeach
            </tbody>
        </table>
    </div>
    <div style="margin-top:16px">{{ $farmers->links() }}</div>
</div>
@endsection
