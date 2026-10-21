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
        $storeInfo = StoreSetting::current();
        $validated = $request->validate(StoreSetting::pickupValidationRules($storeInfo));

        $storeInfo->fill($validated);

        if (empty($validated['pickup_time'])) {
            $storeInfo->pickup_time = $storeInfo->effective_pickup_time;
        }

        $storeInfo->save();

        return back()->with('success', 'Pickup timings, rush buffer, and time slot settings updated successfully.');
    }
}
