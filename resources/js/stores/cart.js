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

  // User-scoped data (connected to active user account, persisted in localStorage)
  const currentUserId = ref(null);
  const isInitialized = ref(false);
  const cart = ref([]);
  const pastOrders = ref([]);
  const masalaPoints = ref(0);

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

      // Load user-specific points and past orders
      masalaPoints.value = loadUserStorage(`masala_points_user_${newUserId}`, 0);
      pastOrders.value = loadUserStorage(`masala_orders_user_${newUserId}`, []);
    } else {
      // Guest mode
      cart.value = loadUserStorage('masala_cart_guest', []);
      masalaPoints.value = 0;
      pastOrders.value = [];
    }

    isInitialized.value = true;
  }

  // Initial load for client environment
  if (typeof window !== 'undefined') {
    cart.value = loadUserStorage('masala_cart_guest', []);
    isInitialized.value = true;
  }

  // Auto-persist changes
  watch(cart, (newVal) => {
    if (isInitialized.value) {
      const key = currentUserId.value ? `masala_cart_user_${currentUserId.value}` : 'masala_cart_guest';
      saveUserStorage(key, newVal);
    }
  }, { deep: true });

  watch(masalaPoints, (newVal) => {
    if (currentUserId.value) {
      saveUserStorage(`masala_points_user_${currentUserId.value}`, newVal);
    }
  });

  watch(pastOrders, (newVal) => {
    if (currentUserId.value) {
      saveUserStorage(`masala_orders_user_${currentUserId.value}`, newVal);
    }
  }, { deep: true });

  // Set / merge products from Laravel database in O(N+M)
  function setProducts(dbProducts) {
    if (!Array.isArray(dbProducts) || dbProducts.length === 0) return;

    const idMap = new Map();
    const slugMap = new Map();
    products.value.forEach((p, idx) => {
      if (p.id != null) idMap.set(p.id, idx);
      if (p.slug) slugMap.set(p.slug, idx);
    });

    dbProducts.forEach(item => {
      const formatted = {
        id: item.id,
        slug: item.slug,
        name: item.name,
        subtitleTag: item.subtitle_tag || item.subtitleTag,
        category: item.category,
        categoryTitle: item.category_title || item.categoryTitle,
        price: Number(item.price),
        originalPrice: Number(item.original_price || item.originalPrice || item.price),
        unitPrice: item.unit_price || item.unitPrice,
        stockBadge: item.stock_badge || item.stockBadge,
        photoLabel: item.photo_label || item.photoLabel,
        image: item.image,
        sizeMain: item.size_main || item.sizeMain || item.size,
        sizeSub: item.size_sub || item.sizeSub,
        size: item.size_main || item.sizeMain || item.size,
        freshnessLine: item.freshness_line || item.freshnessLine,
        description: item.description,
        servings: item.servings,
        cookingTime: item.cooking_time || item.cookingTime,
        isRecipeKit: !!(item.is_recipe_kit || item.isRecipeKit),
        buyAgain: !!(item.buy_again || item.buyAgain),
        label: item.photo_label || item.photoLabel || item.slug,
        frequentlyBoughtTogether: item.frequently_bought_together || item.frequentlyBoughtTogether || [],
        recipeIngredients: item.recipe_ingredients || item.recipeIngredients || [],
      };

      const existingIdx = (item.id != null && idMap.has(item.id))
        ? idMap.get(item.id)
        : (item.slug && slugMap.has(item.slug) ? slugMap.get(item.slug) : -1);

      if (existingIdx !== -1 && existingIdx !== undefined) {
        products.value[existingIdx] = { ...products.value[existingIdx], ...formatted };
      } else {
        const newIdx = products.value.length;
        products.value.push(formatted);
        if (formatted.id != null) idMap.set(formatted.id, newIdx);
        if (formatted.slug) slugMap.set(formatted.slug, newIdx);
      }
    });
  }

  // Resolve ID whether passed as number, string slug, or object
  function resolveProductId(input) {
    if (typeof input === 'object' && input !== null) {
      if (input.id) return resolveProductId(input.id);
      if (input.slug) return resolveProductId(input.slug);
    }
    if (typeof input === 'number') return input;
    if (typeof input === 'string') {
      const num = Number(input);
      if (!isNaN(num) && num > 0) {
        const prodById = products.value.find(p => p.id === num);
        if (prodById) return prodById.id;
      }

      const prod = products.value.find(p => p.slug === input || p.label === input || String(p.id) === input);
      if (prod) return prod.id;
    }
    return input;
  }

  // Computed Cart Items
  const cartItems = computed(() => {
    return cart.value.map(item => {
      const prod = products.value.find(p => p.id === item.id || p.slug === item.slug);

      const price = prod ? prod.price : Number(item.price || 0);
      const name = prod ? prod.name : (item.name || 'Grocery Item');
      const image = prod ? prod.image : (item.image || '/images/products/atta.jpg');
      const size = prod ? (prod.sizeMain || prod.size) : (item.size || item.weight || '');
      const slug = prod ? prod.slug : (item.slug || String(item.id));
      const category = prod ? prod.category : (item.category || 'grocery');
      const originalPrice = prod ? prod.originalPrice : Number(item.originalPrice || price);
      const unitCost = price;

      return {
        id: item.id,
        slug,
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
      };
    });
  });

  const totalItemCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0);
  });

  const rawSubtotal = computed(() => {
    const sum = cart.value.reduce((acc, item) => {
      const prod = products.value.find(p => p.id === item.id || p.slug === item.slug);
      const price = prod ? prod.price : Number(item.price || 0);
      return acc + (price * item.quantity);
    }, 0);
    return Number(sum.toFixed(2));
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
    const id = resolveProductId(productId);
    const item = cart.value.find(i => i.id === id);
    return item ? item.quantity : 0;
  }

  function getItemQuantity(productId) {
    return getQuantity(productId);
  }

  function addToCart(itemOrId, count = 1) {
    let id = itemOrId;
    let qty = count;
    const itemObj = (typeof itemOrId === 'object' && itemOrId !== null) ? itemOrId : null;

    if (itemObj) {
      id = itemObj.id || itemObj.slug;
      if (itemObj.quantity) qty = itemObj.quantity;
    }

    const resolvedId = resolveProductId(id);
    const existing = cart.value.find(i => i.id === resolvedId);

    if (existing) {
      existing.quantity += qty;
    } else {
      const prod = products.value.find(p => p.id === resolvedId || p.slug === id);
      cart.value.push({
        id: resolvedId,
        slug: itemObj?.slug || prod?.slug || String(resolvedId),
        name: itemObj?.name || prod?.name || 'Grocery Item',
        price: Number(itemObj?.price !== undefined ? itemObj.price : (prod?.price || 0)),
        originalPrice: Number(itemObj?.originalPrice || prod?.originalPrice || itemObj?.price || 0),
        image: itemObj?.image || prod?.image || '/images/products/atta.jpg',
        size: itemObj?.size || itemObj?.weight || prod?.sizeMain || '',
        quantity: qty,
      });
    }
  }

  function addImpulseItem(impulseId) {
    addToCart(impulseId, 1);
  }

  function removeFromCart(productId) {
    const id = resolveProductId(productId);
    const index = cart.value.findIndex(i => i.id === id);
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
    cart.value = cart.value.filter(i => i.id !== id);
  }

  function clearCart() {
    cart.value = [];
  }

  function reorderAll(orderId) {
    const order = pastOrders.value.find(o => o.id === orderId);
    if (order && Array.isArray(order.items)) {
      order.items.forEach(item => addToCart(item, item.quantity || 1));
    }
  }

  function getProductBySlug(slug) {
    if (!slug) return null;
    return products.value.find(p => p.slug === slug || String(p.id) === String(slug)) || null;
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
    pastOrders,
    cart,
    cartItems,
    totalItemCount,
    rawSubtotal,
    subtotal,
    total,
    masalaPoints,
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
    reorderAll,
    getProductBySlug,
    setProducts,
    syncUser,
    currentUserId,
    isInitialized,
  };
});
