<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ChevronRight, 
  Plus, 
  Minus, 
  UtensilsCrossed, 
  Sparkles, 
  Clock, 
  Check, 
  ShoppingBag, 
  ArrowRight,
  Flame,
  ShieldAlert
} from 'lucide-vue-next';

const store = useStore();

const activeFilter = ref('all');

function addRecipeKit(name, price) {
  store.cart.push({
    id: 'kit-' + Date.now(),
    name: name,
    weight: 'Complete Kit',
    price: price,
    originalPrice: price,
    quantity: 1,
    isSubscribed: false,
  });
}
</script>

<template>
  <Head title="Masala Mart — Indian Groceries & Fresh Click-and-Collect" />

  <StoreLayout>
    <div class="space-y-10">

      <!-- HERO SECTION: Split Festival Banner (Page 2: 1a Modernist Specification) -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Main Festival Pre-Order Banner (Vermilion Red #E52E04) -->
        <div class="lg:col-span-8 bg-[#E52E04] text-white border border-[#B82200] grid grid-cols-1 md:grid-cols-12 overflow-hidden shadow-sm">
          <!-- Banner Copy -->
          <div class="md:col-span-7 p-6 sm:p-8 flex flex-col justify-between space-y-6">
            <div>
              <div class="text-[10px] font-mono font-bold tracking-widest uppercase text-white/90 mb-2 flex items-center gap-1.5">
                <span class="w-2 h-2 bg-white inline-block"></span>
                <span>FESTIVAL PRE-ORDER · CLOSES OCT 30</span>
              </div>
              <h1 class="text-3xl sm:text-4xl lg:text-5xl font-black leading-[1.08] tracking-tight">
                Diwali sweets,<br />boxed &<br />ready.
              </h1>
              <p class="text-xs sm:text-sm text-white/90 mt-3 max-w-sm leading-relaxed">
                Handcrafted Kaju Katli, Besan Ladoo, and premium dry fruit boxes batched fresh at our local kitchen.
              </p>
            </div>

            <div>
              <Link 
                href="/products/sweets-box" 
                class="inline-flex items-center gap-2 px-4 py-2.5 bg-zinc-950 hover:bg-zinc-800 text-white font-black text-xs uppercase tracking-wider border border-zinc-950 transition-colors"
              >
                <span>Pre-order from $24.99</span>
                <ChevronRight class="w-4 h-4 stroke-[3]" />
              </Link>
            </div>
          </div>

          <!-- Banner Visual Texture (Diagonal Stripe Box labeled "mithai box") -->
          <div class="md:col-span-5 min-h-[160px] md:min-h-full bg-red-950/30 border-t md:border-t-0 md:border-l border-white/20 p-4 flex flex-col justify-between relative overflow-hidden bg-stripes-bold">
            <div class="flex justify-between items-start text-[10px] font-mono text-white/80">
              <span class="px-1.5 py-0.5 bg-black/30 border border-white/20">EDITION 2026</span>
              <span>1 kg / 2.2 lb</span>
            </div>
            
            <div class="relative z-10 bg-zinc-950/70 p-2.5 border border-white/10 backdrop-blur-sm self-start">
              <span class="text-[11px] font-mono text-white font-bold tracking-wide">
                mithai box · assorted 24 pcs
              </span>
            </div>
          </div>
        </div>

        <!-- Side Promo Card: Click & Collect Guarantee -->
        <div class="lg:col-span-4 bg-white border border-zinc-300 p-6 flex flex-col justify-between">
          <div>
            <div class="flex items-center justify-between text-[10px] font-mono uppercase tracking-wider text-zinc-500 mb-2">
              <span>EXPRESS STORE PICKUP</span>
              <span class="text-emerald-700 font-bold">1 HR READY</span>
            </div>
            <h3 class="text-xl font-black text-zinc-950 tracking-tight leading-tight">
              Order online, pull up, pop the trunk.
            </h3>
            <p class="text-xs text-zinc-600 mt-2 leading-relaxed">
              We pick temperature-controlled produce, fresh dairy, and pantry heavy bags so you don't have to wait in line.
            </p>
          </div>

          <div class="mt-6 pt-4 border-t border-zinc-200 flex items-center justify-between">
            <div>
              <div class="text-[11px] font-bold text-zinc-950">{{ store.selectedStore }}</div>
              <div class="text-[10px] font-mono text-zinc-500">Pickups ready in {{ store.readyTime }}</div>
            </div>
            <Link 
              href="/cart"
              class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-xs font-black text-zinc-950 border border-zinc-300"
            >
              Start Order &rsaquo;
            </Link>
          </div>
        </div>
      </section>

      <!-- SECTION: "Buy it again" (Screen 1a) -->
      <section class="space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-300 pb-2">
          <div>
            <h2 class="text-2xl font-black text-zinc-950 tracking-tight">Buy it again</h2>
            <p class="text-xs text-zinc-500">Your frequently replenished pantry staples and household favorites.</p>
          </div>
          <Link 
            href="/reorder" 
            class="text-xs font-bold text-[#E52E04] hover:underline flex items-center gap-1"
          >
            <span>See all past items</span>
            <ChevronRight class="w-3.5 h-3.5" />
          </Link>
        </div>

        <!-- Products Grid (Chakki Atta, Desi Ghee, Glucose Biscuits, Paneer, Basmati) -->
        <div class="grid grid-cols-2 sm:grid-cols-3 md:grid-cols-5 gap-3.5">
          
          <!-- Item 1: Chakki Atta -->
          <div class="bg-white border border-zinc-300 p-2.5 flex flex-col justify-between group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-2 flex flex-col justify-between bg-stripes mb-2">
                <span class="text-[10px] font-mono text-zinc-700 font-bold">atta bag</span>
                
                <!-- Active [- 1 +] control matching Screen 1a -->
                <div class="w-full bg-[#E52E04] text-white h-8 flex items-center justify-between px-2 border border-[#B82200]">
                  <button 
                    @click.stop="store.removeFromCart('atta')" 
                    class="p-1 font-black hover:bg-[#CC2500] cursor-pointer"
                    aria-label="Decrease quantity"
                  >
                    -
                  </button>
                  <span class="text-xs font-black font-mono">{{ store.getItemQuantity('atta') }}</span>
                  <button 
                    @click.stop="store.addToCart({ id: 'atta', name: 'Aashirvaad Chakki Atta', weight: '20 lb', price: 18.04, originalPrice: 18.99, isSubscribed: true })" 
                    class="p-1 font-black hover:bg-[#CC2500] cursor-pointer"
                    aria-label="Increase quantity"
                  >
                    +
                  </button>
                </div>
              </div>

              <Link href="/products/rice" class="block">
                <div class="text-base font-black text-zinc-950 font-mono leading-none">$18.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 line-clamp-1 group-hover:text-[#E52E04]">Aashirvaad Chakki Atta</div>
                <div class="text-[10px] text-zinc-500 font-mono">20 lb (9.07 kg)</div>
              </Link>
            </div>
            
            <div class="mt-2 pt-1.5 border-t border-zinc-100 flex items-center justify-between text-[9px] font-mono text-zinc-500">
              <span class="text-[#E52E04] font-bold">Save 5% sub</span>
              <span>100% Whole Wheat</span>
            </div>
          </div>

          <!-- Item 2: Desi Ghee -->
          <div class="bg-white border border-zinc-300 p-2.5 flex flex-col justify-between group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-2 flex flex-col justify-between bg-stripes mb-2">
                <span class="text-[10px] font-mono text-zinc-700 font-bold">ghee jar</span>
                
                <button 
                  @click="store.addToCart({ id: 'ghee', name: 'Amul Pure Desi Ghee', weight: '500 ml', price: 9.99, originalPrice: 9.99 })"
                  class="self-end w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer"
                  aria-label="Add Desi Ghee"
                >
                  +
                </button>
              </div>

              <div>
                <div class="text-base font-black text-zinc-950 font-mono leading-none">$9.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 line-clamp-1">Amul Pure Desi Ghee</div>
                <div class="text-[10px] text-zinc-500 font-mono">500 ml jar</div>
              </div>
            </div>

            <div class="mt-2 pt-1.5 border-t border-zinc-100 text-[9px] font-mono text-zinc-500">
              Grass-fed cow milk
            </div>
          </div>

          <!-- Item 3: Glucose Biscuits -->
          <div class="bg-white border border-zinc-300 p-2.5 flex flex-col justify-between group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-2 flex flex-col justify-between bg-stripes mb-2">
                <span class="text-[10px] font-mono text-zinc-700 font-bold">biscuits</span>
                
                <button 
                  @click="store.addToCart({ id: 'biscuits', name: 'Parle-G Glucose Biscuits', weight: '800 g', price: 3.49, originalPrice: 3.49 })"
                  class="self-end w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer"
                  aria-label="Add Biscuits"
                >
                  +
                </button>
              </div>

              <div>
                <div class="text-base font-black text-zinc-950 font-mono leading-none">$3.49</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 line-clamp-1">Parle-G Biscuits</div>
                <div class="text-[10px] text-zinc-500 font-mono">800 g family pack</div>
              </div>
            </div>

            <div class="mt-2 pt-1.5 border-t border-zinc-100 text-[9px] font-mono text-zinc-500">
              Tea time classic
            </div>
          </div>

          <!-- Item 4: Malai Paneer (Screen 1b feature item) -->
          <div class="bg-white border border-zinc-300 p-2.5 flex flex-col justify-between group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-2 flex flex-col justify-between bg-stripes mb-2">
                <span class="text-[10px] font-mono text-zinc-700 font-bold">paneer block</span>
                
                <button 
                  @click="store.addToCart({ id: 'paneer', name: 'Amul Malai Paneer', weight: '400 g', price: 5.99, originalPrice: 5.99 })"
                  class="self-end w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer"
                  aria-label="Add Malai Paneer"
                >
                  +
                </button>
              </div>

              <Link href="/products/paneer" class="block">
                <div class="text-base font-black text-zinc-950 font-mono leading-none">$5.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 line-clamp-1 group-hover:text-[#E52E04]">Amul Malai Paneer</div>
                <div class="text-[10px] text-zinc-500 font-mono">400 g · Local Batch</div>
              </Link>
            </div>

            <div class="mt-2 pt-1.5 border-t border-zinc-100 flex items-center justify-between text-[9px] font-mono text-emerald-700 font-bold">
              <span>Fresh Batch</span>
              <span>24 left</span>
            </div>
          </div>

          <!-- Item 5: Basmati Rice (Screen 1c feature item) -->
          <div class="bg-white border border-zinc-300 p-2.5 flex flex-col justify-between group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-2 flex flex-col justify-between bg-stripes mb-2">
                <span class="text-[10px] font-mono text-zinc-700 font-bold">rice sack</span>
                
                <button 
                  @click="store.addToCart({ id: 'rice', name: 'Royal Aged Basmati Rice', weight: '20 lb', price: 24.99, originalPrice: 24.99 })"
                  class="self-end w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer"
                  aria-label="Add Basmati Rice"
                >
                  +
                </button>
              </div>

              <Link href="/products/rice" class="block">
                <div class="text-base font-black text-zinc-950 font-mono leading-none">$24.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 line-clamp-1 group-hover:text-[#E52E04]">Royal Aged Basmati</div>
                <div class="text-[10px] text-zinc-500 font-mono">20 lb · Aged 2 Yrs</div>
              </Link>
            </div>

            <div class="mt-2 pt-1.5 border-t border-zinc-100 text-[9px] font-mono text-zinc-500">
              Extra Long Grain
            </div>
          </div>

        </div>
      </section>

      <!-- SECTION: "Recipe kits" (Screen 1a) -->
      <section id="recipe-kits" class="space-y-4">
        <div class="flex items-center justify-between border-b border-zinc-300 pb-2">
          <div>
            <div class="flex items-center gap-2">
              <h2 class="text-2xl font-black text-zinc-950 tracking-tight">Recipe kits</h2>
              <span class="bg-zinc-950 text-white text-[10px] font-mono px-2 py-0.5 font-bold uppercase">1 Tap Add</span>
            </div>
            <p class="text-xs text-zinc-500 mt-0.5">Every ingredient for one authentic dish, measured and batched together.</p>
          </div>
          <span class="text-xs font-bold text-[#E52E04]">All 14 kits available</span>
        </div>

        <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-4 gap-4">
          
          <!-- Kit 1: Paneer Butter Masala -->
          <div class="bg-white border-2 border-zinc-300 hover:border-zinc-950 p-4 flex flex-col justify-between transition-colors">
            <div>
              <div class="w-full h-32 bg-[#EAEAEA] border border-zinc-200 mb-3 p-2 bg-stripes flex flex-col justify-between">
                <span class="text-[10px] font-mono font-bold text-zinc-700">paneer kit · serves 4</span>
                <button 
                  @click="addRecipeKit('Paneer Butter Masala Kit', 14.99)"
                  class="self-end px-3 py-1.5 bg-[#E52E04] hover:bg-[#CC2500] text-white text-xs font-black flex items-center gap-1 border border-[#B82200]"
                >
                  <Plus class="w-3.5 h-3.5" />
                  <span>Add Kit</span>
                </button>
              </div>

              <div class="text-lg font-black text-zinc-950 font-mono">$14.99</div>
              <h3 class="font-bold text-sm text-zinc-950 mt-1">Paneer Butter Masala</h3>
              <p class="text-xs text-zinc-500 mt-1 leading-snug">
                Includes 400g Malai Paneer, ripe tomato puree, ginger garlic paste, whole garam masala & kasuri methi.
              </p>
            </div>
            
            <div class="mt-4 pt-2 border-t border-zinc-100 flex items-center justify-between text-[10px] font-mono text-zinc-500">
              <span>Cook time: 25 mins</span>
              <span class="text-emerald-700 font-bold">Vegetarian</span>
            </div>
          </div>

          <!-- Kit 2: Royal Dum Biryani -->
          <div class="bg-white border-2 border-zinc-300 hover:border-zinc-950 p-4 flex flex-col justify-between transition-colors">
            <div>
              <div class="w-full h-32 bg-[#EAEAEA] border border-zinc-200 mb-3 p-2 bg-stripes flex flex-col justify-between">
                <span class="text-[10px] font-mono font-bold text-zinc-700">biryani kit · serves 6</span>
                <button 
                  @click="addRecipeKit('Royal Dum Biryani Kit', 19.99)"
                  class="self-end px-3 py-1.5 bg-[#E52E04] hover:bg-[#CC2500] text-white text-xs font-black flex items-center gap-1 border border-[#B82200]"
                >
                  <Plus class="w-3.5 h-3.5" />
                  <span>Add Kit</span>
                </button>
              </div>

              <div class="text-lg font-black text-zinc-950 font-mono">$19.99</div>
              <h3 class="font-bold text-sm text-zinc-950 mt-1">Royal Dum Biryani</h3>
              <p class="text-xs text-zinc-500 mt-1 leading-snug">
                Includes Aged Basmati Rice, shahi biryani masala pot, pure cow ghee, saffron strands & fried onions.
              </p>
            </div>
            
            <div class="mt-4 pt-2 border-t border-zinc-100 flex items-center justify-between text-[10px] font-mono text-zinc-500">
              <span>Cook time: 45 mins</span>
              <span class="text-amber-700 font-bold">Chef's Special</span>
            </div>
          </div>

          <!-- Kit 3: Punjabi Chana Masala -->
          <div class="bg-white border-2 border-zinc-300 hover:border-zinc-950 p-4 flex flex-col justify-between transition-colors">
            <div>
              <div class="w-full h-32 bg-[#EAEAEA] border border-zinc-200 mb-3 p-2 bg-stripes flex flex-col justify-between">
                <span class="text-[10px] font-mono font-bold text-zinc-700">chana kit · serves 4</span>
                <button 
                  @click="addRecipeKit('Punjabi Chana Masala Kit', 11.99)"
                  class="self-end px-3 py-1.5 bg-[#E52E04] hover:bg-[#CC2500] text-white text-xs font-black flex items-center gap-1 border border-[#B82200]"
                >
                  <Plus class="w-3.5 h-3.5" />
                  <span>Add Kit</span>
                </button>
              </div>

              <div class="text-lg font-black text-zinc-950 font-mono">$11.99</div>
              <h3 class="font-bold text-sm text-zinc-950 mt-1">Punjabi Chana Masala</h3>
              <p class="text-xs text-zinc-500 mt-1 leading-snug">
                Includes dry kabuli chickpeas, tea bag for dark color, anardana (pomegranate seed) spice blend & green chilies.
              </p>
            </div>
            
            <div class="mt-4 pt-2 border-t border-zinc-100 flex items-center justify-between text-[10px] font-mono text-zinc-500">
              <span>Cook time: 35 mins</span>
              <span class="text-emerald-700 font-bold">High Protein</span>
            </div>
          </div>

          <!-- Kit 4: Dal Tadka & Jeera Rice -->
          <div class="bg-white border-2 border-zinc-300 hover:border-zinc-950 p-4 flex flex-col justify-between transition-colors">
            <div>
              <div class="w-full h-32 bg-[#EAEAEA] border border-zinc-200 mb-3 p-2 bg-stripes flex flex-col justify-between">
                <span class="text-[10px] font-mono font-bold text-zinc-700">dal kit · serves 4</span>
                <button 
                  @click="addRecipeKit('Dal Tadka & Jeera Rice Kit', 10.99)"
                  class="self-end px-3 py-1.5 bg-[#E52E04] hover:bg-[#CC2500] text-white text-xs font-black flex items-center gap-1 border border-[#B82200]"
                >
                  <Plus class="w-3.5 h-3.5" />
                  <span>Add Kit</span>
                </button>
              </div>

              <div class="text-lg font-black text-zinc-950 font-mono">$10.99</div>
              <h3 class="font-bold text-sm text-zinc-950 mt-1">Dal Tadka & Jeera Rice</h3>
              <p class="text-xs text-zinc-500 mt-1 leading-snug">
                Includes yellow Toor Dal, whole cumin seeds, dried Kashmiri chilies, pure ghee & fragrant basmati.
              </p>
            </div>
            
            <div class="mt-4 pt-2 border-t border-zinc-100 flex items-center justify-between text-[10px] font-mono text-zinc-500">
              <span>Cook time: 20 mins</span>
              <span class="text-emerald-700 font-bold">Everyday Comfort</span>
            </div>
          </div>

        </div>
      </section>

      <!-- SECTION: Subscribe & Save Banner Callout -->
      <section class="bg-zinc-950 text-white p-6 sm:p-8 border border-zinc-800 flex flex-col md:flex-row items-center justify-between gap-6">
        <div class="space-y-2">
          <div class="flex items-center gap-2 text-[10px] font-mono text-[#E52E04] font-bold uppercase tracking-wider">
            <span class="w-2 h-2 bg-[#E52E04]"></span>
            <span>AUTOMATIC REPLENISHMENT · ZERO COMMITMENT</span>
          </div>
          <h3 class="text-2xl sm:text-3xl font-black tracking-tight">
            Never run out of Chakki Atta or Basmati again.
          </h3>
          <p class="text-xs text-zinc-400 max-w-xl leading-relaxed">
            Subscribe to monthly essentials and get 5% discount on every bag, plus free prioritized curbside staging. Skip, pause, or cancel anytime in one click.
          </p>
        </div>

        <div class="shrink-0 flex flex-col sm:flex-row gap-3 w-full md:w-auto">
          <Link 
            href="/products/rice" 
            class="px-5 py-3 bg-[#E52E04] hover:bg-[#CC2500] text-white text-xs font-black uppercase tracking-wider text-center border border-[#B82200]"
          >
            Explore Staples (-5%)
          </Link>
          <Link 
            href="/account" 
            class="px-5 py-3 bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold uppercase tracking-wider text-center border border-zinc-700"
          >
            Manage Subscriptions
          </Link>
        </div>
      </section>

    </div>
  </StoreLayout>
</template>
