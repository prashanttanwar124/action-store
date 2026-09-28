<script setup>
import { computed } from 'vue';
import { Head, Link, usePage } from '@inertiajs/vue3';
import StoreLayout from '@/Layouts/StoreLayout.vue';
import UpdateProfileInformationForm from './Partials/UpdateProfileInformationForm.vue';
import UpdatePasswordForm from './Partials/UpdatePasswordForm.vue';
import DeleteUserForm from './Partials/DeleteUserForm.vue';
import { 
  ArrowLeft, 
  User, 
  Sparkles, 
  ShieldCheck, 
  LogOut, 
  KeyRound, 
  AlertTriangle 
} from 'lucide-vue-next';
import { useStore } from '@/stores/cart';

defineProps({
  mustVerifyEmail: {
    type: Boolean,
    default: false,
  },
  status: {
    type: String,
    default: '',
  },
});

const store = useStore();
const page = usePage();
const authUser = computed(() => page.props.auth?.user);
const userInitial = computed(() => {
  if (!authUser.value?.name) return 'U';
  return authUser.value.name.charAt(0).toUpperCase();
});
</script>

<template>
  <Head title="Profile Settings — Masala Mart" />

  <StoreLayout 
    :showHeader="true" 
    :showFooter="true" 
    :showBottomNav="true" 
    :showCartBar="false"
    headerMode="cart"
    headerTitle="Profile & Security"
    backUrl="/account"
  >
    <div class="max-w-2xl mx-auto px-4 sm:px-6 space-y-6 sm:space-y-8 pb-28 pt-4 sm:pt-6">

      <!-- Breadcrumbs & Navigation -->
      <div class="flex items-center justify-between">
        <Link 
          href="/account"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] bg-white border border-[#e0d9cc] px-3.5 py-1.5 rounded-full transition-colors shadow-2xs group"
        >
          <ArrowLeft class="w-3.5 h-3.5 text-[#86868b] group-hover:-translate-x-0.5 transition-transform" />
          <span>Back to Account Dashboard</span>
        </Link>

        <Link
          href="/logout"
          method="post"
          as="button"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-rose-700 hover:text-rose-800 bg-rose-50 hover:bg-rose-100 border border-rose-200 px-3 py-1.5 rounded-full transition-colors cursor-pointer"
        >
          <LogOut class="w-3.5 h-3.5" />
          <span>Sign Out</span>
        </Link>
      </div>

      <!-- Profile Hero Card -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl p-5 sm:p-6 shadow-xs flex flex-col sm:flex-row sm:items-center justify-between gap-4">
        <div class="flex items-center gap-3.5 sm:gap-4">
          <!-- Avatar Initial Circle -->
          <div class="relative w-14 h-14 sm:w-16 sm:h-16 rounded-2xl bg-[#f5eee2] border border-[#dfd6c8] flex items-center justify-center text-[#7a5620] font-serif font-bold text-2xl shadow-xs shrink-0">
            <span>{{ userInitial }}</span>
            <span class="absolute -bottom-1 -right-1 w-4 h-4 rounded-full bg-emerald-500 border-2 border-white" title="Active Account"></span>
          </div>

          <!-- User Details -->
          <div class="space-y-0.5 min-w-0">
            <div class="flex items-center gap-2">
              <h1 class="text-xl sm:text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight truncate">
                {{ authUser?.name || 'Customer' }}
              </h1>
              <span class="font-devanagari text-[10.5px] text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc] shrink-0 hidden sm:inline-block">
                खाता
              </span>
            </div>
            <p class="text-xs text-[#6e6e73] truncate">
              {{ authUser?.email || '' }}
            </p>
            <div class="flex items-center gap-2 pt-1 text-[11px] text-[#86868b]">
              <span class="inline-flex items-center gap-1 font-semibold text-[#7a5620]">
                <Sparkles class="w-3 h-3 text-[#a47a3c]" />
                <span>{{ store.masalaPoints.toLocaleString() }} Masala Points</span>
              </span>
              <span>·</span>
              <span class="text-stone-500">Gold Member</span>
            </div>
          </div>
        </div>

        <!-- Quick Tag -->
        <div class="sm:text-right shrink-0 pt-2 sm:pt-0 border-t sm:border-t-0 border-[#e0d9cc]/60">
          <div class="inline-flex items-center gap-1.5 px-3 py-1 rounded-full bg-[#f4efe6] border border-[#dfd6c8] text-[#1d1d1f] text-xs font-semibold">
            <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
            <span>Verified Customer</span>
          </div>
        </div>
      </div>

      <!-- SECTION 1: Personal Information -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-xs space-y-5">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-3">
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-serif font-medium text-[#1d1d1f] tracking-tight">
                Personal Information
              </h2>
              <span class="font-devanagari text-[10px] text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
                व्यक्तिगत
              </span>
            </div>
            <p class="text-xs text-[#6e6e73]">
              Update your account's display name and primary email address.
            </p>
          </div>
        </div>

        <UpdateProfileInformationForm
          :must-verify-email="mustVerifyEmail"
          :status="status"
        />
      </div>

      <!-- SECTION 2: Security & Password -->
      <div class="bg-white border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-xs space-y-5">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-3">
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-serif font-medium text-[#1d1d1f] tracking-tight">
                Password & Security
              </h2>
              <span class="font-devanagari text-[10px] text-[#7a5620] bg-[#f5eee2] px-2 py-0.5 rounded-full border border-[#e0d9cc]">
                सुरक्षा
              </span>
            </div>
            <p class="text-xs text-[#6e6e73]">
              Ensure your account is using a long, secure password to protect your orders.
            </p>
          </div>
        </div>

        <UpdatePasswordForm />
      </div>

      <!-- SECTION 3: Account Deletion (Danger Zone) -->
      <div class="bg-[#fbf9f5] border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 shadow-xs space-y-4">
        <div class="flex items-center justify-between border-b border-[#e0d9cc]/60 pb-3">
          <div class="space-y-0.5">
            <div class="flex items-center gap-2">
              <h2 class="text-lg font-serif font-medium text-[#1d1d1f] tracking-tight">
                Delete Account
              </h2>
              <span class="text-[10px] font-semibold text-rose-700 bg-rose-50 border border-rose-200 px-2 py-0.5 rounded-full">
                Danger Zone
              </span>
            </div>
            <p class="text-xs text-[#6e6e73]">
              Permanently remove your account, past orders, and Masala Rewards points.
            </p>
          </div>
        </div>

        <DeleteUserForm />
      </div>

    </div>
  </StoreLayout>
</template>
