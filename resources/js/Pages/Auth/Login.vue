<script setup>
import { ref } from 'vue';
import GuestLayout from '@/Layouts/GuestLayout.vue';
import { Head, Link, useForm } from '@inertiajs/vue3';
import { Mail, Lock, Eye, EyeOff, ArrowRight, Sparkles, CheckCircle2 } from 'lucide-vue-next';

defineProps({
  canResetPassword: {
    type: Boolean,
    default: true,
  },
  status: {
    type: String,
    default: '',
  },
});

const showPassword = ref(false);

const form = useForm({
  email: '',
  password: '',
  remember: true,
});

const submit = () => {
  form.post(route('login'), {
    onFinish: () => form.reset('password'),
  });
};

const fillDemo = (email, password = 'password') => {
  form.email = email;
  form.password = password;
  form.remember = true;
};
</script>

<template>
  <GuestLayout>
    <Head title="Sign In — Customer Account" />

    <div class="space-y-6">
      <!-- Card Title & Subtitle -->
      <div class="space-y-1">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded-full bg-[#f5eee2] border border-[#e0d9cc] text-[#7a5620] text-[10.5px] font-semibold uppercase tracking-wider">
          <Sparkles class="w-3 h-3 text-[#a47a3c]" />
          <span>Customer Sign In</span>
        </div>
        <h1 class="text-2xl font-serif font-medium text-[#1d1d1f] tracking-tight">
          Welcome Back
        </h1>
        <p class="text-xs text-[#6e6e73]">
          Sign in to manage orders and Masala Rewards points.
        </p>
      </div>

      <!-- Session Status Alert -->
      <div 
        v-if="status" 
        class="p-3 rounded-2xl bg-emerald-50 border border-emerald-200 text-xs font-medium text-emerald-800 flex items-center gap-2"
      >
        <CheckCircle2 class="w-4 h-4 text-emerald-600 shrink-0" />
        <span>{{ status }}</span>
      </div>

      <!-- Login Form -->
      <form @submit.prevent="submit" class="space-y-4">
        
        <!-- Email Field -->
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

        <!-- Password Field -->
        <div class="space-y-1.5">
          <div class="flex items-center justify-between">
            <label for="password" class="block text-xs font-semibold text-[#1d1d1f]">
              Password
            </label>
            <Link
              v-if="canResetPassword"
              :href="route('password.request')"
              class="text-[11px] text-[#7a5620] hover:text-[#1d1d1f] hover:underline transition-colors"
            >
              Forgot password?
            </Link>
          </div>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
              <Lock class="w-4 h-4" />
            </div>
            <input
              id="password"
              v-model="form.password"
              :type="showPassword ? 'text' : 'password'"
              required
              autocomplete="current-password"
              placeholder="••••••••"
              class="w-full pl-10 pr-10 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
              :class="{ 'border-rose-400 ring-rose-100': form.errors.password }"
            />
            <button
              type="button"
              @click="showPassword = !showPassword"
              class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#86868b] hover:text-[#1d1d1f] transition-colors cursor-pointer"
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

        <!-- Remember Me Checkbox -->
        <div class="flex items-center justify-between pt-0.5">
          <label class="flex items-center gap-2 cursor-pointer select-none">
            <input
              type="checkbox"
              v-model="form.remember"
              class="w-4 h-4 rounded border-[#dfd6c8] text-[#1a1a1a] focus:ring-0 focus:ring-offset-0 cursor-pointer accent-[#1a1a1a]"
            />
            <span class="text-xs text-[#6e6e73]">Remember my account</span>
          </label>
        </div>

        <!-- Submit Button -->
        <button
          type="submit"
          :disabled="form.processing"
          class="w-full h-11 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-2 transition-all active:scale-[0.99] shadow-sm cursor-pointer disabled:opacity-50 mt-1"
        >
          <span v-if="form.processing">Signing In...</span>
          <template v-else>
            <span>Sign In to Account</span>
            <ArrowRight class="w-4 h-4" />
          </template>
        </button>

      </form>

      <!-- Switch to Register -->
      <div class="text-center pt-2 border-t border-[#e0d9cc]/60">
        <p class="text-xs text-[#6e6e73]">
          New to Masala Mart?
          <Link :href="route('register')" class="font-semibold text-[#1d1d1f] hover:text-[#a47a3c] underline ml-1">
            Create an account
          </Link>
        </p>
      </div>

      <!-- Quick Demo Login Buttons for Testing -->
      <div class="bg-[#f4efe6]/60 border border-[#dfd6c8] rounded-2xl p-3 space-y-2">
        <div class="flex items-center justify-between">
          <span class="text-[10px] font-bold uppercase tracking-wider text-[#7a5620]">
            One-Click Demo Accounts
          </span>
          <span class="text-[10px] text-[#86868b]">Password: password</span>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <button
            type="button"
            @click="fillDemo('priya@example.com')"
            class="p-2 bg-white hover:bg-[#fbf9f5] border border-[#dfd6c8] rounded-xl text-left transition-colors cursor-pointer group"
          >
            <div class="text-[11px] font-bold text-[#1d1d1f] group-hover:text-[#a47a3c] truncate">
              Priya Sharma
            </div>
            <div class="text-[10px] text-[#6e6e73] font-mono truncate">
              priya@example.com
            </div>
          </button>
          <button
            type="button"
            @click="fillDemo('test@example.com')"
            class="p-2 bg-white hover:bg-[#fbf9f5] border border-[#dfd6c8] rounded-xl text-left transition-colors cursor-pointer group"
          >
            <div class="text-[11px] font-bold text-[#1d1d1f] group-hover:text-[#a47a3c] truncate">
              Test Customer
            </div>
            <div class="text-[10px] text-[#6e6e73] font-mono truncate">
              test@example.com
            </div>
          </button>
        </div>
      </div>

    </div>
  </GuestLayout>
</template>
