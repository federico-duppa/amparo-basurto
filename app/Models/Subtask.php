<?php

namespace App\Models;

use Database\Factories\SubtaskFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Subtask extends Model
{
    /** @use HasFactory<SubtaskFactory> */
    use HasFactory;

    protected $fillable = [
        'title',
        'completed_at',
        'position',
    ];

    protected function casts(): array
    {
        return [
            'completed_at' => 'datetime',
            'position' => 'integer',
        ];
    }

    public function isCompleted(): bool
    {
        return $this->completed_at !== null;
    }

    /**
     * @return BelongsTo<Todo, $this>
     */
    public function todo(): BelongsTo
    {
        return $this->belongsTo(Todo::class);
    }
}
