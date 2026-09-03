<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

final class InventorySession extends Model
{
    use HasFactory;

    public const STATUS_IN_PROGRESS = 'in_progress';

    public const STATUS_COMPLETED = 'completed';

    protected $fillable = ['name', 'zone', 'status', 'started_at', 'finished_at'];

    protected $casts = ['started_at' => 'datetime', 'finished_at' => 'datetime'];

    protected static function booted(): void
    {
        self::creating(function (self $session): void {
            $session->uuid ??= (string) Str::uuid();
            $session->started_at ??= now();
            $session->status ??= self::STATUS_IN_PROGRESS;
        });
    }

    public function items(): HasMany
    {
        return $this->hasMany(InventoryItem::class);
    }

    public function isCompleted(): bool
    {
        return $this->status === self::STATUS_COMPLETED;
    }
}
