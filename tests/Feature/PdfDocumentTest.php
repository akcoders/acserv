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
            $bytes = UploadedFile::fake()->image($filename, 120, 60)->getContent();
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
        $this->assertSame(2, substr_count($taxInvoice, '/Subtype /Image'));
        $this->assertStringContainsString('/Filter /DCTDecode', $jobCard);
        $this->assertStringContainsString('/Filter /FlateDecode', $taxInvoice);
    }
}
