<?php

namespace Database\Seeders;

use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;

class SupplierSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $suppliers = [
            [
                'name' => 'Amrit Farm Dairy',
                'code' => 'SUP-001',
                'contact_person' => 'Harpreet Singh',
                'email' => 'supply@amritdairy.com',
                'phone' => '+1 (732) 412-9801',
                'address' => '84 Meadow Road',
                'city' => 'Edison',
                'state' => 'NJ',
                'country' => 'USA',
                'postal_code' => '08817',
                'lead_time_days' => 1,
                'payment_terms' => 'Net 15',
                'status' => 'active',
                'notes' => 'Small-batch artisanal paneer and organic A2 bilona cow ghee supplier. Delivers daily fresh at 6 AM.',
                'product_slugs' => ['paneer', 'desi-ghee'],
            ],
            [
                'name' => 'Punjab Heritage Mills',
                'code' => 'SUP-002',
                'contact_person' => 'Gurinder Bhalla',
                'email' => 'orders@punjabheritagemills.com',
                'phone' => '+1 (510) 902-3482',
                'address' => '1200 Central Express Way',
                'city' => 'Fremont',
                'state' => 'CA',
                'country' => 'USA',
                'postal_code' => '94538',
                'lead_time_days' => 3,
                'payment_terms' => 'Net 30',
                'status' => 'active',
                'notes' => 'Stone-ground 100% whole wheat MP Sharbati chakki atta and premium 2-year aged extra-long grain basmati rice.',
                'product_slugs' => ['atta', 'rice'],
            ],
            [
                'name' => 'Malabar Spice Guild',
                'code' => 'SUP-003',
                'contact_person' => 'K. Narayanan',
                'email' => 'trade@malabarspiceguild.com',
                'phone' => '+1 (408) 781-6720',
                'address' => '450 Spice Route Ave',
                'city' => 'Milpitas',
                'state' => 'CA',
                'country' => 'USA',
                'postal_code' => '95035',
                'lead_time_days' => 2,
                'payment_terms' => 'Net 30',
                'status' => 'active',
                'notes' => 'Direct single-origin stone roasted spices, organic whole spices from Wayanad and Idukki.',
                'product_slugs' => ['garam-masala', 'saunf-mints'],
            ],
            [
                'name' => 'Kisan Fresh Organics',
                'code' => 'SUP-004',
                'contact_person' => 'Anand Patel',
                'email' => 'farm@kisanfreshorganics.com',
                'phone' => '+1 (609) 334-1188',
                'address' => '320 Greenhouse Road',
                'city' => 'Monroe',
                'state' => 'NJ',
                'country' => 'USA',
                'postal_code' => '08831',
                'lead_time_days' => 1,
                'payment_terms' => 'Weekly',
                'status' => 'active',
                'notes' => 'Daily harvested tender green chillies, aromatic curry leaves, fresh bhindi, and fresh coriander.',
                'product_slugs' => ['okra', 'curry-leaves', 'coriander', 'green-chillies'],
            ],
            [
                'name' => 'Royal Mithai & Confectionery',
                'code' => 'SUP-005',
                'contact_person' => 'Sunil Aggarwal',
                'email' => 'sales@royalmithai.com',
                'phone' => '+1 (212) 555-8912',
                'address' => '78 Sweet Lane',
                'city' => 'Queens',
                'state' => 'NY',
                'country' => 'USA',
                'postal_code' => '11372',
                'lead_time_days' => 2,
                'payment_terms' => 'Net 15',
                'status' => 'active',
                'notes' => 'Pure desi ghee handcrafted Indian sweets, festive gift boxes, and bakery tea snacks.',
                'product_slugs' => ['sweets-box', 'glucose-biscuits', 'masala-noodles'],
            ],
        ];

        foreach ($suppliers as $item) {
            $productSlugs = $item['product_slugs'] ?? [];
            unset($item['product_slugs']);

            $supplier = Supplier::updateOrCreate(
                ['code' => $item['code']],
                $item
            );

            if (! empty($productSlugs)) {
                Product::whereIn('slug', $productSlugs)->update(['supplier_id' => $supplier->id]);
            }
        }
    }
}
