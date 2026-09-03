<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class InventoryItem extends Model
{
    protected $fillable = ['inventory_session_id', 'code_article', 'quantity'];

    protected $casts = ['quantity' => 'integer'];

    protected static function booted(): void
    {
        self::creating(function (self $item): void {
            $item->uuid ??= (string) Str::uuid();
        });

        self::saving(function (self $item): void {
            $item->code_article = trim($item->code_article);
        });
    }

    public function inventorySession(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class);
    }
}
