import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const fields = (page) => page.locator('.taxonomy-term-field')
const item = (scope, name) => scope.getByRole('treeitem', { name, exact: true })
const trigger = (field) => field.locator('.taxonomy-term-trigger')
const popup = (field) => field.locator('.taxonomy-term-popup')
const search = (scope) => scope.getByRole('searchbox')
const footer = (field) => field.locator('.taxonomy-term-footer').first()
const state = async (page) =>
    (await page.request.get('/__assignment-state')).json()

let errors

test.beforeEach(({ page }) => {
    errors = []
    page.on('pageerror', (error) => errors.push(error.message))
})

test.afterEach(() => {
    expect(errors).toEqual([])
})

test('the inline tree assigns themes with their ancestors, removes branches and saves through the form', async ({
    page,
}) => {
    const name = 'Browser deck ' + Date.now()
    await page.goto('/admin/decks/create')
    await page.getByRole('textbox', { name: /^Name/ }).fill(name)
    const themes = fields(page).nth(0)
    const brand = fields(page).nth(1)

    await item(themes, 'The Lion King').click()
    await item(themes, 'Wolves').click()
    for (const term of [
        'Pop culture',
        'Disney',
        'The Lion King',
        'Nature',
        'Animals',
        'Wolves',
    ])
        await expect(item(themes, term)).toHaveAttribute('aria-checked', 'true')
    await expect(item(themes, 'Lilo & Stitch')).toHaveAttribute(
        'aria-checked',
        'false',
    )
    await expect(footer(themes)).toContainText('6 selected')

    // Three brands have a Blue back; the path in the search picks the right one.
    await trigger(brand).click()
    await expect(popup(brand)).toBeVisible()
    await search(brand).fill('bicycle blue')
    await item(brand, 'Blue').click()
    await expect(popup(brand)).toBeHidden()
    await expect(trigger(brand)).toContainText('Bicycle › Rider Back ›')
    await expect(trigger(brand)).toContainText('Blue')

    await page.getByRole('button', { name: 'Create', exact: true }).click()
    await expect
        .poll(async () =>
            (await state(page))
                .find((deck) => deck.name === name)
                ?.themes.sort(),
        )
        .toEqual([
            'Animals',
            'Disney',
            'Nature',
            'Pop culture',
            'The Lion King',
            'Wolves',
        ])
    const deck = (await state(page)).find((row) => row.name === name)
    expect(deck.brands).toEqual(['bicycle-rider-back-blue'])

    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(item(themes, 'The Lion King')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(trigger(brand)).toContainText('Blue')
    await item(themes, 'Pop culture').click()
    for (const term of ['Pop culture', 'Disney', 'The Lion King'])
        await expect(item(themes, term)).toHaveAttribute(
            'aria-checked',
            'false',
        )
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await expect
        .poll(async () =>
            (await state(page))
                .find((row) => row.id === deck.id)
                ?.themes.sort(),
        )
        .toEqual(['Animals', 'Nature', 'Wolves'])

    await themes
        .getByRole('button', { name: 'Clear selection', exact: true })
        .click()
    await expect(footer(themes)).toContainText('0 selected')
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await expect
        .poll(
            async () =>
                (await state(page)).find((row) => row.id === deck.id)?.themes,
        )
        .toEqual([])
    expect(
        (await state(page)).find((row) => row.id === deck.id).brands,
    ).toEqual(['bicycle-rider-back-blue'])
})

test('inline search, keyboard selection and collapsed branches with selection counts', async ({
    page,
}) => {
    await page.goto('/admin/decks/create')
    const themes = fields(page).nth(0)

    await search(themes).fill('LILO')
    await expect(themes.getByRole('treeitem')).toHaveCount(1)
    await expect(item(themes, 'Lilo & Stitch')).toContainText(
        'Pop culture › Disney',
    )
    await search(themes).press('ArrowDown')
    await expect(item(themes, 'Lilo & Stitch')).toBeFocused()
    await page.keyboard.press('Space')
    await expect(item(themes, 'Lilo & Stitch')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await page.keyboard.press('ArrowUp')
    await expect(search(themes)).toBeFocused()
    await search(themes).fill('')
    await expect(item(themes, 'Disney')).toHaveAttribute('aria-checked', 'true')

    await themes
        .getByRole('button', { name: 'Collapse all', exact: true })
        .click()
    await expect(themes.getByRole('treeitem')).toHaveCount(2)
    await expect(item(themes, 'Pop culture')).toContainText('2 selected below')
    await item(themes, 'Pop culture').focus()
    await page.keyboard.press('ArrowRight')
    await expect(item(themes, 'Pop culture')).toHaveAttribute(
        'aria-expanded',
        'true',
    )
    await page.keyboard.press('ArrowRight')
    await expect(item(themes, 'Disney')).toBeFocused()
    await page.keyboard.press('ArrowLeft')
    await expect(item(themes, 'Pop culture')).toBeFocused()
    await page.keyboard.press('End')
    await expect(item(themes, 'Nature')).toBeFocused()
})

test('restricted terms, read-only and disabled fields refuse changes', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const themes = fields(page).nth(0)
    await expect(item(themes, 'Lilo & Stitch')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(item(themes, 'Wolves')).toHaveAttribute(
        'aria-disabled',
        'true',
    )
    await expect(item(themes, 'Wolves')).toContainText('Not permitted')
    // Playwright refuses clicks on aria-disabled elements; force one to prove nothing happens.
    await item(themes, 'Wolves').click({ force: true })
    await expect(item(themes, 'Wolves')).toHaveAttribute(
        'aria-checked',
        'false',
    )

    await page.getByText('Toggle read only', { exact: true }).click()
    await expect(item(themes, 'The Lion King')).toHaveAttribute(
        'aria-disabled',
        'true',
    )
    await item(themes, 'The Lion King').click({ force: true })
    await expect(item(themes, 'The Lion King')).toHaveAttribute(
        'aria-checked',
        'false',
    )
    await page.getByText('Toggle read only', { exact: true }).click()
    await expect(item(themes, 'The Lion King')).not.toHaveAttribute(
        'aria-disabled',
    )
    await item(themes, 'The Lion King').click()
    await expect(item(themes, 'The Lion King')).toHaveAttribute(
        'aria-checked',
        'true',
    )

    await page.getByText('Toggle disabled', { exact: true }).click()
    await expect(search(themes)).toBeDisabled()
    await expect(themes.locator('.fi-input-wrp')).toHaveClass(/fi-disabled/)
})

test('repeater rows, an action modal and reactive cardinality keep their fields independent', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    await item(fields(page).nth(1), 'The Mandalorian').click()
    await expect(item(fields(page).nth(1), 'The Mandalorian')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(item(fields(page).nth(2), 'The Mandalorian')).toHaveAttribute(
        'aria-checked',
        'false',
    )

    await page.getByText('Toggle multiple fixture', { exact: true }).click()
    const single = fields(page).nth(0)
    await expect(trigger(single)).toContainText('Select a term')
    await trigger(single).click()
    await item(single, 'Owls').click()
    await expect(trigger(single)).toContainText('Owls')
    await page.getByText('Toggle multiple fixture', { exact: true }).click()
    await expect(footer(fields(page).nth(0))).toContainText('0 selected')
    await expect(item(fields(page).nth(1), 'The Mandalorian')).toHaveAttribute(
        'aria-checked',
        'true',
    )

    const openModal = async () => {
        await page
            .getByRole('button', { name: 'Edit deck assignments', exact: true })
            .click()
        // Filament's dialog wrapper itself has no box; wait for the field inside it instead.
        const modal = page
            .getByRole('dialog')
            .filter({ has: page.locator('.taxonomy-term-field') })
        await expect(item(modal, 'Space')).toBeVisible()
        return modal
    }
    let modal = await openModal()
    await item(modal, 'Space').click()
    await expect(item(modal, 'Space')).toHaveAttribute('aria-checked', 'true')
    await modal.getByRole('button', { name: 'Cancel', exact: true }).click()
    await expect(item(modal, 'Space')).toBeHidden()
    modal = await openModal()
    await expect(item(modal, 'Space')).toHaveAttribute('aria-checked', 'false')
})

test('a large taxonomy loads branches and search results on demand in the dropdown', async ({
    page,
}) => {
    const deck = (await state(page)).find(
        (row) => row.demo_key === 'large-tree-broad',
    )
    await page.goto('/admin/decks/' + deck.id + '/edit')
    const large = fields(page).nth(2)
    const chips = large.locator('.taxonomy-term-chip')
    await expect(trigger(large)).toContainText('90 selected')
    await expect(chips).toHaveCount(20)
    await expect(chips.first()).toContainText('Disney › Lilo & Stitch ›')
    await large
        .getByRole('button', { name: 'Show all (72)', exact: true })
        .click()
    await expect(chips).toHaveCount(72)

    await trigger(large).click()
    await expect(popup(large)).toBeVisible()
    await expect(search(large)).toBeFocused()
    await expect(popup(large).getByRole('treeitem')).toHaveCount(6)
    await expect(item(large, 'Disney')).toContainText('14 selected below')
    await item(large, 'Disney').locator('.taxonomy-term-toggle').click()
    await expect(item(large, 'Lilo & Stitch')).toContainText('6 selected below')
    await expect(popup(large).getByRole('treeitem')).toHaveCount(19)

    await search(large).fill('standard')
    await expect(popup(large).getByRole('treeitem')).toHaveCount(50)
    await expect(popup(large).getByRole('status')).toContainText(
        'Showing 50 of 72',
    )
    await search(large).fill('deep level 25')
    await expect(popup(large).getByRole('treeitem')).toHaveCount(1)
    const deepest = popup(large).getByRole('treeitem').first()
    await expect(deepest).toContainText(
        'Disney › Deep level 02 › Deep level 03',
    )
    await deepest.click()
    await expect(deepest).toHaveAttribute('aria-checked', 'true')
    await expect(trigger(large)).toContainText('114 selected')

    await page.keyboard.press('Escape')
    await expect(popup(large)).toBeHidden()
    await expect(trigger(large)).toBeFocused()
    await large.getByRole('button', { name: /^Remove Deep level 25/ }).click()
    await expect(trigger(large)).toContainText('113 selected')
    await expect(chips.filter({ hasText: 'Deep level 24' })).toHaveCount(1)
})

test('single dropdowns support the keyboard, the empty choice and search', async ({
    page,
}) => {
    await page.goto('/admin/browser-parent-page')
    const parent = fields(page).nth(0)
    await expect(trigger(parent)).toContainText('Italy')
    await trigger(parent).focus()
    await page.keyboard.press('ArrowDown')
    await expect(item(parent, 'Italy')).toBeFocused()
    await expect(item(parent, 'Italy')).toHaveAttribute('aria-selected', 'true')
    await expect(item(parent, 'Italy')).toContainText('Current term')
    await expect(item(parent, 'Rome')).toContainText('Would create a cycle')
    await expect(item(parent, '<img src=x onerror=alert(1)>')).toBeVisible()
    await expect(parent.locator('img')).toHaveCount(0)
    await page.keyboard.press('ArrowDown')
    await page.keyboard.press('Enter')
    await expect(item(parent, 'Rome')).toBeFocused()
    await expect(trigger(parent)).toContainText('Italy')
    await page.keyboard.press('Home')
    await expect(item(parent, 'No parent (root term)')).toBeFocused()
    await page.keyboard.press('Enter')
    await expect(trigger(parent)).toContainText('No parent (root term)')
    await expect(trigger(parent)).toBeFocused()
    await expect(popup(parent)).toBeHidden()

    await page.goto('/admin/decks/create')
    const brand = fields(page).nth(1)
    await trigger(brand).click()
    await search(brand).fill('tally blue')
    await expect(brand.getByRole('treeitem')).toHaveCount(1)
    await search(brand).press('ArrowDown')
    await page.keyboard.press('Enter')
    await expect(trigger(brand)).toContainText('Tally-Ho › Circle Back ›')
    await expect(trigger(brand)).toContainText('Blue')
    await trigger(brand).click()
    await search(brand).fill('bee red')
    await search(brand).press('Enter')
    await expect(trigger(brand)).toContainText('Bee › Club Special ›')
    await expect(trigger(brand)).toContainText('Red')
    await trigger(brand).click()
    await page.keyboard.press('Escape')
    await expect(popup(brand)).toBeHidden()
    await expect(trigger(brand)).toBeFocused()
})

test('Livewire updates reach the dropdown: state, labels and disabled or read-only modes', async ({
    page,
}) => {
    await page.goto('/admin/browser-parent-page')
    const parent = fields(page).nth(0)
    await page.getByText('Set parent', { exact: true }).click()
    await expect(trigger(parent)).toContainText('France')
    await page.getByText('Rename parent', { exact: true }).click()
    await expect(trigger(parent)).toContainText('Updated parent')
    await page.getByText('Toggle disabled', { exact: true }).click()
    await expect(trigger(parent)).toBeDisabled()
    await page.getByText('Toggle disabled', { exact: true }).click()
    await expect(trigger(parent)).toBeEnabled()
    await page.getByText('Toggle read only', { exact: true }).click()
    await expect(trigger(parent)).toHaveAttribute('aria-disabled', 'true')
    await trigger(parent).click({ force: true })
    await expect(popup(parent)).toBeHidden()
})

test('fields stay accessible and contained in narrow dark right-to-left layouts', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 760 })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.goto('/admin/decks/create')
    expect(
        (
            await new AxeBuilder({ page })
                .include('.taxonomy-term-field')
                .analyze()
        ).violations,
    ).toEqual([])

    await page.evaluate(() => {
        document.documentElement.dir = 'rtl'
        document.documentElement.classList.add('dark')
    })
    const themes = fields(page).nth(0)
    const brand = fields(page).nth(1)
    await item(themes, 'The Lion King').click()
    await item(themes, 'Pop culture').focus()
    await page.keyboard.press('ArrowLeft')
    await expect(item(themes, 'Disney')).toBeFocused()
    await trigger(brand).click()
    await expect(popup(brand)).toBeVisible()
    const bounds = await popup(brand).boundingBox()
    expect(bounds.x).toBeGreaterThanOrEqual(0)
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(390)
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true)
    expect(
        (
            await new AxeBuilder({ page })
                .include('.taxonomy-term-field')
                .analyze()
        ).violations,
    ).toEqual([])
})
