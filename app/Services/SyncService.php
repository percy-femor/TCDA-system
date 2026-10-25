<?php

namespace App\Services;

use App\Models\Batch;
use App\Models\BatchMember;
use App\Models\DeviceToken;
use App\Models\Farmer;
use App\Models\Organization;
use App\Models\Plot;
use App\Models\SyncOperation;
use App\Models\User;
use App\Support\FarmPolygon;
use App\Support\GhanaRegions;
use Illuminate\Support\Carbon;
use Illuminate\Validation\ValidationException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class SyncService
{
    public function __construct(
        private CodeGenerator $codes,
        private CustodyLogger $custody,
        private ComplianceEngine $compliance,
    ) {}

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return array{accepted: list<array<string, mixed>>, rejected: list<array<string, mixed>>}
     */
    public function push(User $user, DeviceToken $device, array $operations): array
    {
        $ordered = $this->sortOperations($operations);
        $accepted = [];
        $rejected = [];

        foreach ($ordered as $operation) {
            $opId = $operation['op_id'] ?? null;
            if (! is_string($opId) || $opId === '') {
                $rejected[] = ['op_id' => null, 'reason' => 'Each operation needs an op_id (UUID from the phone).'];
                continue;
            }

            $existing = SyncOperation::query()->where('op_id', $opId)->first();
            if ($existing) {
                $accepted[] = [
                    'op_id' => $existing->op_id,
                    'duplicate' => true,
                    'status' => $existing->status,
                    'public_id' => $existing->public_id,
                    'client_uuid' => $existing->client_uuid,
                    'reason' => $existing->reject_reason,
                ];
                continue;
            }

            try {
                $result = DB::transaction(fn () => $this->apply($user, $device, $operation));
                $accepted[] = $result;
            } catch (Throwable $e) {
                $reason = $e->getMessage();
                SyncOperation::create([
                    'op_id' => $opId,
                    'user_id' => $user->id,
                    'device_id' => $device->device_id,
                    'type' => $operation['type'] ?? 'unknown',
                    'client_uuid' => $operation['client_uuid'] ?? null,
                    'payload' => $operation['payload'] ?? [],
                    'status' => 'rejected',
                    'reject_reason' => Str::limit($reason, 240),
                    'happened_at' => $this->happenedAt($operation),
                    'applied_at' => now(),
                ]);
                $rejected[] = ['op_id' => $opId, 'reason' => $reason];
            }
        }

        $device->forceFill(['last_push_at' => now()])->save();

        return ['accepted' => $accepted, 'rejected' => $rejected];
    }

    /**
     * @return array{server_time: string, aggregators: list<array<string, mixed>>, farmers: list<array<string, mixed>>, batches: list<array<string, mixed>>}
     */
    public function pull(User $user, DeviceToken $device, ?string $since = null): array
    {
        $sinceAt = $since ? Carbon::parse($since) : now()->subYears(10);

        $farmers = Farmer::query()
            ->visibleTo($user)
            ->with('plots')
            ->where(function ($q) use ($sinceAt) {
                $q->where('updated_at', '>=', $sinceAt)
                    ->orWhereHas('plots', fn ($p) => $p->where('updated_at', '>=', $sinceAt));
            })
            ->orderBy('id')
            ->get();

        $batches = Batch::query()
            ->with(['members.farmer', 'members.plot', 'aggregator'])
            ->where(function ($q) use ($sinceAt) {
                $q->where('updated_at', '>=', $sinceAt)
                    ->orWhereHas('members', fn ($m) => $m->where('updated_at', '>=', $sinceAt));
            })
            ->when(! $user->canAccessAllRegions() && $user->operatingRegion(), function ($q) use ($user) {
                $region = $user->operatingRegion();
                $q->where(function ($inner) use ($region) {
                    $inner->whereHas('members.farmer', fn ($f) => $f->where('region', $region))
                        ->orWhere(function ($open) use ($region) {
                            $open->where('status', 'open')
                                ->whereHas('aggregator', fn ($a) => $a->where('region', $region));
                        });
                });
            })
            ->orderBy('id')
            ->get();

        $device->forceFill(['last_pull_at' => now()])->save();

        $aggregatorQuery = Organization::query()->where('type', 'aggregator');
        if (! $user->canAccessAllRegions() && $user->operatingRegion()) {
            $aggregatorQuery->where('region', $user->operatingRegion());
        }

        return [
            'server_time' => now()->toIso8601String(),
            'aggregators' => $aggregatorQuery
                ->orderBy('name')
                ->get()
                ->map(fn (Organization $org) => [
                    'public_id' => $org->public_id,
                    'name' => $org->name,
                    'chain' => $org->chain,
                    'district' => $org->district,
                    'region' => $org->region,
                ])->all(),
            'farmers' => $farmers->map(fn (Farmer $farmer) => $this->serializeFarmer($farmer))->all(),
            'batches' => $batches->map(fn (Batch $batch) => $this->serializeBatch($batch))->all(),
        ];
    }

    /**
     * @param  list<array<string, mixed>>  $operations
     * @return list<array<string, mixed>>
     */
    private function sortOperations(array $operations): array
    {
        $rank = [
            'farmer.upsert' => 1,
            'plot.upsert' => 2,
            'batch.open' => 3,
            'batch.delivery' => 4,
            'batch.close' => 5,
        ];

        usort($operations, function ($a, $b) use ($rank) {
            $ra = $rank[$a['type'] ?? ''] ?? 99;
            $rb = $rank[$b['type'] ?? ''] ?? 99;
            if ($ra !== $rb) {
                return $ra <=> $rb;
            }

            return $this->happenedAt($a)->timestamp <=> $this->happenedAt($b)->timestamp;
        });

        return $operations;
    }

    /**
     * @param  array<string, mixed>  $operation
     * @return array<string, mixed>
     */
    private function apply(User $user, DeviceToken $device, array $operation): array
    {
        $type = $operation['type'] ?? '';
        $payload = $operation['payload'] ?? [];
        if (! is_array($payload)) {
            $payload = [];
        }

        $entity = match ($type) {
            'farmer.upsert' => $this->upsertFarmer($user, $operation, $payload),
            'plot.upsert' => $this->upsertPlot($user, $operation, $payload),
            'batch.open' => $this->openBatch($user, $operation, $payload),
            'batch.delivery' => $this->addDelivery($user, $operation, $payload),
            'batch.close' => $this->closeBatch($user, $operation, $payload),
            default => throw new \InvalidArgumentException("Unknown operation type [{$type}]."),
        };

        $record = SyncOperation::create([
            'op_id' => $operation['op_id'],
            'user_id' => $user->id,
            'device_id' => $device->device_id,
            'type' => $type,
            'client_uuid' => $entity['client_uuid'],
            'payload' => $payload,
            'status' => 'applied',
            'entity_type' => $entity['entity_type'],
            'entity_id' => $entity['entity_id'],
            'public_id' => $entity['public_id'],
            'happened_at' => $this->happenedAt($operation),
            'applied_at' => now(),
        ]);

        return [
            'op_id' => $record->op_id,
            'duplicate' => false,
            'status' => 'applied',
            'public_id' => $record->public_id,
            'client_uuid' => $record->client_uuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array{entity_type: string, entity_id: int, public_id: string, client_uuid: string}
     */
    private function upsertFarmer(User $user, array $operation, array $payload): array
    {
        $clientUuid = $this->requireUuid($operation, 'client_uuid');
        $region = $this->requireString($payload, 'region');
        if (! GhanaRegions::contains($region)) {
            throw new \InvalidArgumentException('Region must be one of Ghana’s 16 official regions.');
        }
        if (! $user->canAccessRegion($region)) {
            throw new \InvalidArgumentException('You can only enrol farmers in your assigned region ('.$user->operatingRegion().').');
        }
        $aggregator = $this->findAggregator($payload['aggregator_public_id'] ?? null);

        $farmer = Farmer::query()->firstOrNew(['client_uuid' => $clientUuid]);
        $isNew = ! $farmer->exists;

        $farmer->fill([
            'name' => $this->requireString($payload, 'name'),
            'phone' => $payload['phone'] ?? $farmer->phone,
            'community' => $this->requireString($payload, 'community'),
            'district' => $this->requireString($payload, 'district'),
            'region' => $region,
            'chain' => $this->requireChain($payload['chain'] ?? null),
            'paper_card_no' => $payload['paper_card_no'] ?? $farmer->paper_card_no,
            'aggregator_id' => $aggregator?->id ?? $farmer->aggregator_id,
            'enrolled_by' => $farmer->enrolled_by ?? $user->id,
        ]);

        if ($isNew) {
            $farmer->public_id = CodeGenerator::next('FRM-', 'farmers');
            $farmer->client_uuid = $clientUuid;
        }

        if (! empty($payload['consent'])) {
            $farmer->consent_at = $farmer->consent_at ?? $this->happenedAt($operation);
        }

        $farmer->save();

        return [
            'entity_type' => Farmer::class,
            'entity_id' => $farmer->id,
            'public_id' => $farmer->public_id,
            'client_uuid' => $farmer->client_uuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array{entity_type: string, entity_id: int, public_id: string, client_uuid: string}
     */
    private function upsertPlot(User $user, array $operation, array $payload): array
    {
        $clientUuid = $this->requireUuid($operation, 'client_uuid');
        $farmer = $this->farmerByClient($payload['farmer_client_uuid'] ?? null);
        if (! $user->canAccessRegion($farmer->region)) {
            throw new \InvalidArgumentException('That farmer is outside your assigned region.');
        }

        $hasPoint = ! empty($payload['latitude']) && ! empty($payload['longitude']);
        $hasPolygon = filled($payload['geojson'] ?? null);
        if (! $hasPoint && ! $hasPolygon) {
            throw new \InvalidArgumentException('Plot needs a GPS point or polygon. Empty maps stay a gap, not a plot record.');
        }

        $fence = null;
        if ($hasPolygon) {
            try {
                $fence = FarmPolygon::fromGeoJson((string) $payload['geojson']);
            } catch (ValidationException $e) {
                throw new \InvalidArgumentException($e->validator->errors()->first('geojson') ?: 'Invalid farm geofence.');
            }
        }

        $plot = Plot::query()->firstOrNew(['client_uuid' => $clientUuid]);
        $isNew = ! $plot->exists;

        $plot->fill([
            'farmer_id' => $farmer->id,
            'latitude' => $fence['latitude'] ?? $payload['latitude'] ?? null,
            'longitude' => $fence['longitude'] ?? $payload['longitude'] ?? null,
            'geojson' => $fence['geojson'] ?? null,
            'area_hectares' => $payload['area_hectares'] ?? $fence['area_hectares'] ?? null,
            'land_status' => $payload['land_status'] ?? 'unknown',
            'mapped_at' => $plot->mapped_at ?? $this->happenedAt($operation),
            'mapped_by' => $plot->mapped_by ?? $user->id,
        ]);

        if ($isNew) {
            $plot->public_id = CodeGenerator::next('PLT-', 'plots');
            $plot->client_uuid = $clientUuid;
        }

        $plot->save();

        return [
            'entity_type' => Plot::class,
            'entity_id' => $plot->id,
            'public_id' => $plot->public_id,
            'client_uuid' => $plot->client_uuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array{entity_type: string, entity_id: int, public_id: string, client_uuid: string}
     */
    private function openBatch(User $user, array $operation, array $payload): array
    {
        $clientUuid = $this->requireUuid($operation, 'client_uuid');
        $existing = Batch::query()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return [
                'entity_type' => Batch::class,
                'entity_id' => $existing->id,
                'public_id' => $existing->public_id,
                'client_uuid' => $existing->client_uuid,
            ];
        }

        $aggregator = $this->findAggregator($payload['aggregator_public_id'] ?? null);
        if (! $aggregator) {
            throw new \InvalidArgumentException('Batch open needs aggregator_public_id.');
        }

        $batch = Batch::create([
            'public_id' => CodeGenerator::next('BAT-', 'batches'),
            'client_uuid' => $clientUuid,
            'chain' => $this->requireChain($payload['chain'] ?? null),
            'status' => 'open',
            'capture_channel' => 'mobile',
            'aggregator_id' => $aggregator->id,
            'opened_by' => $user->id,
            'notes' => $payload['notes'] ?? null,
        ]);

        $this->custody->record($batch, 'opened', $user, [
            'channel' => 'mobile',
            'device_id' => $operation['device_id'] ?? null,
        ]);

        return [
            'entity_type' => Batch::class,
            'entity_id' => $batch->id,
            'public_id' => $batch->public_id,
            'client_uuid' => $batch->client_uuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array{entity_type: string, entity_id: int, public_id: string, client_uuid: string}
     */
    private function addDelivery(User $user, array $operation, array $payload): array
    {
        $clientUuid = $this->requireUuid($operation, 'client_uuid');
        $existing = BatchMember::query()->where('client_uuid', $clientUuid)->first();
        if ($existing) {
            return [
                'entity_type' => BatchMember::class,
                'entity_id' => $existing->id,
                'public_id' => $existing->batch?->public_id ?? '',
                'client_uuid' => $existing->client_uuid,
            ];
        }

        $batch = Batch::query()->where('client_uuid', $payload['batch_client_uuid'] ?? '')->first();
        if (! $batch) {
            throw new \InvalidArgumentException('Delivery refers to a batch the registry has not received yet. Sync the open-batch operation first.');
        }
        if ($batch->status !== 'open') {
            throw new \InvalidArgumentException("Batch {$batch->public_id} is {$batch->status}; deliveries only go on open batches.");
        }

        $farmer = $this->farmerByClient($payload['farmer_client_uuid'] ?? null);
        if (! $user->canAccessRegion($farmer->region)) {
            throw new \InvalidArgumentException('That farmer is outside your assigned region.');
        }
        if ($farmer->chain !== $batch->chain) {
            throw new \InvalidArgumentException('Farmer chain does not match this batch.');
        }

        $plot = null;
        if (! empty($payload['plot_client_uuid'])) {
            $plot = Plot::query()->where('client_uuid', $payload['plot_client_uuid'])->first();
        }
        $plot = $plot ?? $farmer->plots()->first();

        $member = $batch->members()->create([
            'client_uuid' => $clientUuid,
            'farmer_id' => $farmer->id,
            'plot_id' => $plot?->id,
            'volume_kg' => $payload['volume_kg'] ?? 0,
            'paper_stub_no' => $payload['paper_stub_no'] ?? null,
            'capture_channel' => 'mobile',
            'delivered_at' => isset($payload['delivered_at']) ? Carbon::parse($payload['delivered_at']) : $this->happenedAt($operation),
            'captured_by' => $user->id,
        ]);

        $this->custody->record($batch, 'delivery_added', $user, [
            'farmer' => $farmer->public_id,
            'volume_kg' => $member->volume_kg,
            'channel' => 'mobile',
        ]);

        return [
            'entity_type' => BatchMember::class,
            'entity_id' => $member->id,
            'public_id' => $batch->public_id,
            'client_uuid' => $member->client_uuid,
        ];
    }

    /**
     * @param  array<string, mixed>  $operation
     * @param  array<string, mixed>  $payload
     * @return array{entity_type: string, entity_id: int, public_id: string, client_uuid: string}
     */
    private function closeBatch(User $user, array $operation, array $payload): array
    {
        $batch = Batch::query()->where('client_uuid', $payload['batch_client_uuid'] ?? $operation['client_uuid'] ?? '')->first();
        if (! $batch) {
            throw new \InvalidArgumentException('Close refers to a batch the registry has not received yet.');
        }

        if ($batch->status === 'open') {
            if ($batch->members()->count() === 0) {
                throw new \InvalidArgumentException('Cannot close a batch with no deliveries.');
            }
            $batch->update([
                'status' => 'closed',
                'closed_at' => $this->happenedAt($operation),
            ]);
            $assessment = $this->compliance->persist($batch->fresh(['members.farmer.plots', 'members.plot']));
            $this->custody->record($batch, 'closed', $user, [
                'compliance' => $assessment->status,
                'channel' => 'mobile',
            ]);
        }

        return [
            'entity_type' => Batch::class,
            'entity_id' => $batch->id,
            'public_id' => $batch->public_id,
            'client_uuid' => $batch->client_uuid,
        ];
    }

    private function farmerByClient(?string $uuid): Farmer
    {
        if (! $uuid) {
            throw new \InvalidArgumentException('farmer_client_uuid is required.');
        }
        $farmer = Farmer::query()->where('client_uuid', $uuid)->first();
        if (! $farmer) {
            throw new \InvalidArgumentException('Unknown farmer_client_uuid. Enrol the farmer on this device first.');
        }

        return $farmer;
    }

    private function findAggregator(mixed $publicId): ?Organization
    {
        if (! is_string($publicId) || $publicId === '') {
            return null;
        }

        return Organization::query()->where('public_id', $publicId)->where('type', 'aggregator')->first();
    }

    private function requireUuid(array $operation, string $key): string
    {
        $value = $operation[$key] ?? null;
        if (! is_string($value) || $value === '') {
            throw new \InvalidArgumentException("{$key} is required (generated on the phone).");
        }

        return $value;
    }

    private function requireString(array $payload, string $key): string
    {
        $value = $payload[$key] ?? null;
        if (! is_string($value) || trim($value) === '') {
            throw new \InvalidArgumentException("{$key} is required.");
        }

        return trim($value);
    }

    private function requireChain(mixed $chain): string
    {
        if (! in_array($chain, ['palm', 'cashew'], true)) {
            throw new \InvalidArgumentException('chain must be palm or cashew.');
        }

        return $chain;
    }

    /**
     * @param  array<string, mixed>  $operation
     */
    private function happenedAt(array $operation): Carbon
    {
        $raw = $operation['happened_at'] ?? null;

        return $raw ? Carbon::parse($raw) : now();
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeFarmer(Farmer $farmer): array
    {
        return [
            'public_id' => $farmer->public_id,
            'client_uuid' => $farmer->client_uuid,
            'name' => $farmer->name,
            'phone' => $farmer->phone,
            'community' => $farmer->community,
            'district' => $farmer->district,
            'region' => $farmer->region,
            'chain' => $farmer->chain,
            'paper_card_no' => $farmer->paper_card_no,
            'consent_at' => $farmer->consent_at?->toIso8601String(),
            'aggregator_id' => $farmer->aggregator_id,
            'updated_at' => $farmer->updated_at?->toIso8601String(),
            'plots' => $farmer->plots->map(fn (Plot $plot) => [
                'public_id' => $plot->public_id,
                'client_uuid' => $plot->client_uuid,
                'farmer_client_uuid' => $farmer->client_uuid,
                'latitude' => $plot->latitude,
                'longitude' => $plot->longitude,
                'geojson' => $plot->geojson,
                'area_hectares' => $plot->area_hectares,
                'land_status' => $plot->land_status,
            ])->all(),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function serializeBatch(Batch $batch): array
    {
        return [
            'public_id' => $batch->public_id,
            'client_uuid' => $batch->client_uuid,
            'chain' => $batch->chain,
            'status' => $batch->status,
            'capture_channel' => $batch->capture_channel,
            'aggregator_public_id' => $batch->aggregator?->public_id,
            'notes' => $batch->notes,
            'members' => $batch->members->map(fn (BatchMember $member) => [
                'client_uuid' => $member->client_uuid,
                'farmer_client_uuid' => $member->farmer?->client_uuid,
                'plot_client_uuid' => $member->plot?->client_uuid,
                'volume_kg' => $member->volume_kg,
                'paper_stub_no' => $member->paper_stub_no,
                'delivered_at' => $member->delivered_at?->toIso8601String(),
            ])->all(),
        ];
    }
}
