<script setup>
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, ArrowRight, ArrowLeft, KeyRound, CheckCircle2 } from 'lucide-vue-next';

defineProps({
  status: {
    type: String,
    default: '',
  },
});

const form = useForm({
  email: '',
});

const submit = () => {
  form.post(route('password.email'));
};
</script>

<template>
  <GuestLayout>
    <Head title="Reset Password — Masala Mart" />

    <div class="space-y-6">
      
      <div class="space-y-1">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f5eee2] border border-[#e0d9cc] text-[#7a5620] text-[10.5px] font-semibold uppercase tracking-wider">
          <KeyRound class="w-3 h-3 text-[#a47a3c]" />
          <span>Password Recovery</span>
        </div>
        <h1 class="text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">
          Forgot Your Password?
        </h1>
        <p class="text-xs text-[#6e6e73]">
          Enter your registered email address and we'll send you a password reset link.
        </p>
      </div>

      <div 
        v-if="status" 
        class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs font-medium text-emerald-800 flex items-center gap-2"
      >
        <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
        <span>{{ status }}</span>
      </div>

      <form @submit.prevent="submit" class="space-y-4">
        <div class="space-y-1.5">
          <label for="email" class="block text-xs font-semibold text-[#1d1d1f]">
            Email Address
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
              <Mail class="w-4 h-4" />
            </div>
            <input
              id="email"
              v-model="form.email"
              type="email"
              required
              autofocus
              autocomplete="username"
              placeholder="priya@example.com"
              class="w-full pl-10 pr-3.5 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
              :class="{ 'border-rose-400 ring-rose-100': form.errors.email }"
            />
          </div>
          <p v-if="form.errors.email" class="text-xs text-rose-600 font-medium">
            {{ form.errors.email }}
          </p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full h-11 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-2 transition-all active:scale-[0.99] shadow-sm cursor-pointer disabled:opacity-50"
        >
          <span v-if="form.processing">Sending Reset Link...</span>
          <template v-else>
            <span>Email Password Reset Link</span>
            <ArrowRight class="w-4 h-4" />
          </template>
        </button>
      </form>

      <div class="text-center pt-2 border-t border-[#e0d9cc]/60">
        <Link 
          :href="route('login')" 
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] transition-colors"
        >
          <ArrowLeft class="w-3.5 h-3.5" />
          <span>Back to Customer Sign In</span>
        </Link>
      </div>

    </div>
  </GuestLayout>
</template>
