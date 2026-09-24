@extends('layouts.app')

@section('title', 'AC Service, Repair & Installation — '.__('app.name'))

@push('head')
    <meta name="description" content="Book professional AC servicing, repair, installation, warranty support, and annual maintenance with ACServ. Follow every visit from your phone.">
    <meta property="og:title" content="ACServ — AC care with nothing left unclear">
    <meta property="og:type" content="website">
    <script type="application/ld+json">{!! json_encode([chr(64).'context' => 'https://schema.org', chr(64).'type' => 'HVACBusiness', 'name' => config('app.name'), 'url' => route('home')], JSON_UNESCAPED_SLASHES) !!}</script>
@endpush

@section('content')
    <section class="public-hero" aria-labelledby="hero-title">
        <div class="container">
            <div class="row align-items-center gy-5 gx-xl-5">
                <div class="col-lg-6 col-xl-6">
                    <div class="public-eyebrow"><span class="public-eyebrow-line"></span> A better way to care for your AC</div>
                    <h1 id="hero-title" class="public-hero-title">Cool air.<br><span>Clear answers.</span></h1>
                    <p class="public-hero-copy">From a quick service to a complex repair, we make every visit simple to book, easy to follow, and clearly documented.</p>
                    <div class="d-flex flex-wrap gap-3 public-hero-actions">
                        <a class="btn public-button-primary" href="#book-service">Book a service <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a>
                        <a class="btn public-button-outline" href="#services">Explore services <i class="bi bi-arrow-right ms-2" aria-hidden="true"></i></a>
                    </div>
                    <div class="public-hero-assurances" aria-label="Service benefits">
                        <span><i class="bi bi-check2-circle" aria-hidden="true"></i> Clear job updates</span>
                        <span><i class="bi bi-check2-circle" aria-hidden="true"></i> Photo-documented work</span>
                    </div>
                </div>
                <div class="col-lg-6 col-xl-6">
                    <div class="public-hero-visual" aria-hidden="true">
                        <div class="public-visual-orbit public-visual-orbit-one"></div>
                        <div class="public-visual-orbit public-visual-orbit-two"></div>
                        <div class="public-visual-glow"></div>
                        <div class="public-ac-unit">
                            <div class="public-ac-unit-top"><span>ACServ<span class="public-brand-dot">.</span></span><span class="public-ac-indicator"></span></div>
                            <div class="public-ac-unit-center"><i class="bi bi-snow2" aria-hidden="true"></i></div>
                            <div class="public-ac-unit-vent"></div>
                        </div>
                        <div class="public-floating-card public-floating-card-top">
                            <span class="public-floating-icon"><i class="bi bi-shield-check" aria-hidden="true"></i></span>
                            <span><strong>Care with a record</strong><small>Photos, notes and sign-off</small></span>
                        </div>
                        <div class="public-floating-card public-floating-card-bottom">
                            <div class="d-flex align-items-center justify-content-between gap-3 mb-3"><strong>Easy to follow</strong><span class="public-status-dot"></span></div>
                            <div class="public-mini-timeline"><span class="is-done"></span><span class="is-done"></span><span class="is-current"></span><span></span></div>
                            <small>From request to resolution</small>
                        </div>
                        <div class="public-visual-caption">A more comfortable experience, end to end.</div>
                    </div>
                </div>
            </div>
        </div>
    </section>

    <section class="public-proof-strip" aria-label="Why choose ACServ">
        <div class="container">
            <div class="row g-0">
                <div class="col-md-4 public-proof-item"><i class="bi bi-person-check" aria-hidden="true"></i><span><strong>Skilled field team</strong><small>Professional care at your doorstep</small></span></div>
                <div class="col-md-4 public-proof-item"><i class="bi bi-phone" aria-hidden="true"></i><span><strong>Updates you can follow</strong><small>Know where your service stands</small></span></div>
                <div class="col-md-4 public-proof-item"><i class="bi bi-file-earmark-check" aria-hidden="true"></i><span><strong>Everything documented</strong><small>Photos, signatures and invoices</small></span></div>
            </div>
        </div>
    </section>

    <section class="public-section public-services-section" id="services" aria-labelledby="services-title">
        <div class="container">
            <div class="row align-items-end gy-3 mb-5">
                <div class="col-lg-8">
                    <div class="public-section-label">What we do</div>
                    <h2 id="services-title" class="public-section-title">The right care for<br><span>every kind of cool.</span></h2>
                </div>
                <div class="col-lg-4"><p class="public-section-intro mb-0">Routine servicing, repairs and new installations—handled with the same attention to detail.</p></div>
            </div>
            <div class="row g-4">
                @forelse($services as $service)
                    @php($serviceIcon = ['snow2', 'tools', 'wrench-adjustable-circle', 'house-gear', 'wind', 'shield-check', 'fan', 'thermometer-snow', 'gear-wide-connected'][$loop->index % 9])
                    <div class="col-md-6 col-xl-4">
                        <article class="public-service-card h-100">
                            <div class="public-service-card-top"><span class="public-service-icon"><i class="bi bi-{{ $serviceIcon }}" aria-hidden="true"></i></span><span class="public-service-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div>
                            <h3>{{ $service->title }}</h3>
                            <p>{{ $service->summary ?: 'Professional AC care with clear updates throughout the visit.' }}</p>
                            <div class="public-service-card-foot">
                                @if($service->starting_price)
                                    <span>Starts at <strong>₹{{ number_format((float) $service->starting_price, 0) }}</strong></span>
                                @else
                                    <span>Tell us what you need</span>
                                @endif
                                <a href="#book-service" aria-label="Enquire about {{ $service->title }}"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a>
                            </div>
                        </article>
                    </div>
                @empty
                    @foreach(['AC servicing', 'AC repair', 'Installation & relocation'] as $fallback)
                        <div class="col-md-6 col-xl-4">
                            <article class="public-service-card h-100">
                                <div class="public-service-card-top"><span class="public-service-icon"><i class="bi bi-{{ ['snow2', 'tools', 'house-gear'][$loop->index] }}" aria-hidden="true"></i></span><span class="public-service-number">{{ str_pad($loop->iteration, 2, '0', STR_PAD_LEFT) }}</span></div>
                                <h3>{{ $fallback }}</h3>
                                <p>Professional service with clear job updates and documentation.</p>
                                <div class="public-service-card-foot"><span>Tell us what you need</span><a href="#book-service" aria-label="Enquire about {{ $fallback }}"><i class="bi bi-arrow-up-right" aria-hidden="true"></i></a></div>
                            </article>
                        </div>
                    @endforeach
                @endforelse
            </div>
        </div>
    </section>

    <section class="public-section public-process-section" id="how-it-works" aria-labelledby="process-title">
        <div class="container">
            <div class="text-center public-centered-heading mx-auto">
                <div class="public-section-label">The ACServ way</div>
                <h2 id="process-title" class="public-section-title">Service that makes sense<br><span>at every step.</span></h2>
                <p class="public-section-intro">No chasing for updates. A clear path from your first message to a finished job.</p>
            </div>
            <div class="row g-4 public-process-grid">
                <div class="col-md-4"><div class="public-process-step"><div class="public-process-icon"><i class="bi bi-chat-square-text" aria-hidden="true"></i><span>01</span></div><h3>Tell us the problem</h3><p>Share a few details and we’ll get in touch to arrange the right visit.</p></div></div>
                <div class="col-md-4"><div class="public-process-step"><div class="public-process-icon"><i class="bi bi-person-workspace" aria-hidden="true"></i><span>02</span></div><h3>Follow the visit</h3><p>Your technician updates the job as they arrive, inspect and start work.</p></div></div>
                <div class="col-md-4"><div class="public-process-step"><div class="public-process-icon"><i class="bi bi-clipboard2-check" aria-hidden="true"></i><span>03</span></div><h3>Keep the full record</h3><p>Review service notes, photos, billing and sign-off in one place.</p></div></div>
            </div>
        </div>
    </section>

    <section class="public-standard-section" aria-labelledby="standard-title">
        <div class="container">
            <div class="row align-items-center gy-5">
                <div class="col-lg-6"><div class="public-section-label public-label-light">Confidence comes standard</div><h2 id="standard-title">The work is done.<br><span>The details are yours.</span></h2><p>We believe good service should leave you with more than cool air. Every visit can be backed by a transparent service trail.</p><a href="#book-service" class="btn public-button-light">Request your visit <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></a></div>
                <div class="col-lg-5 offset-lg-1"><div class="public-standard-list"><div><i class="bi bi-camera" aria-hidden="true"></i><span><strong>Before & after photos</strong><small>See the condition and the completed work.</small></span></div><div><i class="bi bi-pen" aria-hidden="true"></i><span><strong>Digital customer sign-off</strong><small>Agree on the job before and after service.</small></span></div><div><i class="bi bi-receipt" aria-hidden="true"></i><span><strong>Clear billing records</strong><small>Know which parts and services were used.</small></span></div></div></div>
            </div>
        </div>
    </section>

    @if($offers->isNotEmpty())
        <section class="public-section public-offers-section" id="offers" aria-labelledby="offers-title">
            <div class="container">
                <div class="public-section-label">A little extra</div>
                <h2 id="offers-title" class="public-section-title mb-5">Offers worth a look.</h2>
                <div class="row g-4">
                    @foreach($offers as $offer)
                        <div class="col-md-6"><article class="public-offer-card h-100"><span class="public-offer-tag"><i class="bi bi-stars me-2" aria-hidden="true"></i>Current offer</span><h3>{{ $offer->title }}</h3><p>{{ $offer->body }}</p><a href="#book-service">Ask us about this offer <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a></article></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    <section class="public-section public-booking-section" id="book-service" aria-labelledby="booking-title">
        <div class="container">
            <div class="row gy-5 gx-xl-5 align-items-center">
                <div class="col-lg-5">
                    <div class="public-section-label">Let’s get started</div>
                    <h2 id="booking-title" class="public-section-title">Let’s bring back<br><span>the comfort.</span></h2>
                    <p class="public-section-intro">Tell us what’s happening with your AC. Our team will contact you to confirm the visit and discuss the next steps.</p>
                    <div class="public-booking-note"><span><i class="bi bi-chat-dots" aria-hidden="true"></i></span><p class="mb-0">No commitment from this form—just a conversation about what you need.</p></div>
                </div>
                <div class="col-lg-7">
                    <div class="public-booking-card">
                        <div class="d-flex flex-wrap align-items-start justify-content-between gap-3 mb-4"><div><div class="public-form-kicker">Service request</div><h3 class="mb-1">How can we help?</h3><p class="mb-0">We’ll use these details only to follow up on your request.</p></div><span class="public-form-mark"><i class="bi bi-snow2" aria-hidden="true"></i></span></div>
                        <form method="POST" action="{{ route('website.enquiries.store') }}" data-ajax>
                            @csrf
                            <div class="row g-3">
                                <div class="col-md-6"><label class="form-label" for="enquiry-name">Your name <span aria-hidden="true">*</span></label><input class="form-control" id="enquiry-name" name="name" autocomplete="name" maxlength="160" required></div>
                                <div class="col-md-6"><label class="form-label" for="enquiry-phone">Phone number <span aria-hidden="true">*</span></label><input class="form-control" id="enquiry-phone" type="tel" name="phone" autocomplete="tel" maxlength="20" required></div>
                                <div class="col-md-6"><label class="form-label" for="enquiry-email">Email address <span class="public-optional">Optional</span></label><input class="form-control" id="enquiry-email" type="email" name="email" autocomplete="email" maxlength="255"></div>
                                <div class="col-md-6"><label class="form-label" for="enquiry-postal-code">Postal code <span class="public-optional">Optional</span></label><input class="form-control" id="enquiry-postal-code" name="postal_code" autocomplete="postal-code" maxlength="12"></div>
                                <div class="col-12"><label class="form-label" for="enquiry-service">What do you need?</label><select class="form-select" id="enquiry-service" name="service_id"><option value="">I’m not sure yet</option>@foreach($services as $service)<option value="{{ $service->id }}">{{ $service->title }}</option>@endforeach</select></div>
                                <div class="col-12"><label class="form-label" for="enquiry-message">A little more detail <span class="public-optional">Optional</span></label><textarea class="form-control" id="enquiry-message" name="message" rows="3" maxlength="3000" placeholder="Tell us what’s happening with your AC"></textarea></div>
                                <div class="col-12 d-flex flex-wrap align-items-center justify-content-between gap-3 mt-4"><span class="public-form-privacy"><i class="bi bi-lock" aria-hidden="true"></i> Your details stay with our service team.</span><button class="btn public-button-primary" type="submit">Request a callback <i class="bi bi-arrow-up-right ms-2" aria-hidden="true"></i></button></div>
                            </div>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </section>

    @if($testimonials->isNotEmpty())
        <section class="public-section public-testimonials-section" aria-labelledby="stories-title">
            <div class="container">
                <div class="public-section-label">From our customers</div>
                <h2 id="stories-title" class="public-section-title mb-5">Comfort, in their words.</h2>
                <div class="row g-4">
                    @foreach($testimonials as $testimonial)
                        <div class="col-md-6 col-xl-4"><figure class="public-testimonial-card h-100 mb-0"><div class="public-stars" aria-label="{{ $testimonial->rating }} out of 5 stars">{{ str_repeat('★', min(5, max(0, (int) $testimonial->rating))) }}</div><blockquote>“{{ $testimonial->quote }}”</blockquote><figcaption><span class="public-quote-avatar" aria-hidden="true">{{ mb_substr($testimonial->customer_name, 0, 1) }}</span><span><strong>{{ $testimonial->customer_name }}</strong>@if($testimonial->company_name)<small>{{ $testimonial->company_name }}</small>@endif</span></figcaption></figure></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif

    @if($posts->isNotEmpty())
        <section class="public-section public-blog-section" id="blog" aria-labelledby="blog-title">
            <div class="container">
                <div class="public-section-label">Helpful reads</div>
                <h2 id="blog-title" class="public-section-title mb-5">Know your AC better.</h2>
                <div class="row g-4">
                    @foreach($posts as $post)
                        <div class="col-md-6 col-xl-4"><article class="public-blog-card h-100"><div class="public-blog-art public-blog-art-{{ ($loop->index % 3) + 1 }}" aria-hidden="true"><i class="bi bi-{{ ['snow2', 'lightbulb', 'wind'][$loop->index % 3] }}"></i></div><div class="public-blog-body"><span class="public-blog-category">{{ $post->category ?: 'AC care' }}</span><h3><a href="{{ route('website.post', $post->slug) }}">{{ $post->title }}</a></h3><p>{{ $post->excerpt }}</p><a class="public-text-link" href="{{ route('website.post', $post->slug) }}">Read article <i class="bi bi-arrow-up-right ms-1" aria-hidden="true"></i></a></div></article></div>
                    @endforeach
                </div>
            </div>
        </section>
    @endif
@endsection
