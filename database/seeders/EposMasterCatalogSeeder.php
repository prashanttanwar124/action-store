<?php

namespace Database\Seeders;

use App\Models\Category;
use App\Models\Product;
use App\Models\Supplier;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use RuntimeException;

class EposMasterCatalogSeeder extends Seeder
{
    private const PLACEHOLDER_IMAGE = '/images/products/placeholder.jpg';

    private const DEFAULT_STOCK = 50;

    /**
     * Categories in the Epos export that belong to the hot restaurant / takeout menu and must be skipped.
     *
     * @var list<string>
     */
    private const EXCLUDED_RESTAURANT_CATEGORIES = [
        'Veg',
        'Non-Veg',
        'Indian Street Food',
        'Takeout',
        'Breads',
        'Tandori Items',
        'Curry Mixes',
        'Deserts',
        'Wrap',
    ];

    /**
     * Specific Epos ProductIDs for stray unbarcoded hot dishes, counter drinks, internal empty containers, or unbarcoded exact duplicates.
     *
     * @var list<string>
     */
    private const EXCLUDED_PRODUCT_IDS = [
        '15076998', // Zeera RICE (hot counter)
        '15945992', // Mango Shake (hot counter)
        '17081882', // Chicken Biryani (hot counter)
        '17155443', // Khalsa tea (hot counter)
        '17192226', // Veg Biryani (hot counter)
        '17192332', // Chicken Biryani (hot counter)
        '18183790', // kadak Masala Tea (hot counter)
        '18183815', // Dudh Patti (hot counter)
        '18760332', // Raita (hot counter)
        '20443714', // Royal Faooda (hot counter)
        '20443719', // Lemon Soda (hot counter)
        '20443737', // Lamb Biryani (hot counter)
        '26210138', // Mango Lassi (hot counter)
        '26210145', // Masala Soda (hot counter)
        '26210190', // Pop (hot counter)
        '33877806', // Eco craze clampshell container
        '33877838', // Maple 6” plastic cont.
        '33877844', // Value + 12oz cont.
        '33877848', // Value+ 33oz cont.
        '27864993', // Exact duplicate of #27865042 Cricket Bat set Kids
    ];

    /**
     * Exact-duplicate barcodes where both Epos rows represent the exact same physical item and should merge into one clean product.
     *
     * @var list<string>
     */
    private const EXACT_DUPLICATE_BARCODES = [
        '068656022166',
        '011433160285',
        '011433115520',
        '8901155414310',
        '6670640020450',
        '069276332451',
        '667064005374',
        '068656021022',
        '068656017506',
        '667064006999',
        '069276112695',
        '011433150521',
        '8904067704807',
        '011433153157',
        '8901764021251',
        '011433113298',
        '011433115667',
        '692058017019',
        '011433159999',
        '667064010835',
        '8901063165830',
        '8906064704484',
        '8901207040542',
        '011433117999',
        '8901450003349',
        '8902144389077',
        '664648320809',
        '8901595972241',
        '8901440211204',
        '667064005725',
        '723246181028',
        '665244204104',
    ];

    /**
     * Clean supplier display names mapped from raw CSV supplier strings.
     *
     * @var array<string, string>
     */
    private const SUPPLIER_NAME_MAP = [
        'deep' => 'Deep Foods',
        'SAMOSA FACTORY' => 'Samosa Factory',
        'SUNDINE' => 'Sundine Produce',
        'Wallmart' => 'Walmart',
        'Farah Implex' => 'Farah Impex',
    ];

    /**
     * Smart storefront & POS department definitions.
     *
     * @var array<string, array{name: string, hindi_title: string, icon: string, sort_order: int, description: string, freshness: string}>
     */
    private const DEPARTMENTS = [
        'staples' => [
            'name' => 'Staples, Flours & Lentils',
            'hindi_title' => 'आटा, दाल और चावल',
            'icon' => 'Wheat',
            'sort_order' => 1,
            'description' => 'Stone-ground chakki atta, aged basmati rice, dals, pulses, sugar, and jaggery.',
            'freshness' => 'Clean-milled pantry staple · Fresh batch stock',
        ],
        'spices' => [
            'name' => 'Spices, Masalas & Pickles',
            'hindi_title' => 'मसाले और अचार',
            'icon' => 'Flame',
            'sort_order' => 2,
            'description' => 'Whole spices, regional masala blends, traditional pickles, chutneys, and pastes.',
            'freshness' => 'Aroma-locked pack · Authentic Indian spice pantry',
        ],
        'snacks' => [
            'name' => 'Snacks & Namkeen',
            'hindi_title' => 'नमकीन और स्नैक्स',
            'icon' => 'Cookie',
            'sort_order' => 3,
            'description' => 'Crispy namkeen, bhujia, khakhra, chips, papad, roasted nuts, and dry fruits.',
            'freshness' => 'Crisp sealed pack · Tea-time favourite',
        ],
        'bakery' => [
            'name' => 'Biscuits, Cookies & Rusk',
            'hindi_title' => 'बिस्किट और रस्क',
            'icon' => 'Cookie',
            'sort_order' => 4,
            'description' => 'Classic chai biscuits, bakery cookies, elaichi rusk, khari, and tea cakes.',
            'freshness' => 'Crisp bakery pack · Perfect with garma-garam chai',
        ],
        'sweets' => [
            'name' => 'Indian Sweets & Mithai',
            'hindi_title' => 'मिठाई और डेज़र्ट',
            'icon' => 'Sparkles',
            'sort_order' => 5,
            'description' => 'Fresh mithai counter favourites, packaged burfi, soan papdi, halwa, and dessert mixes.',
            'freshness' => 'Traditional Indian mithai · Freshly stocked',
        ],
        'dairy' => [
            'name' => 'Dairy, Paneer, Ghee & Frozen',
            'hindi_title' => 'डेयरी, पनीर और घी',
            'icon' => 'Milk',
            'sort_order' => 6,
            'description' => 'Pure desi ghee, malai paneer, butter, dahi, shrikhand, parathas, and frozen favourites.',
            'freshness' => 'Chilled & temperature-controlled store stock',
        ],
        'produce' => [
            'name' => 'Fresh Produce & Herbs',
            'hindi_title' => 'ताज़ा फल और सब्ज़ियाँ',
            'icon' => 'Apple',
            'sort_order' => 7,
            'description' => 'Fresh Indian vegetables, herbs, green chillies, okra, karela, and seasonal fruits.',
            'freshness' => 'Farm-fresh Indian produce · Restocked regularly',
        ],
        'beverages' => [
            'name' => 'Tea, Coffee & Beverages',
            'hindi_title' => 'चाय, कॉफ़ी और पेय',
            'icon' => 'Coffee',
            'sort_order' => 8,
            'description' => 'Assam & masala chai, filter coffee, sharbat, mango juices, sodas, and health drinks.',
            'freshness' => 'Refreshing authentic Indian & international beverages',
        ],
        'grocery' => [
            'name' => 'Pantry, Oils & Ready-to-Eat',
            'hindi_title' => 'राशन, तेल और रेडी-टू-ईट',
            'icon' => 'ShoppingBag',
            'sort_order' => 9,
            'description' => 'Cooking oils, noodles, vermicelli, ready-to-eat curries, instant mixes, and daily essentials.',
            'freshness' => 'Everyday Indian kitchen essential · Quality assured',
        ],
        'personal-care' => [
            'name' => 'Ayurveda, Beauty & Wellness',
            'hindi_title' => 'आयुर्वेद और ब्यूटी',
            'icon' => 'Leaf',
            'sort_order' => 10,
            'description' => 'Herbal hair oils, Ayurvedic soaps, skincare, toothpaste, henna, and digestive wellness.',
            'freshness' => 'Trusted Indian wellness & personal care brand',
        ],
        'puja-home' => [
            'name' => 'Puja, Utensils & Home',
            'hindi_title' => 'पूजा सामग्री और बर्तन',
            'icon' => 'Package',
            'sort_order' => 11,
            'description' => 'Puja samagri, diyas, incense, festive decor, traditional cookware, and household items.',
            'freshness' => 'Authentic Indian puja & household collection',
        ],
    ];

    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        $csvPath = database_path('seeders/epos_master_catalog.csv');
        if (! is_file($csvPath)) {
            $csvPath = storage_path('app/epos_master_catalog.csv');
        }

        if (! is_file($csvPath)) {
            throw new RuntimeException("Master CSV not found at {$csvPath}");
        }

        $rawRows = $this->readCsvRows($csvPath);
        [$mergedRecords, $barcodeConflicts] = $this->prepareCleanCatalog($rawRows);

        DB::transaction(function () use ($mergedRecords): void {
            $this->syncCategories();

            Product::query()->delete();
            Supplier::query()->delete();

            $supplierMap = $this->seedSuppliers($mergedRecords);
            $this->insertProducts($mergedRecords, $supplierMap);
        });

        $this->writeAuditReports($mergedRecords, $barcodeConflicts);
    }

    /**
     * Parse raw Epos Now CSV rows, skipping archived/deleted and hot restaurant menu entries.
     *
     * @return list<array{
     *     epos_id: string,
     *     raw_name: string,
     *     raw_description: string,
     *     cost_price: float|null,
     *     price: float,
     *     is_taxable: bool,
     *     raw_category: string,
     *     raw_brand: string,
     *     unit_of_sale: string,
     *     barcode: string|null,
     *     alternate_barcodes: list<string>,
     *     raw_supplier: string,
     *     product_type: string,
     *     raw_sku: string
     * }>
     */
    private function readCsvRows(string $csvPath): array
    {
        $fp = fopen($csvPath, 'r');
        if ($fp === false) {
            throw new RuntimeException("Unable to open {$csvPath}");
        }

        fgetcsv($fp, 0, ',', '"', '');

        $rows = [];
        while (($cols = fgetcsv($fp, 0, ',', '"', '')) !== false) {
            if (count($cols) < 21) {
                continue;
            }

            $deleted = strtolower(trim((string) ($cols[33] ?? '')));
            $archived = strtolower(trim((string) ($cols[35] ?? '')));
            if ($deleted === 'y' || $archived === 'y') {
                continue;
            }

            $eposId = preg_replace('/^\xEF\xBB\xBF/', '', trim((string) $cols[0])) ?? trim((string) $cols[0]);
            $rawCategory = trim((string) ($cols[9] ?? ''));

            if (in_array($rawCategory, self::EXCLUDED_RESTAURANT_CATEGORIES, true)) {
                continue;
            }

            if (in_array($eposId, self::EXCLUDED_PRODUCT_IDS, true)) {
                continue;
            }

            $rawName = trim((string) ($cols[1] ?? ''));
            if ($rawName === '') {
                continue;
            }

            $rawBarcode = trim((string) ($cols[16] ?? ''));

            // Extract embedded barcode in product name (e.g. "Q7 COPPER BOTTLE HAMMERED 8904304388906")
            if ($rawBarcode === '' && preg_match('/\b(\d{12,13})\b/', $rawName, $matches) === 1) {
                $rawBarcode = $matches[1];
                $rawName = trim((string) preg_replace('/\b\d{12,13}\b/', '', $rawName));
            }

            [$primaryBarcode, $altBarcodes] = $this->normalizeBarcodeField($rawBarcode);

            $costStr = trim((string) ($cols[3] ?? ''));
            $costPrice = ($costStr !== '' && is_numeric($costStr) && (float) $costStr > 0)
                ? round((float) $costStr, 2)
                : null;

            $sellingPrice = round(max(0.0, (float) ($cols[4] ?? 0)), 2);
            if ($sellingPrice <= 0) {
                continue;
            }

            $taxStr = trim((string) ($cols[7] ?? ''));
            $isTaxable = $taxStr !== '' && (float) $taxStr > 0;

            $rows[] = [
                'epos_id' => $eposId,
                'raw_name' => $rawName,
                'raw_description' => trim((string) ($cols[2] ?? '')),
                'cost_price' => $costPrice,
                'price' => $sellingPrice,
                'is_taxable' => $isTaxable,
                'raw_category' => $rawCategory,
                'raw_brand' => trim((string) ($cols[10] ?? '')),
                'unit_of_sale' => strtolower(trim((string) ($cols[12] ?? ''))),
                'barcode' => $primaryBarcode,
                'alternate_barcodes' => $altBarcodes,
                'raw_supplier' => trim((string) ($cols[17] ?? '')),
                'product_type' => trim((string) ($cols[20] ?? 'Standard')),
                'raw_sku' => trim((string) ($cols[26] ?? '')),
            ];
        }

        fclose($fp);

        return $rows;
    }

    /**
     * Normalize multi-barcode strings and 11-digit stripped-zero UPCs into primary + alternate_barcodes.
     *
     * @return array{0: string|null, 1: list<string>}
     */
    private function normalizeBarcodeField(string $rawBarcode): array
    {
        $clean = trim($rawBarcode);
        if ($clean === '') {
            return [null, []];
        }

        if (str_contains($clean, '#')) {
            $parts = array_values(array_filter(array_map('trim', explode('#', $clean)), static fn (string $p): bool => $p !== ''));
            $primary = array_shift($parts);

            return [$primary, $parts];
        }

        // Two 12-digit UPCs concatenated (24 digits)
        if (preg_match('/^\d{24}$/', $clean) === 1) {
            return [substr($clean, 0, 12), [substr($clean, 12, 12)]];
        }

        // Two 13-digit EANs concatenated (26 digits)
        if (preg_match('/^\d{26}$/', $clean) === 1) {
            return [substr($clean, 0, 13), [substr($clean, 13, 13)]];
        }

        // 11-digit numeric string where Excel stripped leading zero from a valid 12-digit UPC-A
        if (preg_match('/^\d{11}$/', $clean) === 1) {
            $padded12 = '0'.$clean;
            if ($this->isValidUpcA($padded12)) {
                return [$padded12, [$clean]];
            }
        }

        // 14-digit GTIN-14 starting with '0' wrapping a valid 13-digit EAN-13
        if (preg_match('/^0(\d{13})$/', $clean, $m) === 1) {
            return [$m[1], [$clean]];
        }

        return [$clean, []];
    }

    /**
     * Check standard 12-digit UPC-A modulo-10 check digit.
     */
    private function isValidUpcA(string $code): bool
    {
        if (preg_match('/^\d{12}$/', $code) !== 1) {
            return false;
        }

        $oddSum = 0;
        $evenSum = 0;
        for ($i = 0; $i < 11; $i++) {
            $digit = (int) $code[$i];
            if ($i % 2 === 0) {
                $oddSum += $digit;
            } else {
                $evenSum += $digit;
            }
        }

        $check = (10 - (($oddSum * 3 + $evenSum) % 10)) % 10;

        return $check === (int) $code[11];
    }

    /**
     * Merge ~35 exact duplicate barcodes into single clean rows and separate ~25 shared-barcode pack conflicts.
     *
     * @param  list<array<string, mixed>>  $rows
     * @return array{0: list<array<string, mixed>>, 1: list<array<string, mixed>>}
     */
    private function prepareCleanCatalog(array $rows): array
    {
        $byBarcode = [];
        $unbarcoded = [];

        foreach ($rows as $row) {
            $bc = $row['barcode'];
            if ($bc === null || $bc === '') {
                $unbarcoded[] = $row;
            } else {
                $byBarcode[$bc][] = $row;
            }
        }

        $finalRows = [];
        $conflicts = [];

        foreach ($byBarcode as $bc => $group) {
            if (count($group) === 1) {
                $finalRows[] = $group[0];

                continue;
            }

            // Special 3-item group 628235127868: #9217650 & #9499357 are Zaika Green Mukhwas (merge), #30495432 is Zaika Gujrati Mukhwas (distinct flavour)
            if ((string) $bc === '628235127868') {
                $greenGroup = [];
                $otherGroup = [];
                foreach ($group as $item) {
                    if (stripos((string) $item['raw_name'], 'gujrati') !== false) {
                        $otherGroup[] = $item;
                    } else {
                        $greenGroup[] = $item;
                    }
                }

                $mergedGreen = $this->mergeDuplicateGroup($greenGroup);
                $finalRows[] = $mergedGreen;

                foreach ($otherGroup as $other) {
                    $conflicts[] = [
                        'shared_barcode' => $bc,
                        'kept_on_epos_id' => $mergedGreen['epos_id'],
                        'kept_on_name' => $mergedGreen['raw_name'],
                        'cleared_epos_id' => $other['epos_id'],
                        'cleared_name' => $other['raw_name'],
                        'cleared_price' => $other['price'],
                    ];
                    $other['barcode'] = null;
                    $other['alternate_barcodes'] = [];
                    $finalRows[] = $other;
                }

                continue;
            }

            if (in_array((string) $bc, self::EXACT_DUPLICATE_BARCODES, true)) {
                $finalRows[] = $this->mergeDuplicateGroup($group);

                continue;
            }

            // Shared barcode across genuinely different packs/flavours: keep primary (first/single unit) with barcode, clear secondary & log for review
            usort($group, static fn (array $a, array $b): int => ((int) $a['epos_id']) <=> ((int) $b['epos_id']));
            $primary = array_shift($group);
            $finalRows[] = $primary;

            foreach ($group as $secondary) {
                $conflicts[] = [
                    'shared_barcode' => (string) $bc,
                    'kept_on_epos_id' => $primary['epos_id'],
                    'kept_on_name' => $primary['raw_name'],
                    'cleared_epos_id' => $secondary['epos_id'],
                    'cleared_name' => $secondary['raw_name'],
                    'cleared_price' => $secondary['price'],
                ];
                $secondary['barcode'] = null;
                $secondary['alternate_barcodes'] = [];
                $finalRows[] = $secondary;
            }
        }

        foreach ($unbarcoded as $row) {
            $finalRows[] = $row;
        }

        usort($finalRows, static fn (array $a, array $b): int => ((int) $a['epos_id']) <=> ((int) $b['epos_id']));

        return [$finalRows, $conflicts];
    }

    /**
     * Merge an exact-duplicate group, preferring the latest Epos entry's selling price while preserving supplier, category, and cost_price.
     *
     * @param  list<array<string, mixed>>  $group
     * @return array<string, mixed>
     */
    private function mergeDuplicateGroup(array $group): array
    {
        usort($group, static fn (array $a, array $b): int => ((int) $a['epos_id']) <=> ((int) $b['epos_id']));
        $latest = $group[count($group) - 1];

        foreach (array_reverse($group) as $candidate) {
            if ($latest['raw_supplier'] === '' && $candidate['raw_supplier'] !== '') {
                $latest['raw_supplier'] = $candidate['raw_supplier'];
            }
            if (($latest['raw_category'] === '' || strtolower((string) $latest['raw_category']) === 'misc') && $candidate['raw_category'] !== '' && strtolower((string) $candidate['raw_category']) !== 'misc') {
                $latest['raw_category'] = $candidate['raw_category'];
            }
            if ($latest['cost_price'] === null && $candidate['cost_price'] !== null) {
                $latest['cost_price'] = $candidate['cost_price'];
            }
            if (strlen((string) $candidate['raw_name']) > strlen((string) $latest['raw_name']) && preg_match('/\d+\s*(g|gm|gms|kg|lb|lbs|ml|l|oz)\b/i', (string) $latest['raw_name']) !== 1) {
                $latest['raw_name'] = $candidate['raw_name'];
            }
        }

        return $latest;
    }

    /**
     * Upsert active storefront & POS grocery departments while preserving recipe-kits.
     */
    private function syncCategories(): void
    {
        Category::query()
            ->whereNotIn('slug', array_merge(array_keys(self::DEPARTMENTS), ['recipe-kits']))
            ->update(['is_active' => false]);

        foreach (self::DEPARTMENTS as $slug => $meta) {
            Category::query()->updateOrCreate(
                ['slug' => $slug],
                [
                    'name' => $meta['name'],
                    'hindi_title' => $meta['hindi_title'],
                    'description' => $meta['description'],
                    'icon' => $meta['icon'],
                    'sort_order' => $meta['sort_order'],
                    'is_active' => true,
                ],
            );
        }
    }

    /**
     * Create all real suppliers found in the catalog and return raw_supplier => supplier_id map.
     *
     * @param  list<array<string, mixed>>  $records
     * @return array<string, int>
     */
    private function seedSuppliers(array $records): array
    {
        $rawSuppliers = [];
        foreach ($records as $record) {
            $raw = trim((string) $record['raw_supplier']);
            if ($raw !== '') {
                $rawSuppliers[$raw] = ($rawSuppliers[$raw] ?? 0) + 1;
            }
        }

        arsort($rawSuppliers);

        $map = [];
        $index = 1;
        foreach (array_keys($rawSuppliers) as $rawName) {
            $cleanName = self::SUPPLIER_NAME_MAP[$rawName] ?? $rawName;
            $code = 'SUP-'.str_pad((string) $index, 3, '0', STR_PAD_LEFT);
            $index++;

            $supplier = Supplier::query()->create([
                'name' => $cleanName,
                'code' => $code,
                'country' => 'Canada',
                'lead_time_days' => 5,
                'payment_terms' => 'Net 30',
                'status' => 'active',
                'notes' => "Imported from Epos Now master catalog ({$rawSuppliers[$rawName]} linked SKUs).",
            ]);

            $map[$rawName] = $supplier->id;
        }

        return $map;
    }

    /**
     * Transform and batch-insert all cleaned products.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  array<string, int>  $supplierMap
     */
    private function insertProducts(array $records, array $supplierMap): void
    {
        $usedSlugs = [];
        $usedSkus = [];
        $now = now();
        $batch = [];

        foreach ($records as &$record) {
            [$cleanName, $brandLabel, $sizeMain] = $this->enrichNameBrandAndSize(
                (string) $record['raw_name'],
                (string) $record['raw_brand'],
                (string) $record['raw_supplier'],
                (string) $record['product_type'],
                (string) $record['unit_of_sale'],
            );

            $departmentSlug = $this->classifyDepartment(
                (string) $record['raw_category'],
                $cleanName,
                (string) $record['product_type'],
            );

            $deptMeta = self::DEPARTMENTS[$departmentSlug];
            $isWeighed = strtolower((string) $record['product_type']) === 'weighed' || $record['unit_of_sale'] === 'lb';
            $unitType = $isWeighed ? 'lb' : 'each';

            if ($isWeighed && ($sizeMain === null || $sizeMain === '')) {
                $sizeMain = 'per lb';
            }

            $price = (float) $record['price'];
            $unitPrice = $this->computeUnitPrice($price, $sizeMain, $unitType);

            $baseSlug = Str::slug($cleanName);
            if ($baseSlug === '') {
                $baseSlug = 'product-'.$record['epos_id'];
            }
            $slug = $baseSlug;
            $suffix = 2;
            while (isset($usedSlugs[$slug])) {
                $slug = "{$baseSlug}-{$suffix}";
                $suffix++;
            }
            $usedSlugs[$slug] = true;

            $rawSku = trim((string) $record['raw_sku']);
            $candidateSku = ($rawSku !== '' && ! isset($usedSkus[strtolower($rawSku)]))
                ? $rawSku
                : 'EPOS-'.$record['epos_id'];
            while (isset($usedSkus[strtolower($candidateSku)])) {
                $candidateSku .= '-'.$record['epos_id'];
            }
            $usedSkus[strtolower($candidateSku)] = true;

            $supplierId = $record['raw_supplier'] !== '' ? ($supplierMap[$record['raw_supplier']] ?? null) : null;
            $subtitleTag = $brandLabel !== '' ? $brandLabel : $deptMeta['name'];
            $photoLabel = Str::limit($brandLabel !== '' ? $brandLabel : $deptMeta['icon'], 48, '');

            $description = $this->buildDescription(
                $cleanName,
                $brandLabel,
                $sizeMain,
                $deptMeta['name'],
                (string) $record['raw_description'],
                $isWeighed,
            );

            $record['clean_name'] = $cleanName;
            $record['department'] = $departmentSlug;
            $record['sku_final'] = $candidateSku;
            $record['size_main'] = $sizeMain;
            $record['unit_type'] = $unitType;

            $altBarcodes = $record['alternate_barcodes'];

            $batch[] = [
                'slug' => $slug,
                'barcode' => $record['barcode'],
                'alternate_barcodes' => ! empty($altBarcodes) ? json_encode(array_values($altBarcodes)) : null,
                'sku' => $candidateSku,
                'name' => $cleanName,
                'subtitle_tag' => Str::limit($subtitleTag, 95, ''),
                'category' => $departmentSlug,
                'supplier_id' => $supplierId,
                'category_title' => $deptMeta['name'],
                'price' => $price,
                'cost_price' => $record['cost_price'],
                'original_price' => null,
                'unit_price' => $unitPrice,
                'unit_type' => $unitType,
                'is_taxable' => (bool) $record['is_taxable'],
                'stock' => self::DEFAULT_STOCK,
                'stock_badge' => 'In stock',
                'photo_label' => $photoLabel,
                'image' => self::PLACEHOLDER_IMAGE,
                'images' => json_encode([self::PLACEHOLDER_IMAGE]),
                'size_main' => $sizeMain,
                'size_sub' => $isWeighed ? 'Weighed at register' : null,
                'freshness_line' => $deptMeta['freshness'],
                'description' => $description,
                'buy_again' => in_array($departmentSlug, ['staples', 'dairy', 'snacks', 'bakery', 'produce', 'beverages'], true),
                'has_subscription' => false,
                'frequently_bought_together' => null,
                'created_at' => $now,
                'updated_at' => $now,
            ];

            if (count($batch) >= 250) {
                DB::table('products')->insert($batch);
                $batch = [];
            }
        }
        unset($record);

        if ($batch !== []) {
            DB::table('products')->insert($batch);
        }
    }

    /**
     * Expand POS brand shorthand, fix common typos, and extract normalized pack weight/volume.
     *
     * @return array{0: string, 1: string, 2: string|null}
     */
    private function enrichNameBrandAndSize(
        string $rawName,
        string $rawBrand,
        string $rawSupplier,
        string $productType,
        string $unitOfSale,
    ): array {
        $name = preg_replace('/\s+/', ' ', trim($rawName)) ?? trim($rawName);

        // Strip trailing Epos 'LY' marker
        $name = preg_replace('/\s+LY\b/i', '', $name) ?? $name;
        $name = preg_replace('/\bLY\s+/i', '', $name) ?? $name;

        // Expand leading brand shorthand codes
        $prefixReplacements = [
            '/^HR\s+RTE\b/i' => "Haldiram's Ready to Eat",
            '/^HRS\b/i' => "Haldiram's",
            '/^HR\s+/i' => "Haldiram's ",
            '/^BR\s+/i' => 'Britannia ',
            '/^LXX?\s+/i' => 'Laxmi ',
            '/^S-\s*/i' => 'Surati ',
            '/^Sh-\s*/i' => 'Shalini ',
            '/^G-\s*/i' => 'Surati ',
            '/^GC\s+/i' => 'Global Choice ',
            '/^GG\s+/i' => 'Garvi Gujarat ',
            '/^KKDN\s+/i' => "Kozhikoden's ",
            '/^KK\s+/i' => "Kozhikoden's ",
            '/^Par-\s*/i' => 'Parle ',
            '/^H&S\s+/i' => 'Parle Hide & Seek ',
            '/^DD\s+/i' => 'Daily Delight ',
            '/^(?:BRH|Brmns|Brmn)\s+/i' => 'Brahmins ',
            '/^(?:EBM|ENG)\s+/i' => 'Peek Freans ',
            '/^HL\s+/i' => 'Himalaya ',
            '/^WB\s+/i' => 'Wagh Bakri ',
            '/^AK\s+/i' => "Amma's Kitchen ",
            '/^KB\s+/i' => 'Karachi Bakery ',
            '/^PR\s+/i' => 'Priya ',
            '/^PK\s+RTE\b/i' => 'Punjaabi Kitchen Ready to Eat',
            '/^G&L\s+/i' => 'Glow & Lovely ',
            '/^MDh(?=[A-Z])/i' => 'MDH ',
        ];

        foreach ($prefixReplacements as $pattern => $replacement) {
            if (preg_match($pattern, $name) === 1) {
                $name = preg_replace($pattern, $replacement, $name) ?? $name;
                break;
            }
        }

        // Fix common POS typos
        $typoFixes = [
            '/\bPatato\b/i' => 'Potato',
            '/\bCanty\b/i' => 'Candy',
            '/\bChrush\b/i' => 'Crushed',
            '/\bchrushed\b/i' => 'Crushed',
            '/\bGaric\b/i' => 'Garlic',
            '/\bforzen\b/i' => 'Frozen',
            '/\bWlhite\b/i' => 'White',
            '/\bVermacellin\b/i' => 'Vermicelli',
            '/\bKoya\b/i' => 'Khoya',
            '/\bPart Pik\b/i' => 'Party Pik',
            '/\bPiteed\b/i' => 'Pitted',
            '/\b2\.b\b/i' => '2lb',
            '/\bAjino motto\b/i' => 'Ajinomoto',
            '/\bAjino Moto\b/i' => 'Ajinomoto',
            '/\bPillsburry\b/i' => 'Pillsbury',
            '/\b briyani\b/i' => ' Biryani',
            '/\bPattak\b/i' => "Patak's",
            '/\bPatak\b/i' => "Patak's",
            '/\bChings\b/i' => "Ching's",
            '/\bMothers\b/i' => "Mother's Recipe",
            '/\bBrars?\b/i' => "Brar's",
            '/\bCorse\b/i' => 'Coarse',
            '/\bSooji Course\b/i' => 'Sooji Coarse',
            '/\bFlr\b/i' => 'Flour',
        ];

        foreach ($typoFixes as $pattern => $replacement) {
            $name = preg_replace($pattern, $replacement, $name) ?? $name;
        }

        // Ensure space before glued pack sizes like "Munch90gms" or "atta4.54KG"
        $name = preg_replace('/([a-zA-Z])(\d+(?:\.\d+)?\s*(?:gm|gms|g|kg|lb|lbs|ml|l|lt|oz|pcs|pc)\b)/i', '$1 $2', $name) ?? $name;

        // Normalize ALL-CAPS names (except short acronyms like MDH, MTR, GRB, Q7)
        if ($name === mb_strtoupper($name) && mb_strlen($name) > 5) {
            $name = Str::title(mb_strtolower($name));
            $name = str_replace(['Mdh', 'Mtr', 'Grb', 'Ss ', 'Xxl', 'Xl'], ['MDH', 'MTR', 'GRB', 'SS ', 'XXL', 'XL'], $name);
        }

        // Capitalize lowercase words cleanly (e.g. "kala" -> "Kala") while preserving prepositions & units
        $name = preg_replace_callback('/\b([a-z][a-z\']*)\b/', static function (array $m): string {
            $word = $m[1];
            if (in_array($word, ['in', 'of', 'with', 'and', 'for', 'to', 'by', 'per', 'x'], true)) {
                return $word;
            }
            if (preg_match('/^(gm|gms|g|kg|kgs|lb|lbs|ml|mls|l|lt|oz|pc|pcs|pack|packets)$/', $word) === 1) {
                return $word;
            }

            return ucfirst($word);
        }, $name) ?? $name;
        $name = ucfirst($name);

        // Extract size_main (e.g. "6x10g", "200g", "4lb", "10kg", "500ml", "1L", "14.1oz", "24pcs")
        $sizeMain = null;
        if (preg_match('/\b(\d+\s*x\s*\d+(?:\.\d+)?\s*(?:gm|gms|g|kg|lb|lbs|ml|l|oz))\b/i', $name, $m) === 1) {
            $sizeMain = $this->normalizeUnitString($m[1]);
        } elseif (preg_match('/\b(\d+(?:\.\d+)?\s*(?:gm|gms|g|kg|lb|lbs|ml|lt|l|oz|pcs|pc|pack|packets))\b/i', $name, $m) === 1) {
            $sizeMain = $this->normalizeUnitString($m[1]);
        } elseif (strtolower($productType) === 'weighed' || $unitOfSale === 'lb') {
            $sizeMain = 'per lb';
        }

        // Normalize unit spelling inside display name cleanly
        $name = preg_replace_callback(
            '/\b(\d+(?:\.\d+)?)\s*(gms|gm|g|kgs|kg|lbs|lb|mls|ml|lt|l|oz)\b/i',
            fn (array $m): string => $this->normalizeUnitString($m[1].$m[2]),
            $name,
        ) ?? $name;

        $name = preg_replace('/\s+/', ' ', trim($name)) ?? trim($name);

        $brand = $this->detectBrand($name, $rawBrand, $rawSupplier);

        return [$name, $brand, $sizeMain];
    }

    /**
     * Format unit suffixes consistently (200g, 1kg, 4lb, 500ml, 1L, 12oz).
     */
    private function normalizeUnitString(string $raw): string
    {
        $compact = preg_replace('/\s+/', ' ', trim($raw)) ?? trim($raw);

        if (preg_match('/^(\d+\s*x\s*\d+(?:\.\d+)?)\s*(gms|gm|g|kgs|kg|lbs|lb|mls|ml|lt|l|oz)$/i', $compact, $m) === 1) {
            $num = str_replace(' ', '', strtolower($m[1]));
            $unit = $this->canonicalUnit($m[2]);

            return "{$num}{$unit}";
        }

        if (preg_match('/^(\d+(?:\.\d+)?)\s*(gms|gm|g|kgs|kg|lbs|lb|mls|ml|lt|l|oz|pcs|pc|pack|packets)$/i', $compact, $m) === 1) {
            $unit = $this->canonicalUnit($m[2]);
            $sep = in_array($unit, ['pcs', 'pc', 'pack', 'packets'], true) ? ' ' : '';

            return "{$m[1]}{$sep}{$unit}";
        }

        return $compact;
    }

    /**
     * Map raw unit spellings to canonical short units.
     */
    private function canonicalUnit(string $rawUnit): string
    {
        return match (strtolower(trim($rawUnit))) {
            'gm', 'gms', 'g' => 'g',
            'kg', 'kgs' => 'kg',
            'lb', 'lbs' => 'lb',
            'ml', 'mls' => 'ml',
            'l', 'lt' => 'L',
            'oz' => 'oz',
            'pc' => 'pc',
            'pcs' => 'pcs',
            'pack' => 'pack',
            'packets' => 'packets',
            default => strtolower(trim($rawUnit)),
        };
    }

    /**
     * Detect brand label from cleaned product name, CSV Brand Name, or known supplier brand.
     */
    private function detectBrand(string $cleanName, string $rawBrand, string $rawSupplier): string
    {
        if ($rawBrand !== '' && strtolower($rawBrand) !== 'none') {
            return $rawBrand;
        }

        $knownBrands = [
            "Haldiram's", 'Britannia', 'Parle', 'Laxmi', 'Surati', 'Shalini', 'Global Choice',
            'Garvi Gujarat', "Kozhikoden's", 'Daily Delight', 'Brahmins', 'Peek Freans', 'Himalaya',
            'Wagh Bakri', "Amma's Kitchen", 'Karachi Bakery', 'Priya', 'MDH', 'Everest', 'Shan',
            'National', 'Badshah', "Mother's Recipe", "Patak's", "Ching's", 'Gits', 'MTR', 'Ashoka',
            'Deep', 'Bansi', 'Sher', 'Aashirvaad', 'Pillsbury', 'India Gate', 'Daawat', 'Handi',
            'Amul', "Brar's", 'Nanak', 'Verka', 'Dabur', 'Patanjali', 'Bajaj', 'Parachute',
            'Vatika', 'Hamdard', 'Balaji', 'Kurkure', "Lay's", 'Lays', 'Bikano', 'Chitale',
            'Maggi', 'Nestle', 'Tata', 'Red Label', 'Taj Mahal', 'Society', 'Bru', 'Nescafe',
            'Eastern', 'Swad', 'Megh', 'Nikita', 'Zaika', 'Jabson', 'Veeba', 'Noorani',
        ];

        foreach ($knownBrands as $brand) {
            if (stripos($cleanName, $brand) === 0 || preg_match('/\b'.preg_quote($brand, '/').'\b/i', $cleanName) === 1) {
                return $brand === 'Lays' ? "Lay's" : $brand;
            }
        }

        if ($rawSupplier !== '') {
            return self::SUPPLIER_NAME_MAP[$rawSupplier] ?? $rawSupplier;
        }

        return '';
    }

    /**
     * Classify every item (including the 1,061 Misc/blank rows) into a clean Smart Grocery Department.
     */
    private function classifyDepartment(string $rawCategory, string $cleanName, string $productType): string
    {
        $cat = strtolower(trim($rawCategory));
        $n = strtolower($cleanName);

        // Direct unambiguous CSV categories first
        $directMap = [
            'produce' => 'produce',
            'sweets' => 'sweets',
            'kulfi' => 'sweets',
            'lentils' => 'staples',
            'flour' => 'staples',
            'rice' => 'staples',
            'spice blends' => 'spices',
            'pickles' => 'spices',
            'sauces' => 'spices',
            'drinks' => 'beverages',
            'juice' => 'beverages',
            'beauty products' => 'personal-care',
            'tooth paste' => 'personal-care',
            'puja' => 'puja-home',
            'diwali decoration' => 'puja-home',
            'utensils' => 'puja-home',
            'rakhi' => 'puja-home',
            'crispy' => 'dairy',
            'noodles' => 'grocery',
            'instant eat' => 'grocery',
        ];

        // Catch misplaced rice/sugar inside Vegetable Oil or Household Use before direct mapping
        if ($cat === 'vegetable oil') {
            if (preg_match('/\b(rice|atta|flour|dal)\b/', $n) === 1) {
                return 'staples';
            }

            return 'grocery';
        }

        if ($cat === 'household use') {
            if (preg_match('/\b(sugar|jaggery|gur|atta|rice)\b/', $n) === 1) {
                return 'staples';
            }
            if (preg_match('/\b(ashokarishta|dabur|balm|soap|toothpaste)\b/', $n) === 1) {
                return 'personal-care';
            }

            return 'puja-home';
        }

        if ($cat === 'digestion') {
            if (preg_match('/\b(murabba)\b/', $n) === 1) {
                return 'sweets';
            }
            if (preg_match('/\b(mukhwas|gulkand|anardana|saunf|hing|churan|hajmola)\b/', $n) === 1) {
                return 'spices';
            }
            if (preg_match('/\b(glucose|glucon|gulucon|sharbat)\b/', $n) === 1) {
                return 'beverages';
            }

            return 'personal-care';
        }

        if ($cat === 'dairy/frozen') {
            if (preg_match('/\b(dhokla mix|idli mix|dosa mix)\b/', $n) === 1) {
                return 'grocery';
            }

            return 'dairy';
        }

        if ($cat === 'snacks') {
            if (preg_match('/\b(biscuit|biscuits|cookie|cookies|rusk|khari|toast|toastea|good day|goodday|wonderfulls|pure magic|dark fantasy|choco pie|choco rolls|chunkies|milk bikis|50-50|50 50|marie|hide & seek|bourbon|krackjack|monaco|nice time|little hearts|jim jam|malkist|party pik|digestive|cream treat|treat funky|fruit cake|cake rusk|nankhatai)\b/', $n) === 1) {
                return 'bakery';
            }
            if (preg_match('/\b(soan papdi|gulab jamun|rasgulla|burfi|barfi|laddu|ladoo|halwa|chikki|gajak|rewari|petha)\b/', $n) === 1) {
                return 'sweets';
            }

            return 'snacks';
        }

        if (isset($directMap[$cat])) {
            // Check if a juice category item is actually Ayurvedic syrup like Safi
            if ($cat === 'juice' && preg_match('/\b(safi)\b/', $n) === 1) {
                return 'personal-care';
            }

            return $directMap[$cat];
        }

        // Smart keyword classification for Misc, Food, W misc, and blank categories (1,200+ rows)
        if (preg_match('/\b(diya|diva|wick|wicks|batti|puja|pooja|camphor|kapoor|agarbatti|incense|dhoop|loban|hawan|samagri|kumkum|sindoor|abir|abil|gulal|gangajal|mouli|kalash|lota|thali|thaal|toran|latkan|rangoli|rakhi|clay|kulhad|handi glass|copper bottle|cooker|kadai|tawa|pan|broom|scruber|scrubber|surf excel|rin|vim|lock|tray|playing cards|cricket|bat|ball|asan)\b/', $n) === 1) {
            return 'puja-home';
        }

        if (preg_match('/\b(soap|shampoo|conditioner|hair oil|almond oil|amla oil|jasmine oil|bhringraj|kesh king|navratna|parachute hot|face wash|face cream|cold cream|fair|glow & lovely|lotion|moisturizer|footcare|toothpaste|tooth paste|dant|meswak|mouthwash|tongue cleaner|henna|hena|mehndi|hair color|hair clr|hair dye|bigen|godrej expert|balm|zandu|amrutanjan|tiger balm|vicks|moov|iodex|eno|isabgol|psyllium|chyawanprash|chawanprash|ashokarishta|triphala|shilajit|hajmola|safi|deo|deodrant|deodorant|perfume|attar|talc|powder ponds|multani mitti|ubtan|rose water|gulabari|kajal|wax strips|dettol|savlon|boroline|kailas jeevan|vicco|glycerine|neem oil|castor oil)\b/', $n) === 1) {
            return 'personal-care';
        }

        if (preg_match('/\b(biscuit|biscuits|cookie|cookies|rusk|khari|toastea|good day|goodday|wonderfulls|pure magic|dark fantasy|choco pie|choco rolls|chunkies|milk bikis|50-50|50 50|marie|parle g|parle-g|hide & seek|bourbon|krackjack|monaco|nice time|little hearts|jim jam|malkist|party pik|digestive|karachi bakery|cake rusk|fruit cake|plum cake|nankhatai|bakarkhani|puff)\b/', $n) === 1) {
            return 'bakery';
        }

        if (preg_match('/\b(tea|chai|wagh bakri|red label|taj mahal|brooke bond|tetley|tapal|vital|society tea|girnar|coffee|bru|nescafe|davidoff|horlicks|bournvita|complan|milo|tang|rasna|rooh afza|sharbat|thandai|badam drink|falooda drink|mango drink|maaza|frooti|slice|limca|thums up|thumbs up|fanta|pepsi|coca cola|coke|sprite|7up|mirinda|canada dry|snapple|barbican|appy fizz|soda|lassi|chaas|coconut water|aloe vera drink|rubicon|sherbon|hajoori)\b/', $n) === 1) {
            return 'beverages';
        }

        if (preg_match('/\b(ghee|paneer|butter|cheese|amul|shrikhand|dahi|curd|makhni|malai|khoya|mawa|paratha|parotta|porotta|thepla|bhakri|naan|roti|kulcha|kachori frozen|samosa \d+pcs|spring roll|patty|burger|arvi|frozen|mango pulp|soya chaap|soy chaap|dahi bhalla|palappam|vellayapam|kumbiliappam|batter|fish|tuna)\b/', $n) === 1) {
            return 'dairy';
        }

        if (preg_match('/\b(burfi|barfi|kaju katli|gulab jamun|rasgulla|rasmalai|chamcham|chum chum|kalakand|milk cake|laddu|ladoo|motichoor|besan ladoo|soan papdi|patisa|halwa|jalebi|imarti|peda|petha|chikki|gajak|rewari|tilkut|murabba|shakarpara|gujiya|karanji|mysore pak|balushahi|jelly)\b/', $n) === 1) {
            return 'sweets';
        }

        if (strtolower($productType) === 'weighed' && preg_match('/\b(walnut|walnuts|peanut|peanuts|almond|cashew|pista)\b/', $n) !== 1) {
            return 'produce';
        }

        if (preg_match('/\b(atta|flour|besan|maida|sooji|suji|rava|semolina|dalia|cracked wheat|lapsi|poha|aval|beaten rice|flattened rice|sabudana|tapioca pearl|murmura|mamra|puffed rice|rajgira|rajagira|singhara|kuttu|ragi|nachni|bajra|bajri|jowar|makki|corn meal|corn starch|rice|basmati|sona masoori|matta|idli rice|ponni|parboiled|sella|dal|daal|lentil|lentils|chana|chickpea|chickpeas|kabuli|kala chana|rajma|kidney beans|moong|mung|urad|udad|urid|toor|tuvar|arhar|masoor|moth|matki|val|lobia|black eye|horse gram|kulthi|peas|vatana|matar|soyabean|quinoa|barley|jau|wheat|sugar|jaggery|gur|gud|shakkar|shakhar|mishri|boora)\b/', $n) === 1) {
            return 'staples';
        }

        if (preg_match('/\b(masala|spice|spices|haldi|turmeric|mirch|mirchi|chilli|chili|paprika|coriander|dhania|jeera|cumin|rai|mustard seed|methi|fenugreek|saunf|fennel|elaichi|cardamom|clove|cloves|laung|cinnamon|dalchini|cassia|bay leaf|bay leaves|tej patta|javitri|mace|jaiphal|nutmeg|star anise|badian|chakri phool|pepper|kali mirch|ajwain|carom|kalonji|nigella|sesame|til|poppy|khus khus|kasuri|hing|asafoetida|amchur|mango powder|anardana|tamarind|imli|tamcon|kokum|kachri|char goond|chargoond|goond|gum|kamarkas|magaz|melon seed|alsi|flax|chia|basil seed|tukmaria|sabja|salt|namak|sendha|kala namak|pink salt|citric acid|limbu phool|baking|soda bi|ajinomoto|vinegar|keora|kewra|rose petal|essence|food colour|food color|pickle|achar|chutney|paste|sauce|ketchup|schezwan|mayo|mayonnaise|mukhwas|pan masala|supari|soopari|gulkand|paan|churan)\b/', $n) === 1) {
            return 'spices';
        }

        if (preg_match('/\b(namkeen|bhujia|sev|gathiya|chevdo|chivda|mixture|mix|dalmoth|chanachur|fafda|khakhra|khakhara|chakli|murukku|chakri|kodubale|thatta|seedai|banana chips|tapioca chips|jackfruit chips|chips|wafers|kurkure|crunchem|lays|uncle chipps|crax|fryums|farali|papad|pappad|pappadam|applam|appalam|sandige|pani puri|golgappa|bhel|puri|boondi|boodi|kara|mathri|namak para|bhakarwadi|kachori|tum tum|chorafali|patra|samosa dry|makhana|fox nut|peanut|peanuts|sing|khari sing|chana jor|roasted chana|mahabaleshwar|cashew|kaju|almond|badam|pista|pistachio|walnut|akhrot|raisin|kishmish|dates|khajoor|fig|anjeer|apricot|jardalu|prunes|dry fruit|aam papad|choco|chocolate|dairy milk|perk|munch|kitkat|5 star|eclairs|melody|pulse|hajmola candy|candy|toffee|lollipop|falooda sev)\b/', $n) === 1) {
            return 'snacks';
        }

        return 'grocery';
    }

    /**
     * Compute clean readable unit_price string for storefront cards.
     */
    private function computeUnitPrice(float $price, ?string $sizeMain, string $unitType): string
    {
        if ($unitType === 'lb') {
            return '$'.number_format($price, 2).' / lb';
        }

        if ($sizeMain !== null && preg_match('/^(\d+(?:\.\d+)?)(g|kg|lb|ml|L|oz)$/i', $sizeMain, $m) === 1) {
            $qty = (float) $m[1];
            $unit = $m[2];
            if ($qty > 0) {
                return match ($unit) {
                    'g' => '$'.number_format(($price / $qty) * 100, 2).' / 100g',
                    'kg' => '$'.number_format($price / $qty, 2).' / kg',
                    'lb' => '$'.number_format($price / $qty, 2).' / lb',
                    'ml' => '$'.number_format(($price / $qty) * 100, 2).' / 100ml',
                    'L', 'l' => '$'.number_format($price / $qty, 2).' / L',
                    'oz' => '$'.number_format($price / $qty, 2).' / oz',
                    default => '$'.number_format($price, 2).' / each',
                };
            }
        }

        return '$'.number_format($price, 2).' / each';
    }

    /**
     * Build clean storefront product description.
     */
    private function buildDescription(
        string $cleanName,
        string $brand,
        ?string $sizeMain,
        string $departmentTitle,
        string $rawDescription,
        bool $isWeighed,
    ): string {
        if ($rawDescription !== '' && mb_strlen($rawDescription) > 15) {
            return $rawDescription;
        }

        $brandPhrase = $brand !== '' ? "by {$brand}" : 'sourced from trusted Indian & Canadian distributors';
        $packPhrase = $isWeighed
            ? 'Sold freshly weighed by the pound (lb) at our store counter.'
            : ($sizeMain ? "Convenient {$sizeMain} retail pack." : 'Ready for pickup or local delivery.');

        return "{$cleanName} {$brandPhrase} in our {$departmentTitle} aisle. {$packPhrase}";
    }

    /**
     * Export clean audit CSVs for full catalog transparency and shared-barcode conflict review.
     *
     * @param  list<array<string, mixed>>  $records
     * @param  list<array<string, mixed>>  $conflicts
     */
    private function writeAuditReports(array $records, array $conflicts): void
    {
        $conflictPath = storage_path('app/barcode_review_needed.csv');
        $fp = fopen($conflictPath, 'w');
        if ($fp !== false) {
            fputcsv($fp, [
                'Shared Barcode',
                'Kept On Epos ID',
                'Kept On Product (Scans Now)',
                'Needs Rescan Epos ID',
                'Product Needing Own Barcode Scan',
                'Price ($)',
            ], ',', '"', '');

            foreach ($conflicts as $c) {
                fputcsv($fp, [
                    $c['shared_barcode'],
                    $c['kept_on_epos_id'],
                    $c['kept_on_name'],
                    $c['cleared_epos_id'],
                    $c['cleared_name'],
                    number_format((float) $c['cleared_price'], 2, '.', ''),
                ], ',', '"', '');
            }
            fclose($fp);
        }

        $importedPath = storage_path('app/imported_store_catalog.csv');
        $fp2 = fopen($importedPath, 'w');
        if ($fp2 !== false) {
            fputcsv($fp2, [
                'SKU',
                'Clean Product Name',
                'Original Epos Name',
                'Department',
                'Price',
                'Cost Price',
                'Taxable (13% HST)',
                'Unit Type',
                'Size',
                'Barcode',
                'Alternate Barcodes',
                'Supplier',
            ], ',', '"', '');

            foreach ($records as $r) {
                fputcsv($fp2, [
                    $r['sku_final'] ?? '',
                    $r['clean_name'] ?? $r['raw_name'],
                    $r['raw_name'],
                    $r['department'] ?? '',
                    number_format((float) $r['price'], 2, '.', ''),
                    $r['cost_price'] !== null ? number_format((float) $r['cost_price'], 2, '.', '') : '',
                    $r['is_taxable'] ? 'Yes' : 'No',
                    $r['unit_type'] ?? 'each',
                    $r['size_main'] ?? '',
                    $r['barcode'] ?? '',
                    implode(' | ', $r['alternate_barcodes']),
                    $r['raw_supplier'],
                ], ',', '"', '');
            }
            fclose($fp2);
        }
    }
}
