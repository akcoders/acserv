<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\JobPaymentCollection;
use App\Services\Billing\JobCollectionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\Response;

class PaymentCollectionController extends Controller
{
    public function review(Request $request, JobPaymentCollection $jobPaymentCollection, JobCollectionService $collections): JsonResponse
    {
        $data = $request->validate([
            'action' => ['required', Rule::in(['verify', 'reject'])],
            'rejection_reason' => ['required_if:action,reject', 'nullable', 'string', 'max:5000'],
        ]);
        $collection = $collections->review($jobPaymentCollection, $request->user(), $data['action'] === 'verify', $data['rejection_reason'] ?? null);

        return response()->json([
            'message' => $data['action'] === 'verify' ? 'Payment verified. Job completed and paid invoice generated.' : 'Collection rejected. Technician can submit a corrected payment.',
            'collection' => $collection,
            'reload' => true,
        ]);
    }

    public function proof(JobPaymentCollection $jobPaymentCollection): Response
    {
        abort_unless($jobPaymentCollection->proof_path && Storage::disk('local')->exists($jobPaymentCollection->proof_path), 404);

        return Storage::disk('local')->response($jobPaymentCollection->proof_path, null, ['Cache-Control' => 'private, no-store']);
    }
}
