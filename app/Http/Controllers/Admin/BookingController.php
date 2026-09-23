<?php

namespace App\Http\Controllers\Admin;

use App\Enums\BookingStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreBookingRequest;
use App\Models\Booking;
use App\Models\Branch;
use App\Models\Customer;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\View\View;

class BookingController extends Controller
{
    public function index(Request $request): View
    {
        $bookings = Booking::query()
            ->with(['customer:id,name,phone', 'asset:id,name,brand,model', 'branch:id,name'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('search'), function ($query) use ($request): void {
                $search = '%'.$request->string('search')->trim()->toString().'%';
                $query->where(fn ($filter) => $filter
                    ->where('booking_number', 'like', $search)
                    ->orWhere('service_type', 'like', $search)
                    ->orWhereHas('customer', fn ($customer) => $customer->where('name', 'like', $search)));
            })
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate(20)
            ->withQueryString();

        return view('admin.operations.bookings', [
            'bookings' => $bookings,
            'customers' => Customer::query()->orderBy('name')->get(['id', 'name', 'phone']),
            'branches' => Branch::query()->where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'statuses' => BookingStatus::cases(),
        ]);
    }

    public function store(StoreBookingRequest $request): JsonResponse
    {
        $booking = Booking::query()->create([
            ...$request->validated(),
            'booking_number' => 'BK-'.now()->format('Ymd').'-'.Str::upper(Str::substr((string) Str::ulid(), -6)),
            'status' => BookingStatus::Pending,
        ]);

        return response()->json([
            'message' => 'Booking created successfully.',
            'booking' => $booking,
            'reload' => true,
        ], 201);
    }

    public function update(StoreBookingRequest $request, Booking $booking): JsonResponse
    {
        $booking->update($request->validated());

        return response()->json(['message' => 'Booking updated successfully.', 'reload' => true]);
    }

    public function destroy(Booking $booking): JsonResponse
    {
        $booking->delete();

        return response()->json(['message' => 'Booking deleted successfully.', 'reload' => true]);
    }
}
