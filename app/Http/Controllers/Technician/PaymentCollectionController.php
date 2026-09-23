<?php

namespace App\Http\Controllers\Technician;

use App\Enums\PaymentMode;
use App\Http\Controllers\Controller;
use App\Models\Job;
use App\Services\Billing\JobCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Throwable;

class PaymentCollectionController extends Controller
{
    public function store(Request $request, Job $job, JobCollectionService $collections): JsonResponse
    {
        $mode = $request->input('mode');
        $data = $request->validate([
            'mode' => ['required', Rule::in([PaymentMode::Cash->value, PaymentMode::Upi->value])],
            'reference' => ['nullable', 'string', 'max:191'],
            'transaction_image' => [$mode === PaymentMode::Upi->value ? 'required' : 'prohibited', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $proofPath = $request->hasFile('transaction_image')
            ? $request->file('transaction_image')->store("tenants/{$request->user()->tenant_id}/jobs/{$job->getKey()}/payments", 'local')
            : null;

        try {
            $collection = $collections->submit($job, $request->user(), PaymentMode::from($data['mode']), $proofPath, $data['reference'] ?? null);
        } catch (Throwable $exception) {
            if ($proofPath !== null) {
                Storage::disk('local')->delete($proofPath);
            }

            throw $exception;
        }

        return response()->json(['message' => 'Collection submitted. Waiting for office verification.', 'collection' => $collection, 'reload' => true], 201);
    }
}
