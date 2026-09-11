{{--
    Illustrated error template extending errors.layout.
--}}
@extends('errors.layout')

@section('title', $__env->yieldContent('title', __('errors.default.title')))
@section('code', $__env->yieldContent('code', 'Error'))
@section('badge', __('errors.default.badge', ['code' => $__env->yieldContent('code', 'Error')]))

@section('icon')
<!-- Lucide AlertCircle -->
<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
    <circle cx="12" cy="12" r="10"/>
    <line x1="12" y1="8" x2="12" y2="12"/>
    <line x1="12" y1="16" x2="12.01" y2="16"/>
</svg>
@endsection

@section('message')
@yield('message', __('errors.default.message'))
@endsection
