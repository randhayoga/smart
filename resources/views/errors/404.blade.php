{{--
    404 Not Found error page template.
--}}
@extends('errors.layout')

@section('title', 'Halaman Tidak Ditemukan')
@section('code', '404')
@section('badge', '404 • Halaman Tidak Ditemukan')

@section('icon')
<!-- Lucide FileQuestion -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M14.5 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V7.5L14.5 2z"/>
    <path d="M10 10.3c.2-.4.5-.8.9-1a2.1 2.1 0 0 1 2.6.4c.6.6.8 1.3.5 2.1-.4.9-1.5 1.4-1.5 2.2v.5"/>
    <path d="M12 17h.01"/>
</svg>
@endsection

@section('message')
{{ $exception && $exception->getMessage() ? $exception->getMessage() : 'Maaf, halaman atau tautan yang Anda tuju tidak ditemukan atau tidak lagi tersedia.' }}
@endsection
