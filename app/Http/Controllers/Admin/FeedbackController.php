<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Feedback;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FeedbackController extends Controller
{
    public function index(Request $request): View
    {
        $status = $request->string('status')->toString();
        $periodStart = now()->subDays(30);

        return view('admin.engagement.feedback', [
            'feedback' => Feedback::query()
                ->with(['customer:id,name,phone', 'job:id,job_number,service_type', 'resolver:id,first_name,last_name'])
                ->when(in_array($status, ['OPEN', 'ESCALATED', 'RESOLVED'], true), fn ($query) => $query->where('status', $status))
                ->orderByRaw("case when status = 'ESCALATED' then 0 when status = 'OPEN' then 1 else 2 end")
                ->latest()->paginate(20)->withQueryString(),
            'totalCount' => Feedback::query()->count(),
            'escalatedCount' => Feedback::query()->where('status', 'ESCALATED')->count(),
            'resolvedCount' => Feedback::query()->where('status', 'RESOLVED')->count(),
            'averageRating' => Feedback::query()->where('created_at', '>=', $periodStart)->avg('rating'),
        ]);
    }

    public function review(Request $request, Feedback $feedback): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageWorkforce(), 403);
        $data = $request->validate([
            'status' => ['required', Rule::in(['RESOLVED', 'ESCALATED'])],
            'resolution' => ['required_if:status,RESOLVED', 'nullable', 'string', 'max:5000'],
        ]);

        $feedback->update([
            'status' => $data['status'],
            'resolution' => $data['status'] === 'RESOLVED' ? $data['resolution'] : null,
            'resolved_by' => $data['status'] === 'RESOLVED' ? $request->user()->getKey() : null,
            'resolved_at' => $data['status'] === 'RESOLVED' ? now() : null,
            'escalated_at' => $data['status'] === 'ESCALATED' ? now() : $feedback->escalated_at,
        ]);

        return response()->json(['message' => $data['status'] === 'RESOLVED' ? 'Feedback resolved.' : 'Feedback escalated.', 'reload' => true]);
    }
}
