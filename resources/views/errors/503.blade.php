@extends('errors.layout')

@section('title', '503 — Restocking in Progress')
@section('code', '503')
@section('badge', 'RESTOCKING IN PROGRESS')
@section('heading', 'We are restocking the shelves right now.')
@section('message', 'Masala Mart is undergoing scheduled maintenance to bring you fresh stock and improved checkout speed. We will be right back online in a few minutes!')

@section('icon')
<svg class="w-10 h-10 sm:w-12 sm:h-12 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 6v6h4.5m4.5 0a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
</svg>
@endsection
