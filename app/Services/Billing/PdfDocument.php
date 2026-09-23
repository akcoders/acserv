<?php

namespace App\Services\Billing;

use App\Enums\CollectionStatus;
use App\Enums\EvidenceType;
use App\Models\Invoice;
use App\Models\Job;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

class PdfDocument
{
    public function invoice(Invoice $invoice): string
    {
        $invoice->loadMissing(['customer', 'lines', 'payments', 'job.asset', 'job.assignments.technician']);
        $job = $invoice->job;
        $sheet = new PdfCanvas('Tax invoice', $invoice->invoice_number);

        $sheet->section('Billing information');
        $sheet->details([
            'Invoice number' => $invoice->invoice_number,
            'Job reference' => $job?->job_number ?? 'Direct invoice',
            'Customer' => $invoice->customer?->name ?? 'Customer',
            'Contact' => trim(($invoice->customer?->phone ?? '').'  '.($invoice->customer?->email ?? '')) ?: '-',
            'Issued on' => $invoice->issued_on?->format('d M Y') ?? '-',
            'Due on' => $invoice->due_on?->format('d M Y') ?? '-',
            'Invoice status' => str($invoice->status->value)->lower()->headline()->toString(),
            'Supplier GSTIN' => $invoice->supplier_gstin ?: '-',
        ]);
        $sheet->note('Billing address: '.$this->address($invoice->billing_address));
        if ($job) {
            $sheet->note('Service: '.$job->service_type.' | Technician: '.$job->assignments->pluck('technician.name')->filter()->join(', '));
            if ($job->asset) {
                $sheet->note('Equipment: '.$job->asset->name.' / '.$job->asset->brand.' '.$job->asset->model.' / S/N '.$job->asset->serial_number);
            }
        }

        $sheet->section('Itemized charges');
        $widths = [221, 51, 72, 62, 105];
        $sheet->tableHeader(['Service / item', 'Qty', 'Rate', 'Tax', 'Amount'], $widths);
        foreach ($invoice->lines as $index => $line) {
            $sheet->tableRow([
                $line->description.($line->hsn_code ? ' / HSN '.$line->hsn_code : ''),
                number_format((float) $line->quantity, 2),
                'INR '.number_format((float) $line->unit_price, 2),
                number_format((float) $line->tax_rate, 2).'%',
                'INR '.number_format((float) $line->line_total, 2),
            ], $widths, $index % 2 === 0);
        }
        if ($invoice->lines->isEmpty()) {
            $sheet->tableRow(['No charge lines recorded', '-', '-', '-', '-'], $widths);
        }

        $sheet->section('Invoice summary');
        $sheet->total('Subtotal', $this->money($invoice->subtotal));
        $sheet->total('Discount', $this->money($invoice->discount_total));
        $sheet->total('GST / tax', $this->money($invoice->tax_total));
        $sheet->total('Grand total', $this->money($invoice->grand_total), true);
        $sheet->total('Paid', $this->money($invoice->paid_total));
        $sheet->total('Balance due', $this->money($invoice->balance_due), true);

        if ($invoice->payments->isNotEmpty()) {
            $sheet->section('Payment record');
            $sheet->tableHeader(['Receipt', 'Method', 'Status', 'Date', 'Amount'], $widths);
            foreach ($invoice->payments as $index => $payment) {
                $sheet->tableRow([
                    $payment->payment_number,
                    $payment->mode->value,
                    $payment->status->value,
                    $payment->paid_at?->format('d M Y') ?? '-',
                    $this->money($payment->amount),
                ], $widths, $index % 2 === 0);
            }
        }

        if ($job) {
            $sheet->section('Customer authorization');
            $sheet->imageCard('Signed approval before work', $this->storedImage('local', $job->prework_signature_path), 'Customer approved the inspected work before service began.', 100);
            $sheet->imageCard('Signed completion acknowledgement', $this->storedImage('local', $job->customer_signature_path), 'Customer acknowledged the completed service.', 100);
        }
        if ($invoice->notes) {
            $sheet->section('Notes');
            $sheet->note($invoice->notes);
        }

        return $sheet->output();
    }

    public function jobCard(Job $job): string
    {
        $job->loadMissing([
            'customer', 'asset', 'branch', 'assignments.technician', 'checklistItems',
            'partConsumptions.inventoryItem', 'partConsumptions.stockLocation', 'evidence.uploader',
            'invoice.lines', 'paymentCollections.technician',
        ]);
        $sheet = new PdfCanvas('Job card', $job->job_number);

        $sheet->section('Visit and customer');
        $sheet->details([
            'Job number' => $job->job_number,
            'Current stage' => str($job->status->value)->lower()->headline()->toString(),
            'Customer' => $job->customer?->name ?? '-',
            'Phone / email' => trim(($job->customer?->phone ?? '').' / '.($job->customer?->email ?? ''), ' /') ?: '-',
            'Service type' => $job->service_type,
            'Priority' => str($job->priority)->lower()->headline()->toString(),
            'Scheduled' => $job->scheduled_at?->format('d M Y h:i A') ?? '-',
            'Branch' => $job->branch?->name ?? '-',
        ]);
        $sheet->note('Service address: '.$this->address($job->service_address ?? $job->customer?->service_address));
        if ($job->asset) {
            $sheet->note('Equipment: '.$job->asset->name.' / '.$job->asset->brand.' '.$job->asset->model.' / S/N '.$job->asset->serial_number.' / '.$job->asset->capacity);
        }
        $sheet->note('Assigned technician(s): '.$job->assignments->pluck('technician.name')->filter()->join(', '));
        $sheet->note('Customer complaint: '.($job->description ?: '-'));
        $sheet->note('Inspection / fault found: '.($job->inspection_remark ?: '-'));
        $sheet->note('Completion / resolution: '.($job->completion_remark ?: $job->resolution ?: '-'));

        $sheet->section('Work timeline');
        $widths = [142, 178, 191];
        $sheet->tableHeader(['Stage', 'Time', 'Detail'], $widths);
        $timeline = [
            ['Created', $job->created_at, 'Job registered'],
            ['Assigned', $job->assignments->min('assigned_at'), $job->assignments->pluck('technician.name')->filter()->join(', ')],
            ['Accepted', $job->assignments->min('accepted_at'), 'Technician accepted'],
            ['Reached', $job->reached_at, 'On site'],
            ['Inspected', $job->inspected_at, 'Before photo and fault noted'],
            ['Authorized', $job->authorized_at, 'Customer signed before work'],
            ['Work started', $job->started_at, 'Service in progress'],
            ['Work submitted', $job->completed_at, 'After photo and final signature'],
            ['Payment verified', $job->paymentCollections->first(fn ($collection): bool => $collection->status === CollectionStatus::Verified)?->reviewed_at, 'Admin verified collection'],
            ['Job verified', $job->verified_at, 'Manager approved service'],
            ['Closed', $job->closed_at, 'Record closed'],
        ];
        foreach (array_values(array_filter($timeline, fn (array $row): bool => $row[1] !== null)) as $index => $row) {
            $sheet->tableRow([$row[0], $row[1]->format('d M Y h:i A'), $row[2] ?: '-'], $widths, $index % 2 === 0);
        }

        $sheet->section('Checklist');
        $checklistWidths = [385, 126];
        $sheet->tableHeader(['Work item', 'Result'], $checklistWidths);
        foreach ($job->checklistItems as $index => $item) {
            $sheet->tableRow([$item->label, $item->completed_at ? 'Completed' : 'Pending'], $checklistWidths, $index % 2 === 0);
        }
        if ($job->checklistItems->isEmpty()) {
            $sheet->tableRow(['No checklist items', '-'], $checklistWidths);
        }

        $sheet->section('Parts and labor');
        $partWidths = [205, 57, 59, 82, 108];
        $sheet->tableHeader(['Material / service', 'Used', 'Back', 'Rate', 'Billable'], $partWidths);
        $sheet->tableRow([$job->service_type.' labor', '1', '-', $this->money($job->service_cost), $this->money($job->service_cost)], $partWidths);
        foreach ($job->partConsumptions as $index => $part) {
            $net = max(0, (float) $part->quantity - (float) $part->returned_quantity);
            $sheet->tableRow([
                ($part->inventoryItem?->name ?? 'Part').' / '.($part->stockLocation?->name ?? 'Stock'),
                number_format((float) $part->quantity, 2),
                number_format((float) $part->returned_quantity, 2),
                $this->money($part->unit_price),
                $this->money($net * (float) $part->unit_price),
            ], $partWidths, $index % 2 === 0);
        }
        if ($job->invoice) {
            $sheet->total('Invoice '.$job->invoice->invoice_number, $this->money($job->invoice->grand_total), true);
            $sheet->total('Paid', $this->money($job->invoice->paid_total));
            $sheet->total('Balance', $this->money($job->invoice->balance_due));
        }

        if ($job->paymentCollections->isNotEmpty()) {
            $sheet->section('On-site payment collection');
            foreach ($job->paymentCollections as $collection) {
                $sheet->details([
                    'Method' => $collection->mode->value,
                    'Amount' => $this->money($collection->amount),
                    'Status' => $collection->status->value,
                    'Reference' => $collection->reference ?: '-',
                ]);
                if ($collection->proof_path) {
                    $sheet->imageCard('UPI transaction proof', $this->storedImage('local', $collection->proof_path), '', 160);
                }
            }
        }

        $sheet->section('Photo evidence');
        foreach ($job->evidence->filter(fn ($evidence): bool => $evidence->type !== EvidenceType::Signature && str_starts_with((string) $evidence->mime_type, 'image/')) as $evidence) {
            $sheet->imageCard(
                str($evidence->type->value)->lower()->headline().' / '.($evidence->captured_at?->format('d M Y h:i A') ?? 'Recorded'),
                $this->storedImage($evidence->disk, $evidence->path),
                'Uploaded by '.($evidence->uploader?->name ?? 'Technician').'. '.($evidence->metadata['remark'] ?? ''),
                230,
            );
        }
        if ($job->evidence->isEmpty()) {
            $sheet->note('No photo evidence recorded.');
        }
        $sheet->section('Customer signatures');
        $sheet->imageCard('Approval before work', $this->storedImage('local', $job->prework_signature_path), 'Customer authorized the diagnosis and proposed service.', 100);
        $sheet->imageCard('Completion acknowledgement', $this->storedImage('local', $job->customer_signature_path), 'Customer acknowledged completed work and final condition.', 100);

        return $sheet->output();
    }

    private function money(float|string|null $value): string
    {
        return 'INR '.number_format((float) $value, 2);
    }

    private function address(mixed $address): string
    {
        if (is_array($address)) {
            return implode(', ', array_filter(array_map(fn (mixed $part): string => is_scalar($part) ? (string) $part : '', $address))) ?: '-';
        }

        return (string) ($address ?: '-');
    }

    private function storedImage(?string $disk, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        try {
            return Storage::disk($disk ?: 'local')->get($path);
        } catch (Throwable) {
            return null;
        }
    }

    /** @param array<int, string> $lines */
    public function make(string $title, array $lines): string
    {
        $text = "BT\n/F1 18 Tf\n50 790 Td\n".$this->pdfText($title)." Tj\n/F1 10 Tf\n";

        foreach (array_slice($lines, 0, 45) as $line) {
            $text .= "0 -16 Td\n".$this->pdfText($line)." Tj\n";
        }

        $text .= 'ET';
        $objects = [
            '<< /Type /Catalog /Pages 2 0 R >>',
            '<< /Type /Pages /Kids [3 0 R] /Count 1 >>',
            '<< /Type /Page /Parent 2 0 R /MediaBox [0 0 595 842] /Resources << /Font << /F1 5 0 R >> >> /Contents 4 0 R >>',
            '<< /Length '.mb_strlen($text, '8bit')." >>\nstream\n{$text}\nendstream",
            '<< /Type /Font /Subtype /Type1 /BaseFont /Helvetica >>',
        ];

        $pdf = "%PDF-1.4\n";
        $offsets = [0];

        foreach ($objects as $number => $object) {
            $offsets[] = mb_strlen($pdf, '8bit');
            $objectNumber = $number + 1;
            $pdf .= "{$objectNumber} 0 obj\n{$object}\nendobj\n";
        }

        $xrefOffset = mb_strlen($pdf, '8bit');
        $pdf .= 'xref'."\n0 ".(count($objects) + 1)."\n";
        $pdf .= "0000000000 65535 f \n";

        foreach (array_slice($offsets, 1) as $offset) {
            $pdf .= str_pad((string) $offset, 10, '0', STR_PAD_LEFT)." 00000 n \n";
        }

        return $pdf."trailer\n<< /Size ".(count($objects) + 1)." /Root 1 0 R >>\nstartxref\n{$xrefOffset}\n%%EOF";
    }

    /** @param array<int, string> $lines */
    public function store(string $path, string $title, array $lines, string $disk = 'local'): string
    {
        Storage::disk($disk)->put($path, $this->make($title, $lines));

        return $path;
    }

    private function pdfText(string $value): string
    {
        $ascii = Str::ascii($value);
        $escaped = str_replace(['\\', '(', ')'], ['\\\\', '\\(', '\\)'], $ascii);

        return '('.$escaped.')';
    }
}
