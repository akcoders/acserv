@extends('layouts.app')

@section('title', 'AC Service, Repair & Installation — '.__('app.name'))

@push('head')
    <meta name="description" content="Book professional AC servicing, repair, installation, warranty support, and annual maintenance with ACServ.">
    <meta property="og:title" content="ACServ — Reliable AC care">
    <meta property="og:type" content="website">
    <script type="application/ld+json">{!! json_encode([chr(64).'context' => 'https://schema.org', chr(64).'type' => 'HVACBusiness', 'name' => config('app.name'), 'url' => route('home')], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    <section class="public-hero py-5"><div class="container py-5"><div class="row align-items-center g-5"><div class="col-lg-7"><span class="badge rounded-pill text-bg-primary-subtle text-primary-emphasis mb-3">Trusted local AC experts</span><h1 class="display-3 fw-bold lh-1 mb-4">Comfort, restored without the hassle.</h1><p class="lead text-secondary mb-4">Book AC servicing, repair, installation, and warranty support. Track every visit from your phone.</p><div class="d-flex flex-wrap gap-2"><a class="btn btn-primary btn-lg px-4" href="#book-service">Book a service</a><a class="btn btn-outline-dark btn-lg px-4" href="#services">Explore services</a></div></div><div class="col-lg-5"><div class="card content-card hero-service-card"><div class="card-body p-4 p-lg-5"><i class="bi bi-snow2 display-1 text-primary"></i><h2 class="h3 mt-4">Fast, transparent AC care</h2><ul class="list-unstyled mt-4 vstack gap-3"><li><i class="bi bi-check-circle-fill text-success me-2"></i>Verified field technicians</li><li><i class="bi bi-check-circle-fill text-success me-2"></i>Geo-tagged service evidence</li><li><i class="bi bi-check-circle-fill text-success me-2"></i>GST invoices and warranty records</li></ul></div></div></div></div></div></section>
    <section class="py-5 bg-white" id="services">
        <div class="container py-4">
            <div class="text-center mb-5"><p class="text-primary fw-semibold mb-1">Our services</p><h2 class="display-6 fw-bold">Everything your AC needs</h2></div>
            <div class="row g-4">
                @forelse($services as $service)
                    <div class="col-md-6 col-lg-4"><article class="card content-card h-100"><div class="card-body p-4"><i class="bi bi-tools fs-2 text-primary"></i><h3 class="h5 mt-3">{{ $service->title }}</h3><p class="text-secondary">{{ $service->summary }}</p>@if($service->starting_price)<div class="fw-semibold">From ₹{{ number_format((float)$service->starting_price, 2) }}</div>@endif</div></article></div>
                @empty
                    @foreach(['AC servicing', 'AC repair', 'Installation & relocation'] as $fallback)
                        <div class="col-md-4"><div class="card content-card h-100"><div class="card-body p-4"><i class="bi bi-snow fs-2 text-primary"></i><h3 class="h5 mt-3">{{ $fallback }}</h3><p class="text-secondary mb-0">Professional service with transparent job updates and documentation.</p></div></div></div>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>
    @if($offers->isNotEmpty())<section class="py-5" id="offers"><div class="container"><h2 class="h3 mb-4">Current offers</h2><div class="row g-3">@foreach($offers as $offer)<div class="col-md-6"><div class="alert alert-primary h-100 mb-0"><h3 class="h5">{{ $offer->title }}</h3><p class="mb-0">{{ $offer->body }}</p></div></div>@endforeach</div></div></section>@endif
    <section class="py-5 bg-white" id="book-service"><div class="container"><div class="row g-5"><div class="col-lg-5"><p class="text-primary fw-semibold mb-1">Book now</p><h2 class="display-6 fw-bold">Tell us what you need</h2><p class="text-secondary">Our team will call you to confirm the service window and pricing.</p></div><div class="col-lg-7"><div class="card content-card"><div class="card-body p-4"><form method="POST" action="{{ route('website.enquiries.store') }}" data-ajax>@csrf<div class="row g-3"><div class="col-md-6"><label class="form-label">Name</label><input class="form-control" name="name" required></div><div class="col-md-6"><label class="form-label">Phone</label><input class="form-control" name="phone" required></div><div class="col-md-6"><label class="form-label">Email</label><input class="form-control" type="email" name="email"></div><div class="col-md-6"><label class="form-label">Service</label><select class="form-select" name="service_id"><option value="">Choose later</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->title }}</option>@endforeach</select></div><div class="col-md-4"><label class="form-label">Postal code</label><input class="form-control" name="postal_code"></div><div class="col-md-8"><label class="form-label">Message</label><input class="form-control" name="message"></div><div class="col-12"><button class="btn btn-primary btn-lg w-100">Request a callback</button></div></div></form></div></div></div></div></div></section>
    @if($testimonials->isNotEmpty())<section class="py-5"><div class="container"><h2 class="h3 mb-4">Customer stories</h2><div class="row g-4">@foreach($testimonials as $testimonial)<div class="col-md-6 col-lg-4"><figure class="card content-card h-100"><blockquote class="card-body mb-0"><div class="text-warning mb-2">{{ str_repeat('★', $testimonial->rating) }}</div><p>“{{ $testimonial->quote }}”</p><figcaption class="fw-semibold">{{ $testimonial->customer_name }}</figcaption></blockquote></figure></div>@endforeach</div></div></section>@endif
    @if($posts->isNotEmpty())<section class="py-5 bg-white" id="blog"><div class="container"><h2 class="h3 mb-4">AC care guides</h2><div class="row g-4">@foreach($posts as $post)<div class="col-md-4"><article class="card content-card h-100"><div class="card-body"><h3 class="h5">{{ $post->title }}</h3><p class="text-secondary">{{ $post->excerpt }}</p><a href="{{ route('website.post', $post->slug) }}">Read guide <i class="bi bi-arrow-right"></i></a></div></article></div>@endforeach</div></div></section>@endif
@endsection
