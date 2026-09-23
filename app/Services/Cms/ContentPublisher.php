<?php

namespace App\Services\Cms;

use App\Enums\ContentStatus;
use App\Models\CmsOffer;
use App\Models\CmsPage;
use App\Models\CmsPost;
use App\Models\CmsService;
use App\Models\ContentRevision;
use App\Models\TenantModel;
use App\Models\Testimonial;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class ContentPublisher
{
    /** @param array<string, mixed> $data */
    public function revise(TenantModel $content, array $data): TenantModel
    {
        return DB::transaction(function () use ($content, $data): TenantModel {
            $lockedContent = $content::query()->lockForUpdate()->findOrFail($content->getKey());
            $revisionVersion = $this->nextVersion($lockedContent);
            $this->snapshot($lockedContent, $revisionVersion);

            if ($lockedContent->isFillable('version')) {
                $data['version'] = $revisionVersion;
            }

            $lockedContent->update($data);

            return $lockedContent->refresh();
        });
    }

    public function publish(TenantModel $content): TenantModel
    {
        if (! in_array($content::class, [
            CmsPage::class,
            CmsPost::class,
            CmsService::class,
            CmsOffer::class,
            Testimonial::class,
        ], true)) {
            throw new InvalidArgumentException('This content type cannot be published.');
        }

        return DB::transaction(function () use ($content): TenantModel {
            $lockedContent = $content::query()->lockForUpdate()->findOrFail($content->getKey());

            $revisionVersion = $this->nextVersion($lockedContent);
            $this->snapshot($lockedContent, $revisionVersion);

            $updates = [
                'status' => ContentStatus::Published,
                'published_at' => now(),
            ];

            if ($lockedContent->isFillable('version')) {
                $updates['version'] = $revisionVersion;
            }

            $lockedContent->update($updates);

            return $lockedContent->refresh();
        });
    }

    public function unpublish(TenantModel $content): TenantModel
    {
        $content->update(['status' => ContentStatus::Draft, 'published_at' => null]);

        return $content;
    }

    private function nextVersion(TenantModel $content): int
    {
        return (int) ContentRevision::query()
            ->where('content_type', $content::class)
            ->where('content_id', $content->getKey())
            ->max('version') + 1;
    }

    private function snapshot(TenantModel $content, int $version): void
    {
        ContentRevision::query()->create([
            'content_type' => $content::class,
            'content_id' => $content->getKey(),
            'version' => $version,
            'snapshot' => $content->attributesToArray(),
            'authored_by' => auth()->id(),
        ]);
    }
}
