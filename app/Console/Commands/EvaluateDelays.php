<?php

namespace App\Console\Commands;

use App\Enums\ShipmentStatus;
use App\Models\Shipment;
use App\Services\EtaService;
use Illuminate\Console\Command;

class EvaluateDelays extends Command
{
    protected $signature = 'cargotrace:evaluate-delays';

    protected $description = 'Re-evaluate delay status of all in-flight shipments (shipments past their ETA become delayed over time).';

    public function handle(EtaService $eta): int
    {
        $count = 0;

        Shipment::query()
            ->whereNotIn('status', [ShipmentStatus::Delivered->value, ShipmentStatus::Cancelled->value])
            ->chunkById(100, function ($shipments) use ($eta, &$count) {
                foreach ($shipments as $shipment) {
                    $eta->evaluate($shipment);
                    $count++;
                }
            });

        $this->info("Evaluated {$count} shipment(s).");

        return self::SUCCESS;
    }
}
