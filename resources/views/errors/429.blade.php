{{--
    429 Too Many Requests / Rate limit exceeded error page template.
--}}
@extends('errors.layout')

@section('title', __('errors.429.title'))
@section('code', '429')
@section('badge', __('errors.429.badge'))

@section('icon')
<!-- Lucide Gauge -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <path d="m12 14 4-4"/>
    <path d="M3.34 19a10 10 0 1 1 17.32 0"/>
</svg>
@endsection

@section('message')
{{ $exception && $exception->getMessage() ? $exception->getMessage() : __('errors.429.message') }}
@endsection
