<?php

namespace App\Models;

use Database\Factories\HealthMeasurementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthMeasurement extends Model
{
    /** @use HasFactory<HealthMeasurementFactory> */
    use HasFactory;

    /**
     * Tipos de medición que seguimos. 'dual' marca las de dos valores
     * (máxima/mínima de la presión), que se guardan en value y value2.
     */
    public const TYPES = [
        'peso' => ['label' => 'Peso', 'unit' => 'kg', 'dual' => false],
        'presion' => ['label' => 'Presión', 'unit' => 'mmHg', 'dual' => true],
        'glucemia' => ['label' => 'Glucemia', 'unit' => 'mg/dl', 'dual' => false],
        'temperatura' => ['label' => 'Temperatura', 'unit' => '°C', 'dual' => false],
    ];

    protected $fillable = [
        'type',
        'value',
        'value2',
        'measured_on',
    ];

    protected function casts(): array
    {
        return [
            'value' => 'float',
            'value2' => 'float',
            'measured_on' => 'date',
        ];
    }

    public static function isDualType(string $type): bool
    {
        return self::TYPES[$type]['dual'] ?? false;
    }

    public function typeLabel(): string
    {
        return self::TYPES[$this->type]['label'] ?? $this->type;
    }

    public function unit(): string
    {
        return self::TYPES[$this->type]['unit'] ?? '';
    }

    /**
     * Número en formato es-AR, sin decimales de relleno ("78,5", "95").
     */
    public static function formatNumber(float $value): string
    {
        return rtrim(rtrim(number_format($value, 2, ',', '.'), '0'), ',');
    }

    /**
     * La medición lista para mostrar: "78,5 kg", "120/80 mmHg".
     */
    public function formattedValue(): string
    {
        $number = self::formatNumber($this->value);

        if (self::isDualType($this->type) && $this->value2 !== null) {
            $number .= '/'.self::formatNumber($this->value2);
        }

        return trim($number.' '.$this->unit());
    }

    /**
     * @return BelongsTo<HealthRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'health_record_id');
    }

    /**
     * Quién la anotó (dueño o alguien con la historia compartida).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
