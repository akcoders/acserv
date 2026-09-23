<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreQuickCustomerRequest;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Str;

class QuickCustomerController extends Controller
{
    public function store(StoreQuickCustomerRequest $request): JsonResponse
    {
        $customer = Customer::query()->create([
            ...$request->validated(),
            'customer_number' => 'CUS-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
        ]);

        return response()->json([
            'message' => 'Customer added and selected.',
            'customer' => $customer->only(['id', 'name', 'phone']),
        ], 201);
    }
}
