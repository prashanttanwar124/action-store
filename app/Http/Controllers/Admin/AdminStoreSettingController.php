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
            'prep_time_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'busy_mode_extra_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'busy_mode_reason' => ['nullable', 'string', 'max:100'],
            'pickup_time' => ['nullable', 'string', 'max:100'],
            'pickup_slot_window_label' => ['nullable', 'string', 'max:100'],
            'pickup_slot_start_time' => ['nullable', 'string', 'max:10'],
            'pickup_slot_end_time' => ['nullable', 'string', 'max:10'],
            'pickup_slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'max_orders_per_slot' => ['nullable', 'integer', 'min:1', 'max:500'],
            'min_order_amount' => ['nullable', 'numeric', 'min:0'],
            'pickup_days' => ['nullable', 'array'],
            'pickup_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'pickup_slots' => ['nullable', 'array'],
            'curbside_instructions' => ['nullable', 'string', 'max:1000'],
            'announcement' => ['nullable', 'string', 'max:255'],
            'maps_url' => ['nullable', 'string', 'max:500'],
            'is_pickup_active' => ['boolean'],
            'is_delivery_active' => ['boolean'],
            'delivery_days' => ['nullable', 'array'],
            'delivery_days.*' => ['string', 'in:monday,tuesday,wednesday,thursday,friday,saturday,sunday'],
            'delivery_fee' => ['nullable', 'numeric', 'min:0'],
            'free_delivery_threshold' => ['nullable', 'numeric', 'min:0'],
            'delivery_estimated_time' => ['nullable', 'string', 'max:100'],
        ]);

        $storeInfo = StoreSetting::current();
        $storeInfo->fill($validated);

        if (empty($validated['pickup_time'])) {
            $storeInfo->pickup_time = $storeInfo->effective_pickup_time;
        }

        $storeInfo->save();

        return back()->with('success', 'Store information, rush mode, and delivery settings updated successfully.');
    }

    /**
     * Quickly toggle busy/rush mode prep-time buffer from anywhere in Admin.
     */
    public function quickBusyMode(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'busy_mode_extra_minutes' => ['required', 'integer', 'min:0', 'max:180'],
            'busy_mode_reason' => ['nullable', 'string', 'max:100'],
            'is_pickup_active' => ['nullable', 'boolean'],
        ]);

        $storeInfo = StoreSetting::current();
        $storeInfo->busy_mode_extra_minutes = (int) $validated['busy_mode_extra_minutes'];

        if (array_key_exists('busy_mode_reason', $validated)) {
            $storeInfo->busy_mode_reason = $validated['busy_mode_reason'];
        }

        if (isset($validated['is_pickup_active'])) {
            $storeInfo->is_pickup_active = (bool) $validated['is_pickup_active'];
        }

        $storeInfo->pickup_time = $storeInfo->effective_pickup_time;
        $storeInfo->save();

        $statusMsg = $storeInfo->busy_mode_extra_minutes > 0
            ? "Rush Mode activated (+{$storeInfo->busy_mode_extra_minutes} mins). Customer ETA: {$storeInfo->effective_pickup_time}."
            : 'Store operating at Normal speed (15 min ETA).';

        return back()->with('success', $statusMsg);
    }
}
