import { defineStore } from 'pinia';
import { ref, computed, watch } from 'vue';

export const useStore = defineStore('masalaStore', () => {
  // Navigation & Location (Store Pickup Only)
  const deliveryMode = ref('Store Pickup');
  const selectedStore = ref('Main St. store');
  const pickupLocation = ref('214 Main St. · Masala Mart Express');
  const readyTime = ref('ready in 1 hr');
  const searchQuery = ref('');

  // Products Database (Dynamically populated from Laravel SQLite DB)
  const products = ref([]);

  // Impulse Items for Cart (Dynamically derived from database products under $3.50)
  const impulseItems = computed(() => {
    return products.value
      .filter(p => Number(p.price) <= 2.00 || (Number(p.price) <= 3.50 && ['produce', 'snacks'].includes(p.category)))
      .slice(0, 4);
  });

  // Recipe Kits
  const recipeKits = computed(() => {
    return products.value.filter(p => p.isRecipeKit);
  });

  // User-scoped data (connected to active user account)
  const currentUserId = ref(null);
  const isInitialized = ref(false);
  const cart = ref([]);
  const rewardPoints = ref(0);

  // Storage helper
  function loadUserStorage(key, fallback) {
    if (typeof window === 'undefined') return fallback;
    try {
      const raw = localStorage.getItem(key);
      if (raw !== null) {
        const parsed = JSON.parse(raw);
        return parsed !== null ? parsed : fallback;
      }
    } catch (e) {
      console.error(`Failed to load ${key} from storage:`, e);
    }
    return fallback;
  }

  function saveUserStorage(key, val) {
    if (typeof window === 'undefined') return;
    try {
      localStorage.setItem(key, JSON.stringify(val));
    } catch (e) {
      console.error(`Failed to save ${key} to storage:`, e);
    }
  }

  // Synchronize cart & user data with authenticated user or guest
  function syncUser(user) {
    const newUserId = user ? user.id : null;

    if (currentUserId.value === newUserId && isInitialized.value) {
      if (newUserId && user && typeof user.reward_points !== 'undefined' && user.reward_points !== null) {
        rewardPoints.value = Number(user.reward_points);
      }
      return;
    }

    const prevUserId = currentUserId.value;
    currentUserId.value = newUserId;

    if (newUserId) {
      // User is logged in:
      const userCart = loadUserStorage(`masala_cart_user_${newUserId}`, []);

      if (prevUserId === null) {
        // Transition from guest: merge guest cart items into user's cart
        const guestCart = loadUserStorage('masala_cart_guest', []);
        if (guestCart.length > 0) {
          guestCart.forEach(gItem => {
            const existing = userCart.find(u => u.id === gItem.id);
            if (existing) {
              existing.quantity += gItem.quantity;
            } else {
              userCart.push(gItem);
            }
          });
          saveUserStorage('masala_cart_guest', []);
        }
      }
      cart.value = userCart;
      saveUserStorage(`masala_cart_user_${newUserId}`, cart.value);

      // Load user-specific points from authoritative server value (no localStorage)
      if (user && typeof user.reward_points !== 'undefined' && user.reward_points !== null) {
        rewardPoints.value = Number(user.reward_points);
      } else {
        rewardPoints.value = 0;
      }
    } else {
      // Guest mode
      cart.value = loadUserStorage('masala_cart_guest', []);
      rewardPoints.value = 0;
    }

    isInitialized.value = true;
  }

  // Initial load for client environment
  if (typeof window !== 'undefined') {
    cart.value = loadUserStorage('masala_cart_guest', []);
    isInitialized.value = true;
  }

  // Auto-persist changes (only cart items in localStorage)
  watch(cart, (newVal) => {
    if (isInitialized.value) {
      const key = currentUserId.value ? `masala_cart_user_${currentUserId.value}` : 'masala_cart_guest';
      saveUserStorage(key, newVal);
    }
  }, { deep: true });

  // Global stock notification toast state
  const stockToast = ref({
    visible: false,
    title: '',
    message: '',
    type: 'warning', // 'error' | 'warning' | 'info'
  });
  let stockToastTimer = null;

  function showStockToast(title, message, type = 'warning') {
    if (stockToastTimer) {
      clearTimeout(stockToastTimer);
    }
    stockToast.value = {
      visible: true,
      title,
      message,
      type,
    };
    stockToastTimer = setTimeout(() => {
      stockToast.value.visible = false;
    }, 4200);
  }

  function dismissStockToast() {
    if (stockToastTimer) {
      clearTimeout(stockToastTimer);
    }
    stockToast.value.visible = false;
  }

  function isBadgeOutOfStock(badge) {
    return typeof badge === 'string' && badge.toLowerCase().includes('out');
  }

  // Set / merge products from Laravel database in O(N+M)
  function setProducts(dbProducts) {
    const list = Array.isArray(dbProducts)
      ? dbProducts
      : (Array.isArray(dbProducts?.data) ? dbProducts.data : []);
    if (list.length === 0) return;

    const idMap = new Map();
    const slugMap = new Map();
    products.value.forEach((p, idx) => {
      if (!p.isRecipeKit) {
        if (p.id != null) idMap.set(p.id, idx);
        if (p.slug) slugMap.set(p.slug, idx);
      }
    });

    list.forEach(item => {
      if (!item) return;
      const existingIdx = (item.id != null && idMap.has(item.id))
        ? idMap.get(item.id)
        : (item.slug && slugMap.has(item.slug) ? slugMap.get(item.slug) : -1);
      const prev = (existingIdx !== -1 && existingIdx !== undefined) ? products.value[existingIdx] : null;

      const parsedStock = (item.stock !== undefined && item.stock !== null && !Number.isNaN(Number(item.stock)))
        ? Number(item.stock)
        : (prev?.stock ?? null);

      const formatted = {
        id: item.id ?? prev?.id,
        slug: item.slug ?? prev?.slug,
        name: item.name ?? prev?.name,
        subtitleTag: item.subtitle_tag || item.subtitleTag || prev?.subtitleTag,
        category: item.category ?? prev?.category,
        categoryTitle: item.category_title || item.categoryTitle || prev?.categoryTitle,
        price: item.price !== undefined ? Number(item.price) : Number(prev?.price || 0),
        originalPrice: Number(item.original_price || item.originalPrice || item.price || prev?.originalPrice || 0),
        unitPrice: item.unit_price || item.unitPrice || prev?.unitPrice,
        stock: parsedStock,
        stockBadge: item.stock_badge ?? item.stockBadge ?? prev?.stockBadge ?? null,
        photoLabel: item.photo_label || item.photoLabel || prev?.photoLabel,
        image: item.image || prev?.image,
        sizeMain: item.size_main || item.sizeMain || item.size || prev?.sizeMain,
        sizeSub: item.size_sub || item.sizeSub || prev?.sizeSub,
        size: item.size_main || item.sizeMain || item.size || prev?.size,
        freshnessLine: item.freshness_line || item.freshnessLine || prev?.freshnessLine,
        description: item.description ?? prev?.description,
        servings: item.servings ?? prev?.servings,
        cookingTime: item.cooking_time || item.cookingTime || prev?.cookingTime,
        isRecipeKit: !!(item.is_recipe_kit || item.isRecipeKit),
        buyAgain: !!(item.buy_again || item.buyAgain || prev?.buyAgain),
        label: item.photo_label || item.photoLabel || item.slug || prev?.label,
        frequentlyBoughtTogether: item.frequently_bought_together || item.frequentlyBoughtTogether || prev?.frequentlyBoughtTogether || [],
        recipeIngredients: item.recipe_ingredients || item.recipeIngredients || prev?.recipeIngredients || [],
        kitProducts: item.products || item.kitProducts || prev?.kitProducts || [],
      };

      if (existingIdx !== -1 && existingIdx !== undefined) {
        products.value[existingIdx] = { ...prev, ...formatted };
      } else {
        const newIdx = products.value.length;
        products.value.push(formatted);
        if (formatted.id != null) idMap.set(formatted.id, newIdx);
        if (formatted.slug) slugMap.set(formatted.slug, newIdx);
      }

      if (Array.isArray(formatted.frequentlyBoughtTogether) && formatted.frequentlyBoughtTogether.length > 0) {
        formatted.frequentlyBoughtTogether.forEach(fbt => {
          if (fbt && (fbt.id || fbt.slug) && fbt.stock !== undefined) {
            const fbtIdx = (fbt.id != null && idMap.has(fbt.id))
              ? idMap.get(fbt.id)
              : (fbt.slug && slugMap.has(fbt.slug) ? slugMap.get(fbt.slug) : -1);
            if (fbtIdx !== -1 && fbtIdx !== undefined) {
              products.value[fbtIdx].stock = Number(fbt.stock);
              if (fbt.stock_badge !== undefined) products.value[fbtIdx].stockBadge = fbt.stock_badge;
            } else {
              const idx = products.value.length;
              products.value.push({
                id: fbt.id,
                slug: fbt.slug,
                name: fbt.name,
                price: Number(fbt.price || 0),
                originalPrice: Number(fbt.original_price || fbt.price || 0),
                sizeMain: fbt.size || fbt.size_main || '',
                size: fbt.size || fbt.size_main || '',
                image: fbt.image,
                category: fbt.category,
                stock: Number(fbt.stock),
                stockBadge: fbt.stock_badge ?? null,
                isRecipeKit: false,
              });
              if (fbt.id != null) idMap.set(fbt.id, idx);
              if (fbt.slug) slugMap.set(fbt.slug, idx);
            }
          }
        });
      }
    });
  }

  // Set / merge Recipe Kits and their ingredient products
  function setRecipeKits(dbKits) {
    const list = Array.isArray(dbKits)
      ? dbKits
      : (Array.isArray(dbKits?.data) ? dbKits.data : (dbKits && dbKits.id ? [dbKits] : []));
    if (list.length === 0) return;

    const ingredientProducts = [];
    list.forEach(kit => {
      if (!kit) return;
      if (Array.isArray(kit.products) && kit.products.length > 0) {
        ingredientProducts.push(...kit.products);
      }

      const existingIdx = products.value.findIndex(p =>
        Boolean(p.isRecipeKit) && (p.id === kit.id || p.slug === kit.slug || p.id === `kit-${kit.id}`)
      );

      const formattedKit = {
        id: kit.id,
        slug: kit.slug,
        name: kit.name,
        price: Number(kit.price || 0),
        originalPrice: Number(kit.original_price || kit.originalPrice || kit.price || 0),
        image: kit.image,
        sizeMain: kit.servings || 'Recipe Kit',
        size: kit.servings || 'Recipe Kit',
        isRecipeKit: true,
        isActive: kit.is_active !== undefined ? Boolean(kit.is_active) : true,
        type: 'recipe-kit',
        kitProducts: Array.isArray(kit.products) ? kit.products : [],
      };

      if (existingIdx !== -1) {
        products.value[existingIdx] = { ...products.value[existingIdx], ...formattedKit };
      } else {
        products.value.push(formattedKit);
      }
    });

    if (ingredientProducts.length > 0) {
      setProducts(ingredientProducts);
    }
  }

  // Resolve ID whether passed as number, string slug, or object
  function resolveProductId(input) {
    if (typeof input === 'object' && input !== null) {
      if (input.type === 'recipe-kit' || input.isRecipeKit) {
        return input.slug || input.id;
      }
      if (input.id) return resolveProductId(input.id);
      if (input.slug) return resolveProductId(input.slug);
    }
    if (typeof input === 'number') return input;
    if (typeof input === 'string') {
      if (input.startsWith('kit-')) {
        return input;
      }
      const num = Number(input);
      if (!isNaN(num) && num > 0) {
        const prodById = products.value.find(p => !p.isRecipeKit && p.id === num);
        if (prodById) return prodById.id;
      }

      const prod = products.value.find(p => !p.isRecipeKit && (p.slug === input || p.label === input || String(p.id) === input));
      if (prod) return prod.id;
    }
    return input;
  }

  function findCatalogProduct(itemOrId, isKit = false) {
    const obj = (typeof itemOrId === 'object' && itemOrId !== null) ? itemOrId : null;
    const rawId = obj ? (obj.id ?? obj.slug) : itemOrId;
    const rawSlug = obj?.slug || (typeof rawId === 'string' ? rawId : null);
    const normalizedKitId = typeof rawId === 'string' && rawId.startsWith('kit-') ? Number(rawId.slice(4)) : rawId;

    if (isKit) {
      return products.value.find(p =>
        Boolean(p.isRecipeKit) && (
          p.id === normalizedKitId ||
          p.slug === rawSlug ||
          p.slug === rawId ||
          `kit-${p.id}` === String(rawId)
        )
      ) || null;
    }

    const resolvedId = resolveProductId(itemOrId);
    return products.value.find(p =>
      !p.isRecipeKit && (p.id === resolvedId || p.slug === rawSlug || p.slug === String(resolvedId))
    ) || null;
  }

  // Get available stock for a product or recipe kit
  function getAvailableStock(itemOrId) {
    if (itemOrId === null || itemOrId === undefined) return Infinity;
    const obj = (typeof itemOrId === 'object' && itemOrId !== null) ? itemOrId : null;
    const rawId = obj ? (obj.id ?? obj.slug) : itemOrId;
    const isKit = Boolean(
      obj?.type === 'recipe-kit' ||
      obj?.isRecipeKit ||
      (typeof rawId === 'string' && rawId.startsWith('kit-'))
    );

    const prod = findCatalogProduct(itemOrId, isKit);

    if (isKit) {
      if (prod?.isActive === false || obj?.is_active === false || obj?.isActive === false) {
        return 0;
      }
      const kitProducts = obj?.products || obj?.kitProducts || prod?.kitProducts || [];
      if (Array.isArray(kitProducts) && kitProducts.length > 0) {
        let maxKits = Infinity;
        for (const ing of kitProducts) {
          const needed = Number(ing.pivot?.quantity || 1);
          const ingCatalog = findCatalogProduct(ing, false);
          const ingBadge = ingCatalog?.stockBadge ?? ing.stock_badge ?? ing.stockBadge;
          if (isBadgeOutOfStock(ingBadge)) {
            return 0;
          }
          const ingStock = (ingCatalog && ingCatalog.stock !== null && ingCatalog.stock !== undefined)
            ? Number(ingCatalog.stock)
            : (ing.stock !== null && ing.stock !== undefined ? Number(ing.stock) : null);
          if (ingStock !== null && !Number.isNaN(ingStock)) {
            if (ingStock < needed) return 0;
            maxKits = Math.min(maxKits, Math.floor(ingStock / needed));
          }
        }
        return maxKits;
      }
      return Infinity;
    }

    const badge = prod?.stockBadge ?? obj?.stock_badge ?? obj?.stockBadge;
    if (isBadgeOutOfStock(badge)) {
      return 0;
    }

    const rawStock = (prod && prod.stock !== null && prod.stock !== undefined)
      ? prod.stock
      : (obj && obj.stock !== null && obj.stock !== undefined ? obj.stock : null);

    if (rawStock !== null && rawStock !== undefined && !Number.isNaN(Number(rawStock))) {
      return Math.max(0, Number(rawStock));
    }

    return Infinity;
  }

  function isOutOfStock(itemOrId) {
    return getAvailableStock(itemOrId) <= 0;
  }

  function canAddMore(itemOrId, additionalQty = 1) {
    const avail = getAvailableStock(itemOrId);
    if (avail <= 0) return false;
    const currentQty = getQuantity(itemOrId);
    return (currentQty + additionalQty) <= avail;
  }

  // Computed Cart Items
  const cartItems = computed(() => {
    return cart.value.map(item => {
      const isKit = item.type === 'recipe-kit' || Boolean(item.isRecipeKit) || (typeof item.id === 'string' && item.id.startsWith('kit-'));
      const prod = findCatalogProduct(item, isKit);

      const price = prod ? prod.price : Number(item.price || 0);
      const name = prod ? prod.name : (item.name || 'Grocery Item');
      const image = prod ? prod.image : (item.image || '/images/products/atta.jpg');
      const size = prod ? (prod.sizeMain || prod.size) : (item.size || item.weight || '');
      const slug = prod ? prod.slug : (item.slug || String(item.id));
      const category = prod ? prod.category : (item.category || (isKit ? 'recipe-kit' : 'grocery'));
      const originalPrice = prod ? prod.originalPrice : Number(item.originalPrice || price);
      const unitCost = price;

      const availableStock = getAvailableStock(item);
      const itemIsOutOfStock = availableStock <= 0;
      const itemExceedsStock = !itemIsOutOfStock && availableStock !== Infinity && item.quantity > availableStock;
      const hasStockIssue = itemIsOutOfStock || itemExceedsStock;

      let outOfStockIngredients = [];
      if (isKit) {
        const kitProducts = item.kitProducts || prod?.kitProducts || [];
        outOfStockIngredients = kitProducts
          .filter(ing => {
            const needed = (Number(ing.pivot?.quantity || 1)) * item.quantity;
            const ingAvail = getAvailableStock(ing);
            return ingAvail < needed;
          })
          .map(ing => ing.name);
      }

      let stockIssueLabel = null;
      if (itemIsOutOfStock) {
        stockIssueLabel = (isKit && outOfStockIngredients.length > 0)
          ? `Ingredient Out of Stock (${outOfStockIngredients.join(', ')})`
          : 'Out of Stock';
      } else if (itemExceedsStock) {
        stockIssueLabel = `Only ${availableStock} left in stock`;
      }

      return {
        id: item.id,
        slug,
        type: isKit ? 'recipe-kit' : 'product',
        isRecipeKit: isKit,
        name,
        image,
        category,
        quantity: item.quantity,
        unitCost,
        price: unitCost,
        originalPrice,
        weight: size,
        size,
        total: Number((unitCost * item.quantity).toFixed(2)),
        stock: availableStock === Infinity ? null : availableStock,
        availableStock,
        isOutOfStock: itemIsOutOfStock,
        exceedsStock: itemExceedsStock,
        hasStockIssue,
        outOfStockIngredients,
        stockIssueLabel,
      };
    });
  });

  const stockIssues = computed(() => {
    return cartItems.value.filter(item => item.hasStockIssue);
  });

  const hasStockIssues = computed(() => {
    return stockIssues.value.length > 0;
  });

  function resolveStockIssues() {
    const nextCart = [];
    let removedCount = 0;
    let adjustedCount = 0;

    cart.value.forEach(item => {
      const avail = getAvailableStock(item);
      if (avail <= 0) {
        removedCount += 1;
      } else if (avail !== Infinity && item.quantity > avail) {
        nextCart.push({
          ...item,
          quantity: avail,
        });
        adjustedCount += 1;
      } else {
        nextCart.push(item);
      }
    });

    cart.value = nextCart;

    if (removedCount > 0 || adjustedCount > 0) {
      const parts = [];
      if (removedCount > 0) parts.push(`${removedCount} out-of-stock ${removedCount === 1 ? 'item' : 'items'} removed`);
      if (adjustedCount > 0) parts.push(`${adjustedCount} ${adjustedCount === 1 ? 'item' : 'items'} adjusted to available stock`);
      showStockToast('Cart Updated', parts.join(' · '), 'info');
    }
  }

  const totalItemCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0);
  });

  const subtotal = computed(() => {
    return Number(cartItems.value.reduce((sum, item) => sum + item.total, 0).toFixed(2));
  });

  const total = computed(() => {
    return subtotal.value;
  });

  // Store Pickup (Always 100% Free & Ready in 1 hr)
  const pickupFee = 0.0;
  const isPickupReady = computed(() => true);
  const freeDeliveryThreshold = 0.0;
  const amountToFreeDelivery = computed(() => 0.0);
  const freeDeliveryProgressPercent = computed(() => 100);

  function getQuantity(productId) {
    const obj = (typeof productId === 'object' && productId !== null) ? productId : null;
    const isKit = Boolean(
      obj?.type === 'recipe-kit' ||
      obj?.isRecipeKit ||
      (typeof productId === 'string' && productId.startsWith('kit-'))
    );
    const id = isKit ? (obj ? (obj.slug || obj.id) : productId) : resolveProductId(productId);
    const item = cart.value.find(i => i.id === id || (obj?.slug && i.slug === obj.slug));
    return item ? item.quantity : 0;
  }

  function getItemQuantity(productId) {
    return getQuantity(productId);
  }

  function addToCart(itemOrId, count = 1) {
    let id = itemOrId;
    let qty = count;
    const itemObj = (typeof itemOrId === 'object' && itemOrId !== null) ? itemOrId : null;
    const isKit = itemObj?.type === 'recipe-kit' || Boolean(itemObj?.isRecipeKit) || (typeof id === 'string' && id.startsWith('kit-'));
    const itemType = isKit ? 'recipe-kit' : 'product';

    if (itemObj) {
      id = isKit ? (itemObj.slug || itemObj.id) : (itemObj.id || itemObj.slug);
      if (itemObj.quantity) qty = itemObj.quantity;
    }

    const resolvedId = isKit ? id : resolveProductId(id);
    const prod = findCatalogProduct(itemObj || resolvedId, isKit);
    const itemName = itemObj?.name || prod?.name || 'This item';

    const availableStock = getAvailableStock(itemObj || resolvedId);
    if (availableStock <= 0) {
      showStockToast('Out of Stock', `${itemName} is currently out of stock and cannot be added to your cart.`, 'error');
      return false;
    }

    const existing = cart.value.find(i =>
      (i.id === resolvedId || (isKit && prod && (i.id === prod.slug || i.id === prod.id || i.id === `kit-${prod.id}`))) &&
      ((i.type || (i.isRecipeKit ? 'recipe-kit' : 'product')) === itemType)
    );

    const currentQty = existing ? existing.quantity : 0;
    if (availableStock !== Infinity && (currentQty + qty) > availableStock) {
      const allowedToAdd = Math.max(0, availableStock - currentQty);
      if (allowedToAdd <= 0) {
        showStockToast('Maximum Stock Reached', `Only ${availableStock} of ${itemName} available in stock (already in your cart).`, 'warning');
        return false;
      }
      qty = allowedToAdd;
      showStockToast('Stock Limit Applied', `Only ${availableStock} of ${itemName} available in stock. Added ${allowedToAdd}.`, 'warning');
    }

    if (existing) {
      existing.quantity += qty;
      if (itemObj?.stock !== undefined) existing.stock = Number(itemObj.stock);
      if (itemObj?.stock_badge !== undefined || itemObj?.stockBadge !== undefined) {
        existing.stockBadge = itemObj.stock_badge ?? itemObj.stockBadge;
      }
    } else {
      cart.value.push({
        id: resolvedId,
        slug: itemObj?.slug || prod?.slug || String(resolvedId),
        type: itemType,
        isRecipeKit: isKit,
        name: itemObj?.name || prod?.name || 'Grocery Item',
        price: Number(itemObj?.price !== undefined ? itemObj.price : (prod?.price || 0)),
        originalPrice: Number(itemObj?.originalPrice || prod?.originalPrice || itemObj?.price || 0),
        image: itemObj?.image || prod?.image || '/images/products/atta.jpg',
        size: itemObj?.size || itemObj?.weight || prod?.sizeMain || '',
        stock: (itemObj?.stock !== undefined && itemObj?.stock !== null) ? Number(itemObj.stock) : (prod?.stock ?? null),
        stockBadge: itemObj?.stock_badge ?? itemObj?.stockBadge ?? prod?.stockBadge ?? null,
        kitProducts: isKit ? (itemObj?.products || itemObj?.kitProducts || prod?.kitProducts || []) : undefined,
        quantity: qty,
      });
    }

    return true;
  }

  function addImpulseItem(impulseId) {
    return addToCart(impulseId, 1);
  }

  function removeFromCart(productId) {
    const id = resolveProductId(productId);
    const index = cart.value.findIndex(i => i.id === id || i.id === productId || i.slug === productId);
    if (index !== -1) {
      if (cart.value[index].quantity > 1) {
        cart.value[index].quantity -= 1;
      } else {
        cart.value.splice(index, 1);
      }
    }
  }

  function deleteItem(productId) {
    const id = resolveProductId(productId);
    cart.value = cart.value.filter(i => i.id !== id && i.id !== productId && i.slug !== productId);
  }

  function clearCart() {
    cart.value = [];
  }

  function getProductBySlug(slug) {
    if (!slug) return null;
    return products.value.find(p => p.slug === slug || String(p.id) === String(slug)) || null;
  }

  /**
   * Fetch live stock and pricing ONLY for the items currently inside the customer's cart.
   */
  async function refreshCartStock() {
    if (!cart.value || cart.value.length === 0) return;

    const ids = Array.from(
      new Set(
        cart.value
          .map(item => {
            const isKit = item.type === 'recipe-kit' || Boolean(item.isRecipeKit) || (typeof item.id === 'string' && item.id.startsWith('kit-'));
            const identifier = String(item.id ?? item.slug ?? '').trim();
            if (!identifier) return null;
            if (isKit) {
              return identifier.startsWith('kit-') ? identifier : `kit-${item.slug || identifier}`;
            }
            return identifier;
          })
          .filter(Boolean)
      )
    );

    if (ids.length === 0) return;

    try {
      const { default: axios } = await import('axios');
      const { data } = await axios.post('/cart/stock-check', { ids });
      if (Array.isArray(data?.products) && data.products.length > 0) {
        setProducts(data.products);
      }
      if (Array.isArray(data?.recipeKits) && data.recipeKits.length > 0) {
        setRecipeKits(data.recipeKits);
      }
      // Keep cart.value prices in sync with refreshed catalog prices
      cart.value.forEach(item => {
        const isKit = item.type === 'recipe-kit' || Boolean(item.isRecipeKit) || (typeof item.id === 'string' && item.id.startsWith('kit-'));
        const prod = findCatalogProduct(item, isKit);
        if (prod && prod.price !== undefined) {
          item.price = Number(prod.price);
        }
      });
    } catch {
      // Non-blocking fallback
    }
  }

  return {
    deliveryMode,
    pickupLocation,
    pickupFee,
    isPickupReady,
    selectedStore,
    readyTime,
    searchQuery,
    products,
    impulseItems,
    recipeKits,
    cart,
    cartItems,
    stockIssues,
    hasStockIssues,
    resolveStockIssues,
    refreshCartStock,
    stockToast,
    showStockToast,
    dismissStockToast,
    getAvailableStock,
    isOutOfStock,
    canAddMore,
    totalItemCount,
    subtotal,
    total,
    rewardPoints,
    freeDeliveryThreshold,
    amountToFreeDelivery,
    freeDeliveryProgressPercent,
    getQuantity,
    getItemQuantity,
    addToCart,
    addImpulseItem,
    removeFromCart,
    deleteItem,
    clearCart,
    getProductBySlug,
    setProducts,
    setRecipeKits,
    syncUser,
    currentUserId,
    isInitialized,
  };
});
