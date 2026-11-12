import { test, expect } from '@playwright/test'
import { readFileSync, writeFileSync } from 'node:fs'

test('measure native management, parent selection and sidebar on isolated fixtures', async ({
    page,
    browser,
}) => {
    const fixtures = JSON.parse(readFileSync('build/performance-fixtures.json'))
    const report = {
        browser: browser.version(),
        viewport: page.viewportSize(),
        fixtures: {},
    }
    const errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    for (const [slug, fixture] of Object.entries(fixtures)) {
        const measurements = {}
        const lazy = fixture.size > 100
        for (const [surface, url] of [
            [
                'manage',
                '/admin/taxonomies/' + fixture.taxonomy_id + '/manage-terms',
            ],
            ['parent', '/admin/performance-parent-page?fixture=' + slug],
        ]) {
            const samples = []
            for (let iteration = 0; iteration < 3; iteration++) {
                const started = performance.now()
                const response = await page.goto(url)
                expect(response.ok()).toBe(true)
                if (surface === 'manage') {
                    // Above the default threshold of 100 terms only the roots render.
                    await expect(
                        page.locator('[data-taxonomy-term]'),
                    ).toHaveCount(lazy ? fixture.roots : fixture.size)
                    await expect(
                        page.getByRole('button', {
                            name: 'Collapse All',
                            exact: true,
                        }),
                    ).toBeVisible()
                } else {
                    await expect(
                        page.locator('.taxonomy-term-trigger'),
                    ).toContainText('No parent (root term)')
                }
                samples.push({
                    ready_ms: performance.now() - started,
                    server_ms: Number(
                        response.headers()['x-performance-server-ms'],
                    ),
                    queries: Number(
                        response.headers()['x-performance-queries'],
                    ),
                    html_bytes: Number(
                        response.headers()['x-performance-html-bytes'],
                    ),
                    dom_elements: await page.locator('*').count(),
                    sidebar_items: await page
                        .locator('.fi-sidebar-item')
                        .count(),
                })
            }
            measurements[surface] = samples
            if (surface === 'parent') {
                const field = page.locator('.taxonomy-term-field')
                const rows = field.locator('[role=treeitem]')
                await field.locator('.taxonomy-term-trigger').click()
                const search = field.getByRole('searchbox')
                measurements.search = []
                for (const query of [
                    fixture.last_name,
                    'no-matching-term',
                    '',
                ]) {
                    const started = performance.now()
                    await search.fill(query)
                    if (query === fixture.last_name) {
                        await expect(rows).toHaveCount(1)
                    } else if (query) {
                        await expect(field.getByRole('status')).toHaveText(
                            'No matching terms',
                        )
                        await expect(rows).toHaveCount(0)
                    } else {
                        // Loaded trees show every term; lazy ones the roots. Both add the empty choice.
                        await expect(rows).toHaveCount(
                            (lazy ? fixture.roots : fixture.size) + 1,
                        )
                    }
                    measurements.search.push({
                        query,
                        ms: performance.now() - started,
                    })
                }
                await search.press('ArrowDown')
                const started = performance.now()
                await page.keyboard.press('End')
                await expect(rows.last()).toBeFocused()
                measurements.end_navigation_ms = performance.now() - started
                await page.keyboard.press('Home')
                await expect(
                    field.locator('[data-row-key="empty"]'),
                ).toBeFocused()
                await page.keyboard.press('Escape')
            }
        }
        report.fixtures[slug] = measurements
    }
    expect(errors).toEqual([])
    const label = process.env.PERFORMANCE_REPORT ?? 'current'
    expect(label).toMatch(/^[a-z0-9-]+$/)
    writeFileSync(
        'build/performance-' + label + '-browser.json',
        JSON.stringify(report, null, 2),
    )
})
