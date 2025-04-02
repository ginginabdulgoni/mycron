@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <h3>Halo, {{ auth()->user()->name }}</h3>
    <p>Selamat datang di dashboard MyCron!</p>
    @if (auth()->user()->role === 'admin')
        <p><strong>Anda login sebagai Admin.</strong></p>
    @endif
@endsection
