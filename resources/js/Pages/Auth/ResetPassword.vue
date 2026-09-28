<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, Lock, Eye, EyeOff, ArrowRight, KeyRound } from 'lucide-vue-next';

const props = defineProps({
  email: {
    type: String,
    required: true,
  },
  token: {
    type: String,
    required: true,
  },
});

const showPassword = ref(false);

const form = useForm({
  token: props.token,
  email: props.email,
  password: '',
  password_confirmation: '',
});

const submit = () => {
  form.post(route('password.store'), {
    onFinish: () => form.reset('password', 'password_confirmation'),
  });
};
</script>

<template>
  <GuestLayout>
    <Head title="Set New Password — Masala Mart" />

    <div class="space-y-6">
      
      <div class="space-y-1">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f5eee2] border border-[#e0d9cc] text-[#7a5620] text-[10.5px] font-semibold uppercase tracking-wider">
          <KeyRound class="w-3 h-3 text-[#a47a3c]" />
          <span>Security</span>
        </div>
        <h1 class="text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">
          Create New Password
        </h1>
        <p class="text-xs text-[#6e6e73]">
          Please enter your email and set a new secure password.
        </p>
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
              class="w-full pl-10 pr-3.5 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
              :class="{ 'border-rose-400 ring-rose-100': form.errors.email }"
            />
          </div>
          <p v-if="form.errors.email" class="text-xs text-rose-600 font-medium">
            {{ form.errors.email }}
          </p>
        </div>

        <div class="space-y-1.5">
          <label for="password" class="block text-xs font-semibold text-[#1d1d1f]">
            New Password
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
              <Lock class="w-4 h-4" />
            </div>
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="new-password"
              placeholder="At least 8 characters"
              class="w-full pl-10 pr-10 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
              :class="{ 'border-rose-400 ring-rose-100': form.errors.password }"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#86868b] hover:text-[#1d1d1f] cursor-pointer"
              tabindex="-1"
            >
              <EyeOff v-if="showPassword" class="w-4 h-4" />
              <Eye v-else class="w-4 h-4" />
            </button>
          </div>
          <p v-if="form.errors.password" class="text-xs text-rose-600 font-medium">
            {{ form.errors.password }}
          </p>
        </div>

        <div class="space-y-1.5">
          <label for="password_confirmation" class="block text-xs font-semibold text-[#1d1d1f]">
            Confirm New Password
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
              <Lock class="w-4 h-4" />
            </div>
            <input
              id="password_confirmation"
              v-model="form.password_confirmation"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="new-password"
              placeholder="Confirm password"
              class="w-full pl-10 pr-3.5 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
              :class="{ 'border-rose-400 ring-rose-100': form.errors.password_confirmation }"
            />
          </div>
          <p v-if="form.errors.password_confirmation" class="text-xs text-rose-600 font-medium">
            {{ form.errors.password_confirmation }}
          </p>
        </div>

        <button
          type="submit"
          :disabled="form.processing"
          class="w-full h-11 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-2 transition-all active:scale-[0.99] shadow-sm cursor-pointer disabled:opacity-50 mt-1"
        >
          <span v-if="form.processing">Resetting Password...</span>
          <template v-else>
            <span>Set New Password & Sign In</span>
            <ArrowRight class="w-4 h-4" />
          </template>
        </button>

      </form>

      <div class="text-center pt-2 border-t border-[#e0d9cc]/60">
        <Link 
          :href="route('login')" 
          class="text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f]"
        >
          Cancel and return to Sign In
        </Link>
      </div>

    </div>
  </GuestLayout>
</template>
