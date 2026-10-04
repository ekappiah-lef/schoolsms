import { defineConfig } from 'vite';
import laravel from 'laravel-vite-plugin';
import react from '@vitejs/plugin-react';
import path from 'node:path';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/js/app.jsx'],
            refresh: ['resources/views/app.blade.php', 'app/Http/Controllers/**', 'app/Helpers/**'],
        }),
        react(),
    ],
    resolve: {
        alias: {
            '@': path.resolve(import.meta.dirname, 'resources/js'),
        },
    },
    build: {
        rollupOptions: {
            output: {
                // Charts are only used on a few pages; keep them out of the main chunk.
                manualChunks(id) {
                    if (id.includes('node_modules/recharts') || id.includes('node_modules/d3-')) return 'charts';
                },
            },
        },
    },
});
