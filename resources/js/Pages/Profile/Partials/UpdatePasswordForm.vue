<script setup>
import { useForm } from '@inertiajs/vue3';
import { ref } from 'vue';
import { Lock, Eye, EyeOff, KeyRound, CheckCircle2 } from 'lucide-vue-next';

const passwordInput = ref(null);
const currentPasswordInput = ref(null);

const showCurrent = ref(false);
const showNew = ref(false);
const showConfirm = ref(false);

const form = useForm({
  current_password: '',
  password: '',
  password_confirmation: '',
});

const updatePassword = () => {
  form.put(route('password.update'), {
    preserveScroll: true,
    onSuccess: () => form.reset(),
    onError: () => {
      if (form.errors.password) {
        form.reset('password', 'password_confirmation');
        passwordInput.value?.focus();
      }
      if (form.errors.current_password) {
        form.reset('current_password');
        currentPasswordInput.value?.focus();
      }
    },
  });
};
</script>

<template>
  <form @submit.prevent="updatePassword" class="space-y-4">
    
    <!-- Current Password Input -->
    <div class="space-y-1.5">
      <label for="current_password" class="block text-xs font-semibold text-[#1d1d1f]">
        Current Password
      </label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
          <Lock class="w-4 h-4" />
        </div>
        <input
          id="current_password"
          ref="currentPasswordInput"
          v-model="form.current_password"
          :type="showCurrent ? 'text' : 'password'"
          autocomplete="current-password"
          placeholder="Enter current password"
          class="w-full pl-10 pr-10 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
          :class="{ 'border-rose-400 ring-rose-100': form.errors.current_password }"
        />
        <button
          type="button"
          @click="showCurrent = !showCurrent"
          class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#86868b] hover:text-[#1d1d1f] transition-colors cursor-pointer"
          tabindex="-1"
        >
          <EyeOff v-if="showCurrent" class="w-4 h-4" />
          <Eye v-else class="w-4 h-4" />
        </button>
      </div>
      <p v-if="form.errors.current_password" class="text-xs text-rose-600 font-medium">
        {{ form.errors.current_password }}
      </p>
    </div>

    <!-- New Password Input -->
    <div class="space-y-1.5">
      <label for="new_password" class="block text-xs font-semibold text-[#1d1d1f]">
        New Password
      </label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
          <KeyRound class="w-4 h-4" />
        </div>
        <input
          id="new_password"
          ref="passwordInput"
          v-model="form.password"
          :type="showNew ? 'text' : 'password'"
          autocomplete="new-password"
          placeholder="At least 8 characters"
          class="w-full pl-10 pr-10 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
          :class="{ 'border-rose-400 ring-rose-100': form.errors.password }"
        />
        <button
          type="button"
          @click="showNew = !showNew"
          class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#86868b] hover:text-[#1d1d1f] transition-colors cursor-pointer"
          tabindex="-1"
        >
          <EyeOff v-if="showNew" class="w-4 h-4" />
          <Eye v-else class="w-4 h-4" />
        </button>
      </div>
      <p v-if="form.errors.password" class="text-xs text-rose-600 font-medium">
        {{ form.errors.password }}
      </p>
    </div>

    <!-- Confirm Password Input -->
    <div class="space-y-1.5">
      <label for="password_confirmation" class="block text-xs font-semibold text-[#1d1d1f]">
        Confirm New Password
      </label>
      <div class="relative">
        <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
          <KeyRound class="w-4 h-4" />
        </div>
        <input
          id="password_confirmation"
          v-model="form.password_confirmation"
          :type="showConfirm ? 'text' : 'password'"
          autocomplete="new-password"
          placeholder="Re-enter new password"
          class="w-full pl-10 pr-10 h-11 bg-[#fbf9f5] border border-[#dfd6c8] focus:border-[#1a1a1a] rounded-xl text-xs sm:text-sm text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:bg-white focus:ring-3 focus:ring-[#1a1a1a]/10 transition-all font-normal"
          :class="{ 'border-rose-400 ring-rose-100': form.errors.password_confirmation }"
        />
        <button
          type="button"
          @click="showConfirm = !showConfirm"
          class="absolute inset-y-0 right-0 pr-3.5 flex items-center text-[#86868b] hover:text-[#1d1d1f] transition-colors cursor-pointer"
          tabindex="-1"
        >
          <EyeOff v-if="showConfirm" class="w-4 h-4" />
          <Eye v-else class="w-4 h-4" />
        </button>
      </div>
      <p v-if="form.errors.password_confirmation" class="text-xs text-rose-600 font-medium">
        {{ form.errors.password_confirmation }}
      </p>
    </div>

    <!-- Actions & Feedback -->
    <div class="flex items-center gap-3 pt-2">
      <button
        type="submit"
        :disabled="form.processing"
        class="h-10 px-5 bg-[#1a1a1a] hover:bg-black text-white font-semibold text-xs rounded-full flex items-center justify-center gap-2 transition-all active:scale-[0.99] shadow-xs cursor-pointer disabled:opacity-50"
      >
        <KeyRound class="w-3.5 h-3.5" />
        <span v-if="form.processing">Updating Password...</span>
        <span v-else>Update Password</span>
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
          <span>Password changed successfully!</span>
        </span>
      </Transition>
    </div>

  </form>
</template>
