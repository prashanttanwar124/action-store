<script setup>
import { Head, Link, useForm, usePage } from '@inertiajs/vue3';
import { computed, onBeforeUnmount, onMounted, ref } from 'vue';
import {
    ScanBarcode,
    Lock,
    Mail,
    ArrowRight,
    ArrowLeft,
    Eye,
    EyeOff,
    ShieldCheck,
    LayoutDashboard,
    Zap,
    AlertCircle,
} from 'lucide-vue-next';

const page = usePage();
const storeInfo = computed(() => page.props.storeInfo || {});
const storeName = computed(() => storeInfo.value?.name || 'Masala Mart');
const storeLocation = computed(() =>
    [storeInfo.value?.address, storeInfo.value?.city, storeInfo.value?.state]
        .filter(Boolean)
        .join(', '),
);

const showPassword = ref(false);
const liveClock = ref('');
let clockInterval = null;

const updateClock = () => {
    liveClock.value = new Date().toLocaleTimeString([], {
        hour: '2-digit',
        minute: '2-digit',
    });
};

onMounted(() => {
    updateClock();
    clockInterval = setInterval(updateClock, 15000);
});

onBeforeUnmount(() => {
    clearInterval(clockInterval);
});

const form = useForm({
    email: '',
    password: '',
    remember: true,
});

const submit = () => {
    form.post(route('pos.login.store'), {
        onFinish: () => form.reset('password'),
    });
};

const quickSignIn = (email, password = 'password') => {
    form.email = email;
    form.password = password;
    form.remember = true;
    submit();
};
</script>

<template>
    <Head title="Terminal Sign In" />

    <div class="min-h-screen bg-zinc-100 flex flex-col justify-between relative">
        <!-- Subtle Top Utility Bar -->
        <header class="w-full px-5 py-4 flex items-center justify-between">
            <div class="flex items-center gap-2">
                <Link
                    href="/"
                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-white border border-zinc-200 text-xs font-semibold text-zinc-700 hover:text-zinc-950 hover:border-zinc-300 shadow-sm transition-colors"
                >
                    <ArrowLeft class="w-3.5 h-3.5" />
                    <span>Storefront</span>
                </Link>
                <a
                    href="/admin/dashboard"
                    class="inline-flex items-center gap-1.5 h-8 px-3 rounded-md bg-white border border-zinc-200 text-xs font-semibold text-zinc-600 hover:text-zinc-950 hover:border-zinc-300 shadow-sm transition-colors"
                >
                    <LayoutDashboard class="w-3.5 h-3.5" />
                    <span>Backoffice</span>
                </a>
            </div>

            <div class="inline-flex items-center gap-2 px-3 py-1 rounded-md bg-white border border-zinc-200 shadow-sm text-xs font-mono text-zinc-600 tabular-nums">
                <span class="w-2 h-2 rounded-full bg-emerald-500 animate-pulse" />
                <span>TERMINAL READY · {{ liveClock }}</span>
            </div>
        </header>

        <!-- Centered POS Auth Card -->
        <main class="flex-1 flex items-center justify-center px-4 py-6">
            <div class="w-full max-w-[420px]">
                <!-- Brand & Terminal Identity -->
                <div class="text-center mb-6 space-y-2">
                    <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-zinc-900 text-white shadow-md mb-1">
                        <ScanBarcode class="w-6 h-6 text-emerald-400" />
                    </div>

                    <div class="flex items-center justify-center gap-2">
                        <span class="inline-flex items-center gap-1.5 px-2.5 py-0.5 rounded bg-zinc-900 text-zinc-100 text-[11px] font-mono font-semibold tracking-wider">
                            POS REGISTER
                        </span>
                    </div>

                    <h1 class="text-2xl font-bold text-zinc-950 tracking-tight">
                        {{ storeName }}
                    </h1>
                    <p class="text-xs text-zinc-500">
                        {{ storeLocation || 'In-Store Counter Point of Sale' }}
                    </p>
                </div>

                <!-- Crisp White Auth Surface -->
                <div class="bg-white border border-zinc-200/90 rounded-xl shadow-sm overflow-hidden">
                    <!-- Error Alert -->
                    <div
                        v-if="form.errors.email || form.errors.password"
                        class="px-6 py-3 bg-rose-50 border-b border-rose-200 flex items-start gap-2.5 text-xs text-rose-700 font-medium"
                    >
                        <AlertCircle class="w-4 h-4 text-rose-600 shrink-0 mt-0.5" />
                        <span>{{ form.errors.email || form.errors.password }}</span>
                    </div>

                    <form @submit.prevent="submit" class="p-6 space-y-4">
                        <div>
                            <label for="pos-email" class="block text-xs font-semibold text-zinc-700 mb-1.5">
                                Staff Email
                            </label>
                            <div class="relative">
                                <Mail class="w-4 h-4 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                <input
                                    id="pos-email"
                                    v-model="form.email"
                                    type="email"
                                    required
                                    autofocus
                                    autocomplete="username"
                                    placeholder="admin@masalamart.com"
                                    class="w-full h-11 pl-10 pr-3.5 rounded-lg border border-zinc-200 bg-white text-sm text-zinc-950 font-medium"
                                    :class="{ '!border-rose-500': form.errors.email }"
                                />
                            </div>
                        </div>

                        <div>
                            <label for="pos-password" class="block text-xs font-semibold text-zinc-700 mb-1.5">
                                Password
                            </label>
                            <div class="relative">
                                <Lock class="w-4 h-4 text-zinc-400 absolute left-3.5 top-1/2 -translate-y-1/2 pointer-events-none" />
                                <input
                                    id="pos-password"
                                    v-model="form.password"
                                    :type="showPassword ? 'text' : 'password'"
                                    required
                                    autocomplete="current-password"
                                    placeholder="Enter staff password"
                                    class="w-full h-11 pl-10 pr-10 rounded-lg border border-zinc-200 bg-white text-sm text-zinc-950 font-medium"
                                    :class="{ '!border-rose-500': form.errors.password }"
                                />
                                <button
                                    type="button"
                                    @click="showPassword = !showPassword"
                                    class="absolute right-3 top-1/2 -translate-y-1/2 text-zinc-400 hover:text-zinc-700 cursor-pointer"
                                    :title="showPassword ? 'Hide password' : 'Show password'"
                                >
                                    <EyeOff v-if="showPassword" class="w-4 h-4" />
                                    <Eye v-else class="w-4 h-4" />
                                </button>
                            </div>
                        </div>

                        <div class="flex items-center justify-between pt-0.5">
                            <label class="inline-flex items-center gap-2 cursor-pointer select-none">
                                <input
                                    v-model="form.remember"
                                    type="checkbox"
                                    class="w-4 h-4 rounded border-zinc-300 text-zinc-900"
                                />
                                <span class="text-xs font-medium text-zinc-600">
                                    Keep counter register active
                                </span>
                            </label>
                            <span class="text-[11px] font-mono text-zinc-400">PIN switch enabled</span>
                        </div>

                        <button
                            type="submit"
                            :disabled="form.processing"
                            class="w-full h-11 rounded-lg bg-zinc-900 hover:bg-zinc-800 active:bg-zinc-950 text-white text-sm font-semibold flex items-center justify-center gap-2 shadow-sm transition-colors cursor-pointer disabled:opacity-50"
                        >
                            <span v-if="form.processing">Unlocking Register...</span>
                            <template v-else>
                                <span>Sign In to Register</span>
                                <ArrowRight class="w-4 h-4" />
                            </template>
                        </button>
                    </form>

                    <!-- 1-Tap Quick Terminal Access -->
                    <div class="px-6 py-4 bg-zinc-50 border-t border-zinc-200/80">
                        <div class="flex items-center justify-between mb-2.5">
                            <span class="text-[11px] font-semibold uppercase tracking-wider text-zinc-500 inline-flex items-center gap-1">
                                <Zap class="w-3 h-3 text-amber-500" />
                                Quick Demo Launch
                            </span>
                            <span class="text-[10px] font-mono text-zinc-400">1-tap sign in</span>
                        </div>

                        <div class="grid grid-cols-2 gap-2.5">
                            <button
                                type="button"
                                :disabled="form.processing"
                                @click="quickSignIn('admin@masalamart.com')"
                                class="p-2.5 rounded-lg bg-white hover:bg-zinc-100/80 border border-zinc-200 hover:border-zinc-300 text-left transition-all shadow-sm cursor-pointer group"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-zinc-900 group-hover:text-emerald-700">
                                        Super Admin
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600 font-mono text-[10px] font-semibold">
                                        PIN 1111
                                    </span>
                                </div>
                                <div class="text-[11px] font-mono text-zinc-500 truncate mt-0.5">
                                    admin@masalamart.com
                                </div>
                            </button>

                            <button
                                type="button"
                                :disabled="form.processing"
                                @click="quickSignIn('manager@masalamart.com')"
                                class="p-2.5 rounded-lg bg-white hover:bg-zinc-100/80 border border-zinc-200 hover:border-zinc-300 text-left transition-all shadow-sm cursor-pointer group"
                            >
                                <div class="flex items-center justify-between">
                                    <span class="text-xs font-bold text-zinc-900 group-hover:text-emerald-700">
                                        Store Manager
                                    </span>
                                    <span class="px-1.5 py-0.5 rounded bg-zinc-100 text-zinc-600 font-mono text-[10px] font-semibold">
                                        PIN 2222
                                    </span>
                                </div>
                                <div class="text-[11px] font-mono text-zinc-500 truncate mt-0.5">
                                    manager@masalamart.com
                                </div>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
        </main>

        <!-- Footer Security Bar -->
        <footer class="py-4 text-center">
            <div class="inline-flex items-center gap-1.5 text-xs text-zinc-500">
                <ShieldCheck class="w-3.5 h-3.5 text-emerald-600" />
                <span>Authenticated staff session (`guard: admin` · `operate pos`)</span>
            </div>
        </footer>
    </div>
</template>
