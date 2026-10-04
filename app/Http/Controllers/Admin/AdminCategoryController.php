<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreCategoryRequest;
use App\Http\Requests\Admin\UpdateCategoryRequest;
use App\Models\Category;
use App\Models\Product;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Inertia\Inertia;
use Inertia\Response;

class AdminCategoryController extends Controller
{
    /**
     * Display a listing of categories with product counts, metrics, and search.
     */
    public function index(Request $request): Response
    {
        $search = trim((string) $request->input('search', ''));
        $status = (string) $request->input('status', 'all');
        $sort = (string) $request->input('sort', 'sort_order');

        $query = Category::query()->withCount('products');

        if ($search !== '') {
            $query->where(function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                    ->orWhere('slug', 'like', "%{$search}%")
                    ->orWhere('hindi_title', 'like', "%{$search}%")
                    ->orWhere('description', 'like', "%{$search}%");
            });
        }

        if ($status === 'active') {
            $query->where('is_active', true);
        } elseif ($status === 'inactive') {
            $query->where('is_active', false);
        }

        match ($sort) {
            'name' => $query->orderBy('name'),
            'products_count' => $query->orderByDesc('products_count'),
            'latest' => $query->latest('created_at'),
            default => $query->orderBy('sort_order')->orderBy('name'),
        };

        $categories = $query->paginate(15)->withQueryString()->through(function (Category $category) {
            return [
                'id' => $category->id,
                'name' => $category->name,
                'slug' => $category->slug,
                'hindi_title' => $category->hindi_title,
                'description' => $category->description,
                'image' => $category->image,
                'icon' => $category->icon,
                'sort_order' => (int) $category->sort_order,
                'is_active' => (bool) $category->is_active,
                'products_count' => (int) $category->products_count,
                'created_at' => $category->created_at?->format('M d, Y'),
            ];
        });

        $metrics = [
            'total_categories' => Category::count(),
            'active_categories' => Category::where('is_active', true)->count(),
            'total_products' => Product::count(),
        ];

        return Inertia::render('Admin/Categories/Index', [
            'categories' => $categories,
            'metrics' => $metrics,
            'filters' => [
                'search' => $search,
                'status' => $status,
                'sort' => $sort,
            ],
        ]);
    }

    /**
     * Store a newly created category in storage.
     */
    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        $slug = ! empty($validated['slug'])
            ? Str::slug($validated['slug'])
            : Str::slug($validated['name']);

        // Handle image upload or URL
        $image = null;
        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('categories', 'public');
            $image = Storage::url($path);
        } elseif (! empty($validated['image_url'])) {
            $image = $validated['image_url'];
        } else {
            $image = '/images/products/atta.jpg';
        }

        $category = Category::create([
            'name' => $validated['name'],
            'slug' => $slug,
            'hindi_title' => $validated['hindi_title'] ?? null,
            'description' => $validated['description'] ?? null,
            'image' => $image,
            'icon' => $validated['icon'] ?? 'Package',
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', true),
        ]);

        return back()->with('success', "Category '{$category->name}' created successfully.");
    }

    /**
     * Update the specified category in storage.
     */
    public function update(UpdateCategoryRequest $request, Category $category): RedirectResponse
    {
        $validated = $request->validated();
        $oldSlug = $category->slug;
        $newSlug = Str::slug($validated['slug']);

        $updateData = [
            'name' => $validated['name'],
            'slug' => $newSlug,
            'hindi_title' => $validated['hindi_title'] ?? null,
            'description' => $validated['description'] ?? null,
            'icon' => $validated['icon'] ?? $category->icon,
            'sort_order' => (int) ($validated['sort_order'] ?? 0),
            'is_active' => $request->boolean('is_active', $category->is_active),
        ];

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('categories', 'public');
            $updateData['image'] = Storage::url($path);
        } elseif (! empty($validated['image_url'])) {
            $updateData['image'] = $validated['image_url'];
        }

        $category->update($updateData);

        // If slug or name changed, synchronize existing products referencing this category
        if ($oldSlug !== $newSlug || $category->wasChanged('name')) {
            Product::where('category', $oldSlug)->update([
                'category' => $newSlug,
                'category_title' => $category->name,
            ]);
        }

        return back()->with('success', "Category '{$category->name}' updated successfully.");
    }

    /**
     * Remove the specified category from storage.
     */
    public function destroy(Category $category): RedirectResponse
    {
        $name = $category->name;
        $slug = $category->slug;

        // If products exist in this category, re-assign them to general 'grocery'
        Product::where('category', $slug)->update([
            'category' => 'grocery',
            'category_title' => 'Pantry & Groceries',
        ]);

        $category->delete();

        return back()->with('success', "Category '{$name}' deleted successfully.");
    }

    /**
     * Fast toggle active status.
     */
    public function toggleActive(Request $request, Category $category): JsonResponse|RedirectResponse
    {
        $category->update([
            'is_active' => ! $category->is_active,
        ]);

        if ($request->wantsJson()) {
            return response()->json([
                'success' => true,
                'is_active' => $category->is_active,
            ]);
        }

        return back()->with('success', "Category '{$category->name}' status updated.");
    }
}
