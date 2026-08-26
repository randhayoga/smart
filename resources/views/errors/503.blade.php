@extends('errors.layout')

@section('title', 'Sistem Sedang Dalam Pemeliharaan')
@section('code', '503')
@section('badge', '503 • Pemeliharaan Sistem')

@section('icon')
<!-- Lucide Wrench -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14.7 6.3a1 1 0 0 0 0 1.4l1.6 1.6a1 1 0 0 0 1.4 0l3.77-3.77a6 6 0 0 1-7.94 7.94l-6.91 6.91a2.12 2.12 0 0 1-3-3l6.91-6.91a6 6 0 0 1 7.94-7.94l-3.76 3.76z"/>
</svg>
@endsection

@section('message')
{{ $exception && $exception->getMessage() ? $exception->getMessage() : 'Kami sedang melakukan pemeliharaan sistem SMART. Mohon maaf atas ketidaknyamanan ini, silakan coba beberapa saat lagi.' }}
@endsection
