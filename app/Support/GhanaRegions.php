<?php

namespace App\Support;

class GhanaRegions
{
    /**
     * Official administrative regions of Ghana (since 2019).
     *
     * @return list<string>
     */
    public static function all(): array
    {
        return [
            'Ahafo',
            'Ashanti',
            'Bono',
            'Bono East',
            'Central',
            'Eastern',
            'Greater Accra',
            'North East',
            'Northern',
            'Oti',
            'Savannah',
            'Upper East',
            'Upper West',
            'Volta',
            'Western',
            'Western North',
        ];
    }

    public static function contains(?string $region): bool
    {
        return is_string($region) && in_array($region, self::all(), true);
    }

    public static function rule(): string
    {
        return 'in:'.implode(',', self::all());
    }
}
