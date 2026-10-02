import { readFileSync } from 'node:fs'
import { build } from 'vite'
import laravel from 'laravel-vite-plugin'
import tailwindcss from '@tailwindcss/vite'

const root = readFileSync('build/consumer-path.txt', 'utf8').trim()
await build({
    root,
    configFile: false,
    plugins: [
        laravel({
            input: ['resources/css/filament/admin/theme.css'],
            publicDirectory: 'public',
        }),
        tailwindcss(),
    ],
})
