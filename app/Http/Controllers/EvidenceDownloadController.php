<?php

namespace App\Http\Controllers;

use App\Enums\Role;
use App\Models\JobEvidence;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

class EvidenceDownloadController extends Controller
{
    public function __invoke(Request $request, JobEvidence $jobEvidence): StreamedResponse
    {
        $user = $request->user();
        $allowed = $user?->role?->canManageJobs() ?? false;

        if ($user?->role === Role::Technician) {
            $jobEvidence->load('job');
            $allowed = $jobEvidence->job->assignments()->where('technician_id', $user->getKey())->exists();
        }

        abort_unless($allowed, 404);

        return Storage::disk($jobEvidence->disk)->download(
            $jobEvidence->path,
            $jobEvidence->type->value.'-'.$jobEvidence->getKey().'.'.pathinfo($jobEvidence->path, PATHINFO_EXTENSION),
            ['Content-Type' => $jobEvidence->mime_type],
        );
    }
}
