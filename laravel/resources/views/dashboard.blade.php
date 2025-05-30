@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h3>Halo, {{ auth()->user()->name }}</h3>
    <p>Selamat datang di dashboard MyCron!</p>

    @if (auth()->user()->role === 'admin')
        <p><strong>Anda login sebagai Admin.</strong></p>

        <div class="row my-3">
            <!-- Card Total Cron Aktif -->
            <div class="col-md-6">
                <div class="card text-white bg-success shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Total Cron Aktif</h5>
                        <h3>{{ $totalActive }}</h3>
                    </div>
                </div>
            </div>

            <!-- Card Total Cron Nonaktif -->
            <div class="col-md-6">
                <div class="card text-white bg-danger shadow-sm">
                    <div class="card-body">
                        <h5 class="card-title">Total Cron Nonaktif</h5>
                        <h3>{{ $totalInactive }}</h3>
                    </div>
                </div>
            </div>
        </div>
    @endif
@endsection
