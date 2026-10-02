import { defineConfig } from '@playwright/test'

export default defineConfig({
    testDir: './tests-distribution',
    outputDir: './build/consumer-browser-results',
    workers: 1,
    use: { baseURL: 'http://127.0.0.1:8012', trace: 'retain-on-failure' },
    webServer: {
        command: 'node bin/serve-consumer.js',
        url: 'http://127.0.0.1:8012/admin/taxonomies',
        reuseExistingServer: false,
        timeout: 60000,
    },
})
