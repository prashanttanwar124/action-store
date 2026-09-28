<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('store_settings', function (Blueprint $table) {
            $table->id();
            $table->string('name')->default('Masala Mart — Main St.');
            $table->string('tagline')->default('Authentic Indian Groceries & Fresh Click-and-Collect');
            $table->string('hindi_tagline')->default('किराना');
            $table->string('address')->default('214 Main St.');
            $table->string('city')->default('Edison');
            $table->string('state')->default('NJ');
            $table->string('zip')->default('08817');
            $table->string('phone')->default('+1 (555) 345-6789');
            $table->string('email')->default('support@masalamart.com');
            $table->string('opening_hours')->default('Daily 9:00 AM – 9:00 PM');
            $table->string('pickup_time')->default('Ready in 1 hr');
            $table->text('curbside_instructions')->nullable();
            $table->string('announcement')->nullable();
            $table->string('maps_url')->nullable();
            $table->boolean('is_pickup_active')->default(true);
            $table->timestamps();
        });

        // Insert initial store settings row
        DB::table('store_settings')->insert([
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
            'created_at' => now(),
            'updated_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('store_settings');
    }
};
