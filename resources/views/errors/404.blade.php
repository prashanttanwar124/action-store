@extends('errors.layout')

@section('title', '404 — Aisle Not Found')
@section('code', '404')
@section('badge', 'AISLE NOT FOUND')
@section('heading', 'We couldn’t find that grocery item or page.')
@section('message', 'The product, recipe kit, or page you were looking for has either moved to a different aisle or is currently unavailable.')

@section('icon')
<svg class="w-10 h-10 sm:w-12 sm:h-12 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607ZM13.5 10.5h-6" />
</svg>
@endsection
