<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    public function up(): void
    {
        Schema::table('inventory_items', function (Blueprint $table) {
            $table->decimal('quantity', 13, 3)->change();
        });
    }

    public function down(): void
    {
        $hasFractionalQuantities = DB::table('inventory_items')
            ->pluck('quantity')
            ->contains(function ($quantity): bool {
                $value = (string) $quantity;

                if (! str_contains($value, '.')) {
                    return false;
                }

                [, $fraction] = array_pad(explode('.', $value, 2), 2, '');

                return trim($fraction, '0') !== '';
            });

        if ($hasFractionalQuantities) {
            throw new RuntimeException(
                'Cannot rollback quantity to integer while fractional quantities exist.'
            );
        }

        Schema::table('inventory_items', function (Blueprint $table) {
            $table->unsignedInteger('quantity')->change();
        });
    }
};
