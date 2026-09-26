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
            $kit = RecipeKit::query()->with('products')->where('slug', $slug)->first();
            if ($kit) {
                return redirect()->route('recipe-kits.show', $slug);
            }

            abort(404);
        }

        return Inertia::render('ProductDetail', [
            'slug' => $slug,
            'product' => $product,
        ]);
    }
}
