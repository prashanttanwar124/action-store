<?php

namespace App\Http\Controllers;

use App\Models\RecipeKit;
use Inertia\Inertia;
use Inertia\Response;

class RecipeKitController extends Controller
{
    /**
     * Display the specified recipe kit with linked group buy ingredients.
     */
    public function show(string $slug): Response
    {
        $kit = RecipeKit::query()
            ->with('products')
            ->where('slug', $slug)
            ->firstOrFail();

        return Inertia::render('RecipeKitDetail', [
            'kit' => $kit,
        ]);
    }
}
