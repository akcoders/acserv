<?php

namespace App\Http\Controllers\Admin;

use App\Enums\NotificationChannel;
use App\Http\Controllers\Controller;
use App\Models\NotificationLog;
use App\Models\NotificationTemplate;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class NotificationController extends Controller
{
    public function index(Request $request): View
    {
        return view('admin.engagement.notifications', [
            'templates' => NotificationTemplate::query()->orderBy('event')->orderBy('channel')->get(),
            'logs' => NotificationLog::query()
                ->with('user:id,first_name,last_name')
                ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
                ->orderByDesc('created_at')
                ->paginate(30)
                ->withQueryString(),
            'channels' => NotificationChannel::cases(),
        ]);
    }

    public function storeTemplate(Request $request): JsonResponse
    {
        abort_unless($request->user()?->role?->canManageCoreRecords(), 403);
        $data = $request->validate([
            'event' => ['required', 'string', 'max:100'],
            'channel' => ['required', Rule::enum(NotificationChannel::class)],
            'locale' => ['required', 'string', 'max:16'],
            'subject' => ['nullable', 'string', 'max:255'],
            'body' => ['required', 'string', 'max:10000'],
            'is_active' => ['required', 'boolean'],
        ]);

        $template = NotificationTemplate::query()->updateOrCreate(
            ['event' => $data['event'], 'channel' => $data['channel'], 'locale' => $data['locale']],
            $data,
        );

        return response()->json(['message' => 'Notification template saved.', 'template' => $template, 'reload' => true]);
    }
}
