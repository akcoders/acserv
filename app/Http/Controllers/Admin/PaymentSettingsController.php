<?php

namespace App\Http\Controllers\Admin;

use App\Enums\Role;
use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

class PaymentSettingsController extends Controller
{
    public function update(Request $request): JsonResponse
    {
        abort_unless(in_array($request->user()->role, [Role::Owner, Role::Admin], true), 403);
        $data = $request->validate([
            'upi_id' => ['required', 'string', 'max:191'],
            'upi_payee_name' => ['required', 'string', 'max:160'],
            'qr_image' => ['required', 'image', 'mimes:jpg,jpeg,png,webp', 'max:5120'],
        ]);
        $tenant = $request->user()->tenant()->firstOrFail();
        $newPath = $request->file('qr_image')->store("tenants/{$tenant->getKey()}/payment-qr", 'local');
        $oldPath = $tenant->upi_qr_path;
        $tenant->update(['upi_id' => $data['upi_id'], 'upi_payee_name' => $data['upi_payee_name'], 'upi_qr_path' => $newPath]);

        if ($oldPath !== null) {
            Storage::disk('local')->delete($oldPath);
        }

        return response()->json(['message' => 'UPI QR updated for this workspace.', 'reload' => true]);
    }

    public function image(Request $request): Response
    {
        abort_unless($request->user()->role?->canManageBilling() || $request->user()->role === Role::Technician, 403);
        $path = $request->user()->tenant()->value('upi_qr_path');
        abort_unless($path && Storage::disk('local')->exists($path), 404);

        return Storage::disk('local')->response($path, null, ['Cache-Control' => 'private, no-store']);
    }
}
