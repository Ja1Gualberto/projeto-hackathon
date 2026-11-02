import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js'],
            refresh: ['app/**', 'resources/views/**', 'routes/**'],
            fonts: [
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                    optimizedFallbacks: false,
                }),
            ],
        }),
        tailwindcss(),
    ],
    server: {
        host: process.env.VITE_SERVER_HOST ?? 'localhost',
        port: Number(process.env.VITE_PORT ?? 5173),
        strictPort: true,
        hmr: {
            host: process.env.VITE_HMR_HOST ?? 'localhost',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
