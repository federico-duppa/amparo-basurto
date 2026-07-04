<?php

namespace App\Models;

use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

class ExchangeRate extends Model
{
    public const TYPES = ['blue', 'oficial', 'mep'];

    protected $fillable = [
        'rate_type',
        'quoted_on',
        'buy',
        'sell',
    ];

    protected function casts(): array
    {
        return [
            'quoted_on' => 'date',
            'buy' => 'decimal:4',
            'sell' => 'decimal:4',
        ];
    }

    /**
     * La cotización vigente a una fecha: la última conocida hasta ese día o,
     * si la serie empieza después, la primera que haya.
     */
    public static function quoteOn(string $type, CarbonInterface $date): ?self
    {
        return static::where('rate_type', $type)
            ->whereDate('quoted_on', '<=', $date)
            ->orderByDesc('quoted_on')
            ->first()
            ?? static::where('rate_type', $type)->orderBy('quoted_on')->first();
    }
}
