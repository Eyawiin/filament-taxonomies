import { defineConfig } from '@playwright/test'

export default defineConfig({
    testDir: './tests-browser',
    outputDir: './build/browser-results',
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:8011', trace: 'retain-on-failure' },
    webServer: {
        command:
            'php tests-browser/setup.php && php -S 127.0.0.1:8011 -t vendor/orchestra/testbench-core/laravel/public tests-browser/server.php',
        url: 'http://127.0.0.1:8011/admin/browser-parent-page',
        reuseExistingServer: false,
        timeout: 60000,
    },
})
