import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import vue from '@vitejs/plugin-vue';
import tailwindcss from '@tailwindcss/vite';
import basicSsl from '@vitejs/plugin-basic-ssl';

export default defineConfig({
    plugins: [
        basicSsl(),
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
        tailwindcss(),
    ],
    server: {
        host: '0.0.0.0',
        https: true,
        cors: true,
        hmr: {
            host: 'localhost',
            protocol: 'wss',
        }
    },
});