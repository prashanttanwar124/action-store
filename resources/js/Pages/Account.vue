<script setup>
import { ref, computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '../Layouts/StoreLayout.vue';
import { useStore } from '../stores/cart';
import { 
  ShoppingBag,
  Sparkles, 
  RefreshCw, 
  RotateCcw, 
  ChevronRight, 
  CheckCircle2, 
  LogOut,
  User as UserIcon,
  LogIn,
  UserPlus
} from 'lucide-vue-next';

const props = defineProps({
  dbOrders: {
    type: Array,
    default: () => [],
  },
  activeSection: {
    type: String,
    default: '',
  },
});

const store = useStore();
const page = usePage();
const reorderedNotification = ref(false);
const reorderedItemCount = ref(0);

const authUser = computed(() => page.props.auth?.user);
const userName = computed(() => authUser.value ? authUser.value.name.split(' ')[0] : 'there');

const displayOrders = computed(() => {
  if (props.dbOrders && props.dbOrders.length > 0) {
    return props.dbOrders;
  }
  return store.pastOrders;
});

function handleReorderOrder(order) {
  if (order && Array.isArray(order.items) && order.items.length > 0) {
    order.items.forEach(item => {
      store.addToCart(item, item.quantity || 1);
    });
    reorderedItemCount.value = order.items.length;
  } else {
    store.reorderAll(order?.id);
    reorderedItemCount.value = order?.itemCount || 1;
  }
  reorderedNotification.value = true;
  setTimeout(() => {
    reorderedNotification.value = false;
  }, 2500);
}
</script>

<template>
  <Head title="Customer Dashboard — Masala Mart" />

  <StoreLayout :showHeader="false" :showFooter="false" :showBottomNav="true" :showCartBar="false">
    <div class="space-y-6 sm:space-y-8 max-w-xl mx-auto pb-24 pt-2 sm:pt-4">

      <!-- Toast Feedback -->
      <div 
        v-if="reorderedNotification"
        class="fixed top-16 left-4 right-4 sm:left-auto sm:right-6 sm:top-20 z-50 bg-[#191514] text-white p-3.5 rounded-2xl shadow-2xl flex items-center justify-between text-xs font-semibold"
      >
        <div class="flex items-center gap-2">
          <CheckCircle2 class="w-4 h-4 text-[#F59E0B]" />
          <span>Reordered {{ reorderedItemCount }} item{{ reorderedItemCount > 1 ? 's' : '' }} into your cart!</span>
        </div>
        <Link href="/cart" class="underline text-[#F59E0B] font-semibold inline-flex items-center gap-1">
          <span>View Cart</span>
          <ChevronRight class="w-3.5 h-3.5" />
        </Link>
      </div>

      <!-- Account Header Profile -->
      <div class="flex items-center justify-between pt-1">
        <div>
          <div class="flex items-center gap-2">
            <h1 class="text-2xl sm:text-3xl font-serif font-medium text-[#1d1d1f] tracking-tight">
              {{ authUser ? 'Hi, ' + userName : 'Welcome to Masala Mart' }}
            </h1>
            <span v-if="authUser" class="w-2 h-2 rounded-full bg-emerald-500" title="Signed in"></span>
          </div>
          <p v-if="authUser" class="text-xs text-[#6e6e73] font-normal mt-0.5">
            {{ authUser.email }} · Customer Account
          </p>
          <p v-else class="text-xs text-[#86868b] font-normal mt-0.5 flex items-center gap-1.5">
            <span>Guest Preview</span>
            <span>·</span>
            <Link href="/login" class="text-[#a47a3c] font-semibold hover:underline">Sign In</Link>
            <span>or</span>
            <Link href="/register" class="text-[#a47a3c] font-semibold hover:underline">Register</Link>
          </p>
        </div>

        <div class="flex items-center gap-2">
          <Link
            v-if="authUser"
            href="/logout"
            method="post"
            as="button"
            class="hidden sm:inline-flex items-center gap-1.5 px-3 py-1.5 rounded-full border border-[#e0d9cc] bg-white hover:bg-rose-50 hover:text-rose-600 hover:border-rose-200 text-xs font-semibold text-[#6e6e73] transition-colors cursor-pointer"
            title="Sign out of your account"
          >
            <LogOut class="w-3.5 h-3.5" />
            <span>Sign Out</span>
          </Link>

          <Link 
            href="/cart" 
            class="relative w-10 h-10 rounded-2xl bg-white border border-[#e0d9cc] flex items-center justify-center text-[#1d1d1f] hover:bg-[#f3efe7] transition-colors shadow-xs"
            aria-label="Cart"
          >
            <ShoppingBag class="w-5 h-5 stroke-[2] text-[#1d1d1f]" />
            <span 
              v-if="store.totalItemCount > 0"
              class="absolute -top-1.5 -right-1.5 bg-[#1a1a1a] text-white text-[11px] font-bold w-5 h-5 rounded-full flex items-center justify-center border-2 border-white shadow-xs"
            >
              {{ store.totalItemCount }}
            </span>
          </Link>
        </div>
      </div>

      <!-- Guest Welcome Banner if not logged in -->
      <div 
        v-if="!authUser" 
        class="bg-[#f5eee2] border border-[#e0d9cc] rounded-3xl p-4 sm:p-5 flex flex-col sm:flex-row sm:items-center justify-between gap-3.5 shadow-xs"
      >
        <div class="space-y-1">
          <div class="flex items-center gap-2">
            <Sparkles class="w-4 h-4 text-[#a47a3c]" />
            <span class="text-xs font-bold text-[#1d1d1f] uppercase tracking-wider">
              Unlock Your Masala Rewards
            </span>
          </div>
          <p class="text-xs text-[#6e6e73] leading-relaxed">
            Sign in to track orders and earn 1 point per $1 spent on fresh Indian groceries.
          </p>
        </div>
        <div class="flex items-center gap-2 shrink-0">
          <Link
            href="/login"
            class="px-4 py-2 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full transition-colors shadow-xs"
          >
            Sign In
          </Link>
          <Link
            href="/register"
            class="px-4 py-2 bg-white hover:bg-[#f3efe7] text-[#1d1d1f] border border-[#e0d9cc] text-xs font-semibold rounded-full transition-colors shadow-xs"
          >
            Create Account
          </Link>
        </div>
      </div>

      <!-- SECTION 1: Masala Points Reward Meter (Screen 1f Exact Match) -->
      <div class="bg-[#1a1a1a] text-white p-5 sm:p-6 rounded-3xl shadow-sm space-y-3.5 border border-[#e0d9cc]/20">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-semibold uppercase tracking-wider text-[#a47a3c]">
            MASALA REWARDS
          </span>
          <span class="text-xs text-stone-300 font-normal">
            Gold Member
          </span>
        </div>

        <div class="flex items-baseline">
          <span class="text-3xl sm:text-4xl font-serif font-medium tracking-tight text-white leading-none">
            {{ store.masalaPoints.toLocaleString() }}
          </span>
          <span class="text-sm text-stone-400 font-normal ml-1.5">pts</span>
        </div>

        <!-- 10-Segment Progress Meter -->
        <div class="grid grid-cols-10 gap-1.5 pt-1">
          <div 
            v-for="i in 10" 
            :key="i"
            :class="[
              'h-2 rounded-full transition-colors',
              i <= 7 ? 'bg-[#a47a3c]' : 'bg-stone-800'
            ]"
          ></div>
        </div>

        <div class="flex justify-between text-xs text-stone-400 font-normal pt-0.5">
          <span>260 pts to a $10 reward</span>
          <span>1 pt per $1</span>
        </div>
      </div>



      <!-- SECTION 3: Past Orders -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
          <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Past orders</h2>
            <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">पिछला ऑर्डर</span>
          </div>
          <span v-if="displayOrders.length > 0" class="text-xs font-normal text-[#6e6e73]">
            {{ displayOrders.length }} order{{ displayOrders.length > 1 ? 's' : '' }}
          </span>
        </div>

        <!-- Dynamic Past Orders List or Empty State -->
        <div v-if="displayOrders.length > 0" class="space-y-4">
          <div 
            v-for="order in displayOrders" 
            :key="order.id"
            class="rounded-3xl border border-[#e0d9cc] bg-white p-5 space-y-4 shadow-xs"
          >
            <div class="flex items-center justify-between">
              <div class="flex flex-wrap items-center gap-2">
                <span class="font-bold text-sm text-[#1d1d1f]">{{ order.date }} · {{ order.type }}</span>
                <span 
                  v-if="order.status"
                  class="inline-flex items-center gap-1 px-2.5 py-0.5 rounded-full text-[10px] font-semibold"
                  :class="[
                    order.status === 'confirmed' ? 'bg-amber-100 text-amber-800' :
                    order.status === 'packing' ? 'bg-blue-100 text-blue-800' :
                    order.status === 'ready_for_pickup' ? 'bg-emerald-100 text-emerald-800 animate-pulse' :
                    'bg-zinc-100 text-zinc-700'
                  ]"
                >
                  <span v-if="order.status !== 'completed'" class="w-1.5 h-1.5 rounded-full bg-current"></span>
                  {{ 
                    order.status === 'confirmed' ? 'Received' :
                    order.status === 'packing' ? 'Packing' :
                    order.status === 'ready_for_pickup' ? 'Ready for Pickup' :
                    (order.status === 'completed' ? 'Completed' : order.status)
                  }}
                </span>
              </div>
              <span class="font-serif font-medium text-base text-[#1d1d1f]">${{ Number(order.total).toFixed(2) }}</span>
            </div>

            <div class="text-xs text-[#6e6e73] font-normal leading-relaxed">
              {{ order.id }} · {{ order.summary }}
            </div>

            <!-- Product Thumbnails Row if items are attached -->
            <div v-if="order.items && order.items.length > 0" class="flex items-center gap-2 overflow-x-auto no-scrollbar py-1">
              <div 
                v-for="item in order.items.slice(0, 6)" 
                :key="item.id"
                class="w-10 h-10 rounded-2xl bg-[#f3efe7] overflow-hidden shrink-0 border border-[#e0d9cc]"
                :title="item.name"
              >
                <img :src="item.image || '/images/products/atta.jpg'" :alt="item.name" class="w-full h-full object-cover" />
              </div>
            </div>

            <!-- Actions Row: Track Live & Reorder Button -->
            <div class="pt-1 flex items-center gap-2">
              <Link 
                :href="'/orders/' + encodeURIComponent(order.id)"
                class="flex-1 h-11 bg-[#a47a3c] hover:bg-[#8a6b32] text-white font-semibold text-xs rounded-full transition-colors flex items-center justify-center gap-1.5 cursor-pointer shadow-xs active:scale-[0.99]"
              >
                <Sparkles class="w-3.5 h-3.5" />
                <span>Track Live</span>
              </Link>
              <button 
                @click="handleReorderOrder(order)"
                class="flex-1 h-11 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full transition-colors flex items-center justify-center gap-2 cursor-pointer shadow-sm active:scale-[0.99]"
              >
                <RotateCcw class="w-3.5 h-3.5 stroke-[2.2]" />
                <span>Reorder ({{ order.itemCount }})</span>
              </button>
            </div>
          </div>
        </div>

        <!-- Empty Past Orders State -->
        <div v-else class="p-6 rounded-3xl border border-[#e0d9cc] bg-white text-center space-y-2.5 shadow-2xs">
          <div class="text-sm font-semibold text-[#1d1d1f]">No past orders yet</div>
          <p class="text-xs text-[#6e6e73] max-w-sm mx-auto">
            When you complete pickup orders, your order details and quick reorder buttons will appear here.
          </p>
          <div class="pt-1">
            <Link href="/" class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#a47a3c] hover:underline">
              <span>Start Shopping</span>
              <ChevronRight class="w-3.5 h-3.5" />
            </Link>
          </div>
        </div>
      </div>

      <!-- SECTION 4: Account & Security Management -->
      <div class="space-y-4">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-2">
          <div class="flex items-center gap-2">
            <h2 class="text-lg sm:text-xl font-serif font-medium text-[#1d1d1f] tracking-tight">Account & Security</h2>
            <span class="font-devanagari text-xs text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">खाता</span>
          </div>
        </div>

        <div class="rounded-3xl border border-[#e0d9cc] bg-white p-5 space-y-4 shadow-xs">
          <div class="flex items-center justify-between">
            <div class="flex items-center gap-3">
              <div class="w-10 h-10 rounded-full bg-[#f3efe7] border border-[#e0d9cc] flex items-center justify-center text-[#1a1a1a] font-bold text-sm">
                {{ authUser ? authUser.name.charAt(0).toUpperCase() : 'G' }}
              </div>
              <div>
                <div class="font-bold text-sm text-[#1d1d1f]">
                  {{ authUser ? authUser.name : 'Guest Customer' }}
                </div>
                <div class="text-xs text-[#6e6e73]">
                  {{ authUser ? authUser.email : 'Not signed in · Guest Mode' }}
                </div>
              </div>
            </div>

            <span 
              v-if="authUser" 
              class="text-[11px] font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-2.5 py-0.5 rounded-full"
            >
              Active Session
            </span>
            <span 
              v-else 
              class="text-[11px] font-semibold text-amber-700 bg-amber-50 border border-amber-200 px-2.5 py-0.5 rounded-full"
            >
              Guest Mode
            </span>
          </div>

          <div class="pt-2 border-t border-[#e0d9cc]/60 flex flex-col sm:flex-row items-stretch sm:items-center gap-2.5">
            <template v-if="authUser">
              <Link
                href="/profile"
                class="flex-1 py-2.5 bg-[#f5eee2] hover:bg-[#ede3d1] text-[#1d1d1f] text-xs font-semibold rounded-full transition-colors border border-[#e0d9cc] text-center"
              >
                Edit Profile & Password
              </Link>
              <Link
                href="/logout"
                method="post"
                as="button"
                class="flex-1 py-2.5 bg-rose-50 hover:bg-rose-100 text-rose-700 border border-rose-200 text-xs font-semibold rounded-full transition-colors cursor-pointer text-center flex items-center justify-center gap-1.5"
              >
                <LogOut class="w-3.5 h-3.5" />
                <span>Sign Out</span>
              </Link>
            </template>
            <template v-else>
              <Link
                href="/login"
                class="flex-1 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-full transition-colors text-center shadow-xs"
              >
                Sign In to Account
              </Link>
              <Link
                href="/register"
                class="flex-1 py-2.5 bg-white hover:bg-[#f3efe7] text-[#1d1d1f] border border-[#e0d9cc] text-xs font-semibold rounded-full transition-colors text-center shadow-xs"
              >
                Create New Account
              </Link>
            </template>
          </div>
        </div>
      </div>

    </div>
  </StoreLayout>
</template>
