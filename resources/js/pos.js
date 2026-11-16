import '../css/admin.css';
import './bootstrap';

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createPinia } from 'pinia';
import { createApp, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';

const pinia = createPinia();

createInertiaApp({
    title: (title) => (title ? `${title} — Masala Mart POS` : 'Masala Mart POS Register'),
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob(['./Pages/Pos/**/*.vue', './Pages/Error.vue']),
        ),
    setup({ el, App, props, plugin }) {
        return createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(pinia)
            .use(ZiggyVue)
            .mount(el);
    },
    progress: {
        color: '#10b981',
    },
});

