import { defineConfig } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],

            // Testbench's actual Laravel application's public directory.
            publicDirectory: 'vendor/orchestra/testbench-core/laravel/public',

            // Keep Laravel's normal Vite build directory.
            buildDirectory: 'build',

            refresh: ['resources/views/**/*.blade.php', 'workbench/**/*.php'],
        }),

        tailwindcss(),
    ],
})
