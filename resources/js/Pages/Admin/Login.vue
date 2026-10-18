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

  <div class="min-h-screen bg-zinc-50 flex flex-col justify-center items-center px-4 sm:px-6 py-12 selection:bg-zinc-900 selection:text-white relative">
    
    <!-- Top back button to Storefront -->
    <div class="absolute top-6 left-6">
      <Link 
        href="/" 
        class="inline-flex items-center gap-1.5 text-xs font-medium text-zinc-600 hover:text-zinc-900 bg-white border border-zinc-200 px-3 py-1.5 rounded-md transition-colors shadow-2xs"
      >
        <ArrowLeft class="w-3.5 h-3.5" />
        <span>Return to Storefront</span>
      </Link>
    </div>

    <!-- Login Container -->
    <div class="w-full max-w-sm space-y-5">

      <!-- Header & Brand -->
      <div class="text-center space-y-1.5">
        <div class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded bg-zinc-900 text-zinc-100 text-[11px] font-mono font-medium">
          <Shield class="w-3 h-3 text-emerald-400" />
          <span>ADMIN GUARD</span>
        </div>
        
        <h1 class="text-2xl font-semibold text-zinc-900 tracking-tight">
          Masala Mart Backoffice
        </h1>
        <p class="text-xs text-zinc-500">
          Sign in with your staff or administrator credentials
        </p>
      </div>

      <!-- Card -->
      <div class="bg-white border border-zinc-200 rounded-lg p-6 shadow-xs">
        
        <form @submit.prevent="submit" class="space-y-4">
          <!-- Email Input -->
          <div>
            <Label for="email" class="block mb-1.5 text-xs font-medium text-zinc-700">
              Admin Email
            </Label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400 z-10">
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
                class="w-full pl-9 pr-3 h-9 bg-white border-zinc-200 rounded-md text-xs"
                :class="{ 'border-red-500 focus-visible:ring-red-500': form.errors.email }"
              />
            </div>
            <p v-if="form.errors.email" class="text-xs text-red-600 mt-1 font-medium">
              {{ form.errors.email }}
            </p>
          </div>

          <!-- Password Input -->
          <div>
            <Label for="password" class="block mb-1.5 text-xs font-medium text-zinc-700">
              Password
            </Label>
            <div class="relative">
              <div class="absolute inset-y-0 left-0 pl-3 flex items-center pointer-events-none text-zinc-400 z-10">
                <Lock class="w-4 h-4" />
              </div>
              <Input
                id="password"
                v-model="form.password"
                type="password"
                required
                autocomplete="current-password"
                placeholder="••••••••"
                class="w-full pl-9 pr-3 h-9 bg-white border-zinc-200 rounded-md text-xs"
                :class="{ 'border-red-500 focus-visible:ring-red-500': form.errors.password }"
              />
            </div>
            <p v-if="form.errors.password" class="text-xs text-red-600 mt-1 font-medium">
              {{ form.errors.password }}
            </p>
          </div>

          <!-- Remember Me -->
          <div class="flex items-center justify-between pt-0.5">
            <label class="flex items-center gap-2 cursor-pointer select-none">
              <Checkbox 
                :checked="form.remember" 
                @update:checked="form.remember = $event"
              />
              <span class="text-xs text-zinc-600">Keep session active</span>
            </label>
          </div>

          <!-- Submit Button -->
          <Button
            type="submit"
            :disabled="form.processing"
            class="w-full h-9 bg-zinc-900 hover:bg-zinc-800 text-white font-semibold text-xs rounded-md flex items-center justify-center gap-2 transition-colors cursor-pointer disabled:opacity-50 mt-2"
          >
            <span v-if="form.processing">Authenticating...</span>
            <template v-else>
              <span>Sign In</span>
              <ArrowRight class="w-3.5 h-3.5" />
            </template>
          </Button>
        </form>

        <!-- Pre-fill demo accounts for quick testing -->
        <div class="mt-5 pt-4 border-t border-zinc-100">
          <p class="text-[10px] font-mono uppercase tracking-wider text-zinc-400 mb-2 text-center">
            Quick Demo Access
          </p>
          <div class="grid grid-cols-2 gap-2">
            <button
              type="button"
              @click="fillDemo('admin@masalamart.com')"
              class="p-2 bg-zinc-50 hover:bg-zinc-100 border border-zinc-200 rounded-md text-left transition-colors cursor-pointer"
            >
              <div class="text-[11px] font-semibold text-zinc-900">
                Super Admin
              </div>
              <div class="text-[10px] text-zinc-500 font-mono truncate">
                admin@masalamart.com
              </div>
            </button>
            <button
              type="button"
              @click="fillDemo('manager@masalamart.com')"
              class="p-2 bg-zinc-50 hover:bg-zinc-100 border border-zinc-200 rounded-md text-left transition-colors cursor-pointer"
            >
              <div class="text-[11px] font-semibold text-zinc-900">
                Store Manager
              </div>
              <div class="text-[10px] text-zinc-500 font-mono truncate">
                manager@masalamart.com
              </div>
            </button>
          </div>
        </div>

      </div>

      <!-- Info note -->
      <p class="text-center text-[11px] text-zinc-400">
        Isolated admin authentication session (`guard: admin`).
      </p>

    </div>
  </div>
</template>
