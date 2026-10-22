import { fileURLToPath, URL } from 'node:url';
import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.js', 'resources/js/islands.jsx', 'resources/js/inertia.jsx'],
            refresh: true,
            fonts: [
                bunny('Manrope', {
                    weights: [400, 500, 600, 700, 800],
                }),
            ],
        }),
        react(),
        tailwindcss(),
    ],
    resolve: {
        // « @/… » pointe vers resources/js (alias utilisé par shadcn/ui).
        alias: { '@': fileURLToPath(new URL('./resources/js', import.meta.url)) },
    },
    server: {
        host: '0.0.0.0',
        hmr: {
            host: '192.168.1.110',
        },
        watch: {
            ignored: ['**/storage/framework/views/**'],
        },
    },
});
