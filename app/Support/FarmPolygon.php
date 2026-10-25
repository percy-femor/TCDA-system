<?php

namespace App\Support;

use Illuminate\Validation\ValidationException;

class FarmPolygon
{
    /**
     * @return array{geojson: string, latitude: float, longitude: float, area_hectares: float, corners: int}
     */
    public static function fromGeoJson(string $raw): array
    {
        $decoded = json_decode($raw, true);
        if (! is_array($decoded)) {
            throw ValidationException::withMessages([
                'geojson' => 'The farm geofence must be a polygon GeoJSON.',
            ]);
        }

        $ring = self::ring($decoded);
        if (count($ring) < 4) {
            throw ValidationException::withMessages([
                'geojson' => 'Walk or click at least three corners of the farm, then close the fence.',
            ]);
        }

        $first = $ring[0];
        $last = $ring[count($ring) - 1];
        if ($first[0] !== $last[0] || $first[1] !== $last[1]) {
            $ring[] = $first;
        }

        foreach ($ring as $point) {
            if ($point[1] < -90 || $point[1] > 90 || $point[0] < -180 || $point[0] > 180) {
                throw ValidationException::withMessages([
                    'geojson' => 'A geofence corner is not a valid latitude/longitude.',
                ]);
            }
        }

        $centroid = self::centroid($ring);
        if ($centroid['latitude'] < 4.3 || $centroid['latitude'] > 11.3
            || $centroid['longitude'] < -3.5 || $centroid['longitude'] > 1.5) {
            throw ValidationException::withMessages([
                'geojson' => 'The farm geofence must sit inside Ghana.',
            ]);
        }

        $geojson = json_encode([
            'type' => 'Polygon',
            'coordinates' => [$ring],
        ], JSON_THROW_ON_ERROR);

        return [
            'geojson' => $geojson,
            'latitude' => $centroid['latitude'],
            'longitude' => $centroid['longitude'],
            'area_hectares' => self::areaHectares($ring),
            'corners' => count($ring) - 1,
        ];
    }

    /**
     * @param  array<string, mixed>  $decoded
     * @return list<array{0: float, 1: float}>
     */
    public static function ring(array $decoded): array
    {
        $geometry = $decoded;
        if (($decoded['type'] ?? null) === 'Feature') {
            $geometry = $decoded['geometry'] ?? [];
        }

        if (($geometry['type'] ?? null) !== 'Polygon') {
            return [];
        }

        $coords = $geometry['coordinates'][0] ?? [];
        $ring = [];
        foreach ($coords as $point) {
            if (! is_array($point) || count($point) < 2) {
                continue;
            }
            $ring[] = [(float) $point[0], (float) $point[1]];
        }

        return $ring;
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     * @return array{latitude: float, longitude: float}
     */
    public static function centroid(array $ring): array
    {
        $points = array_slice($ring, 0, -1) ?: $ring;
        $lng = array_sum(array_column($points, 0)) / max(count($points), 1);
        $lat = array_sum(array_column($points, 1)) / max(count($points), 1);

        return [
            'latitude' => round($lat, 7),
            'longitude' => round($lng, 7),
        ];
    }

    /**
     * @param  list<array{0: float, 1: float}>  $ring
     */
    public static function areaHectares(array $ring): float
    {
        $points = $ring;
        $n = count($points);
        if ($n < 4) {
            return 0.0;
        }

        $latMid = self::centroid($points)['latitude'] * M_PI / 180;
        $mPerDegLat = 111320.0;
        $mPerDegLng = 111320.0 * cos($latMid);

        $area = 0.0;
        for ($i = 0; $i < $n - 1; $i++) {
            $x1 = $points[$i][0] * $mPerDegLng;
            $y1 = $points[$i][1] * $mPerDegLat;
            $x2 = $points[$i + 1][0] * $mPerDegLng;
            $y2 = $points[$i + 1][1] * $mPerDegLat;
            $area += ($x1 * $y2) - ($x2 * $y1);
        }

        return round(abs($area) / 2 / 10000, 4);
    }
}
