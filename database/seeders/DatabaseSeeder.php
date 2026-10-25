<?php

namespace Database\Seeders;

use App\Models\Batch;
use App\Models\Farmer;
use App\Models\Organization;
use App\Models\Plot;
use App\Models\User;
use App\Services\ComplianceEngine;
use App\Services\CustodyLogger;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $tcda = Organization::create([
            'public_id' => 'ORG-00001',
            'name' => 'Tree Crops Development Authority',
            'type' => 'regulator',
            'chain' => 'both',
            'region' => 'Greater Accra',
            'district' => 'Accra',
        ]);
        $aggPalm = Organization::create([
            'public_id' => 'ORG-00002',
            'name' => 'Ahanta Buying Company',
            'type' => 'aggregator',
            'chain' => 'palm',
            'region' => 'Western',
            'district' => 'Ahanta West',
        ]);
        $aggCashew = Organization::create([
            'public_id' => 'ORG-00003',
            'name' => 'Techiman Kernel Collective',
            'type' => 'aggregator',
            'chain' => 'cashew',
            'region' => 'Bono',
            'district' => 'Techiman',
        ]);
        $mill = Organization::create([
            'public_id' => 'ORG-00004',
            'name' => 'Benso Oil Palm Mill',
            'type' => 'mill',
            'chain' => 'palm',
            'region' => 'Western',
            'district' => 'Mpohor',
        ]);
        $exporter = Organization::create([
            'public_id' => 'ORG-00005',
            'name' => 'Nutex Ghana Ltd',
            'type' => 'exporter',
            'chain' => 'both',
            'region' => 'Greater Accra',
            'district' => 'Tema',
        ]);

        $password = 'password';

        $tcdaUser = User::create([
            'name' => 'TCDA',
            'email' => 'tcda@tcda.gov.gh',
            'password' => $password,
            'role' => 'tcda',
            'organization_id' => $tcda->id,
        ]);
        $officer = User::create([
            'name' => 'Field officer',
            'email' => 'officer@tcda.gov.gh',
            'password' => $password,
            'role' => 'field_officer',
            'organization_id' => $tcda->id,
            'region' => 'Western',
        ]);
        User::create([
            'name' => 'Field officer (Bono)',
            'email' => 'officer.bono@tcda.gov.gh',
            'password' => $password,
            'role' => 'field_officer',
            'organization_id' => $tcda->id,
            'region' => 'Bono',
        ]);
        $aggUser = User::create([
            'name' => 'Aggregator',
            'email' => 'agg@western.palm.gh',
            'password' => $password,
            'role' => 'aggregator',
            'organization_id' => $aggPalm->id,
            'region' => 'Western',
        ]);
        User::create([
            'name' => 'Mill / processor',
            'email' => 'mill@benso.palm.gh',
            'password' => $password,
            'role' => 'mill',
            'organization_id' => $mill->id,
            'region' => 'Western',
        ]);
        User::create([
            'name' => 'Exporter',
            'email' => 'export@nutex.gh',
            'password' => $password,
            'role' => 'exporter',
            'organization_id' => $exporter->id,
        ]);

        $mappedPalm = Farmer::create([
            'public_id' => 'FRM-00001',
            'name' => 'Kofi Adjei',
            'phone' => '0244111001',
            'community' => 'Agona Nkwanta',
            'district' => 'Ahanta West',
            'region' => 'Western',
            'chain' => 'palm',
            'paper_card_no' => 'PC-AH-104',
            'consent_at' => now()->subDays(20),
            'enrolled_by' => $officer->id,
            'aggregator_id' => $aggPalm->id,
        ]);
        $gapPalm = Farmer::create([
            'public_id' => 'FRM-00002',
            'name' => 'Abena Essel',
            'phone' => '0244111002',
            'community' => 'Dixcove',
            'district' => 'Ahanta West',
            'region' => 'Western',
            'chain' => 'palm',
            'paper_card_no' => 'PC-AH-105',
            'consent_at' => now()->subDays(12),
            'enrolled_by' => $officer->id,
            'aggregator_id' => $aggPalm->id,
        ]);
        $cashewFarmer = Farmer::create([
            'public_id' => 'FRM-00003',
            'name' => 'Seidu Abdulai',
            'phone' => '0244111003',
            'community' => 'Tuobodom',
            'district' => 'Techiman North',
            'region' => 'Bono',
            'chain' => 'cashew',
            'paper_card_no' => 'PC-BN-221',
            'consent_at' => now()->subDays(8),
            'enrolled_by' => $officer->id,
            'aggregator_id' => $aggCashew->id,
        ]);

        $plot = Plot::create([
            'public_id' => 'PLT-00001',
            'farmer_id' => $mappedPalm->id,
            'latitude' => 4.8912000,
            'longitude' => -2.2411000,
            'area_hectares' => 1.8,
            'land_status' => 'customary',
            'mapped_at' => now()->subDays(18),
            'mapped_by' => $officer->id,
        ]);
        Plot::create([
            'public_id' => 'PLT-00002',
            'farmer_id' => $cashewFarmer->id,
            'latitude' => 7.5894000,
            'longitude' => -1.9352000,
            'area_hectares' => 2.4,
            'land_status' => 'customary',
            'mapped_at' => now()->subDays(6),
            'mapped_by' => $officer->id,
        ]);

        $logger = app(CustodyLogger::class);
        $engine = app(ComplianceEngine::class);

        $readyBatch = Batch::create([
            'public_id' => 'BAT-00001',
            'chain' => 'palm',
            'status' => 'mill_received',
            'capture_channel' => 'paper',
            'aggregator_id' => $aggPalm->id,
            'mill_id' => $mill->id,
            'opened_by' => $aggUser->id,
            'closed_at' => now()->subDays(3),
            'mill_weight_kg' => 1180,
        ]);
        $readyBatch->members()->create([
            'farmer_id' => $mappedPalm->id,
            'plot_id' => $plot->id,
            'volume_kg' => 1240,
            'paper_stub_no' => 'ST-8891',
            'capture_channel' => 'paper',
            'delivered_at' => now()->subDays(4),
            'captured_by' => $aggUser->id,
        ]);
        $logger->record($readyBatch, 'opened', $aggUser);
        $logger->record($readyBatch, 'delivery_added', $aggUser, ['farmer' => 'FRM-00001']);
        $logger->record($readyBatch, 'closed', $aggUser);
        $logger->record($readyBatch, 'mill_intake', $tcdaUser, ['weight_kg' => 1180]);
        $engine->persist($readyBatch);

        $gapBatch = Batch::create([
            'public_id' => 'BAT-00002',
            'chain' => 'palm',
            'status' => 'closed',
            'capture_channel' => 'desk',
            'aggregator_id' => $aggPalm->id,
            'opened_by' => $aggUser->id,
            'closed_at' => now()->subDay(),
        ]);
        $gapBatch->members()->create([
            'farmer_id' => $gapPalm->id,
            'plot_id' => null,
            'volume_kg' => 860,
            'paper_stub_no' => 'ST-8892',
            'capture_channel' => 'desk',
            'delivered_at' => now()->subDays(2),
            'captured_by' => $aggUser->id,
        ]);
        $logger->record($gapBatch, 'opened', $aggUser);
        $logger->record($gapBatch, 'closed', $aggUser);
        $engine->persist($gapBatch);

        $openBatch = Batch::create([
            'public_id' => 'BAT-00003',
            'chain' => 'cashew',
            'status' => 'open',
            'capture_channel' => 'mobile',
            'aggregator_id' => $aggCashew->id,
            'opened_by' => $officer->id,
        ]);
        $openBatch->members()->create([
            'farmer_id' => $cashewFarmer->id,
            'plot_id' => $cashewFarmer->plots()->first()?->id,
            'volume_kg' => 320,
            'capture_channel' => 'mobile',
            'delivered_at' => now()->subHours(6),
            'captured_by' => $officer->id,
        ]);
        $logger->record($openBatch, 'opened', $officer);
        $logger->record($openBatch, 'delivery_added', $officer, ['farmer' => 'FRM-00003']);
    }
}
