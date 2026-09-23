<?php

namespace Tests\Feature;

use App\Enums\DocumentStatus;
use App\Enums\EvidenceType;
use App\Enums\JobStatus;
use App\Models\Customer;
use App\Models\Invoice;
use App\Models\Job;
use App\Models\Tenant;
use App\Models\User;
use App\Services\Billing\PdfDocument;
use App\Support\TenantContext;
use Illuminate\Foundation\Testing\LazilyRefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

class PdfDocumentTest extends TestCase
{
    use LazilyRefreshDatabase;

    protected function tearDown(): void
    {
        $this->app->make(TenantContext::class)->clear();

        parent::tearDown();
    }

    public function test_invoice_and_job_card_embed_customer_signatures_and_field_photos(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-PDF-001',
            'status' => JobStatus::Completed,
            'service_type' => 'AC repair',
            'service_cost' => 1200,
            'inspection_remark' => 'Capacitor failed.',
            'completion_remark' => 'Cooling restored.',
        ]);
        $images = [
            'before.jpg' => EvidenceType::Before,
            'after.jpg' => EvidenceType::After,
            'prework.png' => EvidenceType::Signature,
            'completion.png' => EvidenceType::Signature,
        ];
        foreach ($images as $filename => $type) {
            $path = 'jobs/'.$filename;
            $bytes = UploadedFile::fake()->image($filename, $filename === 'prework.png' ? 50 : 120, $filename === 'prework.png' ? 25 : 60)->getContent();
            Storage::disk('local')->put($path, $bytes);
            $job->evidence()->create([
                'uploaded_by' => $technician->getKey(),
                'type' => $type,
                'disk' => 'local',
                'path' => $path,
                'mime_type' => str_ends_with($filename, '.png') ? 'image/png' : 'image/jpeg',
                'size_bytes' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'captured_at' => now(),
                'device_id' => 'test-device',
                'metadata' => ['remark' => 'Photo recorded on site.'],
            ]);
        }
        $job->update([
            'prework_signature_path' => 'jobs/prework.png',
            'customer_signature_path' => 'jobs/completion.png',
        ]);
        $invoice = Invoice::query()->create([
            'job_id' => $job->getKey(),
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'INV-PDF-001',
            'status' => DocumentStatus::Paid,
            'currency' => 'INR',
            'issued_on' => now()->toDateString(),
            'subtotal' => 1200,
            'tax_total' => 216,
            'grand_total' => 1416,
            'paid_total' => 1416,
            'balance_due' => 0,
        ]);
        $invoice->lines()->create([
            'description' => 'AC repair',
            'quantity' => 1,
            'unit_price' => 1200,
            'tax_rate' => 18,
            'line_total' => 1200,
        ]);

        $jobCard = app(PdfDocument::class)->jobCard($job);
        $taxInvoice = app(PdfDocument::class)->invoice($invoice);

        $this->assertStringStartsWith('%PDF-1.4', $jobCard);
        $this->assertSame(4, substr_count($jobCard, '/Subtype /Image'));
        $this->assertSame(1, substr_count($taxInvoice, '/Subtype /Image'));
        $this->assertStringContainsString('/Width 120 /Height 60', $taxInvoice);
        $this->assertStringNotContainsString('/Width 50 /Height 25', $taxInvoice);
        $this->assertStringContainsString('/Filter /DCTDecode', $jobCard);
        $this->assertStringContainsString('/Filter /FlateDecode', $taxInvoice);
        $this->assertSame(1, $this->pageCount($taxInvoice));
        $this->assertLessThanOrEqual(3, $this->pageCount($jobCard));
        foreach ($this->pageStreams($jobCard) as $pageStream) {
            $this->assertGreaterThanOrEqual(9, substr_count($pageStream, 'BT '));
        }
    }

    public function test_invoice_with_many_charge_lines_remains_one_page(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $invoice = Invoice::query()->create([
            'customer_id' => $customer->getKey(),
            'invoice_number' => 'INV-LONG-001',
            'status' => DocumentStatus::Sent,
            'currency' => 'INR',
            'issued_on' => now()->toDateString(),
            'subtotal' => 6000,
            'tax_total' => 1080,
            'grand_total' => 7080,
            'paid_total' => 0,
            'balance_due' => 7080,
            'notes' => str_repeat('Detailed service notes. ', 60),
        ]);
        for ($index = 1; $index <= 60; $index++) {
            $invoice->lines()->create([
                'description' => 'Repair component '.$index.' with a detailed item description',
                'quantity' => 1,
                'unit_price' => 100,
                'tax_rate' => 18,
                'line_total' => 100,
            ]);
        }

        $pdf = app(PdfDocument::class)->invoice($invoice);

        $this->assertSame(1, $this->pageCount($pdf));
        $this->assertSame(0, substr_count($pdf, '/Subtype /Image'));
        $this->assertStringContainsString('47 more items - full list in ERP', $this->pageStreams($pdf)[0]);
        $this->assertStringContainsString('4,700.00', $this->pageStreams($pdf)[0]);
    }

    public function test_job_card_paginates_multiple_photos_without_blank_pages(): void
    {
        Storage::fake('local');
        $tenant = Tenant::factory()->create();
        $technician = User::factory()->for($tenant)->technician()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-MEDIA-001',
            'status' => JobStatus::Completed,
            'service_type' => 'AC repair',
            'description' => str_repeat('Cooling issue identified on site. ', 30),
        ]);
        for ($index = 1; $index <= 10; $index++) {
            $bytes = UploadedFile::fake()->image('photo-'.$index.'.jpg', 120, 60)->getContent();
            $path = 'jobs/photo-'.$index.'.jpg';
            Storage::disk('local')->put($path, $bytes);
            $job->evidence()->create([
                'uploaded_by' => $technician->getKey(),
                'type' => $index % 2 === 0 ? EvidenceType::After : EvidenceType::Before,
                'disk' => 'local',
                'path' => $path,
                'mime_type' => 'image/jpeg',
                'size_bytes' => strlen($bytes),
                'sha256' => hash('sha256', $bytes),
                'captured_at' => now(),
                'device_id' => 'test-device',
                'metadata' => ['remark' => 'Field condition '.$index],
            ]);
        }

        $pdf = app(PdfDocument::class)->jobCard($job);
        $streams = $this->pageStreams($pdf);

        $this->assertSame(count($streams), $this->pageCount($pdf));
        $this->assertLessThanOrEqual(4, count($streams));
        $this->assertSame(10, substr_count($pdf, '/Subtype /Image'));
        foreach ($streams as $pageStream) {
            $this->assertGreaterThanOrEqual(9, substr_count($pageStream, 'BT '));
        }
        $this->assertStringContainsString('CUSTOMER SIGNATURES', end($streams));
    }

    public function test_job_card_without_photos_fits_on_one_page(): void
    {
        $tenant = Tenant::factory()->create();
        $customer = Customer::factory()->for($tenant)->create();
        $this->app->make(TenantContext::class)->set($tenant->getKey());
        $job = Job::query()->create([
            'customer_id' => $customer->getKey(),
            'job_number' => 'JOB-SHORT-001',
            'status' => JobStatus::Completed,
            'service_type' => 'AC inspection',
        ]);

        $pdf = app(PdfDocument::class)->jobCard($job);

        $this->assertSame(1, $this->pageCount($pdf));
    }

    private function pageCount(string $pdf): int
    {
        preg_match('/\/Type \/Pages \/Kids \[[^\]]*\] \/Count (\d+)/', $pdf, $matches);

        return (int) ($matches[1] ?? 0);
    }

    /** @return array<int, string> */
    private function pageStreams(string $pdf): array
    {
        preg_match_all('/\/Filter \/FlateDecode >>\nstream\n(.*?)\nendstream/s', $pdf, $matches);

        return array_map(fn (string $stream): string => gzuncompress($stream) ?: '', $matches[1]);
    }
}
