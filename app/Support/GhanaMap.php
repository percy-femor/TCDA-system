<?php

namespace App\Support;

class GhanaMap
{
    public const SOUTH = 4.539;

    public const WEST = -3.261;

    public const NORTH = 11.175;

    public const EAST = 1.199;

    /**
     * @return array{lat: float, lng: float, zoom: int}
     */
    public static function viewFor(?string $region): array
    {
        return match ($region) {
            'Ahafo' => ['lat' => 7.05, 'lng' => -2.45, 'zoom' => 9],
            'Ashanti' => ['lat' => 6.69, 'lng' => -1.62, 'zoom' => 9],
            'Bono' => ['lat' => 7.58, 'lng' => -2.33, 'zoom' => 9],
            'Bono East' => ['lat' => 7.72, 'lng' => -1.05, 'zoom' => 9],
            'Central' => ['lat' => 5.55, 'lng' => -1.25, 'zoom' => 9],
            'Eastern' => ['lat' => 6.23, 'lng' => -0.45, 'zoom' => 9],
            'Greater Accra' => ['lat' => 5.60, 'lng' => -0.19, 'zoom' => 10],
            'North East' => ['lat' => 10.35, 'lng' => -0.55, 'zoom' => 9],
            'Northern' => ['lat' => 9.40, 'lng' => -0.85, 'zoom' => 9],
            'Oti' => ['lat' => 7.90, 'lng' => 0.30, 'zoom' => 9],
            'Savannah' => ['lat' => 9.10, 'lng' => -1.82, 'zoom' => 9],
            'Upper East' => ['lat' => 10.79, 'lng' => -0.98, 'zoom' => 9],
            'Upper West' => ['lat' => 10.35, 'lng' => -2.32, 'zoom' => 9],
            'Volta' => ['lat' => 6.58, 'lng' => 0.47, 'zoom' => 9],
            'Western' => ['lat' => 5.02, 'lng' => -2.15, 'zoom' => 9],
            'Western North' => ['lat' => 6.20, 'lng' => -2.80, 'zoom' => 9],
            default => ['lat' => 7.95, 'lng' => -1.02, 'zoom' => 7],
        };
    }
}
