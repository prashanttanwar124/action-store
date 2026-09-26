<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSliderRequest;
use App\Http\Requests\Admin\UpdateSliderRequest;
use App\Models\Product;
use App\Models\RecipeKit;
use App\Models\Slider;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Inertia\Inertia;
use Inertia\Response;

class AdminSliderController extends Controller
{
    /**
     * Display a listing of homepage banner sliders.
     */
    public function index(): Response
    {
        $sliders = Slider::query()
            ->orderBy('sort_order')
            ->latest('id')
            ->get();

        return Inertia::render('Admin/Sliders/Index', [
            'sliders' => $sliders,
        ]);
    }

    /**
     * Show the form for creating a new slider banner.
     */
    public function create(): Response
    {
        $products = Product::select('id', 'name', 'slug')->get();
        $recipeKits = RecipeKit::select('id', 'name', 'slug')->get();

        return Inertia::render('Admin/Sliders/Create', [
            'products' => $products,
            'recipeKits' => $recipeKits,
        ]);
    }

    /**
     * Store a newly created slider banner in storage.
     */
    public function store(StoreSliderRequest $request): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('sliders', 'public');
            $validated['image'] = Storage::url($path);
        } elseif (! empty($validated['image_url'])) {
            $validated['image'] = $validated['image_url'];
        } else {
            $validated['image'] = '/images/products/sweets.jpg';
        }

        $validated['images'] = [$validated['image']];
        unset($validated['image_file'], $validated['image_url']);

        Slider::create($validated);

        return redirect()->route('admin.sliders.index')
            ->with('success', "Hero banner slide '{$validated['title']}' created successfully.");
    }

    /**
     * Show the form for editing the specified slider banner.
     */
    public function edit(Slider $slider): Response
    {
        $products = Product::select('id', 'name', 'slug')->get();
        $recipeKits = RecipeKit::select('id', 'name', 'slug')->get();

        return Inertia::render('Admin/Sliders/Edit', [
            'slider' => $slider,
            'products' => $products,
            'recipeKits' => $recipeKits,
        ]);
    }

    /**
     * Update the specified slider banner in storage.
     */
    public function update(UpdateSliderRequest $request, Slider $slider): RedirectResponse
    {
        $validated = $request->validated();

        if ($request->hasFile('image_file')) {
            $path = $request->file('image_file')->store('sliders', 'public');
            $validated['image'] = Storage::url($path);
            $validated['images'] = [$validated['image']];
        } elseif (! empty($validated['image_url'])) {
            $validated['image'] = $validated['image_url'];
            $validated['images'] = [$validated['image']];
        }

        unset($validated['image_file'], $validated['image_url']);

        $slider->update($validated);

        return redirect()->route('admin.sliders.index')
            ->with('success', "Hero banner slide '{$slider->title}' updated successfully.");
    }

    /**
     * Remove the specified slider banner from storage.
     */
    public function destroy(Slider $slider): RedirectResponse
    {
        $title = $slider->title;

        if (str_starts_with($slider->image, '/storage/sliders/')) {
            $relative = str_replace('/storage/', '', $slider->image);
            Storage::disk('public')->delete($relative);
        }

        $slider->delete();

        return redirect()->route('admin.sliders.index')
            ->with('success', "Slider '{$title}' deleted successfully.");
    }
}
