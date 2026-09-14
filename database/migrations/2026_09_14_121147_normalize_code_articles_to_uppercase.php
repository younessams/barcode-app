<?php

use App\Support\CodeArticleNormalizer;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    public function up(): void
    {
        DB::transaction(function (): void {
            $this->normalizeInventoryItems();
            $this->normalizeArticleReferences();
        });
    }

    public function down(): void
    {
        //
    }

    private function normalizeInventoryItems(): void
    {
        $groups = [];

        foreach (DB::table('inventory_items')->orderBy('id')->get() as $item) {
            $code = CodeArticleNormalizer::normalize($item->code_article);
            $key = $item->inventory_session_id.'|'.$code;

            $groups[$key] ??= [
                'code' => $code,
                'items' => [],
            ];

            $groups[$key]['items'][] = $item;
        }

        foreach ($groups as $group) {
            $items = $group['items'];
            $keep = $items[0];
            $duplicateIds = [];
            $quantityUnits = 0;

            foreach ($items as $item) {
                $quantityUnits += $this->quantityToUnits($item->quantity);

                if ($item->id !== $keep->id) {
                    $duplicateIds[] = $item->id;
                }
            }

            if ($duplicateIds !== []) {
                DB::table('inventory_items')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }

            DB::table('inventory_items')
                ->where('id', $keep->id)
                ->update([
                    'code_article' => $group['code'],
                    'quantity' => $this->unitsToQuantity($quantityUnits),
                ]);
        }
    }

    private function normalizeArticleReferences(): void
    {
        $groups = [];

        foreach (DB::table('article_references')->orderBy('id')->get() as $reference) {
            $code = CodeArticleNormalizer::normalize($reference->code_article);

            $groups[$code] ??= [
                'code' => $code,
                'references' => [],
            ];

            $groups[$code]['references'][] = $reference;
        }

        foreach ($groups as $group) {
            $references = $group['references'];

            usort($references, function ($left, $right): int {
                $updated = strcmp((string) $right->updated_at, (string) $left->updated_at);

                return $updated !== 0
                    ? $updated
                    : $right->id <=> $left->id;
            });

            $keep = $references[0];
            $duplicateIds = array_values(array_map(
                fn ($reference): int => $reference->id,
                array_filter(
                    $references,
                    fn ($reference): bool => $reference->id !== $keep->id
                )
            ));

            if ($duplicateIds !== []) {
                DB::table('article_references')
                    ->whereIn('id', $duplicateIds)
                    ->delete();
            }

            DB::table('article_references')
                ->where('id', $keep->id)
                ->update(['code_article' => $group['code']]);
        }
    }

    private function quantityToUnits(string|int|float $quantity): int
    {
        $quantity = str_replace(',', '.', trim((string) $quantity));
        [$whole, $decimal] = array_pad(explode('.', $quantity, 2), 2, '');
        $decimal = str_pad(substr($decimal, 0, 3), 3, '0');

        return ((int) $whole * 1000) + (int) $decimal;
    }

    private function unitsToQuantity(int $units): string
    {
        return sprintf('%d.%03d', intdiv($units, 1000), $units % 1000);
    }
};
