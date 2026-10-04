<?php

namespace App\Http\Controllers;

use App\Models\RecipeKit;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class RecipeKitController extends Controller
{
    /**
     * Display all recipe kits with pagination.
     */
    public function index(Request $request): Response
    {
        $request->merge(['tab' => 'kits']);

        return app(HomeController::class)->search($request);
    }

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
