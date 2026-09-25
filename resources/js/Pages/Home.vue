<script setup>
import { ref } from 'vue';
import { Head, Link } from '@inertiajs/vue3';
import MobileWebLayout from '../Layouts/MobileWebLayout.vue';
import { useStore } from '../stores/cart';
import { Search, ShoppingBag, ChevronDown, ChevronRight, Plus, Minus, X } from 'lucide-vue-next';

const props = defineProps({
  focusSearch: Boolean,
});

const store = useStore();
const activeSlide = ref(0);
</script>

<template>
  <Head title="Masala Mart — Home / Storefront" />

  <MobileWebLayout :showBottomNav="true" :showCartBar="true">
    <!-- Header: Store Title, Location & Cart Icon (Page 2: 1a) -->
    <div class="px-4 pt-3 pb-2 flex items-start justify-between bg-white">
      <div>
        <h1 class="text-2xl font-black tracking-tight text-zinc-950 leading-none">
          Masala Mart
        </h1>
        <div class="flex items-center gap-1 text-xs text-zinc-600 mt-1 cursor-pointer">
          <span class="font-medium text-zinc-700">
            Pickup · {{ store.selectedStore }} · {{ store.readyTime }}
          </span>
          <ChevronDown class="w-3.5 h-3.5 text-zinc-500" />
        </div>
      </div>

      <!-- Square Cart Icon with Red Badge (Links to /cart) -->
      <Link 
        href="/cart"
        class="relative p-2 border-2 border-zinc-950 bg-white text-zinc-950 hover:bg-zinc-100 rounded-none cursor-pointer"
        aria-label="View Shopping Cart"
      >
        <ShoppingBag class="w-5 h-5 stroke-[2.2]" />
        <span 
          v-if="store.totalItemCount > 0"
          class="absolute -top-1.5 -right-1.5 bg-[#E52E04] text-white text-[11px] font-black w-5 h-5 rounded-none flex items-center justify-center border border-white animate-pop"
        >
          {{ store.totalItemCount }}
        </span>
      </Link>
    </div>

    <!-- Pinned Search Bar (Screen 1a) -->
    <div class="px-4 py-2 sticky top-[41px] z-30 bg-white border-b border-zinc-200">
      <div class="relative flex items-center">
        <Search class="absolute left-3 w-4 h-4 text-zinc-600 pointer-events-none stroke-[2.5]" />
        <input 
          v-model="store.searchQuery"
          :autofocus="focusSearch"
          type="text" 
          placeholder="Search paneer, atta, curry leaves…"
          class="w-full pl-9 pr-3 py-2 bg-zinc-100 border border-zinc-400 rounded-none text-xs text-zinc-950 placeholder-zinc-500 focus:outline-none focus:ring-1 focus:ring-zinc-950 focus:bg-white"
        />
        <button 
          v-if="store.searchQuery" 
          @click="store.searchQuery = ''"
          class="absolute right-2 text-zinc-400 hover:text-zinc-600"
        >
          <X class="w-4 h-4" />
        </button>
      </div>
    </div>

    <!-- Main Storefront Content -->
    <div class="px-4 py-3 space-y-6">

      <!-- Festival Pre-Order Banner (Page 2: 1a) -->
      <div>
        <div class="bg-[#E52E04] text-white grid grid-cols-12 min-h-[165px] border border-[#B82200] overflow-hidden rounded-none relative">
          <!-- Left Content -->
          <div class="col-span-7 p-3.5 flex flex-col justify-between">
            <div>
              <div class="text-[9px] font-mono font-bold tracking-wider uppercase text-white/95 leading-tight mb-1">
                FESTIVAL PRE-ORDER · CLOSES OCT 30
              </div>
              <h2 class="text-xl font-black leading-tight tracking-tight">
                Diwali<br />sweets,<br />boxed &<br />ready.
              </h2>
            </div>

            <Link 
              href="/products/sweets-box"
              class="inline-flex items-center gap-1 text-xs font-black text-white hover:underline mt-2"
            >
              <span>Pre-order from $24.99</span>
              <ChevronRight class="w-3.5 h-3.5 stroke-[3]" />
            </Link>
          </div>

          <!-- Right Texture Box with label -->
          <div class="col-span-5 relative bg-red-900/40 border-l border-white/20 flex flex-col justify-end p-2 overflow-hidden">
            <!-- Diagonal stripes -->
            <div class="absolute inset-0 bg-[linear-gradient(135deg,rgba(255,255,255,0.2)_25%,transparent_25%,transparent_50%,rgba(255,255,255,0.2)_50%,rgba(255,255,255,0.2)_75%,transparent_75%,transparent)] bg-[length:16px_16px] opacity-80 pointer-events-none"></div>
            
            <img 
              src="https://images.unsplash.com/photo-1599785209707-a456fc1337bb?w=400&auto=format&fit=crop&q=80" 
              alt="Mithai box" 
              class="absolute inset-0 w-full h-full object-cover mix-blend-overlay opacity-60"
            />

            <span class="relative z-10 text-[10px] font-mono text-white/95">
              mithai box
            </span>
          </div>
        </div>

        <!-- Carousel Dots -->
        <div class="flex items-center gap-1 mt-2">
          <span class="w-4 h-1 bg-zinc-950"></span>
          <span class="w-2 h-1 bg-zinc-300"></span>
          <span class="w-2 h-1 bg-zinc-300"></span>
        </div>
      </div>

      <!-- Buy It Again Section (Exact match to Page 2: 1a) -->
      <div>
        <div class="flex items-center justify-between mb-2.5">
          <h3 class="text-lg font-black text-zinc-950 tracking-tight">Buy it again</h3>
          <Link 
            href="/reorder"
            class="text-xs font-bold text-[#E52E04] hover:text-[#B82200] transition-colors"
          >
            See all
          </Link>
        </div>

        <!-- Horizontal Scroll Cards -->
        <div class="flex gap-2.5 overflow-x-auto no-scrollbar pb-1 -mx-4 px-4 snap-x">
          
          <!-- Card 1: Atta -->
          <div class="w-[125px] shrink-0 snap-start flex flex-col">
            <div class="relative w-full h-[135px] bg-[#EAEAEA] border border-zinc-300 flex flex-col justify-between p-1.5 rounded-none">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:10px_10px] pointer-events-none"></div>
              
              <Link href="/products/atta" class="relative z-10 text-[10px] font-mono text-zinc-700 hover:underline">
                atta bag
              </Link>

              <!-- Red bar quantity control (- 1 +) -->
              <div class="relative z-10 mt-auto w-full">
                <div 
                  v-if="store.getQuantity(2) > 0"
                  class="w-full bg-[#E52E04] text-white h-8 flex items-center justify-between px-2 rounded-none border border-[#B82200]"
                >
                  <button @click="store.removeFromCart(2)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Minus class="w-3 h-3 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black">{{ store.getQuantity(2) }}</span>
                  <button @click="store.addToCart(2)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Plus class="w-3 h-3 stroke-[3]" />
                  </button>
                </div>
                <button 
                  v-else
                  @click="store.addToCart(2)"
                  class="ml-auto w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer border border-[#B82200]"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>
            </div>

            <Link href="/products/atta" class="mt-1.5 text-left block">
              <div class="text-sm font-black text-zinc-950 tracking-tight leading-none">$18.99</div>
              <div class="text-xs font-bold text-zinc-950 mt-0.5 truncate">Chakki Atta</div>
              <div class="text-[10px] text-zinc-500 font-mono">20 lb</div>
            </Link>
          </div>

          <!-- Card 2: Ghee -->
          <div class="w-[125px] shrink-0 snap-start flex flex-col">
            <div class="relative w-full h-[135px] bg-[#EAEAEA] border border-zinc-300 flex flex-col justify-between p-1.5 rounded-none">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:10px_10px] pointer-events-none"></div>
              
              <Link href="/products/ghee" class="relative z-10 text-[10px] font-mono text-zinc-700 hover:underline">
                ghee jar
              </Link>

              <div class="relative z-10 mt-auto w-full">
                <div 
                  v-if="store.getQuantity(3) > 0"
                  class="w-full bg-[#E52E04] text-white h-8 flex items-center justify-between px-2 rounded-none border border-[#B82200]"
                >
                  <button @click="store.removeFromCart(3)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Minus class="w-3 h-3 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black">{{ store.getQuantity(3) }}</span>
                  <button @click="store.addToCart(3)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Plus class="w-3 h-3 stroke-[3]" />
                  </button>
                </div>
                <button 
                  v-else
                  @click="store.addToCart(3)"
                  class="ml-auto w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer border border-[#B82200]"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>
            </div>

            <Link href="/products/ghee" class="mt-1.5 text-left block">
              <div class="text-sm font-black text-zinc-950 tracking-tight leading-none">$9.99</div>
              <div class="text-xs font-bold text-zinc-950 mt-0.5 truncate">Desi Ghee</div>
              <div class="text-[10px] text-zinc-500 font-mono">500 ml</div>
            </Link>
          </div>

          <!-- Card 3: Biscuits -->
          <div class="w-[125px] shrink-0 snap-start flex flex-col">
            <div class="relative w-full h-[135px] bg-[#EAEAEA] border border-zinc-300 flex flex-col justify-between p-1.5 rounded-none">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:10px_10px] pointer-events-none"></div>
              
              <Link href="/products/biscuits" class="relative z-10 text-[10px] font-mono text-zinc-700 hover:underline">
                biscuits
              </Link>

              <div class="relative z-10 mt-auto w-full">
                <div 
                  v-if="store.getQuantity(4) > 0"
                  class="w-full bg-[#E52E04] text-white h-8 flex items-center justify-between px-2 rounded-none border border-[#B82200]"
                >
                  <button @click="store.removeFromCart(4)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Minus class="w-3 h-3 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black">{{ store.getQuantity(4) }}</span>
                  <button @click="store.addToCart(4)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Plus class="w-3 h-3 stroke-[3]" />
                  </button>
                </div>
                <button 
                  v-else
                  @click="store.addToCart(4)"
                  class="ml-auto w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer border border-[#B82200]"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>
            </div>

            <Link href="/products/biscuits" class="mt-1.5 text-left block">
              <div class="text-sm font-black text-zinc-950 tracking-tight leading-none">$3.49</div>
              <div class="text-xs font-bold text-zinc-950 mt-0.5 truncate">Glucose Biscuits</div>
              <div class="text-[10px] text-zinc-500 font-mono">800 g</div>
            </Link>
          </div>

          <!-- Card 4: Malai Paneer (Links to /products/paneer) -->
          <div class="w-[125px] shrink-0 snap-start flex flex-col">
            <div class="relative w-full h-[135px] bg-[#EAEAEA] border border-zinc-300 flex flex-col justify-between p-1.5 rounded-none">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:10px_10px] pointer-events-none"></div>
              
              <Link href="/products/paneer" class="relative z-10 text-[10px] font-mono text-zinc-700 hover:underline">
                paneer block
              </Link>

              <div class="relative z-10 mt-auto w-full">
                <div 
                  v-if="store.getQuantity(5) > 0"
                  class="w-full bg-[#E52E04] text-white h-8 flex items-center justify-between px-2 rounded-none border border-[#B82200]"
                >
                  <button @click="store.removeFromCart(5)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Minus class="w-3 h-3 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black">{{ store.getQuantity(5) }}</span>
                  <button @click="store.addToCart(5)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Plus class="w-3 h-3 stroke-[3]" />
                  </button>
                </div>
                <button 
                  v-else
                  @click="store.addToCart(5)"
                  class="ml-auto w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer border border-[#B82200]"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>
            </div>

            <Link href="/products/paneer" class="mt-1.5 text-left block">
              <div class="text-sm font-black text-zinc-950 tracking-tight leading-none">$5.99</div>
              <div class="text-xs font-bold text-zinc-950 mt-0.5 truncate">Malai Paneer</div>
              <div class="text-[10px] text-zinc-500 font-mono">400 g</div>
            </Link>
          </div>

          <!-- Card 5: Basmati Rice (Links to /products/rice) -->
          <div class="w-[125px] shrink-0 snap-start flex flex-col">
            <div class="relative w-full h-[135px] bg-[#EAEAEA] border border-zinc-300 flex flex-col justify-between p-1.5 rounded-none">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:10px_10px] pointer-events-none"></div>
              
              <Link href="/products/rice" class="relative z-10 text-[10px] font-mono text-zinc-700 hover:underline">
                rice sack
              </Link>

              <div class="relative z-10 mt-auto w-full">
                <div 
                  v-if="store.getQuantity(7) > 0"
                  class="w-full bg-[#E52E04] text-white h-8 flex items-center justify-between px-2 rounded-none border border-[#B82200]"
                >
                  <button @click="store.removeFromCart(7)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Minus class="w-3 h-3 stroke-[3]" />
                  </button>
                  <span class="text-xs font-black">{{ store.getQuantity(7) }}</span>
                  <button @click="store.addToCart(7)" class="hover:bg-black/20 p-0.5 cursor-pointer">
                    <Plus class="w-3 h-3 stroke-[3]" />
                  </button>
                </div>
                <button 
                  v-else
                  @click="store.addToCart(7)"
                  class="ml-auto w-8 h-8 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer border border-[#B82200]"
                >
                  <Plus class="w-4 h-4 stroke-[3]" />
                </button>
              </div>
            </div>

            <Link href="/products/rice" class="mt-1.5 text-left block">
              <div class="text-sm font-black text-zinc-950 tracking-tight leading-none">$24.99</div>
              <div class="text-xs font-bold text-zinc-950 mt-0.5 truncate">Basmati Rice</div>
              <div class="text-[10px] text-zinc-500 font-mono">20 lb</div>
            </Link>
          </div>

        </div>
      </div>

      <!-- Recipe Kits Section (Exact match to Page 2: 1a) -->
      <div>
        <div class="flex items-center justify-between mb-1">
          <h3 class="text-lg font-black text-zinc-950 tracking-tight">Recipe kits</h3>
          <span class="text-xs font-bold text-[#E52E04] cursor-pointer">All 14 kits</span>
        </div>
        <p class="text-xs text-zinc-500 font-medium mb-2.5">
          Every ingredient for one dish, added in one tap.
        </p>

        <div class="grid grid-cols-2 gap-2.5">
          <div 
            v-for="kit in store.recipeKits.slice(0, 2)" 
            :key="kit.id"
            class="bg-white border border-zinc-300 p-2 rounded-none flex flex-col justify-between"
          >
            <div class="relative w-full h-[95px] bg-[#EAEAEA] border border-zinc-200 mb-2 p-1.5 flex flex-col justify-between">
              <div class="absolute inset-0 bg-[linear-gradient(45deg,rgba(0,0,0,0.04)_25%,transparent_25%,transparent_50%,rgba(0,0,0,0.04)_50%,rgba(0,0,0,0.04)_75%,transparent_75%,transparent)] bg-[length:8px_8px] pointer-events-none"></div>
              <span class="relative z-10 text-[9px] font-mono text-zinc-700">{{ kit.label }}</span>
              <button 
                @click="store.addToCart(kit.id)"
                class="relative z-10 self-end w-7 h-7 bg-[#E52E04] hover:bg-[#CC2500] text-white flex items-center justify-center rounded-none cursor-pointer"
              >
                <Plus class="w-4 h-4 stroke-[3]" />
              </button>
            </div>
            <div>
              <div class="font-black text-sm text-zinc-950">${{ kit.price.toFixed(2) }}</div>
              <div class="font-bold text-xs text-zinc-950 leading-snug line-clamp-1">{{ kit.name }}</div>
              <div class="text-[10px] text-zinc-500">{{ kit.servings }}</div>
            </div>
          </div>
        </div>
      </div>

    </div>
  </MobileWebLayout>
</template>
