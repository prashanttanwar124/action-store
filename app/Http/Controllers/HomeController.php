<?php

namespace App\Http\Controllers;

use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use Inertia\Inertia;
use Inertia\Response;

class HomeController extends Controller
{
    /**
     * Display the storefront home page with dynamic hero sliders and curated recipe kits.
     */
    public function index(): Response
    {
        $sliders = Slider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        $recipeKits = RecipeKit::query()
            ->with('products')
            ->where('is_active', true)
            ->latest('id')
            ->get();

        return Inertia::render('Home', [
            'products' => Product::all(),
            'sliders' => $sliders,
            'recipeKits' => $recipeKits,
        ]);
    }

    /**
     * Display the storefront home page with search focus active.
     */
    public function search(): Response
    {
        $sliders = Slider::query()
            ->where('is_active', true)
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        $recipeKits = RecipeKit::query()
            ->with('products')
            ->where('is_active', true)
            ->latest('id')
            ->get();

        return Inertia::render('Home', [
            'products' => Product::all(),
            'sliders' => $sliders,
            'recipeKits' => $recipeKits,
            'focusSearch' => true,
        ]);
    }
}
