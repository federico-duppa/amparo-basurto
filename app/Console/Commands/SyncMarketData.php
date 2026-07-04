<?php

namespace App\Console\Commands;

use App\Support\MarketData;
use Illuminate\Console\Command;

class SyncMarketData extends Command
{
    protected $signature = 'plata:mercado';

    protected $description = 'Trae las series de cotizaciones (blue, oficial, MEP) y de inflación para el módulo Plata';

    public function handle(): int
    {
        $this->info('Buscando cotizaciones e inflación...');

        MarketData::sync();

        $this->info('Listo, quedó todo guardado.');

        return self::SUCCESS;
    }
}
