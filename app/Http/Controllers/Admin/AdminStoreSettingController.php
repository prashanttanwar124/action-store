<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\Admin\StoreSettingRequest;
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
    public function update(StoreSettingRequest $request): RedirectResponse
    {
        $validated = $request->validated();

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
