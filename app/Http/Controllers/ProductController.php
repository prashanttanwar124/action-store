<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RecipeKit;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;
use Inertia\Response;

class ProductController extends Controller
{
    /**
     * Display the specified product detail page or redirect to recipe kit if matched.
     */
    public function show(string $slug): Response|RedirectResponse
    {
        $product = Product::query()->where('slug', $slug)->first();

        if (! $product) {
            $kit = RecipeKit::query()->with('products')->where('is_active', true)->where('slug', $slug)->first();
            if ($kit) {
                return redirect()->route('recipe-kits.show', $slug);
            }

            abort(404);
        }

        abort_if(isset($product->is_active) && ! $product->is_active, 404);

        $fbtProducts = $this->resolveFrequentlyBoughtTogether($product);
        $product->setAttribute('frequently_bought_together', $fbtProducts);

        return Inertia::render('ProductDetail', [
            'slug' => $slug,
            'product' => $product,
            'frequentlyBoughtTogether' => $fbtProducts,
        ]);
    }

    /**
     * Resolve valid companion products for Frequently Bought Together.
     *
     * @return array<int, array<string, mixed>>
     */
    protected function resolveFrequentlyBoughtTogether(Product $product): array
    {
        $fbt = $product->frequently_bought_together ?? [];
        $configuredIds = [];
        $configuredSlugs = [];

        if (is_array($fbt) && ! empty($fbt)) {
            foreach ($fbt as $item) {
                $val = is_array($item) ? ($item['slug'] ?? $item['id'] ?? null) : $item;
                if ($val === null || $val === '') {
                    continue;
                }
                if (is_numeric($val)) {
                    $configuredIds[] = (int) $val;
                } else {
                    $configuredSlugs[] = (string) $val;
                }
            }
        }

        $matchedProducts = collect();

        if (! empty($configuredIds) || ! empty($configuredSlugs)) {
            $matchedProducts = Product::query()
                ->where('id', '!=', $product->id)
                ->where(function ($q) use ($configuredIds, $configuredSlugs) {
                    if (! empty($configuredIds)) {
                        $q->whereIn('id', array_values(array_unique($configuredIds)));
                    }
                    if (! empty($configuredSlugs)) {
                        $q->orWhereIn('slug', array_values(array_unique($configuredSlugs)));
                    }
                })
                ->get();
        }

        // If fewer than 3 companion items are available, dynamically complement with smart recommendations
        if ($matchedProducts->count() < 3) {
            $existingIds = $matchedProducts->pluck('id')->push($product->id)->all();

            $complementaryCategories = match ($product->category) {
                'dairy' => ['spices', 'produce', 'staples'],
                'staples' => ['dairy', 'spices', 'produce'],
                'produce' => ['spices', 'dairy', 'staples'],
                'spices' => ['produce', 'dairy', 'staples'],
                'snacks' => ['sweets', 'snacks'],
                'sweets' => ['snacks'],
                default => ['staples', 'spices', 'dairy'],
            };

            $remainingCount = 3 - $matchedProducts->count();

            $fallbackItems = Product::query()
                ->whereNotIn('id', $existingIds)
                ->whereIn('category', $complementaryCategories)
                ->limit($remainingCount)
                ->get();

            if ($fallbackItems->count() < $remainingCount) {
                $additionalNeeded = $remainingCount - $fallbackItems->count();
                $excludedIds = array_merge($existingIds, $fallbackItems->pluck('id')->all());

                $additionalFallback = Product::query()
                    ->whereNotIn('id', $excludedIds)
                    ->limit($additionalNeeded)
                    ->get();

                $fallbackItems = $fallbackItems->concat($additionalFallback);
            }

            $matchedProducts = $matchedProducts->concat($fallbackItems);
        }

        return $matchedProducts->slice(0, 3)->map(function (Product $p) {
            $isOutOfStock = ((int) $p->stock <= 0) || str_contains(strtolower((string) $p->stock_badge), 'out');

            return [
                'id' => $p->id,
                'slug' => $p->slug,
                'name' => $p->name,
                'price' => (float) $p->price,
                'original_price' => $p->original_price ? (float) $p->original_price : (float) $p->price,
                'size' => $p->size_main ?? ($p->size_sub ?? ''),
                'image' => $p->image,
                'category' => $p->category,
                'stock' => (int) $p->stock,
                'stock_badge' => $p->stock_badge,
                'checked' => ! $isOutOfStock,
            ];
        })->values()->all();
    }
}
