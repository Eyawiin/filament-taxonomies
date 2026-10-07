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

test('the inline tree assigns terms with their ancestors, removes branches and saves through the form', async ({
    page,
}) => {
    const name = 'Browser deck ' + Date.now()
    await page.goto('/admin/decks/create')
    await page.getByRole('textbox', { name: /^Name/ }).fill(name)
    const topics = fields(page).nth(0)
    const level = fields(page).nth(1)

    await item(topics, 'Vocabulary').click()
    await item(topics, 'Algebra').click()
    for (const term of [
        'Languages',
        'English',
        'Vocabulary',
        'Science',
        'Mathematics',
        'Algebra',
    ])
        await expect(item(topics, term)).toHaveAttribute('aria-checked', 'true')
    await expect(item(topics, 'Grammar')).toHaveAttribute(
        'aria-checked',
        'false',
    )
    await expect(footer(topics)).toContainText('6 selected')

    await trigger(level).click()
    await expect(popup(level)).toBeVisible()
    await item(level, 'A2').click()
    await expect(popup(level)).toBeHidden()
    await expect(trigger(level)).toContainText('Beginner › Foundation ›')
    await expect(trigger(level)).toContainText('A2')

    await page.getByRole('button', { name: 'Create', exact: true }).click()
    await expect
        .poll(async () =>
            (await state(page))
                .find((deck) => deck.name === name)
                ?.topics.sort(),
        )
        .toEqual([
            'Algebra',
            'English',
            'Languages',
            'Mathematics',
            'Science',
            'Vocabulary',
        ])
    const deck = (await state(page)).find((row) => row.name === name)
    expect(deck.levels).toEqual(['A2'])

    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(item(topics, 'Vocabulary')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(trigger(level)).toContainText('A2')
    await item(topics, 'Languages').click()
    for (const term of ['Languages', 'English', 'Vocabulary'])
        await expect(item(topics, term)).toHaveAttribute(
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
                ?.topics.sort(),
        )
        .toEqual(['Algebra', 'Mathematics', 'Science'])

    await topics
        .getByRole('button', { name: 'Clear selection', exact: true })
        .click()
    await expect(footer(topics)).toContainText('0 selected')
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await expect
        .poll(
            async () =>
                (await state(page)).find((row) => row.id === deck.id)?.topics,
        )
        .toEqual([])
    expect(
        (await state(page)).find((row) => row.id === deck.id).levels,
    ).toEqual(['A2'])
})

test('inline search, keyboard selection and collapsed branches with selection counts', async ({
    page,
}) => {
    await page.goto('/admin/decks/create')
    const topics = fields(page).nth(0)

    await search(topics).fill('GRAM')
    await expect(topics.getByRole('treeitem')).toHaveCount(1)
    await expect(item(topics, 'Grammar')).toContainText('Languages › English')
    await search(topics).press('ArrowDown')
    await expect(item(topics, 'Grammar')).toBeFocused()
    await page.keyboard.press('Space')
    await expect(item(topics, 'Grammar')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await page.keyboard.press('ArrowUp')
    await expect(search(topics)).toBeFocused()
    await search(topics).fill('')
    await expect(item(topics, 'English')).toHaveAttribute(
        'aria-checked',
        'true',
    )

    await topics
        .getByRole('button', { name: 'Collapse all', exact: true })
        .click()
    await expect(topics.getByRole('treeitem')).toHaveCount(2)
    await expect(item(topics, 'Languages')).toContainText('2 selected below')
    await item(topics, 'Languages').focus()
    await page.keyboard.press('ArrowRight')
    await expect(item(topics, 'Languages')).toHaveAttribute(
        'aria-expanded',
        'true',
    )
    await page.keyboard.press('ArrowRight')
    await expect(item(topics, 'English')).toBeFocused()
    await page.keyboard.press('ArrowLeft')
    await expect(item(topics, 'Languages')).toBeFocused()
    await page.keyboard.press('End')
    await expect(item(topics, 'Science')).toBeFocused()
})

test('restricted terms, read-only and disabled fields refuse changes', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const topics = fields(page).nth(0)
    await expect(item(topics, 'Grammar')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(item(topics, 'Algebra')).toHaveAttribute(
        'aria-disabled',
        'true',
    )
    await expect(item(topics, 'Algebra')).toContainText('Not permitted')
    await item(topics, 'Algebra').click()
    await expect(item(topics, 'Algebra')).toHaveAttribute(
        'aria-checked',
        'false',
    )

    await page.getByText('Toggle read only', { exact: true }).click()
    await expect(item(topics, 'Vocabulary')).toHaveAttribute(
        'aria-disabled',
        'true',
    )
    await item(topics, 'Vocabulary').click()
    await expect(item(topics, 'Vocabulary')).toHaveAttribute(
        'aria-checked',
        'false',
    )
    await page.getByText('Toggle read only', { exact: true }).click()
    await expect(item(topics, 'Vocabulary')).not.toHaveAttribute(
        'aria-disabled',
    )
    await item(topics, 'Vocabulary').click()
    await expect(item(topics, 'Vocabulary')).toHaveAttribute(
        'aria-checked',
        'true',
    )

    await page.getByText('Toggle disabled', { exact: true }).click()
    await expect(search(topics)).toBeDisabled()
    await expect(topics.locator('.fi-input-wrp')).toHaveClass(/fi-disabled/)
})

test('repeater rows, an action modal and reactive cardinality keep their fields independent', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    await item(fields(page).nth(1), 'Verbs').click()
    await expect(item(fields(page).nth(1), 'Verbs')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(item(fields(page).nth(2), 'Verbs')).toHaveAttribute(
        'aria-checked',
        'false',
    )

    await page.getByText('Toggle multiple fixture', { exact: true }).click()
    const single = fields(page).nth(0)
    await expect(trigger(single)).toContainText('Select a term')
    await trigger(single).click()
    await item(single, 'Geometry').click()
    await expect(trigger(single)).toContainText('Geometry')
    await page.getByText('Toggle multiple fixture', { exact: true }).click()
    await expect(footer(fields(page).nth(0))).toContainText('0 selected')
    await expect(item(fields(page).nth(1), 'Verbs')).toHaveAttribute(
        'aria-checked',
        'true',
    )

    const openModal = async () => {
        await page
            .getByRole('button', { name: 'Edit deck assignments', exact: true })
            .click()
        const modal = page
            .getByRole('dialog')
            .filter({ has: page.locator('.taxonomy-term-field') })
        await expect(modal).toBeVisible()
        return modal
    }
    let modal = await openModal()
    await item(modal, 'Biology').click()
    await expect(item(modal, 'Biology')).toHaveAttribute('aria-checked', 'true')
    await modal.getByRole('button', { name: 'Cancel', exact: true }).click()
    await expect(modal).toBeHidden()
    modal = await openModal()
    await expect(item(modal, 'Biology')).toHaveAttribute(
        'aria-checked',
        'false',
    )
    await expect(item(modal, 'Grammar')).toHaveAttribute('aria-checked', 'true')
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
    await expect(chips.first()).toContainText('Science › Subject 01 ›')
    await large
        .getByRole('button', { name: 'Show all (72)', exact: true })
        .click()
    await expect(chips).toHaveCount(72)

    await trigger(large).click()
    await expect(popup(large)).toBeVisible()
    await expect(search(large)).toBeFocused()
    await expect(popup(large).getByRole('treeitem')).toHaveCount(6)
    await expect(item(large, 'Science')).toContainText('14 selected below')
    await item(large, 'Science').locator('.taxonomy-term-toggle').click()
    await expect(item(large, 'Subject 01')).toContainText('6 selected below')
    await expect(popup(large).getByRole('treeitem')).toHaveCount(19)

    await search(large).fill('overview')
    await expect(popup(large).getByRole('treeitem')).toHaveCount(50)
    await expect(popup(large).getByRole('status')).toContainText(
        'Showing 50 of 72',
    )
    await search(large).fill('deep level 25')
    await expect(popup(large).getByRole('treeitem')).toHaveCount(1)
    const deepest = popup(large).getByRole('treeitem').first()
    await expect(deepest).toContainText(
        'Science › Deep level 02 › Deep level 03',
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
    const level = fields(page).nth(1)
    await trigger(level).click()
    await search(level).fill('b2')
    await expect(level.getByRole('treeitem')).toHaveCount(1)
    await search(level).press('ArrowDown')
    await page.keyboard.press('Enter')
    await expect(trigger(level)).toContainText('Intermediate › Independent ›')
    await expect(trigger(level)).toContainText('B2')
    await trigger(level).click()
    await page.keyboard.press('Escape')
    await expect(popup(level)).toBeHidden()
    await expect(trigger(level)).toBeFocused()
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
    await trigger(parent).click()
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
    const topics = fields(page).nth(0)
    const level = fields(page).nth(1)
    await item(topics, 'Vocabulary').click()
    await item(topics, 'Languages').focus()
    await page.keyboard.press('ArrowLeft')
    await expect(item(topics, 'English')).toBeFocused()
    await trigger(level).click()
    await expect(popup(level)).toBeVisible()
    const bounds = await popup(level).boundingBox()
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
