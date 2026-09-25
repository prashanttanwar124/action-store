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
  Flame
} from 'lucide-vue-next';

const store = useStore();

function addRecipeKit(name, price) {
  store.addToCart({
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
    <div class="space-y-6 sm:space-y-10">

      <!-- HERO SECTION: Split Festival Banner (Page 2: Screen 1a Specification) -->
      <section class="grid grid-cols-1 lg:grid-cols-12 gap-4">
        <!-- Main Festival Pre-Order Banner (Vermilion Red #E52E04) -->
        <div class="lg:col-span-8 bg-[#E52E04] text-white border border-[#B82200] grid grid-cols-12 overflow-hidden shadow-sm min-h-[160px] sm:min-h-[190px]">
          <!-- Banner Copy (7 cols on mobile, exact match to Screen 1a) -->
          <div class="col-span-7 p-3.5 sm:p-7 flex flex-col justify-between">
            <div>
              <div class="text-[9px] sm:text-[10px] font-mono font-bold tracking-wider uppercase text-white/95 leading-tight mb-1">
                FESTIVAL PRE-ORDER · CLOSES OCT 30
              </div>
              <h1 class="text-xl sm:text-3xl lg:text-4xl font-black leading-tight sm:leading-none tracking-tight">
                Diwali<br />sweets,<br />boxed &<br />ready.
              </h1>
            </div>

            <div>
              <Link 
                href="/products/sweets-box" 
                class="inline-flex items-center gap-1.5 text-xs font-black text-white hover:underline mt-2 sm:mt-4 group"
              >
                <span>Pre-order from $24.99</span>
                <ChevronRight class="w-4 h-4 group-hover:translate-x-0.5 transition-transform" />
              </Link>
            </div>
          </div>

          <!-- Banner Visual Texture (5 cols: Diagonal Stripe Box labeled "mithai box") -->
          <div class="col-span-5 relative bg-red-900/40 border-l border-white/20 p-2 sm:p-4 flex flex-col justify-end overflow-hidden bg-stripes-bold">
            <span class="photo-label relative z-10 text-[10px] text-white/95 font-semibold">
              mithai box
            </span>
          </div>
        </div>

        <!-- Desktop Click & Collect Guarantee Card -->
        <div class="hidden lg:flex lg:col-span-4 bg-white border border-zinc-300 p-6 flex-col justify-between">
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
              class="px-3 py-1.5 bg-zinc-100 hover:bg-zinc-200 text-xs font-black text-zinc-950 border border-zinc-300 inline-flex items-center gap-1"
            >
              <span>Start Order</span>
              <ChevronRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </section>

      <!-- Banner Pagination Indicator Dots (Screen 1a match) -->
      <div class="flex items-center gap-1 -mt-3 sm:-mt-6">
        <span class="w-4 h-1 bg-zinc-950"></span>
        <span class="w-2 h-1 bg-zinc-300"></span>
        <span class="w-2 h-1 bg-zinc-300"></span>
      </div>

      <!-- SECTION: "Buy it again" (Screen 1a Specification) -->
      <section class="space-y-3">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-2xl font-black text-zinc-950 tracking-tight">Buy it again</h2>
          <Link 
            href="/reorder" 
            class="text-xs font-bold text-[#E52E04] hover:underline cursor-pointer"
          >
            See all
          </Link>
        </div>

        <!-- Products Carousel / Grid (Horizontal swipe on mobile, 5 cols on desktop) -->
        <div class="flex sm:grid sm:grid-cols-5 gap-2.5 overflow-x-auto no-scrollbar -mx-4 px-4 sm:mx-0 sm:px-0 pb-1 snap-x">
          
          <!-- Item 1: Chakki Atta (with active [- 1 +] red bar) -->
          <div class="w-[130px] sm:w-auto shrink-0 snap-start flex flex-col justify-between bg-white border border-zinc-300 p-2 group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-1.5 flex flex-col justify-between bg-stripes mb-1.5">
                <span class="photo-label text-[10px] text-zinc-700">atta bag</span>
                
                <!-- Bottom red [- 1 +] control matching Screen 1a with Lucide icons -->
                <div class="w-full bg-[#E52E04] text-white h-7 sm:h-8 flex items-center justify-between px-1.5 border border-[#B82200]">
                  <button 
                    @click.stop="store.removeFromCart('atta')" 
                    class="p-0.5 hover:bg-[#CC2500] cursor-pointer flex items-center justify-center"
                    aria-label="Decrease quantity"
                  >
                    <Minus class="w-3.5 h-3.5 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black font-mono">{{ store.getItemQuantity('atta') }}</span>
                  <button 
                    @click.stop="store.addToCart({ id: 'atta', name: 'Aashirvaad Chakki Atta', weight: '20 lb', price: 18.04, originalPrice: 18.99, isSubscribed: true })" 
                    class="p-0.5 hover:bg-[#CC2500] cursor-pointer flex items-center justify-center"
                    aria-label="Increase quantity"
                  >
                    <Plus class="w-3.5 h-3.5 stroke-[3]" />
                  </button>
                </div>
              </div>

              <Link href="/products/rice" class="block text-left">
                <div class="text-sm sm:text-base font-black text-zinc-950 font-mono leading-none">$18.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 truncate">Chakki Atta</div>
                <div class="text-[10px] text-zinc-500 font-mono">20 lb</div>
              </Link>
            </div>
          </div>

          <!-- Item 2: Desi Ghee (with red + button at bottom right) -->
          <div class="w-[130px] sm:w-auto shrink-0 snap-start flex flex-col justify-between bg-white border border-zinc-300 p-2 group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-1.5 flex flex-col justify-between bg-stripes mb-1.5">
                <span class="photo-label text-[10px] text-zinc-700">ghee jar</span>
                
                <button 
                  @click="store.addToCart({ id: 'ghee', name: 'Amul Pure Desi Ghee', weight: '500 ml', price: 9.99, originalPrice: 9.99 })"
                  class="self-end w-7 h-7 sm:w-8 sm:h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer active:scale-95 shadow-xs"
                  aria-label="Add Desi Ghee"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>

              <div class="text-left">
                <div class="text-sm sm:text-base font-black text-zinc-950 font-mono leading-none">$9.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 truncate">Desi Ghee</div>
                <div class="text-[10px] text-zinc-500 font-mono">500 ml</div>
              </div>
            </div>
          </div>

          <!-- Item 3: Glucose Biscuits -->
          <div class="w-[130px] sm:w-auto shrink-0 snap-start flex flex-col justify-between bg-white border border-zinc-300 p-2 group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-1.5 flex flex-col justify-between bg-stripes mb-1.5">
                <span class="photo-label text-[10px] text-zinc-700">biscuits</span>
                
                <button 
                  @click="store.addToCart({ id: 'biscuits', name: 'Parle-G Glucose Biscuits', weight: '800 g', price: 3.49, originalPrice: 3.49 })"
                  class="self-end w-7 h-7 sm:w-8 sm:h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer active:scale-95 shadow-xs"
                  aria-label="Add Biscuits"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>

              <div class="text-left">
                <div class="text-sm sm:text-base font-black text-zinc-950 font-mono leading-none">$3.49</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 truncate">Glucose Biscuits</div>
                <div class="text-[10px] text-zinc-500 font-mono">800 g</div>
              </div>
            </div>
          </div>

          <!-- Item 4: Malai Paneer (Links to /products/paneer) -->
          <div class="w-[130px] sm:w-auto shrink-0 snap-start flex flex-col justify-between bg-white border border-zinc-300 p-2 group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-1.5 flex flex-col justify-between bg-stripes mb-1.5">
                <span class="photo-label text-[10px] text-zinc-700">paneer block</span>
                
                <button 
                  @click="store.addToCart({ id: 'paneer', name: 'Amul Malai Paneer', weight: '400 g', price: 5.99, originalPrice: 5.99 })"
                  class="self-end w-7 h-7 sm:w-8 sm:h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer active:scale-95 shadow-xs"
                  aria-label="Add Malai Paneer"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>

              <Link href="/products/paneer" class="block text-left">
                <div class="text-sm sm:text-base font-black text-zinc-950 font-mono leading-none">$5.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 truncate group-hover:text-[#E52E04]">Malai Paneer</div>
                <div class="text-[10px] text-zinc-500 font-mono">400 g</div>
              </Link>
            </div>
          </div>

          <!-- Item 5: Basmati Rice (Links to /products/rice) -->
          <div class="w-[130px] sm:w-auto shrink-0 snap-start flex flex-col justify-between bg-white border border-zinc-300 p-2 group hover:border-zinc-950 transition-colors">
            <div>
              <div class="relative w-full aspect-square bg-[#EAEAEA] border border-zinc-200 p-1.5 flex flex-col justify-between bg-stripes mb-1.5">
                <span class="photo-label text-[10px] text-zinc-700">rice sack</span>
                
                <button 
                  @click="store.addToCart({ id: 'rice', name: 'Royal Aged Basmati Rice', weight: '20 lb', price: 24.99, originalPrice: 24.99 })"
                  class="self-end w-7 h-7 sm:w-8 sm:h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center border border-[#B82200] font-black cursor-pointer active:scale-95 shadow-xs"
                  aria-label="Add Basmati Rice"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>

              <Link href="/products/rice" class="block text-left">
                <div class="text-sm sm:text-base font-black text-zinc-950 font-mono leading-none">$24.99</div>
                <div class="text-xs font-bold text-zinc-950 mt-1 truncate group-hover:text-[#E52E04]">Basmati Rice</div>
                <div class="text-[10px] text-zinc-500 font-mono">20 lb</div>
              </Link>
            </div>
          </div>

        </div>
      </section>

      <!-- SECTION: "Recipe kits" (Screen 1a Specification) -->
      <section id="recipe-kits" class="space-y-2.5">
        <div class="flex items-center justify-between">
          <h2 class="text-lg sm:text-2xl font-black text-zinc-950 tracking-tight">Recipe kits</h2>
          <span class="text-xs font-bold text-[#E52E04] cursor-pointer">All 14 kits</span>
        </div>
        
        <p class="text-xs text-zinc-500 font-medium">
          Every ingredient for one dish, added in one tap.
        </p>

        <!-- 2 Columns on mobile, 4 Columns on desktop (Screen 1a exact match) -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-2.5 sm:gap-4 pt-1">
          
          <!-- Kit 1: Paneer Butter Masala -->
          <div class="bg-white border border-zinc-300 p-2 sm:p-4 flex flex-col justify-between hover:border-zinc-950 transition-colors">
            <div>
              <div class="w-full h-20 sm:h-32 bg-[#EAEAEA] border border-zinc-200 mb-1.5 p-1.5 bg-stripes flex flex-col justify-between">
                <span class="photo-label text-[9px] sm:text-[10px] text-zinc-700">paneer kit</span>
                <button 
                  @click="addRecipeKit('Paneer Butter Masala Kit', 14.99)"
                  class="self-end w-6 h-6 sm:w-7 sm:h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center active:scale-95 cursor-pointer border border-[#B82200]"
                  aria-label="Add Paneer kit"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
              </div>

              <div>
                <div class="font-black text-xs sm:text-base text-zinc-950 font-mono leading-none">$14.99</div>
                <div class="font-bold text-[11px] sm:text-sm text-zinc-950 mt-1 line-clamp-1">Paneer Butter Masala</div>
              </div>
            </div>
          </div>

          <!-- Kit 2: Royal Dum Biryani -->
          <div class="bg-white border border-zinc-300 p-2 sm:p-4 flex flex-col justify-between hover:border-zinc-950 transition-colors">
            <div>
              <div class="w-full h-20 sm:h-32 bg-[#EAEAEA] border border-zinc-200 mb-1.5 p-1.5 bg-stripes flex flex-col justify-between">
                <span class="photo-label text-[9px] sm:text-[10px] text-zinc-700">biryani kit</span>
                <button 
                  @click="addRecipeKit('Royal Dum Biryani Kit', 19.99)"
                  class="self-end w-6 h-6 sm:w-7 sm:h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center active:scale-95 cursor-pointer border border-[#B82200]"
                  aria-label="Add Biryani kit"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
              </div>

              <div>
                <div class="font-black text-xs sm:text-base text-zinc-950 font-mono leading-none">$19.99</div>
                <div class="font-bold text-[11px] sm:text-sm text-zinc-950 mt-1 line-clamp-1">Royal Dum Biryani</div>
              </div>
            </div>
          </div>

          <!-- Kit 3: Punjabi Chana Masala -->
          <div class="bg-white border border-zinc-300 p-2 sm:p-4 flex flex-col justify-between hover:border-zinc-950 transition-colors">
            <div>
              <div class="w-full h-20 sm:h-32 bg-[#EAEAEA] border border-zinc-200 mb-1.5 p-1.5 bg-stripes flex flex-col justify-between">
                <span class="photo-label text-[9px] sm:text-[10px] text-zinc-700">chana kit</span>
                <button 
                  @click="addRecipeKit('Punjabi Chana Masala Kit', 11.99)"
                  class="self-end w-6 h-6 sm:w-7 sm:h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center active:scale-95 cursor-pointer border border-[#B82200]"
                  aria-label="Add Chana kit"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
              </div>

              <div>
                <div class="font-black text-xs sm:text-base text-zinc-950 font-mono leading-none">$11.99</div>
                <div class="font-bold text-[11px] sm:text-sm text-zinc-950 mt-1 line-clamp-1">Punjabi Chana</div>
              </div>
            </div>
          </div>

          <!-- Kit 4: Dal Tadka & Jeera Rice -->
          <div class="bg-white border border-zinc-300 p-2 sm:p-4 flex flex-col justify-between hover:border-zinc-950 transition-colors">
            <div>
              <div class="w-full h-20 sm:h-32 bg-[#EAEAEA] border border-zinc-200 mb-1.5 p-1.5 bg-stripes flex flex-col justify-between">
                <span class="photo-label text-[9px] sm:text-[10px] text-zinc-700">dal kit</span>
                <button 
                  @click="addRecipeKit('Dal Tadka & Jeera Rice Kit', 10.99)"
                  class="self-end w-6 h-6 sm:w-7 sm:h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center active:scale-95 cursor-pointer border border-[#B82200]"
                  aria-label="Add Dal kit"
                >
                  <Plus class="w-3.5 h-3.5 stroke-[3]" />
                </button>
              </div>

              <div>
                <div class="font-black text-xs sm:text-base text-zinc-950 font-mono leading-none">$10.99</div>
                <div class="font-bold text-[11px] sm:text-sm text-zinc-950 mt-1 line-clamp-1">Dal Tadka & Rice</div>
              </div>
            </div>
          </div>

        </div>
      </section>

      <!-- SECTION: Subscribe & Save Banner Callout -->
      <section class="bg-zinc-950 text-white p-5 sm:p-8 border border-zinc-800 flex flex-col md:flex-row items-start md:items-center justify-between gap-5">
        <div class="space-y-1.5">
          <div class="flex items-center gap-2 text-[10px] font-mono text-[#E52E04] font-bold uppercase tracking-wider">
            <span class="w-2 h-2 bg-[#E52E04]"></span>
            <span>AUTOMATIC REPLENISHMENT · ZERO COMMITMENT</span>
          </div>
          <h3 class="text-xl sm:text-2xl font-black tracking-tight">
            Never run out of Chakki Atta or Basmati again.
          </h3>
          <p class="text-xs text-zinc-400 max-w-xl leading-relaxed">
            Subscribe to monthly essentials and get 5% discount on every bag. Skip, pause, or cancel anytime in one click.
          </p>
        </div>

        <div class="shrink-0 flex flex-row gap-2.5 w-full md:w-auto">
          <Link 
            href="/products/rice" 
            class="flex-1 sm:flex-none px-4 py-2.5 bg-[#E52E04] hover:bg-[#CC2500] text-white text-xs font-black uppercase tracking-wider text-center border border-[#B82200]"
          >
            Explore Staples (-5%)
          </Link>
          <Link 
            href="/account" 
            class="flex-1 sm:flex-none px-4 py-2.5 bg-zinc-800 hover:bg-zinc-700 text-white text-xs font-bold uppercase tracking-wider text-center border border-zinc-700"
          >
            Manage Subscriptions
          </Link>
        </div>
      </section>

    </div>
  </StoreLayout>
</template>
