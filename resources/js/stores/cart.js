import { defineStore } from 'pinia';
import { ref, computed } from 'vue';

export const useStore = defineStore('masalaStore', () => {
  // Navigation & Location
  const deliveryMode = ref('Pickup');
  const selectedStore = ref('Main St. store');
  const readyTime = ref('ready in 1 hr');
  const searchQuery = ref('');

  // Products Database
  const products = ref([
    {
      id: 5,
      slug: 'paneer',
      name: 'Malai Paneer',
      subtitleTag: 'FRESH · MADE LOCALLY',
      category: 'dairy',
      categoryTitle: 'Dairy & Paneer',
      price: 5.99,
      unitPrice: '$14.98 / kg',
      stockBadge: 'In stock · 24 left',
      photoLabel: 'product photo · paneer block',
      sizeMain: '400 g',
      sizeSub: '14.1 oz · 1 block',
      size: '400 g',
      freshnessLine: 'Pickup today from 4 pm · Best before Oct 2',
      description: 'Soft, full-cream paneer pressed in small batches. Holds its shape in tikka and melts into makhani.',
      buyAgain: false,
      label: 'paneer block',
      frequentlyBoughtTogether: [
        { id: 9, name: 'Garam Masala', size: '100 g', price: 3.49, checked: true },
        { id: 11, name: 'Garlic Naan', size: '4 pack', price: 3.99, checked: false }
      ]
    },
    {
      id: 7,
      slug: 'rice',
      name: 'Aged Basmati Rice',
      subtitleTag: 'AGED 2 YEARS · EXTRA LONG GRAIN',
      category: 'staples',
      categoryTitle: 'Staples · Rice',
      price: 24.99,
      unitPrice: '$1.25 / lb',
      stockBadge: 'Staple · buy monthly',
      photoLabel: 'product photo · rice sack',
      sizeMain: '20 lb',
      sizeSub: '9.07 kg · ~100 servings',
      size: '20 lb',
      freshnessLine: 'Aged in climate-controlled storage for maximum aroma and non-sticky fluffiness.',
      description: 'Aged Himalayan long grain basmati rice. Slender grains elongate to twice their size when cooked.',
      buyAgain: false,
      label: 'rice sack',
      hasSubscribeAndSave: true,
      goesWithIt: [
        { id: 10, name: 'Toor Dal · 4 lb', label: 'toor dal', price: 7.99, size: '4 lb' },
        { id: 3, name: 'Desi Ghee · 500 ml', label: 'ghee jar', price: 9.99, size: '500 ml' }
      ]
    },
    {
      id: 2,
      slug: 'atta',
      name: 'Chakki Atta',
      subtitleTag: '100% STONE GROUND WHOLE WHEAT',
      category: 'staples',
      categoryTitle: 'Staples · Flour',
      price: 18.99,
      unitPrice: '$0.95 / lb',
      stockBadge: 'Staple · buy monthly',
      photoLabel: 'product photo · atta bag',
      sizeMain: '20 lb',
      sizeSub: '9.07 kg · ~80 rotis',
      size: '20 lb',
      freshnessLine: 'Freshly milled batch · Zero maida added',
      description: 'Traditional chakki stone-ground whole wheat flour for ultra-soft, fluffy phulkas and rotis.',
      buyAgain: true,
      label: 'atta bag',
      isSubscribed: true,
      subscriptionDiscount: 0.95, // $18.04 after 5%
    },
    {
      id: 3,
      slug: 'ghee',
      name: 'Desi Ghee',
      subtitleTag: 'BILONA CHURNED COW BUTTER',
      category: 'dairy',
      categoryTitle: 'Dairy & Ghee',
      price: 9.99,
      unitPrice: '$19.98 / L',
      stockBadge: 'In stock · best seller',
      photoLabel: 'product photo · ghee jar',
      sizeMain: '500 ml',
      sizeSub: '16.9 fl oz glass jar',
      size: '500 ml',
      freshnessLine: 'Golden aromatic granular ghee with rich nutty flavor',
      description: 'Pure grass-fed clarified butter prepared using authentic slow-simmer methods for royal dishes.',
      buyAgain: true,
      label: 'ghee jar'
    },
    {
      id: 4,
      slug: 'biscuits',
      name: 'Glucose Biscuits',
      subtitleTag: 'CLASSIC GOLDEN CHAI BISCUITS',
      category: 'snacks',
      categoryTitle: 'Snacks & Chai',
      price: 3.49,
      unitPrice: '$4.36 / kg',
      stockBadge: 'In stock',
      photoLabel: 'product photo · biscuit pack',
      sizeMain: '800 g',
      sizeSub: 'Family mega pack',
      size: '800 g',
      freshnessLine: 'Crispy crunchy golden wheat biscuits for morning & evening tea',
      description: 'The quintessential Indian tea-time biscuit loved across generations for dunking in piping hot chai.',
      buyAgain: true,
      label: 'biscuits'
    },
    {
      id: 9,
      slug: 'garam-masala',
      name: 'Garam Masala',
      subtitleTag: 'WHOLE GROUND AROMATIC BLEND',
      category: 'spices',
      categoryTitle: 'Spices & Seasoning',
      price: 3.49,
      unitPrice: '$34.90 / kg',
      stockBadge: 'In stock',
      photoLabel: 'product photo · spice jar',
      sizeMain: '100 g',
      sizeSub: '3.5 oz glass jar',
      size: '100 g',
      description: 'Fragrant blend of cardamom, cloves, cinnamon, and black pepper.',
      buyAgain: false,
      label: 'spice jar'
    },
    {
      id: 10,
      slug: 'toor-dal',
      name: 'Toor Dal',
      subtitleTag: 'POLISHED YELLOW PIGEON PEAS',
      category: 'staples',
      categoryTitle: 'Staples · Pulses',
      price: 7.99,
      unitPrice: '$2.00 / lb',
      stockBadge: 'Staple · high protein',
      photoLabel: 'product photo · dal bag',
      sizeMain: '4 lb',
      sizeSub: '1.81 kg',
      size: '4 lb',
      description: 'Premium unpolished toor dal for smooth, hearty dal tadka and sambar.',
      buyAgain: false,
      label: 'toor dal'
    },
    {
      id: 1,
      slug: 'sweets-box',
      name: 'Diwali Sweets Box',
      subtitleTag: 'FESTIVAL PRE-ORDER · READY OCT 30',
      category: 'sweets',
      categoryTitle: 'Festival Sweets',
      price: 24.99,
      unitPrice: '$20.82 / kg',
      stockBadge: 'Pre-order · closes Oct 30',
      photoLabel: 'product photo · mithai box',
      sizeMain: '1.2 kg',
      sizeSub: 'Assorted 24 pcs luxury box',
      size: '1.2 kg',
      description: 'Deluxe gift box featuring Kaju Katli, Pista Barfi, Motichoor Ladoo, and Besan Peda.',
      buyAgain: false,
      label: 'mithai box'
    }
  ]);

  // Impulse Items for Cart (Under $2 · one tap to add)
  const impulseItems = ref([
    { id: 201, name: 'Fresh Coriander', price: 0.99, size: '1 bunch', label: 'fresh herb' },
    { id: 202, name: 'Saunf Mints', price: 1.49, size: '50 g', label: 'mints' },
    { id: 203, name: 'Green Chillies', price: 0.89, size: '100 g', label: 'chillies' },
    { id: 204, name: 'Masala Noodles', price: 1.29, size: '70 g single', label: 'noodles' }
  ]);

  // Recipe Kits
  const recipeKits = ref([
    {
      id: 101,
      name: 'Paneer Butter Masala Kit',
      subtitle: 'Complete restaurant style gravy kit',
      servings: 'Serves 4 · 25 mins',
      price: 14.99,
      items: ['Malai Paneer (400g)', 'Desi Ghee (200ml)', 'Kasuri Methi', 'Makhani Spice Blend'],
      label: 'paneer kit',
    },
    {
      id: 102,
      name: 'Royal Dum Biryani Kit',
      subtitle: 'Aged rice, whole spices, fried onions & saffron',
      servings: 'Serves 6 · 40 mins',
      price: 19.99,
      items: ['Aged Basmati Rice (2 lb)', 'Biryani Pot Masala', 'Kashmiri Saffron', 'Cow Ghee'],
      label: 'biryani kit',
    },
    {
      id: 103,
      name: 'Homestyle Dal Tadka & Jeera Rice',
      subtitle: 'Yellow toor dal with aromatic cumin tempering',
      servings: 'Serves 4 · 20 mins',
      price: 11.49,
      items: ['Toor Dal (2 lb)', 'Basmati Rice (2 lb)', 'Cumin & Mustard Seeds', 'Curry Leaves'],
      label: 'dal kit',
    }
  ]);

  // Subscriptions (Matching 1f Customer dashboard)
  const subscriptions = ref([
    {
      id: 'sub-1',
      name: 'Chakki Atta · 20 lb',
      frequency: 'Monthly',
      price: 18.04,
      originalPrice: 18.99,
      nextDate: 'Next Oct 3',
      status: 'active' // 'active' | 'paused'
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

  // Cart State (Exact match to PDF: 3 items initially = Paneer $5.99, Atta $18.04, Garam Masala $3.49 => Total $27.52)
  const cart = ref([
    { id: 5, quantity: 1, isSubscribed: false }, // Malai Paneer ($5.99)
    { id: 2, quantity: 1, isSubscribed: true },  // Chakki Atta ($18.04 after 5% sub discount)
    { id: 9, quantity: 1, isSubscribed: false }  // Garam Masala ($3.49)
  ]);

  // Computed Cart Items
  const cartItems = computed(() => {
    return cart.value.map(item => {
      const prod = products.value.find(p => p.id === item.id) || 
                   impulseItems.value.find(i => i.id === item.id) ||
                   recipeKits.value.find(k => k.id === item.id);
      
      if (!prod) return null;

      // Price calculation considering 5% subscribe & save
      let unitCost = prod.price;
      if (item.isSubscribed) {
        unitCost = Number((prod.price * 0.95).toFixed(2));
      }

      return {
        ...prod,
        quantity: item.quantity,
        isSubscribed: !!item.isSubscribed,
        unitCost: unitCost,
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
                   impulseItems.value.find(i => i.id === item.id) ||
                   recipeKits.value.find(k => k.id === item.id);
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

  const freeDeliveryThreshold = 40.0;
  const amountToFreeDelivery = computed(() => {
    const rem = Number((freeDeliveryThreshold - subtotal.value).toFixed(2));
    return rem > 0 ? rem : 0;
  });

  const freeDeliveryProgressPercent = computed(() => {
    return Math.min(100, (subtotal.value / freeDeliveryThreshold) * 100);
  });

  function getQuantity(productId) {
    const item = cart.value.find(i => i.id === productId);
    return item ? item.quantity : 0;
  }

  function addToCart(productId, count = 1, isSub = false) {
    const item = cart.value.find(i => i.id === productId);
    if (item) {
      item.quantity += count;
      if (isSub) item.isSubscribed = true;
    } else {
      cart.value.push({ id: productId, quantity: count, isSubscribed: isSub });
    }
  }

  function removeFromCart(productId) {
    const index = cart.value.findIndex(i => i.id === productId);
    if (index !== -1) {
      if (cart.value[index].quantity > 1) {
        cart.value[index].quantity -= 1;
      } else {
        cart.value.splice(index, 1);
      }
    }
  }

  function deleteItem(productId) {
    cart.value = cart.value.filter(i => i.id !== productId);
  }

  function clearCart() {
    cart.value = [];
  }

  function reorderAll(orderId) {
    // Reorder action drops items into cart
    addToCart(2, 1, true); // Atta
    addToCart(3, 1);       // Ghee
    addToCart(4, 1);       // Biscuits
    addToCart(9, 1);       // Garam Masala
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
    return products.value.find(p => p.slug === slug) || products.value[0];
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
    freeDeliveryThreshold,
    amountToFreeDelivery,
    freeDeliveryProgressPercent,
    getQuantity,
    addToCart,
    removeFromCart,
    deleteItem,
    clearCart,
    reorderAll,
    toggleSubscription,
    skipSubscription,
    getProductBySlug,
  };
});
