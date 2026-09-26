<script setup>
import { Head, useForm, Link } from '@inertiajs/vue3';
import { Shield, Lock, Mail, ArrowRight, ArrowLeft } from 'lucide-vue-next';
import { Input } from '@/Components/ui/input';
import { Label } from '@/Components/ui/label';
import { Checkbox } from '@/Components/ui/checkbox';
import { Button } from '@/Components/ui/button';

const form = useForm({
  email: '',
  password: '',
  remember: false,
});

const submit = () => {
  form.post(route('admin.login'), {
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
  <Head title="Admin Login — Masala Mart Backoffice" />

  <div class="min-h-screen bg-[#fbf9f5] flex flex-col justify-center items-center px-4 sm:px-6 py-12 selection:bg-[#a47a3c] selection:text-white relative">
    
    <!-- Top back button to Storefront -->
    <div class="absolute top-6 left-6">
      <Link 
        href="/" 
        class="inline-flex items-center gap-1.5 text-xs font-semibold text-[#6e6e73] hover:text-[#1d1d1f] bg-[#f3efe7] border border-[#e0d9cc] px-3.5 py-1.5 rounded-full transition-colors shadow-2xs"
      >
        <ArrowLeft class="w-3.5 h-3.5" />
        <span>Return to Storefront</span>
      </Link>
    </div>

    <!-- Login Container -->
    <div class="w-full max-w-md space-y-6">

      <!-- Header & Brand -->
      <div class="text-center space-y-2">
        <div class="inline-flex items-center gap-2 px-3 py-1 rounded-full bg-[#f5eee2] border border-[#e0d9cc] text-[#7a5620] text-xs font-mono font-medium">
          <Shield class="w-3.5 h-3.5 text-[#a47a3c]" />
          <span>ADMIN AUTH GUARD</span>
        </div>
        
        <h1 class="text-3xl sm:text-4xl font-serif font-medium text-[#1d1d1f] tracking-tight">
          Masala Mart Backoffice
        </h1>
        <p class="text-xs text-[#6e6e73]">
          Staff & management portal protected by Spatie Role Permissions
        </p>
      </div>

      <!-- Card -->
      <div class="bg-[#f3efe7] border border-[#e0d9cc] rounded-3xl p-6 sm:p-8 shadow-sm">
        
        <form @submit.prevent="submit" class="space-y-4">
          <!-- Email Input -->
          <div>
            <Label for="email" class="block mb-1.5">
              Admin Email
            </Label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b] z-10">
                <Mail class="w-4 h-4" />
              </div>
              <Input
                id="email"
                v-model="form.email"
                type="email"
                required
                autofocus
                autocomplete="username"
                placeholder="admin@masalamart.com"
                class="w-full pl-10 pr-3.5 h-11 bg-white rounded-xl text-sm"
                :class="{ 'border-red-500 focus-visible:ring-red-500': form.errors.email }"
              />
            </div>
            <p v-if="form.errors.email" class="text-xs text-red-600 mt-1 font-medium">
              {{ form.errors.email }}
            </p>
          </div>

          <!-- Password Input -->
          <div>
            <Label for="password" class="block mb-1.5">
              Password
            </Label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b] z-10">
                <Lock class="w-4 h-4" />
              </div>
              <Input
                id="password"
                v-model="form.password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="w-full pl-10 pr-3.5 h-11 bg-white rounded-xl text-sm"
                :class="{ 'border-red-500 focus-visible:ring-red-500': form.errors.password }"
              />
            </div>
            <p v-if="form.errors.password" class="text-xs text-red-600 mt-1 font-medium">
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Remember Me -->
          <div class="flex items-center justify-between pt-1">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <Checkbox 
                :checked="form.remember" 
                @update:checked="form.remember = $event"
              />
              <span class="text-xs text-[#6e6e73]">Stay logged in to admin session</span>
            </label>
          </div>

          <!-- Submit Button -->
          <Button
            type="submit"
            :disabled="form.processing"
            class="w-full h-11 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-2 transition-transform active:scale-98 shadow-md cursor-pointer disabled:opacity-50 mt-2"
          >
            <span v-if="form.processing">Authenticating...</span>
            <template v-else>
              <span>Sign In to Admin Guard</span>
              <ArrowRight class="w-4 h-4" />
            </template>
          </Button>
        </form>

        <!-- Pre-fill demo accounts for quick testing -->
        <div class="mt-6 pt-5 border-t border-[#e0d9cc]/70">
          <p class="text-[11px] font-semibold uppercase tracking-wider text-[#86868b] mb-2.5 text-center">
            One-Click Demo Credentials
          </p>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              @click="fillDemo('admin@masalamart.com')"
              class="p-2 bg-white/80 hover:bg-white border border-[#e0d9cc] rounded-xl text-left transition-colors cursor-pointer group"
            >
              <div class="text-[11px] font-bold text-[#1d1d1f] group-hover:text-[#a47a3c]">
                Super Admin
              </div>
              <div class="text-[10px] text-[#6e6e73] font-mono truncate">
                admin@masalamart.com
              </div>
            </button>
            <button
              type="button"
              @click="fillDemo('manager@masalamart.com')"
              class="p-2 bg-white/80 hover:bg-white border border-[#e0d9cc] rounded-xl text-left transition-colors cursor-pointer group"
            >
              <div class="text-[11px] font-bold text-[#1d1d1f] group-hover:text-[#a47a3c]">
                Store Manager
              </div>
              <div class="text-[10px] text-[#6e6e73] font-mono truncate">
                manager@masalamart.com
              </div>
            </button>
          </div>
        </div>

      </div>

      <!-- Info note -->
      <p class="text-center text-[11px] text-[#86868b]">
        Admin session cookies are isolated from user storefront sessions.
      </p>

    </div>
  </div>
</template>
