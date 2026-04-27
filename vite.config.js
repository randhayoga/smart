import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';

export default defineConfig({
    plugins: [
        laravel({
            input:
            'resources/js/app.ts',
            refresh: true,
            detectTls: false,
        }), 
        vue({
            template: {
                transformAssetUrls: {
                    base: null,
                    includeAbsolute: false,
                },
            },
        }),
    ],
    server: {
        host: '0.0.0.0',
        cors: true,
        hmr: {
            host: '127.0.0.1',
        }
    },
});