<?php

namespace App\Models;

use Database\Factories\HealthContactFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HealthContact extends Model
{
    /** @use HasFactory<HealthContactFactory> */
    use HasFactory;

    protected $fillable = [
        'name',
        'specialty',
        'phone',
        'note',
    ];

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
