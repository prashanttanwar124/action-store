<script setup>
import { computed } from 'vue';
import { Head, useForm } from '@inertiajs/vue3';
import AdminLayout from '@/Layouts/AdminLayout.vue';
import { 
  Store, 
  MapPin, 
  Clock, 
  Phone, 
  Mail, 
  Sparkles, 
  Save, 
  CheckCircle2, 
  ExternalLink,
  Info,
  Car
} from 'lucide-vue-next';

const props = defineProps({
  storeInfo: {
    type: Object,
    required: true,
  },
});

const form = useForm({
  name: props.storeInfo.name || 'Masala Mart — Main St.',
  tagline: props.storeInfo.tagline || 'Authentic Indian Groceries & Fresh Click-and-Collect',
  hindi_tagline: props.storeInfo.hindi_tagline || 'किराना',
  address: props.storeInfo.address || '214 Main St.',
  city: props.storeInfo.city || 'Edison',
  state: props.storeInfo.state || 'NJ',
  zip: props.storeInfo.zip || '08817',
  phone: props.storeInfo.phone || '+1 (555) 345-6789',
  email: props.storeInfo.email || 'support@masalamart.com',
  opening_hours: props.storeInfo.opening_hours || 'Daily 9:00 AM – 9:00 PM',
  pickup_time: props.storeInfo.pickup_time || 'Ready in 1 hr',
  curbside_instructions: props.storeInfo.curbside_instructions || 'Park in Curbside Bay 3 or pick up at the front express counter. Bring your order confirmation SMS.',
  announcement: props.storeInfo.announcement || 'Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30',
  maps_url: props.storeInfo.maps_url || 'https://maps.google.com/?q=214+Main+St,+Edison,+NJ',
  is_pickup_active: props.storeInfo.is_pickup_active !== undefined ? Boolean(props.storeInfo.is_pickup_active) : true,
});

const submit = () => {
  form.put(route('admin.store-info.update'), {
    preserveScroll: true,
  });
};
</script>

<template>
  <Head title="Store Settings & Pickup Info — Admin Console" />

  <AdminLayout title="Store Info & Pickup Settings">
    <div class="space-y-6 max-w-6xl mx-auto pb-12">

      <!-- Header Section -->
      <div class="flex flex-col sm:flex-row sm:items-center justify-between gap-4 bg-white p-6 rounded-2xl border border-stone-200 shadow-xs">
        <div>
          <div class="flex items-center gap-2 text-xs font-semibold text-[#a47a3c] uppercase tracking-wider">
            <Store class="w-4 h-4" />
            <span>Store Operations Hub</span>
          </div>
          <h1 class="text-2xl font-serif font-bold text-stone-900 mt-1">
            Store Location & Pickup Information
          </h1>
          <p class="text-xs text-stone-500 mt-0.5">
            Manage your single store address, opening hours, click-and-collect curbside bay instructions, and top announcement banner.
          </p>
        </div>

        <button
          type="button"
          @click="submit"
          :disabled="form.processing"
          class="inline-flex items-center gap-2 px-6 py-2.5 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl shadow-sm transition-all cursor-pointer disabled:opacity-50"
        >
          <Save class="w-4 h-4" />
          <span>{{ form.processing ? 'Saving Changes...' : 'Save Settings' }}</span>
        </button>
      </div>

      <div class="grid grid-cols-1 lg:grid-cols-12 gap-6 items-start">

        <!-- Form Left Column: Settings Fields (8 cols) -->
        <form @submit.prevent="submit" class="lg:col-span-8 space-y-6">

          <!-- 1. Store Identity -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2 border-b border-stone-100 pb-3">
              <Store class="w-4 h-4 text-[#a47a3c]" />
              <span>1. Store Identity & Branding</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Store Public Name *
                </label>
                <input
                  v-model="form.name"
                  type="text"
                  required
                  placeholder="e.g. Masala Mart — Main St."
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.name" class="text-xs text-rose-500 mt-1">{{ form.errors.name }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Hindi Badge Text (Devanagari)
                </label>
                <input
                  v-model="form.hindi_tagline"
                  type="text"
                  placeholder="e.g. किराना"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.hindi_tagline" class="text-xs text-rose-500 mt-1">{{ form.errors.hindi_tagline }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Store Subtitle / Tagline
              </label>
              <input
                v-model="form.tagline"
                type="text"
                placeholder="e.g. Authentic Indian Groceries & Fresh Click-and-Collect"
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
              />
              <p v-if="form.errors.tagline" class="text-xs text-rose-500 mt-1">{{ form.errors.tagline }}</p>
            </div>
          </div>

          <!-- 2. Store Location & Address -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2 border-b border-stone-100 pb-3">
              <MapPin class="w-4 h-4 text-[#a47a3c]" />
              <span>2. Physical Store Location</span>
            </h2>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Street Address *
              </label>
              <input
                v-model="form.address"
                type="text"
                required
                placeholder="e.g. 214 Main St."
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
              />
              <p v-if="form.errors.address" class="text-xs text-rose-500 mt-1">{{ form.errors.address }}</p>
            </div>

            <div class="grid grid-cols-1 sm:grid-cols-3 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  City *
                </label>
                <input
                  v-model="form.city"
                  type="text"
                  required
                  placeholder="e.g. Edison"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.city" class="text-xs text-rose-500 mt-1">{{ form.errors.city }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  State / Province *
                </label>
                <input
                  v-model="form.state"
                  type="text"
                  required
                  placeholder="e.g. NJ"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.state" class="text-xs text-rose-500 mt-1">{{ form.errors.state }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  ZIP / Postal Code *
                </label>
                <input
                  v-model="form.zip"
                  type="text"
                  required
                  placeholder="e.g. 08817"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.zip" class="text-xs text-rose-500 mt-1">{{ form.errors.zip }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Google Maps Directions URL
              </label>
              <input
                v-model="form.maps_url"
                type="url"
                placeholder="https://maps.google.com/?q=..."
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
              />
              <p v-if="form.errors.maps_url" class="text-xs text-rose-500 mt-1">{{ form.errors.maps_url }}</p>
            </div>
          </div>

          <!-- 3. Store Pickup Operations & Hours -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2 border-b border-stone-100 pb-3">
              <Clock class="w-4 h-4 text-[#a47a3c]" />
              <span>3. Pickup Operations & Hours</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Opening & Pickup Hours *
                </label>
                <input
                  v-model="form.opening_hours"
                  type="text"
                  required
                  placeholder="e.g. Daily 9:00 AM – 9:00 PM"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.opening_hours" class="text-xs text-rose-500 mt-1">{{ form.errors.opening_hours }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Pickup Ready ETA *
                </label>
                <input
                  v-model="form.pickup_time"
                  type="text"
                  required
                  placeholder="e.g. Ready in 1 hr"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.pickup_time" class="text-xs text-rose-500 mt-1">{{ form.errors.pickup_time }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Curbside & Counter Pickup Instructions
              </label>
              <textarea
                v-model="form.curbside_instructions"
                rows="3"
                placeholder="Instructions shown to customers at checkout and on confirmation..."
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
              ></textarea>
              <p v-if="form.errors.curbside_instructions" class="text-xs text-rose-500 mt-1">{{ form.errors.curbside_instructions }}</p>
            </div>

            <!-- Active Switch -->
            <div class="flex items-center justify-between p-3.5 bg-stone-50 rounded-xl border border-stone-200">
              <div>
                <div class="text-xs font-bold text-stone-900">Store Pickup Status</div>
                <div class="text-[11px] text-stone-500 font-normal">When active, shoppers can select store pickup and time slots at checkout.</div>
              </div>
              <label class="relative inline-flex items-center cursor-pointer">
                <input 
                  type="checkbox" 
                  v-model="form.is_pickup_active" 
                  class="sr-only peer"
                />
                <div class="w-11 h-6 bg-stone-300 peer-focus:outline-none rounded-full peer peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-stone-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-[#1a1a1a]"></div>
              </label>
            </div>
          </div>

          <!-- 4. Contact & Announcements -->
          <div class="bg-white p-6 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <h2 class="text-sm font-bold uppercase tracking-wider text-stone-900 flex items-center gap-2 border-b border-stone-100 pb-3">
              <Sparkles class="w-4 h-4 text-[#a47a3c]" />
              <span>4. Contact & Announcement Banner</span>
            </h2>

            <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Customer Support Phone / WhatsApp *
                </label>
                <input
                  v-model="form.phone"
                  type="text"
                  required
                  placeholder="+1 (555) 345-6789"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.phone" class="text-xs text-rose-500 mt-1">{{ form.errors.phone }}</p>
              </div>

              <div>
                <label class="block text-xs font-semibold text-stone-700 mb-1">
                  Store Email *
                </label>
                <input
                  v-model="form.email"
                  type="email"
                  required
                  placeholder="support@masalamart.com"
                  class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
                />
                <p v-if="form.errors.email" class="text-xs text-rose-500 mt-1">{{ form.errors.email }}</p>
              </div>
            </div>

            <div>
              <label class="block text-xs font-semibold text-stone-700 mb-1">
                Top Announcement Bar Message
              </label>
              <input
                v-model="form.announcement"
                type="text"
                placeholder="e.g. Diwali 2026: Fresh Mithai Pre-Orders Open · Closes Oct 30"
                class="w-full px-3.5 py-2.5 bg-stone-50 border border-stone-200 rounded-xl text-xs text-stone-900 focus:bg-white focus:ring-2 focus:ring-black/10 focus:outline-none"
              />
              <p v-if="form.errors.announcement" class="text-xs text-rose-500 mt-1">{{ form.errors.announcement }}</p>
            </div>
          </div>

          <div class="flex items-center justify-end gap-3 pt-2">
            <button
              type="submit"
              :disabled="form.processing"
              class="px-8 py-3 bg-[#1a1a1a] hover:bg-black text-white text-xs font-semibold rounded-xl shadow-md transition-all cursor-pointer disabled:opacity-50"
            >
              {{ form.processing ? 'Saving...' : 'Save Store Settings' }}
            </button>
          </div>

        </form>

        <!-- Right Column: Live Storefront Header Preview (4 cols) -->
        <div class="lg:col-span-4 space-y-5 sticky top-24">
          <div class="bg-white p-5 rounded-2xl border border-stone-200 shadow-xs space-y-4">
            <div class="flex items-center justify-between border-b border-stone-100 pb-3">
              <span class="text-xs font-bold uppercase tracking-wider text-stone-900 flex items-center gap-1.5">
                <Sparkles class="w-3.5 h-3.5 text-[#a47a3c]" />
                <span>Live Header Badge Preview</span>
              </span>
              <span class="text-[10px] font-semibold px-2 py-0.5 rounded-full bg-emerald-50 text-emerald-700 border border-emerald-200">
                Customer View
              </span>
            </div>

            <p class="text-xs text-stone-500 font-normal">
              This preview shows how your store pickup pill looks in the top navigation header on desktop and mobile.
            </p>

            <!-- Rendered Badge Preview -->
            <div class="p-4 bg-[#fbf9f5] rounded-2xl border border-[#e0d9cc] space-y-3">
              <div class="flex items-center gap-2">
                <span class="font-serif font-semibold text-base text-[#1d1d1f]">
                  {{ form.name.split('—')[0].trim() || 'Masala Mart' }}
                </span>
                <span v-if="form.hindi_tagline" class="font-devanagari text-[10px] text-[#7a5620] bg-[#f5eee2] px-1.5 py-0.5 rounded border border-[#e0d9cc]">
                  {{ form.hindi_tagline }}
                </span>
              </div>

              <!-- Interactive Pickup Pill -->
              <div class="flex items-center gap-2.5 px-3 py-2 border border-[#e0d9cc] bg-white rounded-xl shadow-2xs">
                <div class="w-6 h-6 rounded-lg bg-[#f5eee2] flex items-center justify-center text-[#a47a3c] shrink-0">
                  <MapPin class="w-3.5 h-3.5" />
                </div>
                <div class="min-w-0 flex-1">
                  <div class="flex items-center gap-1.5">
                    <span class="text-[9px] text-[#7a5620] font-bold uppercase tracking-wider">STORE PICKUP</span>
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                    <span class="text-[9px] text-stone-400 font-normal">{{ form.pickup_time }}</span>
                  </div>
                  <div class="font-bold text-xs text-[#1d1d1f] truncate mt-0.5">
                    {{ form.address }} · {{ form.city }}
                  </div>
                </div>
              </div>

              <!-- Extra Info Snippet -->
              <div class="text-[11px] text-stone-500 space-y-1 pt-1 border-t border-stone-200/60">
                <div class="flex items-center gap-1.5">
                  <Clock class="w-3 h-3 text-[#a47a3c]" />
                  <span>{{ form.opening_hours }}</span>
                </div>
                <div class="flex items-center gap-1.5">
                  <Phone class="w-3 h-3 text-[#a47a3c]" />
                  <span>{{ form.phone }}</span>
                </div>
              </div>
            </div>

            <!-- Curbside Note -->
            <div class="p-3 bg-[#f5eee2] rounded-xl border border-[#e0d9cc] text-[11px] text-[#7a5620] space-y-1">
              <div class="font-bold flex items-center gap-1">
                <Car class="w-3.5 h-3.5" />
                <span>Curbside Pickup Bay</span>
              </div>
              <p class="font-normal text-stone-600 leading-snug">
                {{ form.curbside_instructions || 'Thermal bagged and loaded upon arrival.' }}
              </p>
            </div>
          </div>
        </div>

      </div>

    </div>
  </AdminLayout>
</template>
