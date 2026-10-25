<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\ComplianceAssessment;

class ComplianceEngine
{
    /**
     * @return array{status: string, gaps: list<string>}
     */
    public function evaluate(Batch $batch): array
    {
        $batch->loadMissing(['members.farmer.plots', 'members.plot']);

        $gaps = [];

        if ($batch->members->isEmpty()) {
            $gaps[] = 'Batch has no farm deliveries.';
        }

        foreach ($batch->members as $member) {
            $farmer = $member->farmer;
            $label = $farmer->public_id.' '.$farmer->name;

            if (! $farmer->consent_at) {
                $gaps[] = "{$label}: missing farmer consent.";
            }

            $plot = $member->plot ?? $farmer->plots->first();
            $hasPoint = $plot && $plot->latitude && $plot->longitude;
            $hasPolygon = $plot && filled($plot->geojson);

            if ($batch->chain === 'palm') {
                if (! $hasPoint && ! $hasPolygon) {
                    $gaps[] = "{$label}: palm lot missing geolocation (EUDR).";
                }
                if (! $plot || $plot->land_status === 'unknown') {
                    $gaps[] = "{$label}: land legality status unknown.";
                }
            } elseif (! $hasPoint && ! $hasPolygon) {
                $gaps[] = "{$label}: plot not mapped (recommended for cashew due diligence).";
            }
        }

        $gaps = array_values(array_unique($gaps));

        return [
            'status' => $gaps === [] ? 'ready' : 'gaps',
            'gaps' => $gaps,
        ];
    }

    public function persist(Batch $batch): ComplianceAssessment
    {
        $result = $this->evaluate($batch);

        return ComplianceAssessment::updateOrCreate(
            ['batch_id' => $batch->id],
            [
                'status' => $result['status'],
                'gaps' => $result['gaps'],
                'assessed_at' => now(),
            ]
        );
    }
}
