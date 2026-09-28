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
        'pickup_time',
        'curbside_instructions',
        'announcement',
        'maps_url',
        'is_pickup_active',
    ];

    /**
     * @var array<string, string>
     */
    protected $casts = [
        'is_pickup_active' => 'boolean',
    ];

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
                'pickup_time' => 'Ready in 1 hr',
                'curbside_instructions' => 'Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS.',
                'announcement' => 'Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30',
                'maps_url' => 'https://maps.google.com/?q=214+Main+St,+Edison,+NJ',
                'is_pickup_active' => true,
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
                'pickup_time' => 'Ready in 1 hr',
                'curbside_instructions' => 'Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS.',
                'announcement' => 'Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30',
                'maps_url' => 'https://maps.google.com/?q=214+Main+St,+Edison,+NJ',
                'is_pickup_active' => true,
            ]);
        }
    }

    /**
     * Get full address formatted string.
     */
    public function getFullAddressAttribute(): string
    {
        return trim("{$this->address}, {$this->city}, {$this->state} {$this->zip}");
    }
}
