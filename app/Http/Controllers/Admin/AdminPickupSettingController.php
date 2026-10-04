<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\StoreSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;

class AdminPickupSettingController extends Controller
{
    /**
     * Show the dedicated pickup operations and slot scheduling settings.
     */
    public function edit(): Response
    {
        $storeInfo = StoreSetting::current();

        return Inertia::render('Admin/PickupSettings/Edit', [
            'storeInfo' => $storeInfo,
        ]);
    }

    /**
     * Update pickup operations, rush mode buffer, and scheduling slots.
     */
    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'is_pickup_active' => ['boolean'],
            'prep_time_minutes' => ['nullable', 'integer', 'min:5', 'max:240'],
            'busy_mode_extra_minutes' => ['nullable', 'integer', 'min:0', 'max:180'],
            'busy_mode_reason' => ['nullable', 'string', 'max:100'],
            'pickup_time' => ['nullable', 'string', 'max:100'],
            'pickup_slot_window_label' => ['nullable', 'string', 'max:100'],
            'pickup_slot_start_time' => ['nullable', 'string', 'max:10'],
            'pickup_slot_end_time' => ['nullable', 'string', 'max:10'],
            'pickup_slot_duration_minutes' => ['nullable', 'integer', 'min:15', 'max:240'],
            'pickup_slots' => ['nullable', 'array'],
            'curbside_instructions' => ['nullable', 'string', 'max:1000'],
        ]);

        $storeInfo = StoreSetting::current();
        $storeInfo->fill($validated);

        if (empty($validated['pickup_time'])) {
            $storeInfo->pickup_time = $storeInfo->effective_pickup_time;
        }

        $storeInfo->save();

        return back()->with('success', 'Pickup timings, rush buffer, and time slot settings updated successfully.');
    }
}
