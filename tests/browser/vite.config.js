import { defineConfig } from 'vite';
import tailwindcss from '@tailwindcss/vite';
import { resolve } from 'node:path';

// Builds the engine's real stylesheet and scripts for the browser tests.
export default defineConfig({
    plugins: [tailwindcss()],
    build: {
        outDir: resolve(import.meta.dirname, '.build/assets'),
        emptyOutDir: true,
        rollupOptions: {
            input: {
                styles: resolve(import.meta.dirname, '../../resources/assets/app.css'),
                app: resolve(import.meta.dirname, '../../resources/assets/app.js'),
            },
            output: { entryFileNames: '[name].js', assetFileNames: 'app.[ext]' },
        },
    },
});
