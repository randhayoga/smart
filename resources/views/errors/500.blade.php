{{--
    500 Internal Server Error page template.
--}}
@extends('errors.layout')

@section('title', 'Terjadi Kesalahan Server')
@section('code', '500')
@section('badge', '500 • Kesalahan Server')

@section('icon')
<!-- Lucide ServerCrash -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="M6 10H4a2 2 0 0 1-2-2V4a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v4a2 2 0 0 1-2 2h-2"/>
    <path d="M6 14H4a2 2 0 0 0-2 2v4a2 2 0 0 0 2 2h16a2 2 0 0 0 2-2v-4a2 2 0 0 0-2-2h-2"/>
    <path d="M6 6h.01"/>
    <path d="M6 18h.01"/>
    <path d="m14 10-2 4"/>
    <path d="m10 10 2 4"/>
</svg>
@endsection

@section('message')
{{ $exception && $exception->getMessage() ? $exception->getMessage() : 'Maaf, terjadi kendala teknis internal pada sistem kami saat memproses permintaan Anda.' }}
@endsection
