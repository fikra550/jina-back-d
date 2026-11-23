<?php

namespace App\Services;

use App\Models\Product;
use App\Models\ProductStockMovement;

/**
 * Gestion du stock en LITRES pour les produits liquides.
 *
 *  - products.stock_quantity  = nombre d'unités FERMÉES (ex: bidons de 5 L)
 *  - products.volume_per_unit = litres par unité (null => produit non liquide)
 *  - products.open_volume     = litres restants dans l'unité entamée
 *
 * Les méthodes consume()/restore() doivent être appelées dans une transaction
 * sur un produit chargé avec lockForUpdate().
 */
class ProductVolume
{
    public static function isLiquid(Product $p): bool
    {
        return (float) $p->volume_per_unit > 0;
    }

    /** Litres totaux disponibles (null si le produit n'est pas liquide). */
    public static function total(Product $p): ?float
    {
        if (!self::isLiquid($p)) return null;

        return round(((int) $p->stock_quantity) * (float) $p->volume_per_unit + (float) $p->open_volume, 2);
    }

    /**
     * Consomme $liters. Ouvre de nouvelles unités si besoin.
     * @return int nombre d'unités fermées ouvertes
     */
    public static function consume(Product $p, float $liters): int
    {
        $vol    = (float) $p->volume_per_unit;
        $liters = round($liters, 2);

        if ($vol <= 0) throw new \Exception("« {$p->name} » n'est pas un produit liquide.");
        if ($liters <= 0) throw new \Exception('Quantité (L) invalide.');

        $stock     = (int) $p->stock_quantity;
        $open      = (float) $p->open_volume;
        $available = round($stock * $vol + $open, 2);

        if ($liters > $available + 0.001) {
            throw new \Exception("Quantité insuffisante pour « {$p->name} » (disponible : {$available} L, demandé : {$liters} L)");
        }

        $units = 0;
        if ($liters <= $open + 0.001) {
            $open -= $liters;
        } else {
            $need  = $liters - $open;
            $units = (int) ceil(round($need / $vol, 6));
            $open  = $open + $units * $vol - $liters;
        }

        $p->stock_quantity = $stock - $units;
        $p->open_volume    = max(0, round($open, 2));
        $p->save();

        return $units;
    }

    /**
     * Remet $liters en stock.
     * @return int nombre d'unités fermées reconstituées
     */
    public static function restore(Product $p, float $liters): int
    {
        $vol    = (float) $p->volume_per_unit;
        $liters = round($liters, 2);
        if ($vol <= 0 || $liters <= 0) return 0;

        $stock = (int) $p->stock_quantity;
        $open  = (float) $p->open_volume + $liters;
        $units = 0;

        while ($open >= $vol - 0.001) {
            $open -= $vol;
            $stock++;
            $units++;
        }

        $p->stock_quantity = $stock;
        $p->open_volume    = max(0, round($open, 2));
        $p->save();

        return $units;
    }

    /** Consomme + écrit le mouvement de stock (historique). */
    public static function consumeAndLog(Product $p, float $liters, string $reason, string $by, array $refs = []): int
    {
        $liters = round($liters, 2);
        $units  = self::consume($p, $liters);

        ProductStockMovement::create(array_merge([
            'product_id'  => $p->id,
            'type'        => 'out',
            'quantity'    => -$units,
            'volume'      => -$liters,
            'stock_after' => (int) $p->stock_quantity,
            'reason'      => $reason,
            'created_by'  => $by,
        ], $refs));

        return $units;
    }

    /** Restaure + écrit le mouvement de stock (historique). */
    public static function restoreAndLog(Product $p, float $liters, string $reason, string $by, array $refs = []): int
    {
        $liters = round($liters, 2);
        $units  = self::restore($p, $liters);

        ProductStockMovement::create(array_merge([
            'product_id'  => $p->id,
            'type'        => 'in',
            'quantity'    => $units,
            'volume'      => $liters,
            'stock_after' => (int) $p->stock_quantity,
            'reason'      => $reason,
            'created_by'  => $by,
        ], $refs));

        return $units;
    }
}