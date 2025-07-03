<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\MarginCalculationService;

class CalculateMargins extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'margins:calculate {--export : Exporter le rapport}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Calculer toutes les marges produits';

    /**
     * Execute the console command.
     */
    public function handle(MarginCalculationService $marginService)
    {
        $this->info('Calcul des marges en cours...');

        $marginService->calculateAllMargins();

        $this->info('✓ Marges calculées avec succès');

        if ($this->option('export')) {
            $this->info('Export du rapport...');
            $report = $marginService->exportMarginReport();
            $this->table(
                array_keys($report[0] ?? []),
                $report
            );
        }

        return 0;
    }
}
