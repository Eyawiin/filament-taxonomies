import { test, expect } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const first = (page) => page.locator('.taxonomy-parent-tree').first()
const trigger = (field) => field.locator('.taxonomy-parent-trigger')
const item = (field, id) => field.locator('[data-node-id="' + id + '"]')

let errors

test.beforeEach(async ({ page }) => {
    errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    await page.goto('/admin/browser-parent-page')
    await expect(trigger(first(page))).toHaveText('Italy')
})

test.afterEach(() => {
    expect(errors).toEqual([])
})

test('keyboard enters selected item, separates focus and selection, clears root and restores focus', async ({
    page,
}) => {
    const field = first(page)
    await trigger(field).focus()
    await page.keyboard.press('ArrowDown')
    await expect(item(field, 2)).toBeFocused()
    await expect(item(field, 2)).not.toHaveAttribute('aria-selected')
    await page.keyboard.press('End')
    await expect(trigger(field)).toHaveText('Italy')
    await page.keyboard.press('Home')
    await expect(item(field, 'root')).toBeFocused()
    await page.keyboard.press('Enter')
    await expect(trigger(field)).toHaveText('No parent (root term)')
    await expect(trigger(field)).toBeFocused()
    await expect(field.locator('[role=tree]')).toBeHidden()
})

test('collapse and search recover visible focus; unavailable branches remain explorable', async ({
    page,
}) => {
    const field = first(page)
    await trigger(field).click()
    await field.locator('input[type=search]').fill('Rome')
    await page.keyboard.press('ArrowDown')
    await expect(item(field, 'root')).toBeFocused()
    await page.keyboard.press('End')
    await expect(item(field, 3)).toBeFocused()
    await page.keyboard.press('Enter')
    await expect(trigger(field)).toHaveText('Italy')
    await expect(field.locator('[role=tree]')).toBeVisible()
    await page.keyboard.press('ArrowLeft')
    await expect(item(field, 2)).toBeFocused()
    await page.keyboard.press('Escape')
    await trigger(field).click()
    await field.locator('input[type=search]').fill('missing')
    await expect(field.getByRole('status')).toHaveText('No matching terms')
    await page.keyboard.press('ArrowDown')
    await expect(item(field, 'root')).toBeFocused()
    await page.keyboard.press('Escape')
    await trigger(field).click()
    await field.locator('input[type=search]').press('Tab')
    await expect(item(field, 2)).toBeFocused()
})

test('Livewire updates state, options and disabled/read-only controls without stale listeners', async ({
    page,
}) => {
    const field = first(page)
    await page.getByText('Set parent', { exact: true }).click()
    await expect(trigger(field)).toHaveText('France')
    await trigger(field).click()
    await page.getByText('Toggle disabled', { exact: true }).click()
    await expect(trigger(field)).toBeDisabled()
    await expect(field.locator('[role=tree]')).toBeHidden()
    await page.getByText('Toggle disabled', { exact: true }).click()
    await expect(trigger(field)).toBeEnabled()
    await page.getByText('Toggle read only', { exact: true }).click()
    await trigger(field).click()
    await expect(field.locator('[role=tree]')).toBeHidden()
    await page.getByText('Toggle read only', { exact: true }).click()
    await page.getByText('Rename parent', { exact: true }).click()
    await expect(trigger(field)).toHaveText('Updated parent')
    await page.getByText('Validate', { exact: true }).click()
    await expect(trigger(field)).toHaveText('Updated parent')
})

test('fields and repeater instances are isolated and labels remain plain text', async ({
    page,
}) => {
    const fields = page.locator('.taxonomy-parent-tree')
    await expect(fields).toHaveCount(4)
    const ids = await fields
        .locator('.taxonomy-parent-trigger')
        .evaluateAll((elements) => elements.map((el) => el.id))
    expect(new Set(ids).size).toBe(4)
    await trigger(fields.nth(1)).click()
    await item(fields.nth(1), 4).click()
    await expect(trigger(fields.nth(1))).toHaveText('France')
    await expect(trigger(fields.first())).toHaveText('Italy')
    await trigger(fields.nth(2)).click()
    await item(fields.nth(2), 5).click()
    await expect(trigger(fields.nth(2))).toHaveText(
        '<img src=x onerror=alert(1)>',
    )
    await expect(fields.locator('img')).toHaveCount(0)
    await expect(trigger(fields.nth(3))).toHaveText('No parent (root term)')
})

test('nested accessibility tree exposes hierarchy, selection and unavailable reasons', async ({
    page,
}) => {
    const field = first(page)
    await trigger(field).click()
    await expect(item(field, 3).locator('..')).toHaveAttribute('role', 'group')
    await expect(item(field, 3)).toHaveAttribute('aria-level', '3')
    await expect(item(field, 2)).toHaveAttribute('aria-disabled', 'true')
    await expect(item(field, 2)).not.toHaveAttribute('aria-selected')
    const scan = await new AxeBuilder({ page })
        .include('.taxonomy-parent-tree')
        .analyze()
    expect(scan.violations).toEqual([])
    const snapshot = await field.locator('[role=tree]').ariaSnapshot()
    expect(snapshot).toContain('treeitem "Country"')
    expect(snapshot).toContain('treeitem "Italy"')
    expect(snapshot).toContain('group')
})

test('RTL, dark mode and reduced motion keep popup and disclosure usable at narrow widths', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 640 })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.evaluate(() => {
        document.documentElement.dir = 'rtl'
        document.body.dir = 'rtl'
        document.documentElement.classList.add('dark')
    })
    const field = first(page)
    await trigger(field).focus()
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('ArrowRight')
    await expect(item(field, 2)).toBeFocused()
    await page.keyboard.press('ArrowRight')
    await expect(item(field, 1)).toBeFocused()
    const bounds = await field
        .locator('.taxonomy-parent-dropdown')
        .boundingBox()
    expect(bounds.x).toBeGreaterThanOrEqual(0)
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(390)
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(640)
})

test('action modals remount cleanly after validation and cancellation', async ({
    page,
}) => {
    await page.goto('/admin/taxonomies/1/manage-terms')
    for (let attempt = 0; attempt < 2; attempt++) {
        await page
            .getByRole('button', { name: 'Create Term', exact: true })
            .click()
        const modal = page
            .getByRole('dialog')
            .filter({ has: page.locator('.taxonomy-parent-tree') })
        const field = modal.locator('.taxonomy-parent-tree')
        await trigger(field).click()
        await item(field, 4).click()
        await expect(trigger(field)).toHaveText('France')
        await modal
            .getByRole('textbox', { name: /^Name/ })
            .fill('Duplicate test')
        await modal.getByRole('textbox', { name: /^Slug/ }).fill('term-1')
        await modal.getByRole('button', { name: 'Submit', exact: true }).click()
        await expect(
            modal.locator('[data-validation-error]').first(),
        ).toBeVisible()
        await expect(trigger(field)).toHaveText('France')
        await trigger(field).focus()
        await page.keyboard.press('ArrowDown')
        await expect(item(field, 4)).toBeFocused()
        await page.keyboard.press('Home')
        await expect(item(field, 'root')).toBeFocused()
        await page.keyboard.press('Enter')
        await expect(trigger(field)).toHaveText('No parent (root term)')
        await page.keyboard.press('Escape')
        await expect(modal).toBeHidden()
    }
})
