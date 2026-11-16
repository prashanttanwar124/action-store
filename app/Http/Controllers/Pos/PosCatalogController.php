<?php

namespace App\Http\Controllers\Pos;

use App\Http\Controllers\Controller;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

class PosCatalogController extends Controller
{
    public const UNCATEGORIZED_SLUG = '__uncategorized__';

    /**
     * Columns required by the POS register catalog and cart lines.
     *
     * @var list<string>
     */
    private const CATALOG_COLUMNS = [
        'id',
        'slug',
        'barcode',
        'alternate_barcodes',
        'sku',
        'name',
        'category',
        'category_title',
        'price',
        'unit_type',
        'is_taxable',
        'stock',
        'size_main',
        'image',
        'images',
    ];

    /**
     * Fetch paginated POS products on demand by category, scale filter, or search query.
     */
    public function index(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'ids' => ['nullable', 'array', 'max:100'],
            'ids.*' => ['integer', 'min:1'],
            'category' => ['nullable', 'string', 'max:100'],
            'weighed' => ['nullable', 'boolean'],
            'q' => ['nullable', 'string', 'max:120'],
            'page' => ['nullable', 'integer', 'min:1'],
            'per_page' => ['nullable', 'integer', 'min:1', 'max:100'],
        ]);

        $ids = array_values(array_unique(array_map('intval', (array) ($validated['ids'] ?? []))));
        $category = trim((string) ($validated['category'] ?? ''));
        $weighedOnly = $request->boolean('weighed');
        $search = trim((string) ($validated['q'] ?? ''));
        $perPage = (int) ($validated['per_page'] ?? 48);

        $query = Product::query()->select(self::CATALOG_COLUMNS);

        if ($ids !== []) {
            $query->whereIn('id', $ids);
        }

        if ($weighedOnly) {
            $query->whereNotNull('unit_type')
                ->where('unit_type', '!=', 'each');
        }

        if ($category !== '' && $category !== 'all') {
            if ($category === self::UNCATEGORIZED_SLUG) {
                $query->where(function (Builder $builder): void {
                    $builder->whereNull('category')
                        ->orWhere('category', '');
                });
            } else {
                $query->where('category', $category);
            }
        }

        if ($search !== '') {
            $escaped = addcslashes($search, '%_');
            $lowerSearch = mb_strtolower($search);
            $barcodeVariants = self::barcodeVariants($search);

            $query->where(function (Builder $builder) use ($search, $escaped, $lowerSearch, $barcodeVariants): void {
                $builder->whereIn('barcode', $barcodeVariants)
                    ->orWhere('sku', $search)
                    ->orWhereRaw('LOWER(COALESCE(sku, \'\')) = ?', [$lowerSearch])
                    ->orWhere('name', 'like', "%{$escaped}%")
                    ->orWhere('sku', 'like', "%{$escaped}%")
                    ->orWhere('barcode', 'like', "%{$escaped}%")
                    ->orWhere('category_title', 'like', "%{$escaped}%");

                foreach ($barcodeVariants as $variant) {
                    $builder->orWhereJsonContains('alternate_barcodes', $variant);
                }
            });

            $query->orderByRaw(
                "CASE
                    WHEN barcode = ? OR LOWER(COALESCE(sku, '')) = ? THEN 0
                    WHEN LOWER(name) LIKE ? THEN 1
                    ELSE 2
                END ASC",
                [$search, $lowerSearch, addcslashes($lowerSearch, '%_').'%'],
            )->orderBy('name');
        } else {
            $query->orderBy('name');
        }

        $paginator = $query->paginate($perPage);

        return response()->json([
            'products' => $paginator->getCollection()
                ->map(static fn (Product $product): array => self::formatProduct($product))
                ->values()
                ->all(),
            'meta' => [
                'current_page' => $paginator->currentPage(),
                'last_page' => $paginator->lastPage(),
                'per_page' => $paginator->perPage(),
                'total' => $paginator->total(),
                'has_more' => $paginator->hasMorePages(),
            ],
        ]);
    }

    /**
     * Resolve a single product immediately by indexed barcode, UPC/EAN leading-zero variant, alternate barcode, or PLU/SKU scan code.
     */
    public function scan(Request $request): JsonResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'max:120'],
        ]);

        $code = trim($validated['code']);
        $upperCode = mb_strtoupper($code);
        $lowerCode = mb_strtolower($code);
        $barcodeVariants = self::barcodeVariants($code);

        $product = Product::query()
            ->select(self::CATALOG_COLUMNS)
            ->where(function (Builder $builder) use ($code, $upperCode, $lowerCode, $barcodeVariants): void {
                $builder->whereIn('barcode', $barcodeVariants)
                    ->orWhere('sku', $code)
                    ->orWhere('sku', $upperCode)
                    ->orWhere('sku', $lowerCode);

                foreach ($barcodeVariants as $variant) {
                    $builder->orWhereJsonContains('alternate_barcodes', $variant);
                }
            })
            ->orderByRaw('CASE WHEN barcode = ? THEN 0 WHEN sku = ? THEN 1 ELSE 2 END ASC', [$code, $code])
            ->first();

        if (! $product) {
            return response()->json([
                'product' => null,
                'message' => "No product matches barcode or PLU \"{$code}\".",
            ], 404);
        }

        return response()->json([
            'product' => self::formatProduct($product),
        ]);
    }

    /**
     * Build normalized UPC-A / EAN-13 / GTIN-14 leading-zero variants for physical scanner interoperability.
     *
     * @return list<string>
     */
    public static function barcodeVariants(string $rawCode): array
    {
        $code = trim($rawCode);
        if ($code === '') {
            return [];
        }

        $variants = [$code];

        if (preg_match('/^\d+$/', $code) === 1) {
            $stripped = ltrim($code, '0');
            if ($stripped !== '') {
                $variants[] = $stripped;
                foreach ([8, 12, 13, 14] as $targetLength) {
                    if (strlen($stripped) <= $targetLength) {
                        $variants[] = str_pad($stripped, $targetLength, '0', STR_PAD_LEFT);
                    }
                }
            }
        }

        return array_values(array_unique($variants));
    }

    /**
     * Build lightweight POS Home department cards and aggregate SKU counts without loading products.
     *
     * @return array{
     *     categories: list<array{
     *         id: int|string,
     *         name: string,
     *         slug: string,
     *         hindi_title: string|null,
     *         icon: string|null,
     *         products_count: int
     *     }>,
     *     summary: array{total_products: int, weighed_products: int}
     * }
     */
    public static function buildCatalogOverview(): array
    {
        $activeCategories = Category::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->orderBy('name')
            ->get(['id', 'name', 'slug', 'hindi_title', 'icon']);

        $rawGroups = Product::query()
            ->selectRaw("
                category,
                COUNT(*) as products_count,
                SUM(CASE WHEN unit_type IS NOT NULL AND unit_type != 'each' THEN 1 ELSE 0 END) as weighed_count
            ")
            ->groupBy('category')
            ->get();

        /** @var array<string, array{products_count: int, weighed_count: int}> $productGroups */
        $productGroups = [];
        $totalProducts = 0;
        $weighedProducts = 0;

        foreach ($rawGroups as $row) {
            $slug = trim((string) ($row->category ?? ''));
            $count = (int) $row->products_count;
            $weighed = (int) $row->weighed_count;

            $totalProducts += $count;
            $weighedProducts += $weighed;

            if (! isset($productGroups[$slug])) {
                $productGroups[$slug] = [
                    'products_count' => 0,
                    'weighed_count' => 0,
                ];
            }

            $productGroups[$slug]['products_count'] += $count;
            $productGroups[$slug]['weighed_count'] += $weighed;
        }

        $knownSlugs = [];
        $categories = [];

        foreach ($activeCategories as $category) {
            $slug = (string) $category->slug;
            $knownSlugs[$slug] = true;
            $group = $productGroups[$slug] ?? null;

            $categories[] = [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $slug,
                'hindi_title' => $category->hindi_title,
                'icon' => $category->icon ?: 'Package',
                'products_count' => (int) ($group['products_count'] ?? 0),
            ];
        }

        foreach ($productGroups as $slug => $group) {
            $count = $group['products_count'];
            if ($count <= 0) {
                continue;
            }

            if ($slug !== '' && isset($knownSlugs[$slug])) {
                continue;
            }

            if ($slug === '') {
                $categories[] = [
                    'id' => self::UNCATEGORIZED_SLUG,
                    'name' => 'General Merchandise',
                    'slug' => self::UNCATEGORIZED_SLUG,
                    'hindi_title' => null,
                    'icon' => 'Package',
                    'products_count' => $count,
                ];

                continue;
            }

            $categories[] = [
                'id' => "derived-{$slug}",
                'name' => Str::headline($slug),
                'slug' => $slug,
                'hindi_title' => null,
                'icon' => 'Package',
                'products_count' => $count,
            ];
        }

        return [
            'categories' => $categories,
            'summary' => [
                'total_products' => $totalProducts,
                'weighed_products' => $weighedProducts,
            ],
        ];
    }

    /**
     * Format a Product model into a lean POS register payload.
     *
     * @return array{
     *     id: int,
     *     slug: string,
     *     barcode: string|null,
     *     sku: string|null,
     *     name: string,
     *     category: string|null,
     *     category_title: string|null,
     *     price: float,
     *     unit_type: string,
     *     is_taxable: bool,
     *     stock: int,
     *     size_main: string|null,
     *     image: string|null
     * }
     */
    public static function formatProduct(Product $product): array
    {
        return [
            'id' => $product->id,
            'slug' => $product->slug,
            'barcode' => $product->barcode,
            'sku' => $product->sku,
            'name' => $product->name,
            'category' => $product->category,
            'category_title' => $product->category_title,
            'price' => (float) $product->price,
            'unit_type' => $product->unit_type ?? 'each',
            'is_taxable' => (bool) $product->is_taxable,
            'stock' => (int) ($product->stock ?? 0),
            'size_main' => $product->size_main,
            'image' => $product->images_list[0] ?? $product->image,
        ];
    }
}
