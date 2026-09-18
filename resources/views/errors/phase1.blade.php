{{--
    Phase 1 Restricted Access Error Page.
    Displayed to authenticated Portal users who are not authorized during Phase 1.
    Renders in English matching Enterprise Portal conventions.
--}}
@extends('errors.layout')

@section('title', 'Application Not Available Yet')
@section('code', '403')
@section('badge', 'Phase 1 - Limited Rollout')

@section('icon')
<!-- Lucide Lock -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <rect width="18" height="11" x="3" y="11" rx="2" ry="2"/>
    <path d="M7 11V7a5 5 0 0 1 10 0v4"/>
</svg>
@endsection

@section('message')
We apologize, the SMART application is currently in a limited rollout (Phase 1) and is not yet available for your account. Please return to the Portal.
@endsection

@section('action')
<a href="{{ $portalUrl ?? config('app.redirect.portal', 'https://portal.ptre.co.id') }}" class="btn-primary">
    <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
        <path d="M18 13v6a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V8a2 2 0 0 1 2-2h6"/>
        <polyline points="15 3 21 3 21 9"/>
        <line x1="10" y1="14" x2="21" y2="3"/>
    </svg>
    <span>Return to Portal</span>
</a>
@endsection
