import axios from 'axios';
import { defineStore } from 'pinia';
import { computed, ref } from 'vue';

const PARKED_STORAGE_KEY = 'masala_mart_pos_parked_v1';

const round2 = (value) => {
    const n = Number(value || 0);
    if (!Number.isFinite(n) || n === 0) {
        return 0;
    }

    return Math.sign(n) * (Math.round(Math.abs(n) * 100 + 1e-9) / 100);
};
const round3 = (value) => Math.round((Number(value || 0) + Number.EPSILON) * 1000) / 1000;

function loadParkedSales() {
    if (typeof window === 'undefined') {
        return [];
    }

    try {
        const raw = window.localStorage.getItem(PARKED_STORAGE_KEY);
        const parsed = raw ? JSON.parse(raw) : [];
        return Array.isArray(parsed) ? parsed : [];
    } catch {
        return [];
    }
}

function persistParkedSales(list) {
    if (typeof window === 'undefined') {
        return;
    }

    try {
        window.localStorage.setItem(PARKED_STORAGE_KEY, JSON.stringify(list));
    } catch {
        // Ignore storage quota errors on kiosk browsers
    }
}

export const usePosStore = defineStore('pos', () => {
    const productsById = ref({});
    const catalogRevision = ref(0);
    const catalogPageCache = new Map();

    const taxRate = ref(0);
    const loyaltyConfig = ref({
        points_per_block: 500,
        discount_per_block: 10,
    });

    const lines = ref([]);
    const customer = ref(null);
    const discountType = ref('none');
    const discountValue = ref(0);
    const pointsBlocksToRedeem = ref(0);
    const orderNotes = ref('');
    const parkedSales = ref(loadParkedSales());

    let lineCounter = 1;

    const upsertProducts = (productList = []) => {
        if (!Array.isArray(productList) || productList.length === 0) {
            return;
        }

        const next = { ...productsById.value };
        for (const product of productList) {
            if (product && product.id != null) {
                next[product.id] = { ...product };
            }
        }
        productsById.value = next;
        catalogRevision.value += 1;
    };

    const hydrateRegisterConfig = (storeTaxRate = 0, config = null) => {
        taxRate.value = Math.max(0, Number(storeTaxRate || 0));

        if (config && typeof config === 'object') {
            loyaltyConfig.value = {
                points_per_block: Math.max(1, Number(config.points_per_block || 500)),
                discount_per_block: Math.max(0.01, Number(config.discount_per_block || 10)),
            };
        }
    };

    const hydrateCatalog = (catalogProducts = [], storeTaxRate = 0, config = null) => {
        hydrateRegisterConfig(storeTaxRate, config);
        if (Array.isArray(catalogProducts) && catalogProducts.length > 0) {
            upsertProducts(catalogProducts);
        }
    };

    const invalidateCatalogCache = () => {
        catalogPageCache.clear();
        catalogRevision.value += 1;
    };

    const syncCartProductPrices = async () => {
        const productIds = [
            ...new Set(
                lines.value
                    .map((line) => Number(line.product_id || 0))
                    .filter((id) => Number.isInteger(id) && id > 0),
            ),
        ];

        if (productIds.length === 0) {
            return { updatedCount: 0, missingProductNames: [] };
        }

        const fetchedById = new Map();
        for (let i = 0; i < productIds.length; i += 100) {
            const batch = productIds.slice(i, i + 100);
            const { data } = await axios.get(route('pos.products.index'), {
                params: {
                    ids: batch,
                    per_page: batch.length,
                },
            });

            const list = Array.isArray(data?.products) ? data.products : [];
            upsertProducts(list);
            for (const product of list) {
                fetchedById.set(Number(product.id), product);
            }
        }

        let updatedCount = 0;
        const missingProductNames = [];

        for (const line of lines.value) {
            if (!line.product_id) {
                continue;
            }
            const fresh = fetchedById.get(Number(line.product_id));
            if (!fresh) {
                missingProductNames.push(line.name);
                continue;
            }

            const freshPrice = round2(fresh.price);
            const freshTaxable = Boolean(fresh.is_taxable);

            if (
                Math.abs(Number(line.unit_price) - freshPrice) > 0.0001 ||
                Boolean(line.is_taxable) !== freshTaxable ||
                line.name !== fresh.name
            ) {
                line.unit_price = freshPrice;
                line.is_taxable = freshTaxable;
                line.name = fresh.name;
                updatedCount += 1;
            }
        }

        return { updatedCount, missingProductNames };
    };

    const applyStockUpdates = (stockMap = {}) => {
        if (!stockMap || typeof stockMap !== 'object') {
            return;
        }

        let changed = false;
        const next = { ...productsById.value };

        for (const [rawId, nextStock] of Object.entries(stockMap)) {
            const id = Number(rawId);
            if (next[id]) {
                next[id] = {
                    ...next[id],
                    stock: Number(nextStock),
                };
                changed = true;
            }
        }

        if (changed) {
            productsById.value = next;
            catalogRevision.value += 1;
        }
    };

    const knownProductsList = computed(() => Object.values(productsById.value));

    const barcodeIndex = computed(() => {
        const map = new Map();
        for (const product of knownProductsList.value) {
            if (product.barcode) {
                map.set(String(product.barcode).trim().toLowerCase(), product);
            }
        }
        return map;
    });

    const skuIndex = computed(() => {
        const map = new Map();
        for (const product of knownProductsList.value) {
            if (product.sku) {
                map.set(String(product.sku).trim().toLowerCase(), product);
            }
        }
        return map;
    });

    const findByScanCode = (rawCode) => {
        const code = String(rawCode || '').trim().toLowerCase();
        if (!code) {
            return null;
        }

        return barcodeIndex.value.get(code) || skuIndex.value.get(code) || null;
    };

    const resolveScanCode = async (rawCode) => {
        const trimmed = String(rawCode || '').trim();
        if (!trimmed) {
            return null;
        }

        const cached = findByScanCode(trimmed);
        if (cached) {
            return cached;
        }

        try {
            const { data } = await axios.get(route('pos.products.scan'), {
                params: { code: trimmed },
            });

            if (data?.product) {
                upsertProducts([data.product]);
                return productsById.value[data.product.id] || data.product;
            }

            return null;
        } catch {
            return null;
        }
    };

    const fetchCatalogPage = async ({
        category = 'all',
        weighed = false,
        q = '',
        page = 1,
        perPage = 48,
        force = false,
        signal = undefined,
    } = {}) => {
        const normalizedCategory = String(category || 'all').trim() || 'all';
        const normalizedQuery = String(q || '').trim();
        const cacheKey = `${normalizedCategory}|${weighed ? '1' : '0'}|${normalizedQuery.toLowerCase()}|${page}|${perPage}`;

        if (force) {
            catalogPageCache.delete(cacheKey);
        }

        if (!force && catalogPageCache.has(cacheKey)) {
            const cached = catalogPageCache.get(cacheKey);
            return {
                products: cached.ids.map((id) => productsById.value[id]).filter(Boolean),
                meta: cached.meta,
                fromCache: true,
            };
        }

        const params = {
            page,
            per_page: perPage,
        };

        if (normalizedCategory !== 'all') {
            params.category = normalizedCategory;
        }
        if (weighed) {
            params.weighed = 1;
        }
        if (normalizedQuery) {
            params.q = normalizedQuery;
        }

        const { data } = await axios.get(route('pos.products.index'), {
            params,
            signal,
        });

        const fetchedProducts = Array.isArray(data?.products) ? data.products : [];
        const meta = data?.meta || {
            current_page: page,
            last_page: 1,
            per_page: perPage,
            total: fetchedProducts.length,
            has_more: false,
        };

        upsertProducts(fetchedProducts);

        const ids = fetchedProducts.map((item) => item.id);
        catalogPageCache.set(cacheKey, { ids, meta });

        return {
            products: ids.map((id) => productsById.value[id]).filter(Boolean),
            meta,
            fromCache: false,
        };
    };

    const computeLineSubtotal = (line) => {
        const price = Number(line.unit_price || 0);
        const qty = Math.max(1, Number(line.quantity || 1));

        if (line.unit_type && line.unit_type !== 'each') {
            const weight = Math.max(0, Number(line.weight || 0));
            return round2(price * weight * qty);
        }

        return round2(price * qty);
    };

    const subtotal = computed(() =>
        round2(lines.value.reduce((sum, line) => sum + computeLineSubtotal(line), 0)),
    );

    const manualDiscount = computed(() => {
        const val = Math.max(0, Number(discountValue.value || 0));

        if (discountType.value === 'percent' && val > 0) {
            return round2(subtotal.value * (Math.min(100, val) / 100));
        }

        if (discountType.value === 'fixed' && val > 0) {
            return Math.min(subtotal.value, round2(val));
        }

        return 0;
    });

    const remainingAfterManualDiscount = computed(() =>
        Math.max(0, round2(subtotal.value - manualDiscount.value)),
    );

    const maxCustomerRedeemableBlocks = computed(() => {
        if (!customer.value) {
            return 0;
        }

        const ptsPerBlock = Math.max(1, Number(loyaltyConfig.value.points_per_block || 500));
        const discPerBlock = Math.max(0.01, Number(loyaltyConfig.value.discount_per_block || 10));
        const availableBlocks = Math.floor(Math.max(0, Number(customer.value.reward_points || 0)) / ptsPerBlock);

        if (remainingAfterManualDiscount.value + 0.0001 < discPerBlock) {
            return 0;
        }

        const usableBlocks = Math.floor((remainingAfterManualDiscount.value + 0.0001) / discPerBlock);
        return Math.min(availableBlocks, usableBlocks);
    });

    const effectivePointsBlocks = computed(() =>
        Math.min(Math.max(0, Number(pointsBlocksToRedeem.value || 0)), maxCustomerRedeemableBlocks.value),
    );

    const effectivePointsRedeemed = computed(
        () => effectivePointsBlocks.value * Math.max(1, Number(loyaltyConfig.value.points_per_block || 500)),
    );

    const pointsDiscount = computed(() => {
        if (effectivePointsBlocks.value <= 0) {
            return 0;
        }

        const discPerBlock = Math.max(0.01, Number(loyaltyConfig.value.discount_per_block || 10));
        return Math.min(
            remainingAfterManualDiscount.value,
            round2(effectivePointsBlocks.value * discPerBlock),
        );
    });

    const totalDiscount = computed(() =>
        Math.min(subtotal.value, round2(manualDiscount.value + pointsDiscount.value)),
    );

    const taxAmount = computed(() => {
        const rate = Math.max(0, Number(taxRate.value || 0));
        if (rate <= 0 || subtotal.value <= 0) {
            return 0;
        }

        const discountRatio = subtotal.value > 0 ? totalDiscount.value / subtotal.value : 0;

        return round2(
            lines.value.reduce((sum, line) => {
                if (!line.is_taxable) {
                    return sum;
                }
                const lineBase = computeLineSubtotal(line);
                const discountedBase = Math.max(0, lineBase * (1 - discountRatio));
                return sum + round2(discountedBase * (rate / 100));
            }, 0),
        );
    });

    const grandTotal = computed(() =>
        Math.max(0, round2(subtotal.value - manualDiscount.value - pointsDiscount.value + taxAmount.value)),
    );

    const totalUnits = computed(() =>
        lines.value.reduce((sum, line) => sum + Number(line.quantity || 1), 0),
    );

    const estimatedPointsEarned = computed(() => Math.max(0, Math.floor(grandTotal.value)));

    const cartSummaryByProduct = computed(() => {
        const summary = new Map();
        for (const line of lines.value) {
            if (!line.product_id) {
                continue;
            }
            const prev = summary.get(line.product_id) || {
                quantity: 0,
                weight: 0,
                unit_type: line.unit_type || 'each',
            };
            prev.quantity += Number(line.quantity || 1);
            if (line.unit_type !== 'each' && line.weight) {
                prev.weight = round3(prev.weight + Number(line.weight) * Number(line.quantity || 1));
            }
            summary.set(line.product_id, prev);
        }
        return summary;
    });

    const addUnitProduct = (product, quantity = 1) => {
        const qtyToAdd = Math.max(1, Math.floor(Number(quantity || 1)));
        const existing = lines.value.find(
            (line) => line.product_id === product.id && (line.unit_type || 'each') === 'each',
        );

        if (existing) {
            existing.quantity += qtyToAdd;
            return existing;
        }

        const newLine = {
            key: `line-${Date.now()}-${lineCounter++}`,
            product_id: product.id,
            name: product.name,
            sku: product.sku || product.barcode || null,
            size: product.size_main || 'Each',
            unit_type: 'each',
            unit_price: round2(product.price),
            quantity: qtyToAdd,
            weight: null,
            is_taxable: Boolean(product.is_taxable),
            image: product.image || null,
        };

        lines.value.push(newLine);
        return newLine;
    };

    const addWeighedProduct = (product, weight, quantity = 1) => {
        const cleanWeight = round3(weight);
        if (cleanWeight <= 0) {
            return null;
        }

        const unitType = product.unit_type === 'kg' ? 'kg' : 'lb';
        const newLine = {
            key: `line-${Date.now()}-${lineCounter++}`,
            product_id: product.id,
            name: product.name,
            sku: product.sku || product.barcode || null,
            size: `${cleanWeight} ${unitType}`,
            unit_type: unitType,
            unit_price: round2(product.price),
            quantity: Math.max(1, Math.floor(Number(quantity || 1))),
            weight: cleanWeight,
            is_taxable: Boolean(product.is_taxable),
            image: product.image || null,
        };

        lines.value.push(newLine);
        return newLine;
    };

    const addCustomItem = ({ name, unit_price, quantity = 1, is_taxable = false }) => {
        const cleanName = String(name || '').trim();
        const cleanPrice = round2(unit_price);

        if (!cleanName || cleanPrice <= 0) {
            return null;
        }

        const newLine = {
            key: `custom-${Date.now()}-${lineCounter++}`,
            product_id: null,
            name: cleanName,
            sku: 'CUSTOM',
            size: 'Custom Item',
            unit_type: 'each',
            unit_price: cleanPrice,
            quantity: Math.max(1, Math.floor(Number(quantity || 1))),
            weight: null,
            is_taxable: Boolean(is_taxable),
            image: null,
        };

        lines.value.push(newLine);
        return newLine;
    };

    const incrementLine = (key) => {
        const target = lines.value.find((line) => line.key === key);
        if (target) {
            target.quantity += 1;
        }
    };

    const decrementLine = (key) => {
        const index = lines.value.findIndex((line) => line.key === key);
        if (index === -1) {
            return;
        }

        if (lines.value[index].quantity <= 1) {
            lines.value.splice(index, 1);
        } else {
            lines.value[index].quantity -= 1;
        }
    };

    const setLineQuantity = (key, nextQuantity) => {
        const index = lines.value.findIndex((line) => line.key === key);
        if (index === -1) {
            return;
        }

        const parsed = Math.floor(Number(nextQuantity || 0));
        if (parsed <= 0) {
            lines.value.splice(index, 1);
        } else {
            lines.value[index].quantity = Math.min(9999, parsed);
        }
    };

    const updateLineWeight = (key, weight) => {
        const target = lines.value.find((line) => line.key === key);
        const cleanWeight = round3(weight);
        if (target && cleanWeight > 0) {
            target.weight = cleanWeight;
            target.size = `${cleanWeight} ${target.unit_type}`;
        }
    };

    const removeLine = (key) => {
        lines.value = lines.value.filter((line) => line.key !== key);
    };

    const attachCustomer = (customerRecord) => {
        customer.value = customerRecord ? { ...customerRecord } : null;
        pointsBlocksToRedeem.value = 0;
    };

    const detachCustomer = () => {
        customer.value = null;
        pointsBlocksToRedeem.value = 0;
    };

    const setManualDiscount = (type, value) => {
        discountType.value = ['fixed', 'percent'].includes(type) ? type : 'none';
        discountValue.value = discountType.value === 'none' ? 0 : Math.max(0, Number(value || 0));
    };

    const clearManualDiscount = () => {
        discountType.value = 'none';
        discountValue.value = 0;
    };

    const setPointsBlocks = (blocks) => {
        pointsBlocksToRedeem.value = Math.max(0, Math.floor(Number(blocks || 0)));
    };

    const clearSale = () => {
        lines.value = [];
        customer.value = null;
        discountType.value = 'none';
        discountValue.value = 0;
        pointsBlocksToRedeem.value = 0;
        orderNotes.value = '';
    };

    const parkCurrentSale = (label = '') => {
        if (lines.value.length === 0) {
            return false;
        }

        const snapshot = {
            id: `parked-${Date.now()}`,
            label:
                String(label || '').trim() ||
                customer.value?.name ||
                `Counter Hold #${parkedSales.value.length + 1}`,
            parkedAt: new Date().toISOString(),
            totalPreview: grandTotal.value,
            itemCount: totalUnits.value,
            lines: lines.value.map((line) => ({ ...line })),
            customer: customer.value ? { ...customer.value } : null,
            discountType: discountType.value,
            discountValue: discountValue.value,
            pointsBlocksToRedeem: pointsBlocksToRedeem.value,
            orderNotes: orderNotes.value,
        };

        parkedSales.value = [snapshot, ...parkedSales.value];
        persistParkedSales(parkedSales.value);
        clearSale();
        return true;
    };

    const resumeParkedSale = (parkedId) => {
        const target = parkedSales.value.find((item) => item.id === parkedId);
        if (!target) {
            return false;
        }

        lines.value = (target.lines || []).map((line) => ({ ...line }));
        customer.value = target.customer ? { ...target.customer } : null;
        discountType.value = target.discountType || 'none';
        discountValue.value = Number(target.discountValue || 0);
        pointsBlocksToRedeem.value = Number(target.pointsBlocksToRedeem || 0);
        orderNotes.value = target.orderNotes || '';

        parkedSales.value = parkedSales.value.filter((item) => item.id !== parkedId);
        persistParkedSales(parkedSales.value);
        return true;
    };

    const deleteParkedSale = (parkedId) => {
        parkedSales.value = parkedSales.value.filter((item) => item.id !== parkedId);
        persistParkedSales(parkedSales.value);
    };

    const buildOrderPayload = ({
        payment_method,
        cash_amount = null,
        terminal_amount = null,
        cash_tendered = null,
        payment_reference = null,
        idempotency_key = null,
    }) => ({
        idempotency_key: idempotency_key || null,
        expected_total: grandTotal.value,
        items: lines.value.map((line) => ({
            product_id: line.product_id,
            name: line.product_id ? undefined : line.name,
            unit_price: line.product_id ? undefined : line.unit_price,
            quantity: line.quantity,
            weight: line.unit_type !== 'each' ? line.weight : null,
            is_taxable: line.is_taxable,
        })),
        customer_id: customer.value?.id || null,
        customer_name: customer.value?.name || null,
        customer_phone: customer.value?.phone || null,
        discount_type: discountType.value,
        discount_value: discountType.value === 'none' ? 0 : discountValue.value,
        points_to_redeem: effectivePointsRedeemed.value,
        payment_method,
        cash_amount,
        terminal_amount,
        cash_tendered,
        payment_reference: payment_reference || null,
        notes: orderNotes.value || null,
    });

    return {
        productsById,
        catalogRevision,
        taxRate,
        loyaltyConfig,
        lines,
        customer,
        discountType,
        discountValue,
        pointsBlocksToRedeem,
        orderNotes,
        parkedSales,
        subtotal,
        manualDiscount,
        remainingAfterManualDiscount,
        maxCustomerRedeemableBlocks,
        effectivePointsBlocks,
        effectivePointsRedeemed,
        pointsDiscount,
        totalDiscount,
        taxAmount,
        grandTotal,
        totalUnits,
        estimatedPointsEarned,
        cartSummaryByProduct,
        hydrateRegisterConfig,
        hydrateCatalog,
        upsertProducts,
        invalidateCatalogCache,
        syncCartProductPrices,
        applyStockUpdates,
        findByScanCode,
        resolveScanCode,
        fetchCatalogPage,
        computeLineSubtotal,
        addUnitProduct,
        addWeighedProduct,
        addCustomItem,
        incrementLine,
        decrementLine,
        setLineQuantity,
        updateLineWeight,
        removeLine,
        attachCustomer,
        detachCustomer,
        setManualDiscount,
        clearManualDiscount,
        setPointsBlocks,
        clearSale,
        parkCurrentSale,
        resumeParkedSale,
        deleteParkedSale,
        buildOrderPayload,
    };
});

