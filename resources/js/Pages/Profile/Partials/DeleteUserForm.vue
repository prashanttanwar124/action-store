<script setup>
import { useForm } from '@inertiajs/vue3';
import { nextTick, ref } from 'vue';
import Modal from '@/Components/Modal.vue';
import { Trash2, AlertTriangle, Lock, X } from 'lucide-vue-next';

const confirmingUserDeletion = ref(false);
const passwordInput = ref(null);

const form = useForm({
  password: '',
});

const confirmUserDeletion = () => {
  confirmingUserDeletion.value = true;
  nextTick(() => passwordInput.value?.focus());
};

const deleteUser = () => {
  form.delete(route('profile.destroy'), {
    preserveScroll: true,
    onSuccess: () => closeModal(),
    onError: () => passwordInput.value?.focus(),
    onFinish: () => form.reset(),
  });
};

const closeModal = () => {
  confirmingUserDeletion.value = false;
  form.clearErrors();
  form.reset();
};
</script>

<template>
  <div class="space-y-4">
    <div class="text-xs text-[#6e6e73] leading-relaxed">
      Once your account is deleted, all of your saved grocery preferences, past orders, and Masala Rewards points will be permanently erased.
    </div>

    <div>
      <button
        type="button"
        @click="confirmUserDeletion"
        class="h-10 px-4 bg-rose-50 hover:bg-rose-100 text-rose-700 hover:text-rose-800 border border-rose-200 text-xs font-semibold rounded-full inline-flex items-center gap-2 transition-colors cursor-pointer"
      >
        <Trash2 class="w-3.5 h-3.5" />
        <span>Delete Account Permanently</span>
      </button>
    </div>

    <!-- Confirm Deletion Modal -->
    <Modal :show="confirmingUserDeletion" @close="closeModal" maxWidth="md">
      <div class="bg-[#fbf9f5] border border-[#e0d9cc] rounded-3xl p-6 sm:p-7 space-y-5">
        
        <!-- Header -->
        <div class="flex items-start justify-between gap-3">
          <div class="flex items-center gap-3">
            <div class="w-10 h-10 rounded-2xl bg-rose-100/80 border border-rose-200 flex items-center justify-center text-rose-700 shrink-0">
              <AlertTriangle class="w-5 h-5 stroke-[2.2]" />
            </div>
            <div>
              <h3 class="text-base font-serif font-medium text-[#1d1d1f]">
                Delete Your Account?
              </h3>
              <p class="text-xs text-[#6e6e73]">
                This action is immediate and irreversible.
              </p>
            </div>
          </div>

          <button
            type="button"
            @click="closeModal"
            class="text-[#86868b] hover:text-[#1d1d1f] p-1 rounded-full transition-colors cursor-pointer"
          >
            <X class="w-4 h-4" />
          </button>
        </div>

        <p class="text-xs text-[#6e6e73] leading-relaxed">
          Please confirm by entering your current password. All associated order history, store credit, and rewards will be deleted permanently.
        </p>

        <!-- Password Confirmation Input -->
        <div class="space-y-1.5">
          <label for="delete_password" class="block text-xs font-semibold text-[#1d1d1f]">
            Confirm Password
          </label>
          <div class="relative">
            <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-[#86868b]">
              <Lock class="w-4 h-4" />
            </div>
            <input
              id="delete_password"
              ref="passwordInput"
              v-model="form.password"
              type="password"
              placeholder="Enter your password to confirm"
              @keyup.enter="deleteUser"
              class="w-full pl-10 pr-3.5 h-11 bg-white border border-[#dfd6c8] focus:border-rose-500 rounded-xl text-xs sm:text-sm text-[#1d1d1f] placeholder-[#86868b] focus:outline-none focus:ring-3 focus:ring-rose-500/15 transition-all font-normal"
              :class="{ 'border-rose-400 ring-rose-100': form.errors.password }"
            />
          </div>
          <p v-if="form.errors.password" class="text-xs text-rose-600 font-medium">
            {{ form.errors.password }}
          </p>
        </div>

        <!-- Action Buttons -->
        <div class="flex items-center justify-end gap-2.5 pt-2 border-t border-[#e0d9cc]/60">
          <button
            type="button"
            @click="closeModal"
            class="px-4 py-2 bg-white hover:bg-[#f3efe7] border border-[#dfd6c8] text-[#1d1d1f] text-xs font-semibold rounded-full transition-colors cursor-pointer"
          >
            Cancel
          </button>

          <button
            type="button"
            :disabled="form.processing"
            @click="deleteUser"
            class="px-4 py-2 bg-rose-600 hover:bg-rose-700 text-white text-xs font-semibold rounded-full transition-all active:scale-[0.99] shadow-xs cursor-pointer disabled:opacity-50 inline-flex items-center gap-1.5"
          >
            <Trash2 class="w-3.5 h-3.5" />
            <span v-if="form.processing">Deleting...</span>
            <span v-else>Permanently Delete</span>
          </button>
        </div>

      </div>
    </Modal>
  </div>
</template>
