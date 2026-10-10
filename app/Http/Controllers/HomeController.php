<?php

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Display the storefront home page with dynamic hero sliders, curated recipe kits, and top 10 products.
     */
    public function index(): Response
    {
        $sliders = Slider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        $recipeKits = RecipeKit::query()
            ->with(['products:id,name,price,size_main,image'])
            ->where('is_active', true)
            ->latest('id')
            ->limit(10)
            ->get();

        $products = Product::query()
            ->select([
                'id',
                'slug',
                'name',
                'price',
                'original_price',
                'unit_price',
                'image',
                'size_main',
                'stock_badge',
                'photo_label',
                'category',
                'category_title',
                'buy_again',
            ])
            ->limit(10)
            ->get();

        $totalProductCount = Product::count();
        $totalRecipeKitCount = RecipeKit::where('is_active', true)->count();

        return Inertia::render('Home', [
            'products' => $products,
            'sliders' => $sliders,
            'recipeKits' => $recipeKits,
            'totalProductCount' => $totalProductCount,
            'totalRecipeKitCount' => $totalRecipeKitCount,
        ]);
    }

    /**
     * Display products filtered by category with pagination.
     */
    public function category(Request $request, string $category): Response
    {
        $request->merge(['category' => $category]);

        return $this->search($request);
    }

    /**
     * Display the search and catalogue page with Laravel pagination, sorting, and filters with query string URL persistence.
     */
    public function search(Request $request): Response
    {
        $query = trim((string) $request->input('q', ''));
        $category = (string) $request->input('category', 'all');
        $sort = (string) $request->input('sort', 'featured');
        $inStock = $request->boolean('in_stock');
        $tab = (string) $request->input('tab', 'products');

        $productsQuery = Product::query()
            ->select([
                'id',
                'slug',
                'name',
                'price',
                'original_price',
                'unit_price',
                'image',
                'size_main',
                'stock_badge',
                'photo_label',
                'category',
                'category_title',
                'buy_again',
                'description',
            ]);

        if ($query !== '') {
            $productsQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('category', 'like', "%{$query}%")
                    ->orWhere('category_title', 'like', "%{$query}%")
                    ->orWhere('photo_label', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%");
            });
        }

        if ($category !== 'all' && $category !== '') {
            $productsQuery->where('category', $category);
        }

        if ($inStock) {
            $productsQuery->where(function ($q) {
                $q->whereNull('stock_badge')
                    ->orWhere('stock_badge', 'not like', '%out%');
            });
        }

        switch ($sort) {
            case 'price_asc':
                $productsQuery->orderBy('price', 'asc');
                break;
            case 'price_desc':
                $productsQuery->orderBy('price', 'desc');
                break;
            case 'name_asc':
                $productsQuery->orderBy('name', 'asc');
                break;
            case 'name_desc':
                $productsQuery->orderBy('name', 'desc');
                break;
            case 'featured':
            default:
                $productsQuery->orderBy('id', 'asc');
                break;
        }

        $products = $productsQuery->paginate(10)->withQueryString();

        $recipeKitsQuery = RecipeKit::query()
            ->with(['products:id,name,price,size_main,image'])
            ->where('is_active', true);

        if ($query !== '') {
            $recipeKitsQuery->where(function ($q) use ($query) {
                $q->where('name', 'like', "%{$query}%")
                    ->orWhere('description', 'like', "%{$query}%")
                    ->orWhere('subtitle_tag', 'like', "%{$query}%");
            });
        }

        if ($sort === 'price_asc') {
            $recipeKitsQuery->orderBy('price', 'asc');
        } elseif ($sort === 'price_desc') {
            $recipeKitsQuery->orderBy('price', 'desc');
        } elseif ($sort === 'name_asc') {
            $recipeKitsQuery->orderBy('name', 'asc');
        } else {
            $recipeKitsQuery->latest('id');
        }

        $recipeKits = $recipeKitsQuery->paginate(10, ['*'], 'kits_page')->withQueryString();

        $dbCategories = Category::where('is_active', true)->orderBy('sort_order')->get();
        if ($dbCategories->isNotEmpty()) {
            $categories = $dbCategories->map(fn (Category $c) => [
                'category' => $c->slug,
                'category_title' => $c->name,
                'hindi_title' => $c->hindi_title,
                'icon' => $c->icon,
            ]);
        } else {
            $categories = Product::query()
                ->select('category', 'category_title')
                ->distinct()
                ->whereNotNull('category')
                ->get()
                ->unique('category')
                ->values();
        }

        return Inertia::render('Search', [
            'products' => $products,
            'recipeKits' => $recipeKits,
            'categories' => $categories,
            'filters' => [
                'q' => $query,
                'category' => $category,
                'sort' => $sort,
                'in_stock' => $inStock,
                'tab' => $tab,
            ],
        ]);
    }
}
