@extends('errors.layout')

@section('title', '500 — Server Encountered An Issue')
@section('code', '500')
@section('badge', 'KITCHEN ISSUE')
@section('heading', 'Our store servers hit a temporary bump.')
@section('message', 'Something unexpected happened on our end while preparing your request. Our technical chefs have been alerted and are resolving the issue.')

@section('icon')
<svg class="w-10 h-10 sm:w-12 sm:h-12 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
</svg>
@endsection
