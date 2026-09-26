<?php

namespace Database\Seeders;

use App\Models\Slider;
use Illuminate\Database\Seeder;

class SliderSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        Slider::truncate();

        $slides = [
            [
                'title' => "Diwali sweets,\nboxed &\nready.",
                'tag' => 'FESTIVAL PRE-ORDER · CLOSES OCT 30',
                'cta_text' => 'Pre-order from $24.99',
                'link_url' => '/products/sweets-box',
                'image' => '/images/products/sweets.jpg',
                'images' => ['/images/products/sweets.jpg'],
                'photo_label' => 'mithai box',
                'bg_color' => '#1a1a1a',
                'sort_order' => 0,
                'is_active' => true,
            ],
            [
                'title' => "Restaurant curry,\ncooked at\nhome.",
                'tag' => 'CHEF-CRAFTED · DINNER IN 20 MINS',
                'cta_text' => 'Order kit for $14.99',
                'link_url' => '/recipe-kits/paneer-curry',
                'image' => '/images/products/paneer_curry.jpg',
                'images' => ['/images/products/paneer_curry.jpg'],
                'photo_label' => 'paneer kit',
                'bg_color' => '#221c17',
                'sort_order' => 1,
                'is_active' => true,
            ],
            [
                'title' => "Never run out\nof fresh atta\nagain.",
                'tag' => 'SUBSCRIBE & SAVE · 10% OFF AUTO-DELIVER',
                'cta_text' => 'Subscribe from $18.04',
                'link_url' => '/products/atta',
                'image' => '/images/products/atta.jpg',
                'images' => ['/images/products/atta.jpg'],
                'photo_label' => 'chakki atta',
                'bg_color' => '#26201b',
                'sort_order' => 2,
                'is_active' => true,
            ],
        ];

        foreach ($slides as $slide) {
            Slider::create($slide);
        }
    }
}
