@extends('layouts.app')

@section('title', ($post->meta_title ?: $post->title).' — '.__('app.name'))

@push('head')
    <meta name="description" content="{{ $post->meta_description ?: $post->excerpt }}">
    <link rel="canonical" href="{{ route('website.post', $post->slug) }}">
    <meta property="og:title" content="{{ $post->title }}">
    <meta property="og:description" content="{{ $post->meta_description ?: $post->excerpt }}">
    @if($post->featuredMedia)
        <meta property="og:image" content="{{ Storage::disk($post->featuredMedia->disk)->url($post->featuredMedia->path) }}">
    @endif
@endpush

@section('content')
    <article>
        <header class="public-article-hero">
            <div class="container">
                <a class="public-article-back" href="{{ route('home') }}#blog"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to guides</a>
                <div class="public-section-label">{{ $post->category ?: 'AC care guide' }}</div>
                <h1>{{ $post->title }}</h1>
                @if($post->excerpt)
                    <p class="lead">{{ $post->excerpt }}</p>
                @endif
                @if($post->published_at)
                    <div class="public-article-meta"><i class="bi bi-calendar3 me-2" aria-hidden="true"></i>Published {{ $post->published_at->format('d M Y') }}</div>
                @endif
            </div>
        </header>
        <div class="public-article-body">
            <div class="container public-article-copy">{!! nl2br(e($post->body)) !!}</div>
        </div>
    </article>
@endsection
