<?php

namespace App\Http\Controllers;

use App\Enums\ContentStatus;
use App\Http\Requests\StoreLeadRequest;
use App\Models\BookingEnquiry;
use App\Models\CmsOffer;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\Testimonial;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class WebsiteController extends Controller
{
    public function home(): View
    {
        return view('public.home', [
            'services' => CmsService::query()->where('status', ContentStatus::Published)->orderBy('sort_order')->limit(9)->get(),
            'offers' => CmsOffer::query()->where('status', ContentStatus::Published)->where(fn ($query) => $query->whereNull('starts_on')->orWhere('starts_on', '<=', today()))->where(fn ($query) => $query->whereNull('ends_on')->orWhere('ends_on', '>=', today()))->latest('published_at')->limit(4)->get(),
            'testimonials' => Testimonial::query()->where('status', ContentStatus::Published)->orderBy('sort_order')->limit(8)->get(),
            'posts' => CmsPost::query()->where('status', ContentStatus::Published)->latest('published_at')->limit(3)->get(),
        ]);
    }

    public function page(string $slug): View
    {
        return view('public.page', [
            'page' => CmsPage::query()->with('ogMedia')->where('status', ContentStatus::Published)->where('slug', $slug)->firstOrFail(),
        ]);
    }

    public function post(string $slug): View
    {
        return view('public.post', [
            'post' => CmsPost::query()->with('featuredMedia')->where('status', ContentStatus::Published)->where('slug', $slug)->firstOrFail(),
        ]);
    }

    public function storeLead(StoreLeadRequest $request): JsonResponse
    {
        $data = $request->validated();
        $enquiry = BookingEnquiry::query()->create([
            'cms_service_id' => $data['service_id'] ?? null,
            'name' => $data['name'],
            'phone' => $data['phone'],
            'email' => $data['email'] ?? null,
            'source' => 'WEBSITE',
            'status' => 'NEW',
            'service_type' => $data['service_type'] ?? null,
            'postal_code' => $data['postal_code'] ?? null,
            'message' => $data['message'] ?? null,
            'utm_source' => $data['utm_source'] ?? null,
            'utm_medium' => $data['utm_medium'] ?? null,
            'utm_campaign' => $data['utm_campaign'] ?? null,
        ]);

        return response()->json(['message' => 'Thank you. Our service team will contact you shortly.', 'enquiry_id' => $enquiry->getKey()]);
    }

    public function sitemap(): Response
    {
        $urls = collect([route('home')])
            ->merge(CmsPage::query()->where('status', ContentStatus::Published)->pluck('slug')->map(fn (string $slug): string => route('website.page', $slug)))
            ->merge(CmsPost::query()->where('status', ContentStatus::Published)->pluck('slug')->map(fn (string $slug): string => route('website.post', $slug)));
        $xml = view('public.sitemap', ['urls' => $urls])->render();

        return response($xml, 200, ['Content-Type' => 'application/xml']);
    }

    public function robots(): Response
    {
        return response("User-agent: *\nAllow: /\nSitemap: ".route('website.sitemap')."\n", 200, ['Content-Type' => 'text/plain']);
    }
}
