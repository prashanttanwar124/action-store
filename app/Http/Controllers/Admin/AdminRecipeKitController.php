<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreRecipeKitRequest;
use App\Http\Requests\Admin\UpdateRecipeKitRequest;
use App\Models\Product;
use App\Models\RecipeKit;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminRecipeKitController extends Controller
{
    /**
     * Display a listing of chef-crafted recipe kits.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));

        $query = RecipeKit::query()->with('products')->latest();

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('subtitle_tag', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        $kits = $query->paginate(10)->withQueryString()->through(function (RecipeKit $kit) {
            return [
                'id' => $kit->id,
                'name' => $kit->name,
                'slug' => $kit->slug,
                'subtitle_tag' => $kit->subtitle_tag,
                'price' => $kit->price,
                'original_price' => $kit->original_price,
                'servings' => $kit->servings,
                'cooking_time' => $kit->cooking_time,
                'difficulty' => $kit->difficulty,
                'image' => $kit->image,
                'images' => $kit->images_list,
                'images_count' => count($kit->images_list),
                'is_active' => $kit->is_active,
                'products_count' => $kit->products->count(),
                'individual_total' => $kit->calculated_individual_total,
                'products' => $kit->products->map(fn (Product $p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                    'price' => $p->price,
                    'image' => $p->image,
                    'quantity' => $p->pivot->quantity,
                    'unit_notes' => $p->pivot->unit_notes,
                    'is_optional' => (bool) $p->pivot->is_optional,
                ]),
                'created_at' => $kit->created_at?->format('M d, Y'),
            ];
        });

        $stats = [
            'total' => RecipeKit::count(),
            'active' => RecipeKit::where('is_active', true)->count(),
        ];

        return Inertia::render('Admin/RecipeKits/Index', [
            'recipeKits' => $kits,
            'filters' => [
                'search' => $search,
            ],
            'stats' => $stats,
        ]);
    }

    /**
     * Show the form for creating a new recipe kit.
     */
    public function create(): Response
    {
        $availableProducts = Product::select('id', 'name', 'price', 'image', 'size_main', 'category')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/RecipeKits/Create', [
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Store a newly created recipe kit in storage.
     */
    public function store(StoreRecipeKitRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        $originalSlug = $slug;
        $counter = 1;
        while (RecipeKit::where('slug', $slug)->exists()) {
            $slug = "{$originalSlug}-{$counter}";
            $counter++;
        }
        $validated['slug'] = $slug;

        // Process images
        $uploadedImages = [];
        if ($request->hasFile('image_files')) {
            foreach ($request->file('image_files') as $file) {
                if ($file->isValid()) {
                    $path = $file->store('recipe-kits', 'public');
                    $uploadedImages[] = Storage::url($path);
                }
            }
        }

        $imageUrls = $validated['image_urls'] ?? [];
        $allImages = array_values(array_filter(array_merge($uploadedImages, $imageUrls)));

        if (! empty($allImages)) {
            $validated['images'] = $allImages;
            $validated['image'] = $allImages[0];
        } else {
            $validated['image'] = '/images/products/paneer_curry.jpg';
            $validated['images'] = ['/images/products/paneer_curry.jpg'];
        }

        $linkedProducts = $validated['products'] ?? [];
        unset($validated['image_files'], $validated['image_urls'], $validated['products']);

        $kit = RecipeKit::create($validated);

        // Sync linked products for Group Buy
        if (! empty($linkedProducts)) {
            $syncData = [];
            foreach ($linkedProducts as $idx => $item) {
                if (! empty($item['product_id'])) {
                    $syncData[$item['product_id']] = [
                        'quantity' => (int) ($item['quantity'] ?? 1),
                        'unit_notes' => $item['unit_notes'] ?? null,
                        'is_optional' => ! empty($item['is_optional']),
                        'sort_order' => $idx,
                    ];
                }
            }
            $kit->products()->sync($syncData);
        }

        return redirect()->route('admin.recipe-kits.index')
            ->with('success', "Recipe kit '{$kit->name}' created successfully with ".count($kit->products).' linked group buy ingredients.');
    }

    /**
     * Show the form for editing the specified recipe kit.
     */
    public function edit(RecipeKit $recipeKit): Response
    {
        $recipeKit->load('products');

        $availableProducts = Product::select('id', 'name', 'price', 'image', 'size_main', 'category')
            ->orderBy('name')
            ->get();

        return Inertia::render('Admin/RecipeKits/Edit', [
            'recipeKit' => [
                'id' => $recipeKit->id,
                'name' => $recipeKit->name,
                'slug' => $recipeKit->slug,
                'subtitle_tag' => $recipeKit->subtitle_tag,
                'description' => $recipeKit->description,
                'servings' => $recipeKit->servings,
                'cooking_time' => $recipeKit->cooking_time,
                'difficulty' => $recipeKit->difficulty,
                'price' => (float) $recipeKit->price,
                'original_price' => $recipeKit->original_price ? (float) $recipeKit->original_price : null,
                'image' => $recipeKit->image,
                'images' => $recipeKit->images_list,
                'recipe_steps' => $recipeKit->recipe_steps ?: [],
                'is_active' => (bool) $recipeKit->is_active,
                'products' => $recipeKit->products->map(fn (Product $p) => [
                    'product_id' => $p->id,
                    'name' => $p->name,
                    'price' => (float) $p->price,
                    'image' => $p->image,
                    'quantity' => $p->pivot->quantity ?? 1,
                    'unit_notes' => $p->pivot->unit_notes ?? '',
                    'is_optional' => (bool) ($p->pivot->is_optional ?? false),
                ]),
            ],
            'availableProducts' => $availableProducts,
        ]);
    }

    /**
     * Update the specified recipe kit in storage.
     */
    public function update(UpdateRecipeKitRequest $request, RecipeKit $recipeKit): RedirectResponse
    {
        $validated = $request->validated();

        if (empty($validated['slug'])) {
            $validated['slug'] = Str::slug($validated['name']);
        } else {
            $validated['slug'] = Str::slug($validated['slug']);
        }

        $existingImages = $validated['existing_images'] ?? [];

        $newUploadedImages = [];
        if ($request->hasFile('image_files')) {
            foreach ($request->file('image_files') as $file) {
                if ($file->isValid()) {
                    $path = $file->store('recipe-kits', 'public');
                    $newUploadedImages[] = Storage::url($path);
                }
            }
        }

        $imageUrls = $validated['image_urls'] ?? [];
        $combinedImages = array_values(array_filter(array_merge($existingImages, $newUploadedImages, $imageUrls)));

        if (! empty($combinedImages)) {
            $validated['images'] = $combinedImages;
            $validated['image'] = $combinedImages[0];
        } else {
            $validated['image'] = $recipeKit->image ?: '/images/products/paneer_curry.jpg';
            $validated['images'] = [$validated['image']];
        }

        $linkedProducts = $validated['products'] ?? [];
        unset($validated['image_files'], $validated['existing_images'], $validated['image_urls'], $validated['products']);

        $recipeKit->update($validated);

        // Sync linked products
        $syncData = [];
        foreach ($linkedProducts as $idx => $item) {
            if (! empty($item['product_id'])) {
                $syncData[$item['product_id']] = [
                    'quantity' => (int) ($item['quantity'] ?? 1),
                    'unit_notes' => $item['unit_notes'] ?? null,
                    'is_optional' => ! empty($item['is_optional']),
                    'sort_order' => $idx,
                ];
            }
        }
        $recipeKit->products()->sync($syncData);

        return redirect()->route('admin.recipe-kits.index')
            ->with('success', "Recipe kit '{$recipeKit->name}' updated successfully.");
    }

    /**
     * Remove the specified recipe kit from storage.
     */
    public function destroy(RecipeKit $recipeKit): RedirectResponse
    {
        $name = $recipeKit->name;

        if (is_array($recipeKit->images)) {
            foreach ($recipeKit->images as $img) {
                if (str_starts_with($img, '/storage/recipe-kits/')) {
                    $relative = str_replace('/storage/', '', $img);
                    Storage::disk('public')->delete($relative);
                }
            }
        }

        $recipeKit->delete();

        return redirect()->route('admin.recipe-kits.index')
            ->with('success', "Recipe kit '{$name}' deleted successfully.");
    }
}
