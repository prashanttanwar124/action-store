<script setup>
import { Head } from '@inertiajs/vue3';
import { useStore } from '../stores/cart';
import HeaderNav from '../Components/HeaderNav.vue';
import FestivalBanner from '../Components/FestivalBanner.vue';
import BuyAgainSection from '../Components/BuyAgainSection.vue';
import RecipeKitsSection from '../Components/RecipeKitsSection.vue';
import CatalogGrid from '../Components/CatalogGrid.vue';
import ReorderView from '../Components/ReorderView.vue';
import AccountView from '../Components/AccountView.vue';
import BottomCartBar from '../Components/BottomCartBar.vue';
import BottomNavigation from '../Components/BottomNavigation.vue';
import CartDrawer from '../Components/CartDrawer.vue';
import ProductDetailModal from '../Components/ProductDetailModal.vue';
import Footer from '../Components/Footer.vue';

const store = useStore();
</script>

<template>
  <Head title="Masala Mart - Indian Grocery Express Store" />

  <div class="min-h-screen bg-zinc-100 flex flex-col antialiased text-zinc-900 selection:bg-[#E52E04] selection:text-white">
    <!-- Header -->
    <HeaderNav />

    <!-- Main Content Body -->
    <main class="flex-1 w-full pb-16 md:pb-8">
      <template v-if="store.activeTab === 'shop'">
        <!-- Hero Showcase Banner -->
        <FestivalBanner />

        <!-- Buy It Again Section (Exact match to prompt image with red controls) -->
        <BuyAgainSection />

        <!-- Recipe Kits Section (Exact match to prompt image) -->
        <RecipeKitsSection />

        <!-- Responsive Catalog Grid -->
        <CatalogGrid />
      </template>

      <template v-else-if="store.activeTab === 'search'">
        <CatalogGrid />
      </template>

      <template v-else-if="store.activeTab === 'reorder'">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <ReorderView />
        </div>
      </template>

      <template v-else-if="store.activeTab === 'account'">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 py-6">
          <AccountView />
        </div>
      </template>
    </main>

    <!-- Web Application Footer -->
    <Footer />

    <!-- Floating Quick Cart Bar (Mobile/Tablet view) -->
    <BottomCartBar />

    <!-- Mobile Bottom Navigation (Hidden on desktop md:) -->
    <div class="block md:hidden">
      <BottomNavigation />
    </div>

    <!-- Product Detail Modal / View (Screens 2 & 3: Malai Paneer & Basmati Rice) -->
    <ProductDetailModal />

    <!-- Slide-Over Shadcn Vue Cart Drawer -->
    <CartDrawer />
  </div>
</template>
