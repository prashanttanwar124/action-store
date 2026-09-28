@extends('errors.layout')

@section('title', '419 — Shopping Session Expired')
@section('code', '419')
@section('badge', 'SESSION EXPIRED')
@section('heading', 'Your shopping session has timed out.')
@section('message', 'For your security, checkout and browsing sessions expire after inactivity. Please refresh the page to continue shopping.')

@section('icon')
<svg class="w-10 h-10 sm:w-12 sm:h-12 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M16.023 9.348h4.992v-.001M2.985 19.644v-4.992m0 0h4.992m-4.993 0 3.181 3.183a8.25 8.25 0 0 0 13.803-3.7M4.031 9.865a8.25 8.25 0 0 1 13.803-3.7l3.181 3.182m0-4.991v4.99" />
</svg>
@endsection
