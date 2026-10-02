import { defineConfig } from '@playwright/test'

export default defineConfig({
    testDir: './tests-performance',
    outputDir: './build/performance-browser-results',
    workers: 1,
    timeout: 180000,
    use: { baseURL: 'http://127.0.0.1:8013', trace: 'retain-on-failure' },
    webServer: {
        command:
            'php tests-performance/setup.php && php -S 127.0.0.1:8013 -t vendor/orchestra/testbench-core/laravel/public tests-performance/server.php',
        url: 'http://127.0.0.1:8013/admin/performance-parent-page',
        env: { ...process.env, XDEBUG_MODE: 'off' },
        reuseExistingServer: false,
        timeout: 60000,
    },
})
