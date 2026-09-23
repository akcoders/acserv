<?php

namespace App\Services\Workforce;

use App\Models\Branch;
use Illuminate\Validation\ValidationException;

class GeoFenceService
{
    public function assertAllowed(Branch $branch, float $latitude, float $longitude, float $accuracyMetres, bool $isMockLocation): void
    {
        if ($isMockLocation) {
            throw ValidationException::withMessages(['is_mock_location' => 'Mock locations are not permitted.']);
        }

        if ($accuracyMetres > 100) {
            throw ValidationException::withMessages(['accuracy_metres' => 'Location accuracy must be within 100 metres.']);
        }

        if ($branch->latitude === null || $branch->longitude === null) {
            throw ValidationException::withMessages(['latitude' => 'The branch geofence is not configured.']);
        }

        $distance = $this->distanceMetres(
            (float) $branch->latitude,
            (float) $branch->longitude,
            $latitude,
            $longitude,
        );

        if ($distance > (float) config('acserv.attendance.geofence_metres', 250)) {
            throw ValidationException::withMessages([
                'latitude' => 'You are outside the permitted attendance geofence.',
            ]);
        }
    }

    public function distanceMetres(float $fromLatitude, float $fromLongitude, float $toLatitude, float $toLongitude): float
    {
        $earthRadius = 6371000;
        $latitudeDelta = deg2rad($toLatitude - $fromLatitude);
        $longitudeDelta = deg2rad($toLongitude - $fromLongitude);
        $fromLatitudeRadians = deg2rad($fromLatitude);
        $toLatitudeRadians = deg2rad($toLatitude);
        $a = sin($latitudeDelta / 2) ** 2
            + cos($fromLatitudeRadians) * cos($toLatitudeRadians) * sin($longitudeDelta / 2) ** 2;

        return $earthRadius * 2 * atan2(sqrt($a), sqrt(1 - $a));
    }
}
