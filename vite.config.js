import { defineConfig } from 'vite';
import vue from '@vitejs/plugin-vue';
import path from 'node:path';

// Builds Vue + SCSS into webroot/build/ with a manifest.json that the
// CakePHP layout reads via a small helper to load hashed asset URLs.
export default defineConfig({
    plugins: [vue()],

    build: {
        outDir: path.resolve(__dirname, 'webroot/build'),
        emptyOutDir: true,
        manifest: 'manifest.json',
        rollupOptions: {
            input: {
                app: path.resolve(__dirname, 'resources/js/app.js'),
            },
        },
    },

    css: {
        preprocessorOptions: {
            scss: {
                api: 'modern-compiler',
            },
        },
    },

    server: {
        // Vite dev server runs OUTSIDE Coolify (npm run dev locally) so it
        // can hot-reload while CakePHP serves the rest.
        host: '0.0.0.0',
        port: 5173,
        strictPort: true,
    },
});
