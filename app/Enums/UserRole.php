<?php

namespace App\Enums;

enum UserRole: string
{
    case Tcda = 'tcda';
    case FieldOfficer = 'field_officer';
    case Aggregator = 'aggregator';
    case Mill = 'mill';
    case Exporter = 'exporter';

    public function label(): string
    {
        return match ($this) {
            self::Tcda => 'TCDA',
            self::FieldOfficer => 'Field officer',
            self::Aggregator => 'Aggregator',
            self::Mill => 'Mill / processor',
            self::Exporter => 'Exporter',
        };
    }
}
