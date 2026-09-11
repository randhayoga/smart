{{--
    419 Page Expired / CSRF token mismatch error page template.
--}}
@extends('errors.layout')

@section('title', __('errors.419.title'))
@section('code', '419')
@section('badge', __('errors.419.badge'))

@section('icon')
<!-- Lucide Clock -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"/>
    <polyline points="12 6 12 12 16 14"/>
</svg>
@endsection

@section('message')
{{ $exception && $exception->getMessage() ? $exception->getMessage() : __('errors.419.message') }}
@endsection
