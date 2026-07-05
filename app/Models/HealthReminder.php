<?php

namespace App\Models;

use App\Models\Concerns\HasExpiryStatus;
use Carbon\Carbon;
use Carbon\CarbonInterface;
use Database\Factories\HealthReminderFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthReminder extends Model
{
    /** @use HasFactory<HealthReminderFactory> */
    use HasExpiryStatus, HasFactory;

    protected $fillable = [
        'name',
        'expires_on',
        'interval_months',
        'note',
    ];

    protected function casts(): array
    {
        return [
            'expires_on' => 'date',
            'interval_months' => 'integer',
        ];
    }

    /**
     * Próxima fecha sugerida al marcarlo como hecho, según su periodicidad.
     * Sin periodicidad no hay sugerencia.
     */
    public function suggestedNextExpiry(): ?Carbon
    {
        return $this->interval_months
            ? $this->expires_on->copy()->addMonths($this->interval_months)
            : null;
    }

    /**
     * @return array{level: string, rank: int, urgency: int, headline: string, detail: string}
     */
    public function status(?CarbonInterface $today = null): array
    {
        return $this->statusFor($this->expires_on, $today);
    }

    /**
     * @return BelongsTo<HealthRecord, $this>
     */
    public function record(): BelongsTo
    {
        return $this->belongsTo(HealthRecord::class, 'health_record_id');
    }

    /**
     * Quién lo anotó (dueño o alguien con la historia compartida).
     *
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
