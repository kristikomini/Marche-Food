import { createApp, h } from 'vue';
import { createInertiaApp } from '@inertiajs/vue3';
import PrimeVue from 'primevue/config';
import { definePreset } from '@primeuix/themes';
import Aura from '@primeuix/themes/aura';
import ToastService from 'primevue/toastservice';
import ConfirmationService from 'primevue/confirmationservice';
import Tooltip from 'primevue/tooltip';
import 'primeicons/primeicons.css';

// Self-hosted variable fonts (GDPR-safe — no Google CDN). See resources/css/app.css.
import '@fontsource-variable/fraunces';
import '@fontsource-variable/inter';
import '@fontsource-variable/jetbrains-mono';

// Align PrimeVue's Aura preset with the Marche design tokens so its components
// (buttons, data tables, selects, focus rings) share the brand's pine primary
// and warm-stone surfaces — no more emerald-vs-forest mismatch.
const MarchePreset = definePreset(Aura, {
    semantic: {
        primary: {
            50:  '#ecf5f1',
            100: '#cfe7dd',
            200: '#a6d2c2',
            300: '#74b7a1',
            400: '#479b81',
            500: '#2e6b57',
            600: '#255846',
            700: '#1e4638',
            800: '#17352a',
            900: '#0f241c',
            950: '#08150f',
        },
        colorScheme: {
            light: {
                primary: {
                    color: '#2e6b57',
                    contrastColor: '#ffffff',
                    hoverColor: '#255846',
                    activeColor: '#1e4638',
                },
                surface: {
                    0:   '#ffffff',
                    50:  '#faf8f4',
                    100: '#f4f1ea',
                    200: '#e7e1d6',
                    300: '#d8d0c1',
                    400: '#b8ae9c',
                    500: '#928b7e',
                    600: '#6e685d',
                    700: '#57514a',
                    800: '#3a362f',
                    900: '#211e19',
                    950: '#14120e',
                },
            },
        },
    },
});

createInertiaApp({
    resolve: name => {
        const pages = import.meta.glob('./Pages/**/*.vue', { eager: true });
        return pages[`./Pages/${name}.vue`];
    },
    setup({ el, App, props, plugin }) {
        createApp({ render: () => h(App, props) })
            .use(plugin)
            .use(PrimeVue, {
                theme: {
                    preset: MarchePreset,
                    options: { darkModeSelector: '.dark' },
                },
            })
            .use(ToastService)
            .use(ConfirmationService)
            .directive('tooltip', Tooltip)
            .mount(el);
    },
});
