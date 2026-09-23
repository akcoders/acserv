@extends('layouts.app')
@section('title', 'Preview — '.__('app.name'))
@section('content')<article class="container py-5 public-content"><div class="alert alert-warning">Draft preview · {{ str($type)->headline() }}</div><header class="mx-auto py-4"><h1 class="display-5 fw-bold">{{ $content->title ?? $content->customer_name }}</h1>@if($content->excerpt ?? false)<p class="lead text-secondary">{{ $content->excerpt }}</p>@endif</header><div class="mx-auto fs-5 lh-lg">{!! nl2br(e($content->body ?? $content->quote ?? '')) !!}</div></article>@endsection
