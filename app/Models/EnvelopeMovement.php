<?php

namespace App\Models;

use Database\Factories\EnvelopeMovementFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class EnvelopeMovement extends Model
{
    /** @use HasFactory<EnvelopeMovementFactory> */
    use HasFactory;

    public const APORTE = 'aporte';

    public const RETIRO = 'retiro';

    public const TRANSFER_IN = 'transfer_in';

    public const TRANSFER_OUT = 'transfer_out';

    protected $fillable = [
        'type',
        'amount',
        'currency',
        'moved_on',
        'note',
        'transfer_group',
        'exchange_rate',
    ];

    protected function casts(): array
    {
        return [
            'amount' => 'decimal:2',
            'moved_on' => 'date',
            'exchange_rate' => 'decimal:4',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<Envelope, $this>
     */
    public function envelope(): BelongsTo
    {
        return $this->belongsTo(Envelope::class);
    }

    public function isEntrada(): bool
    {
        return in_array($this->type, [self::APORTE, self::TRANSFER_IN], true);
    }
}
