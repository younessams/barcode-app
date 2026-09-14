<?php

namespace App\Models;

use App\Support\CodeArticleNormalizer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

final class InventoryItem extends Model
{
    protected $fillable = ['inventory_session_id', 'code_article', 'quantity'];

    protected $casts = ['quantity' => 'decimal:3'];

    protected static function booted(): void
    {
        self::creating(function (self $item): void {
            $item->uuid ??= (string) Str::uuid();
        });

        self::saving(function (self $item): void {
            $item->code_article = CodeArticleNormalizer::normalize($item->code_article);
        });
    }

    public function inventorySession(): BelongsTo
    {
        return $this->belongsTo(InventorySession::class);
    }
}
