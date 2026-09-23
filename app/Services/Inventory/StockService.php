<?php

namespace App\Services\Inventory;

use App\Enums\StockMovementType;
use App\Models\InventoryItem;
use App\Models\Job;
use App\Models\JobPartConsumption;
use App\Models\StockLocation;
use App\Models\StockMovement;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class StockService
{
    /** @param array<string, mixed> $data */
    public function move(array $data): StockMovement
    {
        return DB::transaction(function () use ($data): StockMovement {
            $item = InventoryItem::query()->lockForUpdate()->findOrFail($data['inventory_item_id']);
            $type = StockMovementType::from($data['type']);
            $quantity = (float) $data['quantity'];
            $fromLocationId = $data['from_location_id'] ?? null;
            $toLocationId = $data['to_location_id'] ?? null;

            if (in_array($type, [StockMovementType::Transfer, StockMovementType::Consumption], true) && $fromLocationId === null) {
                throw ValidationException::withMessages(['from_location_id' => 'A source location is required.']);
            }

            if (in_array($type, [StockMovementType::Opening, StockMovementType::Purchase, StockMovementType::Transfer, StockMovementType::Return], true) && $toLocationId === null) {
                throw ValidationException::withMessages(['to_location_id' => 'A destination location is required.']);
            }

            if ($fromLocationId !== null && $this->balance($item, $fromLocationId) < $quantity) {
                throw ValidationException::withMessages(['quantity' => 'The source location does not have enough stock.']);
            }

            return StockMovement::query()->create([
                ...$data,
                'type' => $type,
                'performed_by' => auth()->id(),
                'moved_at' => now(),
            ]);
        });
    }

    public function balance(InventoryItem $item, StockLocation|string $location): float
    {
        $locationId = $location instanceof StockLocation ? $location->getKey() : $location;
        $incoming = (float) StockMovement::query()
            ->where('inventory_item_id', $item->getKey())
            ->where('to_location_id', $locationId)
            ->sum('quantity');
        $outgoing = (float) StockMovement::query()
            ->where('inventory_item_id', $item->getKey())
            ->where('from_location_id', $locationId)
            ->sum('quantity');

        return $incoming - $outgoing;
    }

    public function consume(Job $job, InventoryItem $item, StockLocation $location, float $quantity, float $unitPrice, float $taxRate = 0): JobPartConsumption
    {
        return DB::transaction(function () use ($job, $item, $location, $quantity, $unitPrice, $taxRate): JobPartConsumption {
            $movement = $this->move([
                'inventory_item_id' => $item->getKey(),
                'from_location_id' => $location->getKey(),
                'job_id' => $job->getKey(),
                'type' => StockMovementType::Consumption->value,
                'quantity' => $quantity,
                'unit_cost' => $item->unit_cost,
                'reference' => $job->job_number,
            ]);

            return JobPartConsumption::query()->create([
                'job_id' => $job->getKey(),
                'inventory_item_id' => $item->getKey(),
                'stock_location_id' => $location->getKey(),
                'consumed_by' => auth()->id(),
                'quantity' => $quantity,
                'returned_quantity' => 0,
                'unit_price' => $unitPrice,
                'tax_rate' => $taxRate,
                'consumed_at' => $movement->moved_at,
            ]);
        });
    }

    public function returnConsumption(JobPartConsumption $consumption, float $quantity): JobPartConsumption
    {
        return DB::transaction(function () use ($consumption, $quantity): JobPartConsumption {
            $locked = JobPartConsumption::query()->lockForUpdate()->findOrFail($consumption->getKey());
            $availableToReturn = (float) $locked->quantity - (float) $locked->returned_quantity;

            if ($quantity > $availableToReturn) {
                throw ValidationException::withMessages(['quantity' => 'The return quantity exceeds the unreturned quantity.']);
            }

            $locked->load(['inventoryItem', 'job']);

            $this->move([
                'inventory_item_id' => $locked->inventory_item_id,
                'to_location_id' => $locked->stock_location_id,
                'job_id' => $locked->job_id,
                'type' => StockMovementType::Return->value,
                'quantity' => $quantity,
                'unit_cost' => $locked->inventoryItem->unit_cost,
                'reference' => $locked->job->job_number.'-RETURN',
            ]);
            $locked->increment('returned_quantity', $quantity);

            return $locked->refresh();
        });
    }
}
