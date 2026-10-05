<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreProductRequest;
use App\Http\Requests\Admin\UpdateProductRequest;
use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminProductController extends Controller
{
    /**
     * Standard category choices with titles.
     *
     * @var array<string, string>
     */
    protected array $defaultCategories = [
        'grocery' => 'Pantry & Groceries',
        'spices' => 'Fresh Spices & Masalas',
        'dairy' => 'Dairy, Paneer & Ghee',
        'sweets' => 'Indian Sweets & Snacks',
        'vegetables' => 'Fresh Herbs & Produce',
        'recipe-kits' => 'Artisanal Meal Kits',
    ];

    /**
     * Get available categories from database or fallback defaults.
     *
     * @return array<string, string>
     */
    protected function getCategoriesList(): array
    {
        $dbCategories = Category::where('is_active', true)->orderBy('sort_order')->pluck('name', 'slug')->toArray();
        if (! empty($dbCategories)) {
            return $dbCategories;
        }

        return $this->defaultCategories;
    }

    /**
     * Display a listing of products with search and filtering.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $category = trim((string) $request->input('category', ''));

        $query = Product::query()->with('supplier:id,name,code')->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('category', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($category !== '' && $category !== 'all') {
            $query->where('category', $category);
        }

        $products = $query->paginate(12)->withQueryString()->through(function (Product $product) {
            return [
                'id' => $product->id,
                'name' => $product->name,
                'slug' => $product->slug,
                'category' => $product->category,
                'category_title' => $product->category_title,
                'price' => $product->price,
                'original_price' => $product->original_price,
                'stock' => $product->stock,
                'stock_badge' => $product->stock_badge,
                'size_main' => $product->size_main,
                'image' => $product->image,
                'images' => $product->images_list,
                'images_count' => count($product->images_list),
                'supplier' => $product->supplier ? [
                    'id' => $product->supplier->id,
                    'name' => $product->supplier->name,
                    'code' => $product->supplier->code,
                ] : null,
                'created_at' => $product->created_at?->format('M d, Y'),
            ];
        });

        $categories = Product::select('category')->distinct()->pluck('category')->filter()->values();

        $stats = [
            'total' => Product::count(),
            'categories_count' => $categories->count(),
        ];

        return Inertia::render('Admin/Products/Index', [
            'products' => $products,
            'filters' => [
                'search' => $search,
                'category' => $category,
            ],
            'categories' => $categories,
            'defaultCategories' => $this->getCategoriesList(),
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new product.
     */
    public function create(): Response
    {
        return Inertia::render('Admin/Products/Create', [
            'defaultCategories' => $this->getCategoriesList(),
            'suppliers' => Supplier::where('status', 'active')->orderBy('name')->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Store a newly created product in storage.
     */
    public function store(StoreProductRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        // Generate clean unique slug if omitted
        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $originalSlug = $slug;
        $existingSlugs = Product::where('slug', $slug)
            ->orWhere('slug', 'like', "{$originalSlug}-%")
            ->pluck('slug')
            ->flip()
            ->all();

        if (isset($existingSlugs[$slug])) {
            $counter = 1;
            while (isset($existingSlugs["{$originalSlug}-{$counter}"])) {
                $counter++;
            }
            $slug = "{$originalSlug}-{$counter}";
        }
        $validated['slug'] = $slug;

        $categoriesList = $this->getCategoriesList();
        if (empty($validated['category_title']) && isset($categoriesList[$validated['category']])) {
            $validated['category_title'] = $categoriesList[$validated['category']];
        }

        // Process Multiple Images
        $uploadedImages = [];
        if ($request->hasFile('image_files')) {
            foreach ($request->file('image_files') as $file) {
                if ($file->isValid()) {
                    $path = $file->store('products', 'public');
                    $uploadedImages[] = Storage::url($path);
                }
            }
        }

        // Merge with any manual image URLs
        $imageUrls = $validated['image_urls'] ?? [];
        $allImages = array_values(array_filter(array_merge($uploadedImages, $imageUrls)));

        if (! empty($allImages)) {
            $validated['images'] = $allImages;
            $validated['image'] = $allImages[0];
        } else {
            $validated['image'] = '/images/products/atta.jpg';
            $validated['images'] = ['/images/products/atta.jpg'];
        }

        unset($validated['image_files'], $validated['image_urls']);

        if (isset($validated['stock'])) {
            $stockVal = (int) $validated['stock'];
            if ($stockVal <= 0) {
                $validated['stock_badge'] = 'Out of stock';
            } elseif ($stockVal <= 25) {
                $validated['stock_badge'] = "In stock · {$stockVal} left";
            } else {
                $validated['stock_badge'] = 'In stock';
            }
        }

        Product::create($validated);

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$validated['name']}' created successfully with ".count($validated['images']).' image(s).');
    }

    /**
     * Show the form for editing the specified product.
     */
    public function edit(Product $product): Response
    {
        return Inertia::render('Admin/Products/Edit', [
            'product' => [
                'id' => $product->id,
                'supplier_id' => $product->supplier_id,
                'name' => $product->name,
                'slug' => $product->slug,
                'subtitle_tag' => $product->subtitle_tag,
                'category' => $product->category,
                'category_title' => $product->category_title,
                'price' => (float) $product->price,
                'original_price' => $product->original_price ? (float) $product->original_price : null,
                'unit_price' => $product->unit_price,
                'stock' => (int) ($product->stock ?? 50),
                'stock_badge' => $product->stock_badge,
                'photo_label' => $product->photo_label,
                'image' => $product->image,
                'images' => $product->images_list,
                'size_main' => $product->size_main,
                'size_sub' => $product->size_sub,
                'freshness_line' => $product->freshness_line,
                'description' => $product->description,
                'buy_again' => (bool) $product->buy_again,
                'has_subscription' => (bool) $product->has_subscription,
            ],
            'defaultCategories' => $this->getCategoriesList(),
            'suppliers' => Supplier::where('status', 'active')
                ->when($product->supplier_id, fn ($q) => $q->orWhere('id', $product->supplier_id))
                ->orderBy('name')
                ->get(['id', 'name', 'code']),
        ]);
    }

    /**
     * Update the specified product in storage.
     */
    public function update(UpdateProductRequest $request, Product $product): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $categoriesList = $this->getCategoriesList();
        if (empty($validated['category_title']) && isset($categoriesList[$validated['category']])) {
            $validated['category_title'] = $categoriesList[$validated['category']];
        }

        // Existing images kept by admin
        $existingImages = $validated['existing_images'] ?? [];

        // Upload any new image files
        $newUploadedImages = [];
        if ($request->hasFile('image_files')) {
            foreach ($request->file('image_files') as $file) {
                if ($file->isValid()) {
                    $path = $file->store('products', 'public');
                    $newUploadedImages[] = Storage::url($path);
                }
            }
        }

        // Additional image URLs
        $imageUrls = $validated['image_urls'] ?? [];

        // Combine into full image gallery
        $combinedImages = array_values(array_filter(array_merge($existingImages, $newUploadedImages, $imageUrls)));

        if (! empty($combinedImages)) {
            $validated['images'] = $combinedImages;
            $validated['image'] = $combinedImages[0];
        } else {
            // Keep previous main image if available
            $validated['image'] = $product->image ?: '/images/products/atta.jpg';
            $validated['images'] = [$validated['image']];
        }

        unset($validated['image_files'], $validated['existing_images'], $validated['image_urls']);

        if (isset($validated['stock'])) {
            $stockVal = (int) $validated['stock'];
            if ($stockVal <= 0) {
                $validated['stock_badge'] = 'Out of stock';
            } elseif ($stockVal <= 25) {
                $validated['stock_badge'] = "In stock · {$stockVal} left";
            } else {
                $validated['stock_badge'] = 'In stock';
            }
        }

        $product->update($validated);

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$product->name}' updated successfully.");
    }

    /**
     * Remove the specified product from storage.
     */
    public function destroy(Product $product): RedirectResponse
    {
        $name = $product->name;

        // Clean up uploaded images in storage if applicable
        if (is_array($product->images)) {
            foreach ($product->images as $img) {
                if (str_starts_with($img, '/storage/products/')) {
                    $relative = str_replace('/storage/', '', $img);
                    Storage::disk('public')->delete($relative);
                }
            }
        }

        $product->delete();

        return redirect()->route('admin.products.index')
            ->with('success', "Product '{$name}' was deleted successfully.");
    }
}
