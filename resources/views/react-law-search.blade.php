@extends('layouts.app')

@section('title', 'Marokko Biz | Moroccan Law Search')

@push('styles')
  <link rel="preconnect" href="https://fonts.googleapis.com">
  <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
  <link
    href="https://fonts.googleapis.com/css2?family=Manrope:wght@400;500;600;700;800&family=Fraunces:opsz,wght@9..144,600;9..144,700&display=swap"
    rel="stylesheet"
  >
  @vite('resources/css/search-workspace.css')
@endpush

@section('content')
  <div id="root"></div>
@endsection

@push('scripts')
  @vite(['resources/js/search-workspace.js', 'resources/js/search-behaviors.js', 'resources/js/search-translations.js'])
@endpush
