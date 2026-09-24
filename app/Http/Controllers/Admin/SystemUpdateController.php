<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use App\Services\Operations\SystemUpdateService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use RuntimeException;

class SystemUpdateController extends Controller
{
    public function index(Request $request, SystemUpdateService $updates): View
    {
        $this->assertOperator($request);
        $updates->ensureAccessToken();

        $update = null;
        $id = $request->query('id');
        if (is_string($id) && $id !== '') {
            try {
                $update = $updates->status($id);
            } catch (RuntimeException) {
                abort(404);
            }
        }

        return view('admin.system-updates.index', [
            'update' => $update,
            'accessTokenPath' => $updates->accessTokenPath(),
        ]);
    }

    public function store(Request $request, SystemUpdateService $updates): JsonResponse
    {
        $this->assertOperator($request);

        $data = $request->validate([
            'package' => ['required', 'file', 'mimes:zip', 'extensions:zip', 'max:102400'],
            'access_key' => ['required', 'string', 'size:64'],
        ]);

        if (! $updates->hasValidToken($data['access_key'])) {
            throw ValidationException::withMessages(['access_key' => 'The private update key is invalid.']);
        }

        try {
            $update = $updates->stage($data['package']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['package' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => 'Update package validated and staged. Review it before applying.',
            'redirect' => route('admin.system-updates.index', ['id' => $update['id']]),
        ], 201);
    }

    public function apply(Request $request, string $id, SystemUpdateService $updates): JsonResponse
    {
        $this->assertOperator($request);

        $data = $request->validate([
            'access_key' => ['required', 'string', 'size:64'],
            'backup_reference' => ['required', 'string', 'min:3', 'max:255'],
            'confirm_backup' => ['accepted'],
            'confirm_downtime' => ['accepted'],
        ]);

        if (! $updates->hasValidToken($data['access_key'])) {
            throw ValidationException::withMessages(['access_key' => 'The private update key is invalid.']);
        }

        $configuredWebRoot = config('acserv.update_web_root');
        $scriptFilename = $request->server('SCRIPT_FILENAME');
        $webRoot = is_string($configuredWebRoot) && $configuredWebRoot !== ''
            ? $configuredWebRoot
            : (is_string($scriptFilename) && $scriptFilename !== '' ? dirname($scriptFilename) : public_path());

        try {
            $updates->queue($id, $webRoot, (string) $request->user()->getKey(), $data['backup_reference']);
        } catch (RuntimeException $exception) {
            throw ValidationException::withMessages(['backup_reference' => $exception->getMessage()]);
        }

        return response()->json([
            'message' => 'Update queued. The server scheduler will apply it shortly.',
            'redirect' => route('admin.system-updates.index', ['id' => $id]),
        ]);
    }

    public function status(Request $request, string $id, SystemUpdateService $updates): JsonResponse
    {
        $this->assertOperator($request);

        try {
            $update = $updates->status($id);
        } catch (RuntimeException) {
            abort(404);
        }

        return response()->json(['update' => array_intersect_key($update, array_flip([
            'id', 'version', 'file_count', 'total_bytes', 'state', 'error', 'started_at', 'completed_at',
        ]))]);
    }

    private function assertOperator(Request $request): void
    {
        $user = $request->user();

        abort_unless(
            $user?->role === Role::Owner
            && $user->tenant?->slug === config('acserv.public_tenant_slug'),
            403,
        );
    }
}
