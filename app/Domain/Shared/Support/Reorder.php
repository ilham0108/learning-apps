<?php

namespace App\Domain\Shared\Support;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Memindahkan item satu langkah ke atas/bawah di antara saudaranya (kolom `position`),
 * lalu merapikan urutan menjadi 1..n.
 */
class Reorder
{
    /**
     * @template TModel of Model
     *
     * @param  Builder<TModel>  $siblings  Query semua item dalam induk yang sama.
     * @param  TModel  $item
     */
    public static function move(Builder $siblings, Model $item, string $direction): void
    {
        DB::transaction(function () use ($siblings, $item, $direction): void {
            /** @var list<int> $ids */
            $ids = (clone $siblings)->orderBy('position')->orderBy('id')->lockForUpdate()->pluck('id')->all();
            $index = array_search($item->getKey(), $ids, true);

            if ($index === false) {
                return;
            }

            $target = $direction === 'up' ? $index - 1 : $index + 1;

            if (isset($ids[$target])) {
                [$ids[$index], $ids[$target]] = [$ids[$target], $ids[$index]];
            }

            foreach ($ids as $position => $id) {
                (clone $siblings)->whereKey($id)->update(['position' => $position + 1]);
            }
        });
    }
}
