<script setup>
import { Link, useForm, usePage } from '@inertiajs/vue3';
import { User, Mail, Check, CheckCircle2, AlertCircle } from 'lucide-vue-next';

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

const user = usePage().props.auth.user;

const form = useForm({
  name: user.name,
  email: user.email,
});
</script>

<template>
  <form @submit.prevent="form.patch(route('profile.update'))" class="space-y-4">
    
    <!-- Full Name Input -->
    <div class="space-y-1.5">
      <label for="profile_name" class="block text-xs font-semibold text-[#1d1d1f]">
        Full Name
      </label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
          <User class="w-4 h-4" />
        </div>
        <input
          id="profile_name"
          v-model="form.name"
          type="text"
          required
          autocomplete="name"
          placeholder="e.g. Priya Sharma"
          class="w-full pl-10 pr-3.5 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
          :class="{ 'border-rose-400 ring-rose-100': form.errors.name }"
        />
      </div>
      <p v-if="form.errors.name" class="text-xs text-rose-600 font-medium">
        {{ form.errors.name }}
      </p>
    </div>

    <!-- Email Address Input -->
    <div class="space-y-1.5">
      <label for="profile_email" class="block text-xs font-semibold text-[#1d1d1f]">
        Email Address
      </label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
          <Mail class="w-4 h-4" />
        </div>
        <input
          id="profile_email"
          v-model="form.email"
          type="email"
          required
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

    <!-- Email Verification Alert -->
    <div v-if="mustVerifyEmail && user.email_verified_at === null" class="p-3.5 rounded-2xl bg-amber-50 border border-amber-200 text-xs text-amber-900 space-y-1.5">
      <div class="flex items-center gap-2 font-semibold">
        <AlertCircle class="w-4 h-4 text-amber-600 shrink-0" />
        <span>Your email address is unverified.</span>
      </div>
      <p class="text-stone-600">
        <Link
          :href="route('verification.send')"
          method="post"
          as="button"
          class="underline font-semibold text-amber-800 hover:text-amber-950 cursor-pointer"
        >
          Click here to re-send the verification email.
        </Link>
      </p>

      <div
        v-show="status === 'verification-link-sent'"
        class="mt-2 text-xs font-semibold text-emerald-700 bg-emerald-50 p-2 rounded-xl border border-emerald-200"
      >
        A new verification link has been sent to your email address.
      </div>
    </div>

    <!-- Actions & Feedback -->
    <div class="flex items-center gap-3 pt-2">
      <button
        type="submit"
        :disabled="form.processing"
        class="h-10 px-5 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-2 transition-all active:scale-[0.99] shadow-xs cursor-pointer disabled:opacity-50"
      >
        <Check class="w-3.5 h-3.5" />
        <span v-if="form.processing">Saving Changes...</span>
        <span v-else>Save Profile Information</span>
      </button>

      <Transition
        enter-active-class="transition ease-out duration-200"
        enter-from-class="opacity-0 translate-y-1"
        enter-to-class="opacity-100 translate-y-0"
        leave-active-class="transition ease-in duration-150"
        leave-from-class="opacity-100 translate-y-0"
        leave-to-class="opacity-0 translate-y-1"
      >
        <span
          v-if="form.recentlySuccessful"
          class="inline-flex items-center gap-1.5 text-xs font-semibold text-emerald-700 bg-emerald-50 border border-emerald-200 px-3 py-1.5 rounded-full"
        >
          <CheckCircle2 class="w-3.5 h-3.5 text-emerald-600" />
          <span>Profile updated successfully!</span>
        </span>
      </Transition>
    </div>

  </form>
</template>
