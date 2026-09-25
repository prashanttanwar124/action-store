import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useStore = defineStore('masalaStore', () => {
  // Navigation & Location
  const deliveryMode = ref('Pickup');
  const selectedStore = ref('Main St. store');
  const readyTime = ref('ready in 1 hr');
  const searchQuery = ref('');

  // Products Database (Synchronized with Laravel SQLite DB)
  const products = ref([
    {
      id: 1,
      slug: 'atta',
      name: 'Chakki Atta',
      subtitleTag: '100% STONE GROUND WHOLE WHEAT',
      category: 'staples',
      categoryTitle: 'Staples · Flour',
      price: 18.99,
      originalPrice: 18.99,
      unitPrice: '$0.95 / lb',
      stockBadge: 'Staple · buy monthly',
      photoLabel: 'atta bag',
      image: '/images/products/atta.jpg',
      sizeMain: '20 lb',
      sizeSub: '9.07 kg · ~80 rotis',
      size: '20 lb',
      freshnessLine: 'Freshly milled batch · Zero maida added',
      description: 'Traditional chakki stone-ground whole wheat flour for ultra-soft, fluffy phulkas and rotis.',
      buyAgain: true,
      label: 'atta bag',
      isSubscribed: true,
      subscriptionDiscount: 0.95,
      hasSubscribeAndSave: true,
      frequentlyBoughtTogether: [
        { id: 2, name: 'Desi Ghee', size: '500 ml', price: 9.99, image: '/images/products/ghee.jpg' },
        { id: 12, name: 'Organic Toor Dal', size: '4 lb', price: 7.99, image: '/images/products/toor_dal.jpg' },
      ],
    },
    {
      id: 2,
      slug: 'ghee',
      name: 'Desi Ghee',
      subtitleTag: 'BILONA CHURNED COW BUTTER',
      category: 'dairy',
      categoryTitle: 'Dairy & Ghee',
      price: 9.99,
      originalPrice: 9.99,
      unitPrice: '$19.98 / L',
      stockBadge: 'In stock · best seller',
      photoLabel: 'ghee jar',
      image: '/images/products/ghee.jpg',
      sizeMain: '500 ml',
      sizeSub: '16.9 fl oz glass jar',
      size: '500 ml',
      freshnessLine: 'Golden aromatic granular ghee with rich nutty flavor',
      description: 'Pure grass-fed clarified butter prepared using authentic slow-simmer methods for royal dishes.',
      buyAgain: true,
      label: 'ghee jar',
      frequentlyBoughtTogether: [
        { id: 5, name: 'Basmati Rice', size: '20 lb', price: 24.99, image: '/images/products/rice.jpg' },
      ],
    },
    {
      id: 3,
      slug: 'biscuits',
      name: 'Glucose Biscuits',
      subtitleTag: 'CLASSIC GOLDEN CHAI BISCUITS',
      category: 'snacks',
      categoryTitle: 'Snacks & Chai',
      price: 3.49,
      originalPrice: 3.49,
      unitPrice: '$4.36 / kg',
      stockBadge: 'In stock',
      photoLabel: 'biscuits',
      image: '/images/products/biscuits.jpg',
      sizeMain: '800 g',
      sizeSub: 'Family mega pack',
      size: '800 g',
      freshnessLine: 'Crispy crunchy golden wheat biscuits for morning & evening tea',
      description: 'The quintessential Indian tea-time biscuit loved across generations for dunking in piping hot chai.',
      buyAgain: true,
      label: 'biscuits',
    },
    {
      id: 4,
      slug: 'paneer',
      name: 'Malai Paneer',
      subtitleTag: 'FRESH · MADE LOCALLY',
      category: 'dairy',
      categoryTitle: 'Dairy & Paneer',
      price: 5.99,
      originalPrice: 5.99,
      unitPrice: '$14.98 / kg',
      stockBadge: 'In stock · 24 left',
      photoLabel: 'paneer block',
      image: '/images/products/paneer.jpg',
      sizeMain: '400 g',
      sizeSub: '14.1 oz · 1 block',
      size: '400 g',
      freshnessLine: 'Pickup today from 4 pm · Best before Oct 2',
      description: 'Soft, full-cream paneer pressed in small batches. Holds its shape in tikka and melts into makhani.',
      buyAgain: true,
      label: 'paneer block',
      frequentlyBoughtTogether: [
        { id: 11, name: 'Garam Masala', size: '100 g', price: 3.49, checked: true, image: '/images/products/garam_masala.jpg' },
        { id: 14, name: 'Garlic Naan', size: '4 pack', price: 3.99, checked: false, image: '/images/products/sweets.jpg' },
        { id: 15, name: 'Kasuri Methi', size: '50 g', price: 2.49, checked: false, image: '/images/products/okra.jpg' },
      ],
    },
    {
      id: 5,
      slug: 'rice',
      name: 'Basmati Rice',
      subtitleTag: 'AGED 2 YEARS · EXTRA LONG GRAIN',
      category: 'staples',
      categoryTitle: 'Staples · Rice',
      price: 24.99,
      originalPrice: 24.99,
      unitPrice: '$1.25 / lb',
      stockBadge: 'Staple · buy monthly',
      photoLabel: 'rice sack',
      image: '/images/products/rice.jpg',
      sizeMain: '20 lb',
      sizeSub: '9.07 kg · ~100 servings',
      size: '20 lb',
      freshnessLine: 'Aged in climate-controlled storage for maximum aroma and non-sticky fluffiness.',
      description: 'Aged Himalayan long grain basmati rice. Slender grains elongate to twice their size when cooked.',
      buyAgain: true,
      label: 'rice sack',
      hasSubscribeAndSave: true,
      frequentlyBoughtTogether: [
        { id: 12, name: 'Organic Toor Dal', size: '4 lb', price: 7.99, image: '/images/products/toor_dal.jpg' },
        { id: 2, name: 'Desi Ghee', size: '500 ml', price: 9.99, image: '/images/products/ghee.jpg' },
      ],
    },
    // Recipe Kits
    {
      id: 16,
      slug: 'veg-manchurian',
      name: 'Veg Manchurian Kit',
      subtitleTag: 'INDO-CHINESE STREET STYLE',
      category: 'recipe-kits',
      categoryTitle: 'Recipe Kits · Street Food',
      price: 16.49,
      originalPrice: 18.94,
      unitPrice: '$4.12 / serving',
      stockBadge: '6 items',
      photoLabel: 'manchurian',
      image: '/images/products/sweets.jpg',
      sizeMain: 'Kit for 4',
      sizeSub: '6 fresh items included',
      freshnessLine: 'Cabbage · Carrot · Spring onion · Corn starch · Soy sauce · Chilli sauce',
      description: 'Crispy fried vegetable dumplings tossed in a savoury, tangy, and spicy garlic-soy gravy. Complete with freshly chopped veggies and authentic sauces.',
      servings: 'Serves 4',
      cookingTime: '30 mins',
      isRecipeKit: true,
      buyAgain: false,
      label: 'manchurian',
      recipeIngredients: [
        'Fresh Shredded Cabbage & Carrot (500g)',
        'Spring Onions & Green Chillies',
        'Corn Starch & All-Purpose Flour Pack',
        'Dark Soy Sauce & Chilli Garlic Sauce',
      ],
    },
    {
      id: 6,
      slug: 'paneer-curry',
      name: 'Paneer Butter Masala Kit',
      subtitleTag: 'RESTAURANT STYLE GRAVY KIT',
      category: 'recipe-kits',
      categoryTitle: 'Recipe Kits · Dinner',
      price: 19.99,
      originalPrice: 22.99,
      unitPrice: '$5.00 / serving',
      stockBadge: 'Fresh kit · ready today',
      photoLabel: 'butter masala',
      image: '/images/products/paneer_curry.jpg',
      sizeMain: 'Kit for 4',
      sizeSub: 'All fresh & pantry ingredients included',
      size: 'Kit for 4',
      freshnessLine: 'Paneer · Tomato · Cream · Kasuri methi',
      description: 'Everything you need to make rich, silky restaurant-style Paneer Butter Masala in 25 minutes. Includes recipe card with step-by-step instructions.',
      servings: 'Serves 4',
      cookingTime: '25 mins',
      isRecipeKit: true,
      buyAgain: false,
      label: 'butter masala',
      recipeIngredients: [
        'Fresh Malai Paneer (400g)',
        'Desi Butter & Cream Pack',
        'Slow-cooked Makhani Gravy Base',
        'Kasuri Methi & Whole Spices',
      ],
    },
    {
      id: 7,
      slug: 'biryani',
      name: 'Royal Dum Biryani Kit',
      subtitleTag: 'HYDERABADI DUM SPECIAL',
      category: 'recipe-kits',
      categoryTitle: 'Recipe Kits · Weekend Feast',
      price: 19.99,
      originalPrice: 22.99,
      unitPrice: '$5.00 / serving',
      stockBadge: 'Chef recipe',
      photoLabel: 'biryani kit',
      image: '/images/products/biryani.jpg',
      sizeMain: 'Feast for 4',
      sizeSub: 'Includes royal saffron & kewra',
      size: 'Feast for 4',
      freshnessLine: 'Fragrant aged basmati paired with whole aromatics and barista fried onions',
      description: 'Layered aromatic Hyderabadi Dum Biryani kit. Contains 2-year aged long-grain basmati, authentic biryani masala pot, pure saffron strands, fried crispy onions, and desi ghee.',
      servings: 'Serves 4',
      cookingTime: '45 mins',
      isRecipeKit: true,
      buyAgain: false,
      label: 'biryani kit',
      recipeIngredients: [
        'Royal Aged Basmati Rice (2 lb)',
        'Shahi Biryani Pot Masala',
        'Crispy Barista Fried Onions',
        'Pure Saffron Strands & Cow Ghee',
      ],
    },
    {
      id: 8,
      slug: 'chana-masala',
      name: 'Punjabi Chana Masala Kit',
      subtitleTag: 'AMRITSARI PIND RECIPE',
      category: 'recipe-kits',
      categoryTitle: 'Recipe Kits · Classic Curry',
      price: 11.99,
      originalPrice: 13.99,
      unitPrice: '$3.00 / serving',
      stockBadge: 'High protein',
      photoLabel: 'chana kit',
      image: '/images/products/garam_masala.jpg',
      sizeMain: 'Kit for 4',
      sizeSub: 'Rich dark tea-infused gravy',
      size: 'Kit for 4',
      freshnessLine: 'Organic kabuli chickpeas soaked & par-cooked with aromatic dry masala',
      description: 'Authentic Amritsari Chana Masala kit with roasted anardana (pomegranate seed), amchur, freshly ground garam masala, and ginger juliennes.',
      servings: 'Serves 4',
      cookingTime: '30 mins',
      isRecipeKit: true,
      buyAgain: false,
      label: 'chana kit',
      recipeIngredients: [
        'Kabuli Chickpeas (2 lb pre-soaked)',
        'Amritsari Chana Masala Spice Blend',
        'Anardana & Black Salt',
        'Fresh Ginger & Green Chilli Pack',
      ],
    },
    {
      id: 9,
      slug: 'dal-tadka',
      name: 'Dal Tadka & Jeera Rice Kit',
      subtitleTag: 'COMFORT FOOD ESSENTIAL',
      category: 'recipe-kits',
      categoryTitle: 'Recipe Kits · Quick Dinners',
      price: 10.99,
      originalPrice: 12.49,
      unitPrice: '$2.75 / serving',
      stockBadge: 'Ready in 20 mins',
      photoLabel: 'dal kit',
      image: '/images/products/toor_dal.jpg',
      sizeMain: 'Kit for 4',
      sizeSub: 'Double tadka aromatics',
      size: 'Kit for 4',
      freshnessLine: 'Yellow pigeon peas with cumin, mustard seeds, whole red chillies & garlic',
      description: 'Smoky dhaba-style yellow dal tadka paired with fragrant cumin jeera rice. Comes with pure desi ghee for the sizzle tempering.',
      servings: 'Serves 4',
      cookingTime: '20 mins',
      isRecipeKit: true,
      buyAgain: false,
      label: 'dal kit',
      recipeIngredients: [
        'Unpolished Toor Dal (1 lb)',
        'Fragrant Jeera Rice (1 lb)',
        'Tempering Spice Box (Jeera, Rai, Hing)',
        'Cow Ghee & Dried Whole Red Chillies',
      ],
    },
    // Sweets, Spices, Produce
    {
      id: 10,
      slug: 'sweets-box',
      name: 'Diwali Sweets Box',
      subtitleTag: 'FESTIVAL PRE-ORDER · READY OCT 30',
      category: 'sweets',
      categoryTitle: 'Festival Sweets & Mithai',
      price: 24.99,
      originalPrice: 29.99,
      unitPrice: '$20.82 / kg',
      stockBadge: 'Pre-order · closes Oct 30',
      photoLabel: 'mithai box',
      image: '/images/products/sweets.jpg',
      sizeMain: '1.2 kg',
      sizeSub: 'Assorted 24 pcs luxury box',
      size: '1.2 kg',
      freshnessLine: 'Handcrafted by master halwais with pure silver vark and organic pistachios',
      description: 'Deluxe festival presentation box featuring Kaju Katli, Pista Barfi, Motichoor Ladoo, and Besan Peda.',
      buyAgain: false,
      label: 'mithai box',
    },
    {
      id: 11,
      slug: 'garam-masala',
      name: 'Aromatic Garam Masala',
      subtitleTag: 'WHOLE GROUND AROMATIC BLEND',
      category: 'spices',
      categoryTitle: 'Spices & Seasoning',
      price: 3.49,
      originalPrice: 3.49,
      unitPrice: '$34.90 / kg',
      stockBadge: 'In stock',
      photoLabel: 'spice jar',
      image: '/images/products/garam_masala.jpg',
      sizeMain: '100 g',
      sizeSub: '3.5 oz glass jar',
      size: '100 g',
      freshnessLine: 'Freshly stone-ground batches sealing in natural essential oils',
      description: 'Fragrant small-batch blend of green cardamom, cloves, cinnamon, mace, and black pepper.',
      buyAgain: false,
      label: 'spice jar',
    },
    {
      id: 12,
      slug: 'toor-dal',
      name: 'Organic Toor Dal',
      subtitleTag: 'UNPOLISHED YELLOW PIGEON PEAS',
      category: 'staples',
      categoryTitle: 'Staples · Pulses',
      price: 7.99,
      originalPrice: 7.99,
      unitPrice: '$2.00 / lb',
      stockBadge: 'High protein',
      photoLabel: 'dal bag',
      image: '/images/products/toor_dal.jpg',
      sizeMain: '4 lb',
      sizeSub: '1.81 kg',
      size: '4 lb',
      freshnessLine: 'Zero oil polish, naturally sun-dried pulses rich in dietary fiber',
      description: 'Premium unpolished toor dal for smooth, creamy dal tadka, rasam, and South Indian sambar.',
      buyAgain: false,
      label: 'toor dal',
    },
    {
      id: 13,
      slug: 'okra',
      name: 'Fresh Okra (Bhindi)',
      subtitleTag: 'FARM FRESH · CRISP & TENDER',
      category: 'produce',
      categoryTitle: 'Fresh Vegetables',
      price: 4.99,
      originalPrice: 4.99,
      unitPrice: '$4.99 / lb',
      stockBadge: 'Harvested yesterday',
      photoLabel: 'fresh okra',
      image: '/images/products/okra.jpg',
      sizeMain: '1 lb',
      sizeSub: '~450 g',
      size: '1 lb',
      freshnessLine: 'Hand-snapped tender green pods, guaranteed non-slimy when cooked',
      description: 'Crisp, tender baby okra ideal for crispy kurkuri bhindi, bharwan bhindi, or homestyle sabzi.',
      buyAgain: false,
      label: 'fresh okra',
    },
    {
      id: 14,
      slug: 'curry-leaves',
      name: 'Fresh Curry Leaves (Kadi Patta)',
      subtitleTag: 'INTENSELY AROMATIC HERB',
      category: 'produce',
      categoryTitle: 'Fresh Herbs',
      price: 1.99,
      originalPrice: 1.99,
      unitPrice: '$1.99 / bunch',
      stockBadge: 'Daily delivery',
      photoLabel: 'curry leaves',
      image: '/images/products/curry_leaves.jpg',
      sizeMain: '1 fresh bunch',
      sizeSub: 'Approx 30-40 leaves',
      size: '1 fresh bunch',
      freshnessLine: 'Washed and chilled stems bursting with citrusy peppery oils',
      description: 'Essential tempering herb for South Indian and coastal curries, poha, upma, and dal tadka.',
      buyAgain: false,
      label: 'curry leaves',
    }
  ]);

  // Impulse Items for Cart (Under $2 · one tap to add)
  const impulseItems = ref([
    { id: 201, name: 'Fresh Coriander', price: 0.99, size: '1 bunch', label: 'fresh herb' },
    { id: 202, name: 'Saunf Mints', price: 1.49, size: '50 g', label: 'mints' },
    { id: 203, name: 'Green Chillies', price: 0.89, size: '100 g', label: 'chillies' },
    { id: 204, name: 'Masala Noodles', price: 1.29, size: '70 g single', label: 'noodles' }
  ]);

  // Recipe Kits (matching products 6, 7, 8, 9)
  const recipeKits = computed(() => {
    return products.value.filter(p => p.isRecipeKit);
  });

  // Subscriptions (Matching 1f Customer dashboard)
  const subscriptions = ref([
    {
      id: 'sub-1',
      name: 'Chakki Atta · 20 lb',
      frequency: 'Monthly',
      price: 18.04,
      originalPrice: 18.99,
      nextDate: 'Next Oct 3',
      status: 'active'
    },
    {
      id: 'sub-2',
      name: 'Toor Dal · 4 lb',
      frequency: 'Every 2 weeks',
      price: 7.59,
      originalPrice: 7.99,
      nextDate: 'Paused',
      status: 'paused'
    }
  ]);

  // Past Orders (Matching 1f Customer dashboard)
  const pastOrders = ref([
    {
      id: '#10482',
      date: 'Sep 18',
      type: 'Picked up',
      total: 62.40,
      itemCount: 6,
      summary: 'Chakki Atta, Desi Ghee, Okra (Bhindi), Curry Leaves, Glucose Biscuits, Masala Noodles'
    },
    {
      id: '#10391',
      date: 'Sep 4',
      type: 'Delivered',
      total: 58.15,
      itemCount: 4,
      summary: 'Aged Basmati Rice, Toor Dal, Fresh Coriander, Ghee'
    }
  ]);

  // Masala Points Rewards (Matching Screen 1f: 1,240 pts)
  const masalaPoints = ref(1240);

  // Cart State (Exact match to PDF: 3 items initially = Paneer $5.99 [id:4], Atta $18.04 [id:1], Garam Masala $3.49 [id:11] => Total $27.52)
  const cart = ref([
    { id: 4, quantity: 1, isSubscribed: false }, // Malai Paneer ($5.99)
    { id: 1, quantity: 1, isSubscribed: true },  // Chakki Atta ($18.04 after 5% sub discount)
    { id: 11, quantity: 1, isSubscribed: false }  // Garam Masala ($3.49)
  ]);

  // Set / merge products from Laravel database
  function setProducts(dbProducts) {
    if (!Array.isArray(dbProducts) || dbProducts.length === 0) return;

    dbProducts.forEach(item => {
      const idx = products.value.findIndex(p => p.id === item.id || p.slug === item.slug);
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
        hasSubscribeAndSave: !!(item.has_subscription || item.hasSubscribeAndSave),
        label: item.photo_label || item.photoLabel || item.slug,
        frequentlyBoughtTogether: item.frequently_bought_together || item.frequentlyBoughtTogether || [],
        recipeIngredients: item.recipe_ingredients || item.recipeIngredients || [],
      };

      if (idx !== -1) {
        products.value[idx] = { ...products.value[idx], ...formatted };
      } else {
        products.value.push(formatted);
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

      const impulse = impulseItems.value.find(i => i.label === input || String(i.id) === input);
      if (impulse) return impulse.id;
    }
    return input;
  }

  // Computed Cart Items
  const cartItems = computed(() => {
    return cart.value.map(item => {
      const prod = products.value.find(p => p.id === item.id) || 
                   impulseItems.value.find(i => i.id === item.id);
      
      if (!prod) return null;

      let unitCost = prod.price;
      if (item.isSubscribed) {
        unitCost = Number((prod.price * 0.95).toFixed(2));
      }

      return {
        ...prod,
        quantity: item.quantity,
        isSubscribed: !!item.isSubscribed,
        unitCost: unitCost,
        price: unitCost,
        weight: prod.sizeMain || prod.size || '',
        total: Number((unitCost * item.quantity).toFixed(2))
      };
    }).filter(Boolean);
  });

  const totalItemCount = computed(() => {
    return cart.value.reduce((sum, item) => sum + item.quantity, 0);
  });

  const rawSubtotal = computed(() => {
    return cart.value.reduce((sum, item) => {
      const prod = products.value.find(p => p.id === item.id) || 
                   impulseItems.value.find(i => i.id === item.id);
      return sum + (prod ? prod.price * item.quantity : 0);
    }, 0);
  });

  const subscribeSavings = computed(() => {
    return cart.value.reduce((sum, item) => {
      if (item.isSubscribed) {
        const prod = products.value.find(p => p.id === item.id);
        if (prod) {
          return sum + Number((prod.price * 0.05 * item.quantity).toFixed(2));
        }
      }
      return sum;
    }, 0);
  });

  const subtotal = computed(() => {
    return Number((rawSubtotal.value - subscribeSavings.value).toFixed(2));
  });

  const total = computed(() => {
    return subtotal.value;
  });

  const freeDeliveryThreshold = 40.0;
  const amountToFreeDelivery = computed(() => {
    const rem = Number((freeDeliveryThreshold - subtotal.value).toFixed(2));
    return rem > 0 ? rem : 0;
  });

  const freeDeliveryProgressPercent = computed(() => {
    return Math.min(100, (subtotal.value / freeDeliveryThreshold) * 100);
  });

  function getQuantity(productId) {
    const id = resolveProductId(productId);
    const item = cart.value.find(i => i.id === id);
    return item ? item.quantity : 0;
  }

  function getItemQuantity(productId) {
    return getQuantity(productId);
  }

  function addToCart(itemOrId, count = 1, isSub = false) {
    let id = itemOrId;
    let sub = isSub;
    let qty = count;
    const itemObj = (typeof itemOrId === 'object' && itemOrId !== null) ? itemOrId : null;

    if (itemObj) {
      id = itemObj.id || itemObj.slug;
      if (itemObj.quantity) qty = itemObj.quantity;
      if (itemObj.isSubscribed !== undefined) sub = itemObj.isSubscribed;
    }

    const resolvedId = resolveProductId(id);
    const existing = cart.value.find(i => i.id === resolvedId);
    if (existing) {
      existing.quantity += qty;
      if (sub) existing.isSubscribed = true;
    } else {
      if (itemObj && !products.value.some(p => p.id === resolvedId)) {
        products.value.push({
          id: resolvedId,
          slug: itemObj.slug || String(resolvedId),
          name: itemObj.name || 'Pantry Item',
          price: Number(itemObj.price || 0),
          originalPrice: Number(itemObj.originalPrice || itemObj.price || 0),
          image: itemObj.image || '/images/products/atta.jpg',
          sizeMain: itemObj.sizeMain || itemObj.weight || itemObj.size || '',
          size: itemObj.sizeMain || itemObj.weight || itemObj.size || '',
          category: itemObj.category || 'grocery',
          label: itemObj.label || itemObj.photoLabel || itemObj.photo_label || 'item',
        });
      }
      cart.value.push({ id: resolvedId, quantity: qty, isSubscribed: sub });
    }
  }

  function addImpulseItem(impulseId) {
    const resolvedId = resolveProductId(impulseId);
    addToCart(resolvedId, 1);
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
    addToCart(1, 1, true); // Atta
    addToCart(2, 1);       // Ghee
    addToCart(3, 1);       // Biscuits
    addToCart(11, 1);      // Garam Masala
  }

  function toggleSubscription(subId) {
    const sub = subscriptions.value.find(s => s.id === subId);
    if (sub) {
      sub.status = sub.status === 'active' ? 'paused' : 'active';
      sub.nextDate = sub.status === 'active' ? 'Next Oct 3' : 'Paused';
    }
  }

  function skipSubscription(subId) {
    const sub = subscriptions.value.find(s => s.id === subId);
    if (sub) {
      sub.nextDate = 'Skipped · Nov 3';
    }
  }

  function getProductBySlug(slug) {
    return products.value.find(p => p.slug === slug || String(p.id) === slug) || products.value[0];
  }

  return {
    deliveryMode,
    selectedStore,
    readyTime,
    searchQuery,
    products,
    impulseItems,
    recipeKits,
    subscriptions,
    pastOrders,
    cart,
    cartItems,
    totalItemCount,
    rawSubtotal,
    subscribeSavings,
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
    toggleSubscription,
    skipSubscription,
    getProductBySlug,
    setProducts,
  };
});
