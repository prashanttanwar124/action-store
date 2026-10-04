<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

class StoreSetting extends Model
{
    use HasFactory;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'tagline',
        'hindi_tagline',
        'address',
        'city',
        'state',
        'zip',
        'phone',
        'email',
        'opening_hours',
        'prep_time_minutes',
        'busy_mode_extra_minutes',
        'busy_mode_reason',
        'pickup_time',
        'pickup_slot_window_label',
        'pickup_slot_start_time',
        'pickup_slot_end_time',
        'pickup_slot_duration_minutes',
        'pickup_slots',
        'curbside_instructions',
        'announcement',
        'maps_url',
        'is_pickup_active',
        'is_delivery_active',
        'delivery_days',
        'delivery_fee',
        'free_delivery_threshold',
        'delivery_estimated_time',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_pickup_active' => 'boolean',
        'is_delivery_active' => 'boolean',
        'prep_time_minutes' => 'integer',
        'busy_mode_extra_minutes' => 'integer',
        'pickup_slot_duration_minutes' => 'integer',
        'pickup_slots' => 'array',
        'delivery_days' => 'array',
        'delivery_fee' => 'float',
        'free_delivery_threshold' => 'float',
    ];

    /**
     * @var list<string>
     */
    protected $appends = [
        'effective_prep_time_minutes',
        'effective_pickup_time',
        'is_busy',
        'is_delivery_available_today',
        'available_pickup_slots',
    ];

    /**
     * The "booted" method of the model.
     */
    protected static function booted(): void
    {
        static::saving(function (StoreSetting $setting) {
            if ($setting->pickup_slot_duration_minutes == 30 && ($setting->pickup_slot_window_label === '1-hour window' || empty($setting->pickup_slot_window_label))) {
                $setting->pickup_slot_window_label = '30-min window';
            } elseif ($setting->pickup_slot_duration_minutes == 120 && ($setting->pickup_slot_window_label === '1-hour window' || empty($setting->pickup_slot_window_label))) {
                $setting->pickup_slot_window_label = '2-hour window';
            }
        });
    }

    /**
     * Get or create the singleton store settings instance.
     */
    public static function current(): self
    {
        try {
            return static::firstOrCreate([], [
                'name' => 'Masala Mart — Main St.',
                'tagline' => 'Authentic Indian Groceries & Fresh Click-and-Collect',
                'hindi_tagline' => 'किराना',
                'address' => '214 Main St.',
                'city' => 'Edison',
                'state' => 'NJ',
                'zip' => '08817',
                'phone' => '+1 (555) 345-6789',
                'email' => 'support@masalamart.com',
                'opening_hours' => 'Daily 9:00 AM – 9:00 PM',
                'prep_time_minutes' => 15,
                'busy_mode_extra_minutes' => 0,
                'busy_mode_reason' => null,
                'pickup_time' => 'Ready in 15 mins',
                'pickup_slot_window_label' => '1-hour window',
                'pickup_slot_start_time' => '09:00',
                'pickup_slot_end_time' => '21:00',
                'pickup_slot_duration_minutes' => 60,
                'pickup_slots' => null,
                'curbside_instructions' => 'Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS.',
                'announcement' => 'Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30',
                'maps_url' => 'https://maps.google.com/?q=214+Main+St,+Edison,+NJ',
                'is_pickup_active' => true,
                'is_delivery_active' => false,
                'delivery_days' => ['friday', 'saturday', 'sunday'],
                'delivery_fee' => 4.99,
                'free_delivery_threshold' => 50.00,
                'delivery_estimated_time' => 'Same Day 5:00 PM – 8:00 PM',
            ]);
        } catch (\Throwable) {
            return new static([
                'name' => 'Masala Mart — Main St.',
                'tagline' => 'Authentic Indian Groceries & Fresh Click-and-Collect',
                'hindi_tagline' => 'किराना',
                'address' => '214 Main St.',
                'city' => 'Edison',
                'state' => 'NJ',
                'zip' => '08817',
                'phone' => '+1 (555) 345-6789',
                'email' => 'support@masalamart.com',
                'opening_hours' => 'Daily 9:00 AM – 9:00 PM',
                'prep_time_minutes' => 15,
                'busy_mode_extra_minutes' => 0,
                'busy_mode_reason' => null,
                'pickup_time' => 'Ready in 15 mins',
                'pickup_slot_window_label' => '1-hour window',
                'pickup_slot_start_time' => '09:00',
                'pickup_slot_end_time' => '21:00',
                'pickup_slot_duration_minutes' => 60,
                'pickup_slots' => null,
                'curbside_instructions' => 'Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS.',
                'announcement' => 'Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30',
                'maps_url' => 'https://maps.google.com/?q=214+Main+St,+Edison,+NJ',
                'is_pickup_active' => true,
                'is_delivery_active' => false,
                'delivery_days' => ['friday', 'saturday', 'sunday'],
                'delivery_fee' => 4.99,
                'free_delivery_threshold' => 50.00,
                'delivery_estimated_time' => 'Same Day 5:00 PM – 8:00 PM',
            ]);
        }
    }

    /**
     * Generate default slots based on hours and duration.
     *
     * @return array<int, array{id: string, startHour: int, label: string, active: bool}>
     */
    public static function generateDefaultSlots(int $startHour = 9, int $endHour = 21, int $durationMinutes = 60): array
    {
        $slots = [];
        $current = $startHour * 60;
        $end = $endHour * 60;

        while ($current + $durationMinutes <= $end) {
            $startMinutes = $current;
            $endSlotMinutes = $current + $durationMinutes;

            $startH = intdiv($startMinutes, 60);
            $startM = $startMinutes % 60;
            $endH = intdiv($endSlotMinutes, 60);
            $endM = $endSlotMinutes % 60;

            $startPeriod = $startH >= 12 ? 'PM' : 'AM';
            $endPeriod = $endH >= 12 ? 'PM' : 'AM';

            $startH12 = $startH % 12 ?: 12;
            $endH12 = $endH % 12 ?: 12;

            $startFormatted = sprintf('%d:%02d %s', $startH12, $startM, $startPeriod);
            $endFormatted = sprintf('%d:%02d %s', $endH12, $endM, $endPeriod);

            $id = sprintf('%02d-%02d', $startH, $endH);
            $label = "{$startFormatted} – {$endFormatted}";

            $slots[] = [
                'id' => $id,
                'startHour' => $startH,
                'label' => $label,
                'active' => true,
            ];

            $current += $durationMinutes;
        }

        return $slots;
    }

    /**
     * Get available pickup slots.
     *
     * @return array<int, array{id: string, startHour: int, label: string, active: bool}>
     */
    public function getAvailablePickupSlotsAttribute(): array
    {
        if (! empty($this->pickup_slots) && is_array($this->pickup_slots)) {
            return array_values(array_filter($this->pickup_slots, fn ($s) => ($s['active'] ?? true)));
        }

        $startHour = (int) explode(':', $this->pickup_slot_start_time ?? '09:00')[0];
        $endHour = (int) explode(':', $this->pickup_slot_end_time ?? '21:00')[0];
        $duration = (int) ($this->pickup_slot_duration_minutes ?: 60);

        return static::generateDefaultSlots($startHour, $endHour, $duration);
    }

    /**
     * Get pickup slot window label with duration fallback.
     */
    public function getPickupSlotWindowLabelAttribute(?string $value): string
    {
        if ($this->pickup_slot_duration_minutes == 30 && ($value === '1-hour window' || empty($value))) {
            return '30-min window';
        }
        if ($this->pickup_slot_duration_minutes == 120 && ($value === '1-hour window' || empty($value))) {
            return '2-hour window';
        }

        return $value ?: '1-hour window';
    }

    /**
     * Get full address formatted string.
     */
    public function getFullAddressAttribute(): string
    {
        return trim("{$this->address}, {$this->city}, {$this->state} {$this->zip}");
    }

    /**
     * Calculate effective prep time in minutes including busy mode extra buffer.
     */
    public function getEffectivePrepTimeMinutesAttribute(): int
    {
        return (int) ($this->prep_time_minutes ?? 15) + (int) ($this->busy_mode_extra_minutes ?? 0);
    }

    /**
     * Get dynamic customer-facing pickup string.
     */
    public function getEffectivePickupTimeAttribute(): string
    {
        if (! $this->is_pickup_active) {
            return 'Pickup Currently Paused';
        }

        $totalMinutes = $this->effective_prep_time_minutes;
        if ($this->busy_mode_extra_minutes > 0) {
            $reason = $this->busy_mode_reason ? " ({$this->busy_mode_reason})" : ' (Store Rush)';

            return "Ready in {$totalMinutes} mins{$reason}";
        }

        if ($totalMinutes >= 60) {
            $hours = round($totalMinutes / 60, 1);

            return $hours == 1 ? 'Ready in 1 hr' : "Ready in {$hours} hrs";
        }

        return "Ready in {$totalMinutes} mins";
    }

    /**
     * Determine if store is in busy mode.
     */
    public function getIsBusyAttribute(): bool
    {
        return ($this->busy_mode_extra_minutes ?? 0) > 0;
    }

    /**
     * Check if delivery is enabled and scheduled for today's day of week.
     */
    public function getIsDeliveryAvailableTodayAttribute(): bool
    {
        if (! $this->is_delivery_active) {
            return false;
        }

        $days = array_map('strtolower', (array) ($this->delivery_days ?? ['friday', 'saturday', 'sunday']));
        $currentDay = strtolower(now()->format('l'));

        return in_array($currentDay, $days, true);
    }
}
