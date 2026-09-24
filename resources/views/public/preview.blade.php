@extends('layouts.app')

@section('title', 'Preview — '.__('app.name'))

@section('content')
    <article>
        <header class="public-article-hero">
            <div class="container">
                <a class="public-article-back" href="{{ route('home') }}"><i class="bi bi-arrow-left" aria-hidden="true"></i> Back to home</a>
                <div class="alert alert-warning" role="note">Draft preview · {{ str($type)->headline() }}</div>
                <h1>{{ $content->title ?? $content->customer_name }}</h1>
                @if($content->excerpt ?? false)
                    <p class="lead mb-0">{{ $content->excerpt }}</p>
                @endif
            </div>
        </header>
        <div class="public-article-body">
            <div class="container public-article-copy">{!! nl2br(e($content->body ?? $content->quote ?? '')) !!}</div>
        </div>
    </article>
@endsection
