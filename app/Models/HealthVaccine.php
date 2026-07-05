<?php

namespace App\Models;

use App\Models\Concerns\HasExpiryStatus;
use Carbon\CarbonInterface;
use Database\Factories\HealthVaccineFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthVaccine extends Model
{
    /** @use HasFactory<HealthVaccineFactory> */
    use HasExpiryStatus, HasFactory;

    protected $fillable = [
        'name',
        'dose',
        'applied_on',
        'next_due_on',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'applied_on' => 'date',
            'next_due_on' => 'date',
        ];
    }

    /**
     * Estado de la próxima dosis, si está anotada: sirve para avisar cuando
     * se acerca o ya pasó.
     *
     * @return array{level: string, rank: int, urgency: int, headline: string, detail: string}|null
     */
    public function nextDoseStatus(?CarbonInterface $today = null): ?array
    {
        return $this->next_due_on ? $this->statusFor($this->next_due_on, $today) : null;
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
