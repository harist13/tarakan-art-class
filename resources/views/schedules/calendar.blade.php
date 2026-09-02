@extends('layouts.app')

@section('content')
<div class="d-sm-flex align-items-center justify-content-between mb-4">
    <h1 class="h3 mb-0 text-gray-800 fw-bold">Kalender Jadwal</h1>
    <div class="d-flex gap-2">
        <a href="{{ route('classes.index', ['tab' => 'kalender']) }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-easel2"></i> Manajemen Kelas</a>
        <a href="{{ route('schedules.index') }}" class="btn btn-sm btn-outline-secondary"><i class="bi bi-list-ul"></i> Tampilan Daftar</a>
        <a href="{{ route('schedules.create') }}" class="btn btn-sm btn-primary shadow-sm"><i class="bi bi-plus-lg"></i> Ajukan Replacement</a>
    </div>
</div>

@include('schedules._calendar-panel')
@endsection
