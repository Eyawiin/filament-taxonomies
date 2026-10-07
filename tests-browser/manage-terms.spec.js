import { expect, test } from '@playwright/test'

let errors
let taxonomy

const terms = async (page) =>
    (await page.request.get('/__taxonomy-terms/demo-large-tree')).json()
const byName = (name) => taxonomy.terms.find((term) => term.name === name)
const rowOf = (page, name) =>
    page.locator(
        '[data-term-id="' + byName(name).id + '"] > [data-taxonomy-row]',
    )
const rootOrder = async (page) =>
    (await terms(page)).terms
        .filter((term) => term.parent_id === null)
        .sort((a, b) => a.position - b.position)
        .map((term) => term.name)
const ROOTS = [
    'Science',
    'Languages',
    'Humanities',
    'Technology',
    'Arts',
    'Society',
]

test.beforeEach(async ({ page }) => {
    errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    taxonomy = await terms(page)
    await page.goto('/admin/taxonomies/' + taxonomy.id + '/manage-terms')
})

test.afterEach(() => {
    expect(errors).toEqual([])
})

test('large taxonomies render roots and load branches on demand, remembered across visits', async ({
    page,
}) => {
    await expect(page.locator('[data-taxonomy-term]')).toHaveCount(6)
    await expect(
        page.getByRole('button', { name: 'Expand All', exact: true }),
    ).toHaveCount(0)
    await expect(rowOf(page, 'Science')).toContainText('13')
    await rowOf(page, 'Science')
        .getByRole('button', { name: 'Toggle children', exact: true })
        .click()
    await expect(page.locator('[data-taxonomy-term]')).toHaveCount(19)
    await expect(rowOf(page, 'Subject 01')).toBeVisible()

    await page.reload()
    await expect(page.locator('[data-taxonomy-term]')).toHaveCount(19)
    await page
        .getByRole('button', { name: 'Collapse All', exact: true })
        .click()
    await expect(page.locator('[data-taxonomy-term]')).toHaveCount(6)
})

test('search shows matches below their ancestors and pauses reordering', async ({
    page,
}) => {
    await page
        .getByRole('searchbox', { name: 'Search terms' })
        .fill('deep level 25')
    await expect(page.getByText('1 matching term.')).toBeVisible()
    await expect(page.locator('[data-taxonomy-term]')).toHaveCount(25)
    await expect(page.locator('[data-taxonomy-drag-handle]')).toHaveCount(0)
    await expect(
        rowOf(page, 'Science').getByRole('button', {
            name: 'Move Science down',
            exact: true,
        }),
    ).toHaveAttribute('aria-disabled', 'true')

    await page.getByRole('searchbox', { name: 'Search terms' }).fill('')
    await expect(page.locator('[data-taxonomy-term]')).toHaveCount(6)
    await expect(page.locator('[data-taxonomy-drag-handle]')).toHaveCount(6)
})

test('arrows and dragging move terms, and hovering a collapsed branch opens it while dragging', async ({
    page,
}) => {
    await rowOf(page, 'Science')
        .getByRole('button', { name: 'Move Science down', exact: true })
        .click()
    await expect
        .poll(() => rootOrder(page))
        .toEqual(['Languages', 'Science', ...ROOTS.slice(2)])
    await rowOf(page, 'Science')
        .getByRole('button', { name: 'Move Science up', exact: true })
        .click()
    await expect.poll(() => rootOrder(page)).toEqual(ROOTS)

    const handle = rowOf(page, 'Society').locator('[data-taxonomy-drag-handle]')
    const source = await handle.boundingBox()
    const target = await rowOf(page, 'Arts').boundingBox()
    await page.mouse.move(
        source.x + source.width / 2,
        source.y + source.height / 2,
    )
    await page.mouse.down()
    await page.mouse.move(source.x + 8, source.y + 8, { steps: 5 })
    await page.mouse.move(
        target.x + target.width / 2,
        target.y + target.height / 2,
        { steps: 12 },
    )
    // Hovering inside a collapsed branch loads it from the server without ending the drag.
    await expect(page.locator('[data-taxonomy-term]')).toHaveCount(18)
    const arts = await rowOf(page, 'Arts').boundingBox()
    await page.mouse.move(arts.x + arts.width / 2, arts.y + arts.height / 2, {
        steps: 4,
    })
    await page.mouse.up()
    await expect
        .poll(
            async () =>
                (await terms(page)).terms.find(
                    (term) => term.name === 'Society',
                ).parent_id,
        )
        .toBe(byName('Arts').id)

    // Restore the shared fixture: back to the last root position.
    await rowOf(page, 'Society')
        .getByRole('button', { name: 'Edit Term', exact: true })
        .click()
    const modal = page
        .getByRole('dialog')
        .filter({ has: page.locator('.taxonomy-term-field') })
    await modal.locator('.taxonomy-term-trigger').click()
    await modal
        .getByRole('treeitem', { name: 'No parent (root term)', exact: true })
        .click()
    await modal.getByRole('button', { name: 'Submit', exact: true }).click()
    await expect(modal).toBeHidden()
    await expect.poll(() => rootOrder(page)).toEqual(ROOTS)
})
