import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

async function open(field) {
    await field.locator('.taxonomy-parent-trigger').click()
    await expect(field.locator('input[type="search"]')).toBeFocused()
}
const option = (field, name) =>
    field.getByRole('treeitem', { name, exact: true })

test('Deck resource creates and reopens single and multiple assignments then clears just one taxonomy', async ({
    page,
}) => {
    const name = 'Browser deck ' + Date.now()
    await page.goto('/admin/decks/create')
    await page.getByRole('textbox', { name: /^Name/ }).fill(name)
    const topics = page.locator('.taxonomy-parent-tree').nth(0)
    const level = page.locator('.taxonomy-parent-tree').nth(1)
    await open(topics)
    await option(topics, 'Vocabulary').click()
    await option(topics, 'Algebra').click()
    const scienceGroup = topics
        .locator('.taxonomy-selection-tags')
        .getByRole('group', { name: 'Science', exact: true })
    const mathsGroup = scienceGroup.getByRole('group', {
        name: 'Mathematics',
        exact: true,
    })
    await expect(
        mathsGroup.getByRole('button', { name: 'Remove Algebra', exact: true }),
    ).toBeVisible()
    for (const term of ['Science', 'Mathematics', 'Algebra'])
        await expect(
            topics
                .locator('.taxonomy-selection-tag-name')
                .filter({ hasText: new RegExp('^' + term + '$') }),
        ).toHaveCount(1)
    await expect(topics.getByRole('tree')).toHaveAttribute(
        'aria-multiselectable',
        'true',
    )
    await expect(option(topics, 'Vocabulary')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(option(topics, 'English')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await page.keyboard.press('Escape')
    await open(level)
    await option(level, 'A2').click()
    await page.getByRole('button', { name: 'Create', exact: true }).click()
    await expect
        .poll(async () =>
            (await (await page.request.get('/__assignment-state')).json())
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
    const deck = (
        await (await page.request.get('/__assignment-state')).json()
    ).find((deck) => deck.name === name)
    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(topics.locator('.taxonomy-selection-tags')).toContainText(
        'Vocabulary',
    )
    await expect(level.locator('.taxonomy-parent-trigger')).toContainText('A2')
    await topics
        .getByRole('button', { name: 'Remove Vocabulary', exact: true })
        .click()
    await expect(topics.locator('.taxonomy-parent-trigger')).toBeFocused()
    await expect(topics.getByRole('tree')).not.toBeVisible()
    await expect(
        topics.locator('.taxonomy-selection-tag .fi-badge-label'),
    ).toHaveCount(5)
    await expect(
        topics.getByRole('button', { name: 'Remove Vocabulary', exact: true }),
    ).toHaveCount(0)
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await expect
        .poll(async () =>
            (await (await page.request.get('/__assignment-state')).json())
                .find((row) => row.id === deck.id)
                ?.topics.sort(),
        )
        .toEqual(['Algebra', 'English', 'Languages', 'Mathematics', 'Science'])
    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(
        topics.getByRole('button', { name: 'Remove Algebra', exact: true }),
    ).toBeVisible()
    await open(topics)
    await topics
        .getByRole('treeitem', {
            name: 'No terms (clear selection)',
            exact: true,
        })
        .click()
    await page.keyboard.press('Escape')
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await expect
        .poll(
            async () =>
                (
                    await (await page.request.get('/__assignment-state')).json()
                ).find((row) => row.id === deck.id)?.topics,
        )
        .toEqual([])
    expect(
        (await (await page.request.get('/__assignment-state')).json()).find(
            (row) => row.id === deck.id,
        ).levels,
    ).toEqual(['A2'])
})

test('multiple assignment keyboard toggles independently of focus and branch disclosure, with accessible disabled reasons', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const field = page.locator('.taxonomy-parent-tree').first()
    await field
        .getByRole('button', { name: 'Remove Grammar', exact: true })
        .focus()
    await page.keyboard.press('Enter')
    await expect(field.locator('.taxonomy-parent-trigger')).toBeFocused()
    await expect(field.getByRole('tree')).not.toBeVisible()
    await open(field)
    await option(field, 'Grammar').click()
    await option(field, 'Vocabulary').focus()
    await page.keyboard.press(' ')
    await expect(option(field, 'Vocabulary')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(
        field.getByRole('status').filter({ hasText: '2 terms selected' }),
    ).toBeVisible()
    const englishGroup = field
        .locator('.taxonomy-selection-tags')
        .getByRole('group', { name: 'English', exact: true })
    await expect(
        englishGroup
            .locator('.taxonomy-selection-context')
            .filter({ hasText: 'English' }),
    ).toHaveCount(1)
    await expect(
        englishGroup.getByRole('button', {
            name: 'Remove English',
            exact: true,
        }),
    ).toHaveCount(0)
    await expect(
        englishGroup.getByRole('button', {
            name: 'Remove Grammar',
            exact: true,
        }),
    ).toBeVisible()
    await expect(
        englishGroup.getByRole('button', {
            name: 'Remove Vocabulary',
            exact: true,
        }),
    ).toBeVisible()
    await page.keyboard.press('ArrowUp')
    await expect(option(field, 'Vocabulary')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await option(field, 'Languages')
        .locator(':scope > .taxonomy-parent-node button')
        .click()
    await expect(field.locator('.taxonomy-selection-tags')).toContainText(
        'Vocabulary',
    )
    await option(field, 'Algebra').focus()
    await page.keyboard.press('Enter')
    await expect(option(field, 'Algebra')).toHaveAttribute(
        'aria-disabled',
        'true',
    )
    await expect(field.locator('.taxonomy-selection-tags')).not.toContainText(
        'Algebra',
    )
    const result = await new AxeBuilder({ page })
        .include('.taxonomy-parent-tree')
        .analyze()
    expect(result.violations).toEqual([])
    await page.keyboard.press('Home')
    await page.keyboard.press('Enter')
    await expect(field.locator('.taxonomy-parent-trigger')).toContainText(
        'No terms',
    )
})

test('assignment fields in repeater rows remain independent and react to read-only state', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const fields = page.locator('.taxonomy-parent-tree')
    await expect(fields).toHaveCount(3)
    const firstRow = fields.nth(1)
    await open(firstRow)
    await option(firstRow, 'Vocabulary').click()
    await page.keyboard.press('Escape')
    await expect(
        fields.nth(2).locator('.taxonomy-parent-trigger'),
    ).toContainText('No terms')
    await page
        .getByRole('button', { name: 'Toggle read only', exact: true })
        .click()
    await expect(
        fields.first().locator('.taxonomy-parent-trigger'),
    ).toHaveAttribute('aria-disabled', 'true')
    await expect(
        fields
            .first()
            .getByRole('button', { name: 'Remove Grammar', exact: true }),
    ).toBeDisabled()
    await fields.first().locator('.taxonomy-parent-trigger').focus()
    await page.keyboard.press('Space')
    await expect(fields.first().getByRole('tree')).not.toBeVisible()
    await page
        .getByRole('button', { name: 'Toggle disabled', exact: true })
        .click()
    await expect(
        fields.first().locator('.taxonomy-parent-trigger'),
    ).toBeDisabled()
})

test('assignment modal saves its owner relationships and cancellation discards pending changes', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    await page
        .getByRole('button', { name: 'Edit deck assignments', exact: true })
        .click()
    const dialog = page
        .getByRole('dialog')
        .filter({ has: page.locator('.taxonomy-parent-tree') })
    const field = dialog.locator('.taxonomy-parent-tree')
    await open(field)
    await option(field, 'Vocabulary').click()
    await page.keyboard.press('Escape')
    await dialog.getByRole('button', { name: 'Cancel', exact: true }).click()
    await page
        .getByRole('button', { name: 'Edit deck assignments', exact: true })
        .click()
    await expect(field.locator('.taxonomy-selection-tags')).not.toContainText(
        'Vocabulary',
    )
    await open(field)
    await option(field, 'Vocabulary').click()
    await page.keyboard.press('Escape')
    await dialog
        .getByRole('button', { name: 'Save assignments', exact: true })
        .click()
    await expect(dialog).not.toBeVisible()
    await expect
        .poll(
            async () =>
                (
                    await (await page.request.get('/__assignment-state')).json()
                ).find((deck) => deck.name === 'English basics')?.topics,
        )
        .toContain('Vocabulary')
})

test('optional ancestor selection checks parents and saves branch removal without selecting siblings', async ({
    page,
}) => {
    const name = 'Ancestor browser deck ' + Date.now()
    await page.goto('/admin/decks/create')
    await page.getByRole('textbox', { name: /^Name/ }).fill(name)
    const topics = page.locator('.taxonomy-parent-tree').first()
    await expect(topics.locator('[data-tree-config]')).toHaveAttribute(
        'data-tree-config',
        /"selectAncestors":true/,
    )
    await open(topics)
    await expect(topics.locator('.taxonomy-parent-help')).toBeVisible()
    await option(topics, 'Vocabulary').click()
    for (const term of ['Languages', 'English', 'Vocabulary']) {
        await expect(option(topics, term)).toHaveAttribute(
            'aria-checked',
            'true',
        )
        await expect(
            topics.getByRole('button', { name: 'Remove ' + term, exact: true }),
        ).toBeVisible()
    }
    await expect(option(topics, 'Grammar')).toHaveAttribute(
        'aria-checked',
        'false',
    )
    await expect(
        option(topics, 'English').locator(
            ':scope > .taxonomy-parent-node > .taxonomy-parent-summary',
        ),
    ).toHaveText('1 selected below')
    await option(topics, 'Algebra').click()
    await page.keyboard.press('Escape')
    await topics
        .getByRole('button', { name: 'Remove English', exact: true })
        .click()
    await expect(
        topics.getByRole('button', { name: 'Remove Vocabulary', exact: true }),
    ).toHaveCount(0)
    await expect(
        topics.getByRole('button', { name: 'Remove Languages', exact: true }),
    ).toBeVisible()
    await open(topics)
    await expect(option(topics, 'English')).toHaveAttribute(
        'aria-checked',
        'false',
    )
    const result = await new AxeBuilder({ page })
        .include('.taxonomy-parent-tree')
        .analyze()
    expect(result.violations).toEqual([])
    await page.keyboard.press('Escape')
    await page.getByRole('button', { name: 'Create', exact: true }).click()
    await expect
        .poll(async () =>
            (await (await page.request.get('/__assignment-state')).json())
                .find((deck) => deck.name === name)
                ?.topics.sort(),
        )
        .toEqual(['Algebra', 'Languages', 'Mathematics', 'Science'])
    const deck = (
        await (await page.request.get('/__assignment-state')).json()
    ).find((deck) => deck.name === name)
    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(topics.locator('.taxonomy-selection-tags')).toContainText(
        'Mathematics',
    )
    await expect(
        topics.getByRole('button', { name: 'Remove Vocabulary', exact: true }),
    ).toHaveCount(0)
})

test('configured ancestor selection expands existing child assignments only in pending form state', async ({
    page,
}) => {
    const before = (
        await (await page.request.get('/__assignment-state')).json()
    ).find((deck) => deck.name === 'Math warm-up')
    await page.goto('/admin/decks/' + before.id + '/edit')
    await expect(
        page.getByRole('switch', { name: 'Include parent topics' }),
    ).toHaveCount(0)
    const topics = page.locator('.taxonomy-parent-tree').first()
    for (const term of ['Languages', 'English', 'Grammar'])
        await expect(
            topics.getByRole('button', { name: 'Remove ' + term, exact: true }),
        ).toBeVisible()
    const after = (
        await (await page.request.get('/__assignment-state')).json()
    ).find((deck) => deck.id === before.id)
    expect(after.topics).toEqual(before.topics)
    await topics
        .getByRole('button', { name: 'Remove Grammar', exact: true })
        .click()
    await expect(
        topics.getByRole('button', { name: 'Remove English', exact: true }),
    ).toBeVisible()
})

test('ancestor indicators and the full popup remain usable at narrow widths in dark RTL mode', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 700 })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.goto('/admin/decks/create')
    await page.evaluate(() => {
        document.documentElement.dir = 'rtl'
        document.documentElement.classList.add('dark')
    })
    const topics = page.locator('.taxonomy-parent-tree').first()
    await expect(topics.locator('[data-tree-config]')).toHaveAttribute(
        'data-tree-config',
        /"selectAncestors":true/,
    )
    await open(topics)
    await option(topics, 'Vocabulary').click()
    await expect(option(topics, 'English')).toHaveAttribute(
        'aria-checked',
        'true',
    )
    await expect(topics.locator('.taxonomy-parent-help')).toBeVisible()
    await expect(
        topics
            .locator('.taxonomy-selection-tags')
            .getByRole('group', { name: 'Languages', exact: true }),
    ).toBeVisible()
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true)
    await expect
        .poll(async () => {
            const bounds = await topics
                .locator('.taxonomy-parent-dropdown')
                .boundingBox()
            return (
                bounds.y >= 0 &&
                bounds.y + bounds.height <= 700 &&
                bounds.x >= 0 &&
                bounds.x + bounds.width <= 390
            )
        })
        .toBe(true)
    const result = await new AxeBuilder({ page })
        .include('.taxonomy-parent-tree')
        .analyze()
    expect(result.violations).toEqual([])
})

test('selected-descendant summaries do not move rows when children are selected or removed', async ({
    page,
}) => {
    await page.goto('/admin/decks/create')
    const topics = page.locator('.taxonomy-parent-tree').first()
    await open(topics)
    const summary = option(topics, 'English').locator(
        ':scope > .taxonomy-parent-node > .taxonomy-parent-summary',
    )
    await expect(summary).toHaveText('')
    const measure = () =>
        topics.locator('.taxonomy-parent-options').evaluate((options) => {
            const top = options.getBoundingClientRect().top
            return [...options.querySelectorAll('.taxonomy-parent-node')].map(
                (row) => {
                    const bounds = row.getBoundingClientRect()
                    return {
                        top: bounds.top - top + options.scrollTop,
                        height: bounds.height,
                    }
                },
            )
        })
    const before = await measure()
    await option(topics, 'Vocabulary').click()
    await expect(summary).toHaveText('1 selected below')
    expect(await measure()).toEqual(before)
    await option(topics, 'Vocabulary').click()
    await expect(summary).toHaveText('')
    expect(await measure()).toEqual(before)
})
