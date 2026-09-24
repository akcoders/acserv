@extends('layouts.app')

@section('title', ($page->meta_title ?: $page->title).' — '.__('app.name'))

@push('head')
    <meta name="description" content="{{ $page->meta_description ?: $page->excerpt }}">
    <link rel="canonical" href="{{ route('website.page', $page->slug) }}">
    <meta property="og:title" content="{{ $page->title }}">
    <meta property="og:description" content="{{ $page->meta_description ?: $page->excerpt }}">
    @if($page->ogMedia)
        <meta property="og:image" content="{{ Storage::disk($page->ogMedia->disk)->url($page->ogMedia->path) }}">
    @endif
@endpush

@section('content')
    <article>
        <header class="public-article-hero">
            <div class="container">
                <a class="public-article-back" href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to home</a>
                <div class="public-section-label">ACServ information</div>
                <h1>{{ $page->title }}</h1>
                @if($page->excerpt)
                    <p class="lead mb-0">{{ $page->excerpt }}</p>
                @endif
            </div>
        </header>
        <div class="public-article-body">
            <div class="container public-article-copy">{!! nl2br(e($page->body)) !!}</div>
        </div>
    </article>
@endsection
