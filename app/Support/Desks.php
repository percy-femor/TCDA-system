<?php

namespace App\Support;

use App\Enums\UserRole;

class Desks
{
    /**
     * @return array<string, array{key: string, role: string, title: string, blurb: string, home: string, email: string, kicker: string}>
     */
    public static function all(): array
    {
        return [
            'tcda' => [
                'key' => 'tcda',
                'role' => UserRole::Tcda->value,
                'title' => 'National command',
                'kicker' => 'TCDA',
                'blurb' => 'Supervise the registry across Ghana’s 16 regions. Assign officers. Watch gaps.',
                'home' => 'dashboard',
                'email' => 'tcda@tcda.gov.gh',
            ],
            'officer' => [
                'key' => 'officer',
                'role' => UserRole::FieldOfficer->value,
                'title' => 'Field officer desk',
                'kicker' => 'Field',
                'blurb' => 'Enrol farmers and draw farm geofences in your assigned region only.',
                'home' => 'officer.home',
                'email' => 'officer@tcda.gov.gh',
            ],
            'aggregator' => [
                'key' => 'aggregator',
                'role' => UserRole::Aggregator->value,
                'title' => 'Buying-point floor',
                'kicker' => 'Aggregator',
                'blurb' => 'Open a farm-set, record kg, then send it to the mill.',
                'home' => 'buying.home',
                'email' => 'agg@western.palm.gh',
            ],
            'mill' => [
                'key' => 'mill',
                'role' => UserRole::Mill->value,
                'title' => 'Weighbridge',
                'kicker' => 'Mill',
                'blurb' => 'Stamp mill kg for closed farm-sets. You do not enrol farmers.',
                'home' => 'mill.index',
                'email' => 'mill@benso.palm.gh',
            ],
            'exporter' => [
                'key' => 'exporter',
                'role' => UserRole::Exporter->value,
                'title' => 'Buyer packs',
                'kicker' => 'Export',
                'blurb' => 'Pack due-diligence files for buyers. Gaps stay printed.',
                'home' => 'dds.index',
                'email' => 'export@nutex.gh',
            ],
        ];
    }

    public static function get(string $key): ?array
    {
        return self::all()[$key] ?? null;
    }
}
