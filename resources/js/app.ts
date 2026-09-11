import '../css/app.css';
import './bootstrap';

if (typeof window !== 'undefined') {
    (window as any).global = window;
}

import { createInertiaApp } from '@inertiajs/vue3';
import { resolvePageComponent } from 'laravel-vite-plugin/inertia-helpers';
import { createApp, DefineComponent, h } from 'vue';
import { ZiggyVue } from '../../vendor/tightenco/ziggy';
import { i18n, setI18nLanguage } from './locales';

const appName = import.meta.env.VITE_APP_NAME || 'SMART';

createInertiaApp({
    title: (title) => `${title} - ${appName}`,
    resolve: (name) =>
        resolvePageComponent(
            `./Pages/${name}.vue`,
            import.meta.glob<DefineComponent>('./Pages/**/*.vue'),
        ),
    setup({ el, App, props, plugin }) {
        const initialLocale = (props.initialPage.props as any)?.locale || 'id';
        setI18nLanguage(initialLocale);

        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(ZiggyVue)
            .use(i18n)
            .mount(el);
    },
    progress: {
        color: '#4B5563',
    },
});
