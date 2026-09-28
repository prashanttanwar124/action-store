<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminStoreSettingController extends Controller
{
    /**
     * Show the form for editing store settings.
     */
    public function edit(): Response
    {
        $storeInfo = StoreSetting::current();

        return Inertia::render('Admin/StoreInfo/Edit', [
            'storeInfo' => $storeInfo,
        ]);
    }

    /**
     * Update the store settings.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'tagline' => ['nullable', 'string', 'max:255'],
            'hindi_tagline' => ['nullable', 'string', 'max:50'],
            'address' => ['required', 'string', 'max:255'],
            'city' => ['required', 'string', 'max:100'],
            'state' => ['required', 'string', 'max:50'],
            'zip' => ['required', 'string', 'max:20'],
            'phone' => ['required', 'string', 'max:50'],
            'email' => ['required', 'email', 'max:255'],
            'opening_hours' => ['required', 'string', 'max:100'],
            'pickup_time' => ['required', 'string', 'max:100'],
            'curbside_instructions' => ['nullable', 'string', 'max:1000'],
            'announcement' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500'],
            'is_pickup_active' => ['boolean'],
        ]);

        $storeInfo = StoreSetting::current();
        $storeInfo->update($validated);

        return back()->with('success', 'Store information and pickup settings updated successfully.');
    }
}
