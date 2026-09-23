<?php

namespace App\Http\Controllers\Admin;

use App\Enums\ContentStatus;
use App\Enums\EnquiryStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCmsContentRequest;
use App\Models\BookingEnquiry;
use App\Models\CmsOffer;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\MediaAsset;
use App\Models\TenantModel;
use App\Models\Testimonial;
use App\Models\User;
use App\Services\Cms\ContentPublisher;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CmsController extends Controller
{
    /** @var array<string, class-string<TenantModel>> */
    private const CONTENT_TYPES = [
        'page' => CmsPage::class,
        'post' => CmsPost::class,
        'service' => CmsService::class,
        'offer' => CmsOffer::class,
        'testimonial' => Testimonial::class,
    ];

    public function index(): View
    {
        return view('admin.cms.index', [
            'pages' => CmsPage::query()->orderByDesc('updated_at')->get(),
            'posts' => CmsPost::query()->orderByDesc('updated_at')->get(),
            'services' => CmsService::query()->orderBy('sort_order')->get(),
            'offers' => CmsOffer::query()->orderByDesc('starts_on')->get(),
            'testimonials' => Testimonial::query()->orderBy('sort_order')->get(),
            'media' => MediaAsset::query()->orderByDesc('created_at')->limit(30)->get(),
            'statuses' => ContentStatus::cases(),
            'enquiries' => BookingEnquiry::query()->with(['service:id,title', 'assignee:id,first_name,last_name'])->orderByDesc('created_at')->limit(100)->get(),
            'enquiryStatuses' => EnquiryStatus::cases(),
            'assignees' => User::query()->where('tenant_id', auth()->user()->tenant_id)->whereIn('role', ['OWNER', 'ADMIN', 'MANAGER', 'DISPATCHER'])->orderBy('first_name')->get(['id', 'first_name', 'last_name']),
        ]);
    }

    public function store(StoreCmsContentRequest $request): JsonResponse
    {
        $data = $request->validated();
        $type = $data['type'];
        unset($data['type']);
        $model = self::CONTENT_TYPES[$type];
        $content = $model::query()->create($this->contentData($type, $data));

        return response()->json(['message' => 'Content saved successfully.', 'content' => $content, 'reload' => true], 201);
    }

    public function update(StoreCmsContentRequest $request, string $type, string $content, ContentPublisher $publisher): JsonResponse
    {
        $data = $request->validated();
        abort_unless($data['type'] === $type, 422, 'The content type does not match.');
        unset($data['type']);
        $updated = $publisher->revise($this->resolveContent($type, $content), $this->contentData($type, $data));

        return response()->json(['message' => 'Content updated and revision saved.', 'content' => $updated, 'reload' => true]);
    }

    public function storeMedia(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageContent(), 403);
        $request->validate([
            'media' => ['required', 'file', 'mimes:jpg,jpeg,png,webp,pdf,mp4,webm', 'max:20480'],
            'alt_text' => ['nullable', 'string', 'max:255'],
        ]);
        $file = $request->file('media');
        $path = $file->store('tenants/'.$request->user()->tenant_id.'/cms', 'public');
        $media = MediaAsset::query()->create([
            'uploaded_by' => $request->user()->getKey(),
            'disk' => 'public',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType() ?? 'application/octet-stream',
            'size_bytes' => $file->getSize(),
            'sha256' => hash_file('sha256', $file->getRealPath()),
            'alt_text' => $request->string('alt_text')->toString() ?: null,
        ]);

        return response()->json(['message' => 'Media uploaded successfully.', 'media' => $media, 'reload' => true], 201);
    }

    public function updateEnquiry(Request $request, BookingEnquiry $bookingEnquiry): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageContent(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::enum(EnquiryStatus::class)],
            'assigned_to' => ['nullable', 'ulid', Rule::exists('users', 'id')->where('tenant_id', $request->user()->tenant_id)],
        ]);
        $status = EnquiryStatus::from($data['status']);
        $bookingEnquiry->update([
            ...$data,
            'contacted_at' => $bookingEnquiry->contacted_at ?? ($status === EnquiryStatus::New ? null : now()),
        ]);

        return response()->json(['message' => 'Lead queue updated.', 'enquiry' => $bookingEnquiry, 'reload' => true]);
    }

    public function publish(Request $request, string $type, string $content, ContentPublisher $publisher): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageContent(), 403);
        $published = $publisher->publish($this->resolveContent($type, $content));

        return response()->json(['message' => 'Content published successfully.', 'content' => $published, 'reload' => true]);
    }

    public function unpublish(Request $request, string $type, string $content, ContentPublisher $publisher): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageContent(), 403);
        $draft = $publisher->unpublish($this->resolveContent($type, $content));

        return response()->json(['message' => 'Content returned to draft.', 'content' => $draft, 'reload' => true]);
    }

    public function preview(Request $request, string $type, string $content): View
    {
        abort_unless($request->user()?->role?->canManageContent(), 403);

        return view('public.preview', ['content' => $this->resolveContent($type, $content), 'type' => $type]);
    }

    public function destroy(Request $request, string $type, string $content): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageContent(), 403);
        $this->resolveContent($type, $content)->delete();

        return response()->json(['message' => 'Content deleted successfully.', 'reload' => true]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function contentData(string $type, array $data): array
    {
        if (in_array($type, ['page', 'post', 'service'], true)) {
            $data['slug'] = ($data['slug'] ?? null) ?: Str::slug((string) $data['title']);
        }

        return match ($type) {
            'page' => [
                ...Arr::only($data, ['og_media_id', 'slug', 'title', 'excerpt', 'body', 'status', 'meta_title', 'meta_description']),
                'meta_keywords' => $this->csv($data['meta_keywords'] ?? null),
            ],
            'post' => [
                ...Arr::only($data, ['featured_media_id', 'slug', 'title', 'excerpt', 'body', 'category', 'status', 'meta_title', 'meta_description']),
                'author_id' => auth()->id(),
                'tags' => $this->csv($data['tags'] ?? null),
            ],
            'service' => [
                ...Arr::only($data, ['slug', 'title', 'body', 'status', 'starting_price', 'duration_minutes']),
                'summary' => $data['excerpt'] ?? null,
            ],
            'offer' => Arr::only($data, ['title', 'body', 'status', 'discount_type', 'discount_value', 'starts_on', 'ends_on']),
            'testimonial' => Arr::only($data, ['customer_name', 'company_name', 'rating', 'quote', 'status']),
        };
    }

    /** @return array<int, string>|null */
    private function csv(?string $value): ?array
    {
        if ($value === null || trim($value) === '') {
            return null;
        }

        return collect(explode(',', $value))->map(fn (string $item): string => trim($item))->filter()->unique()->values()->all();
    }

    private function resolveContent(string $type, string $id): TenantModel
    {
        abort_unless(array_key_exists($type, self::CONTENT_TYPES), 404);
        $model = self::CONTENT_TYPES[$type];

        return $model::query()->findOrFail($id);
    }
}
