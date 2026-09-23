<?php

namespace Tests\Feature;

use App\Enums\EvidenceType;
use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Job;
use App\Models\JobEvidence;
use App\Models\Tenant;
use App\Models\User;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class AdminJobEvidenceTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_owner_views_own_job_photo_but_not_wrong_job_or_foreign_tenant_photo(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $owner = User::factory()->for($tenant)->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-PHOTO-001',
            'status' => JobStatus::Completed,
            'service_type' => 'AC repair',
        ]);
        $otherJob = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-PHOTO-002',
            'status' => JobStatus::Completed,
            'service_type' => 'AC repair',
        ]);
        $photo = $this->photo($job, $owner, 'photos/own.jpg');

        $foreignTenant = Tenant::factory()->create();
        $foreignCustomer = Customer::factory()->for($foreignTenant)->create();
        $foreignOwner = User::factory()->for($foreignTenant)->create();
        $this->app->make(TenantContext::class)->set($foreignTenant->getKey());
        $foreignJob = Job::query()->create([
            'customer_id' => $foreignCustomer->getKey(),
            'job_number' => 'JOB-PHOTO-FOREIGN',
            'status' => JobStatus::Completed,
            'service_type' => 'AC repair',
        ]);
        $foreignPhoto = $this->photo($foreignJob, $foreignOwner, 'photos/foreign.jpg');
        $this->app->make(TenantContext::class)->set($tenant->getKey());

        $this->actingAs($owner)->get(route('admin.jobs.evidence', [$job, $photo]))
            ->assertOk()
            ->assertHeader('Content-Type', 'image/jpeg');
        $this->get(route('admin.jobs.evidence', [$otherJob, $photo]))->assertNotFound();
        $this->get(route('admin.jobs.evidence', [$foreignJob, $foreignPhoto]))->assertNotFound();
    }

    private function photo(Job $job, User $uploader, string $path): JobEvidence
    {
        $bytes = UploadedFile::fake()->image('photo.jpg', 30, 30)->getContent();
        Storage::disk('local')->put($path, $bytes);

        return $job->evidence()->create([
            'uploaded_by' => $uploader->getKey(),
            'type' => EvidenceType::Before,
            'disk' => 'local',
            'path' => $path,
            'mime_type' => 'image/jpeg',
            'size_bytes' => strlen($bytes),
            'sha256' => hash('sha256', $bytes),
            'captured_at' => now(),
            'device_id' => 'test-device',
        ]);
    }
}
