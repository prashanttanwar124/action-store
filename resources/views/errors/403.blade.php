@extends('errors.layout')

@section('title', '403 — Restricted Area')
@section('code', '403')
@section('badge', 'RESTRICTED AISLE')
@section('heading', 'Access to this shelf is restricted.')
@section('message', 'You do not have permission to access this area of the store. If you are an administrator or team member, please sign in with an authorized account.')

@section('icon')
<svg class="w-10 h-10 sm:w-12 sm:h-12 stroke-[1.8]" fill="none" viewBox="0 0 24 24" stroke="currentColor">
    <path stroke-linecap="round" stroke-linejoin="round" d="M12 9v3.75m0-10.036A11.959 11.959 0 0 1 3.598 6 11.99 11.99 0 0 0 3 9.75c0 5.592 3.824 10.29 9 11.622 5.176-1.332 9-6.03 9-11.622 0-1.31-.21-2.57-.598-3.75h-.002A11.959 11.959 0 0 1 12 2.714Zm0 13.036h.008v.008H12v-.008Z" />
</svg>
@endsection
