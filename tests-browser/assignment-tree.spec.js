import { expect, test } from '@playwright/test'
import AxeBuilder from '@axe-core/playwright'

const fieldAt = (page, index = 0) =>
    page.locator('.taxonomy-parent-tree').nth(index)
const picker = (field) => field.locator('.taxonomy-assignment-dialog')
const removalDialog = (field) => field.locator('.taxonomy-removal-dialog')
const assignment = (field, name) =>
    picker(field).locator('[data-assignment-name="' + name + '"]')
const review = (field) => field.locator('.taxonomy-selection-review')
const remove = (field, name) =>
    review(field).getByRole('button', { name: 'Remove ' + name, exact: true })
const branchRemoval = (field, name, count) =>
    review(field).getByRole('button', {
        name: 'Remove branch ' + name + ' (' + count + ')',
        exact: true,
    })

async function open(field) {
    await field.locator('.taxonomy-parent-trigger').click()
    await expect(picker(field)).toBeVisible()
    await expect(picker(field).getByRole('searchbox')).toBeFocused()
}

async function find(field, name) {
    await picker(field).getByRole('searchbox').fill(name)
    await expect(assignment(field, name)).toBeVisible()
    return assignment(field, name)
}

async function select(field, ...names) {
    for (const name of names) await (await find(field, name)).check()
}

async function apply(field) {
    await picker(field)
        .getByRole('button', { name: 'Apply', exact: true })
        .click()
    await expect(picker(field)).not.toBeVisible()
}

async function state(page) {
    return (await page.request.get('/__assignment-state')).json()
}

test('Deck resource creates and reopens assignments, removes a leaf and clears only its taxonomy', async ({
    page,
}) => {
    const name = 'Browser deck ' + Date.now()
    await page.goto('/admin/decks/create')
    await page.getByRole('textbox', { name: /^Name/ }).fill(name)
    const topics = fieldAt(page)
    const level = fieldAt(page, 1)
    await open(topics)
    await select(topics, 'Vocabulary', 'Algebra')
    await apply(topics)
    for (const term of [
        'Languages',
        'English',
        'Vocabulary',
        'Science',
        'Mathematics',
        'Algebra',
    ])
        await expect(
            review(topics)
                .locator('.taxonomy-review-name')
                .filter({ hasText: new RegExp('^' + term + '$') }),
        ).toHaveCount(1)
    await level.locator('.taxonomy-parent-trigger').click()
    await level.getByRole('treeitem', { name: 'A2', exact: true }).click()
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
    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(review(topics)).toContainText('Vocabulary')
    await expect(level.locator('.taxonomy-parent-trigger')).toContainText('A2')
    await remove(topics, 'Vocabulary').click()
    await expect(remove(topics, 'Vocabulary')).toHaveCount(0)
    await expect(remove(topics, 'English')).toBeVisible()
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await expect
        .poll(async () =>
            (await state(page))
                .find((row) => row.id === deck.id)
                ?.topics.sort(),
        )
        .toEqual(['Algebra', 'English', 'Languages', 'Mathematics', 'Science'])
    await page.goto('/admin/decks/' + deck.id + '/edit')
    await open(topics)
    await picker(topics)
        .getByRole('button', { name: 'Clear selection', exact: true })
        .click()
    await removalDialog(topics).locator('[data-removal-confirm]').click()
    await apply(topics)
    await expect(review(topics)).not.toContainText('Algebra')
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

test('picker drafts support Apply, Cancel and Escape without persisting the form', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const field = fieldAt(page)
    await expect(remove(field, 'Grammar')).toBeVisible()
    await open(field)
    await picker(field)
        .getByRole('button', { name: 'Apply', exact: true })
        .focus()
    await page.keyboard.press('Tab')
    await expect(
        picker(field).getByRole('button', { name: 'Close', exact: true }),
    ).toBeFocused()
    await page.keyboard.press('Shift+Tab')
    await expect(
        picker(field).getByRole('button', { name: 'Apply', exact: true }),
    ).toBeFocused()
    await select(field, 'Vocabulary')
    await picker(field)
        .getByRole('button', { name: 'Cancel', exact: true })
        .click()
    await expect(picker(field)).not.toBeVisible()
    await expect(field.locator('.taxonomy-parent-trigger')).toBeFocused()
    await expect(remove(field, 'Vocabulary')).toHaveCount(0)
    await open(field)
    await select(field, 'Vocabulary')
    await page.keyboard.press('Escape')
    await expect(picker(field)).not.toBeVisible()
    await expect(remove(field, 'Vocabulary')).toHaveCount(0)
    await open(field)
    await select(field, 'Vocabulary')
    await apply(field)
    await expect(remove(field, 'Vocabulary')).toBeVisible()
    expect(
        (await state(page)).find((deck) => deck.name === 'English basics')
            .topics,
    ).not.toContain('Vocabulary')
})

test('native checkbox keyboard selection has visible ancestor context and disabled reasons', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const field = fieldAt(page)
    await remove(field, 'Grammar').focus()
    await page.keyboard.press('Enter')
    await expect(remove(field, 'Grammar')).toHaveCount(0)
    await expect(field.locator('.taxonomy-parent-trigger')).toBeFocused()
    await open(field)
    const grammar = await find(field, 'Grammar')
    await grammar.focus()
    await page.keyboard.press('Space')
    await expect(grammar).toBeChecked()
    const vocabulary = await find(field, 'Vocabulary')
    await vocabulary.focus()
    await page.keyboard.press('Space')
    await expect(vocabulary).toBeChecked()
    await select(field, 'English')
    const algebra = await find(field, 'Algebra')
    await expect(algebra).toBeDisabled()
    await expect(algebra).toHaveAccessibleDescription(/Not permitted/)
    await expect(picker(field)).toContainText('Not permitted')
    await apply(field)
    await expect(remove(field, 'English')).toBeVisible()
    await remove(field, 'English').click()
    await expect(
        review(field)
            .locator('[data-context="true"] .taxonomy-review-name')
            .filter({ hasText: /^English$/ }),
    ).toHaveCount(1)
    await expect(remove(field, 'English')).toHaveCount(0)
    await expect(remove(field, 'Grammar')).toBeVisible()
    await expect(remove(field, 'Vocabulary')).toBeVisible()
    await expect(review(field)).not.toContainText('Algebra')
    const result = await new AxeBuilder({ page })
        .include('.taxonomy-parent-tree')
        .analyze()
    expect(result.violations).toEqual([])
})

test('assignment fields in repeater rows stay independent and react to read-only state', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const fields = page.locator('.taxonomy-parent-tree')
    await expect(fields).toHaveCount(3)
    const firstRow = fields.nth(1)
    await open(firstRow)
    await select(firstRow, 'Vocabulary')
    await apply(firstRow)
    await expect(
        fields.nth(2).locator('.taxonomy-parent-trigger'),
    ).toContainText('Choose terms')
    await page
        .getByRole('button', { name: 'Toggle read only', exact: true })
        .click()
    await expect(
        fields.first().locator('.taxonomy-parent-trigger'),
    ).toHaveAttribute('aria-disabled', 'true')
    await expect(remove(fields.first(), 'Grammar')).toBeDisabled()
    await fields.first().locator('.taxonomy-parent-trigger').focus()
    await page.keyboard.press('Space')
    await expect(picker(fields.first())).not.toBeVisible()
    await page
        .getByRole('button', { name: 'Toggle disabled', exact: true })
        .click()
    await expect(
        fields.first().locator('.taxonomy-parent-trigger'),
    ).toBeDisabled()
})

test('picker works inside an owner modal whose Cancel discards applied pending changes', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    await page
        .getByRole('button', { name: 'Edit deck assignments', exact: true })
        .click()
    const ownerDialog = page
        .getByRole('dialog')
        .filter({ has: page.locator('.taxonomy-parent-tree') })
        .first()
    const field = ownerDialog.locator('.taxonomy-parent-tree')
    await open(field)
    await select(field, 'Vocabulary')
    await apply(field)
    await ownerDialog
        .getByRole('button', { name: 'Cancel', exact: true })
        .click()
    await page
        .getByRole('button', { name: 'Edit deck assignments', exact: true })
        .click()
    await expect(remove(field, 'Vocabulary')).toHaveCount(0)
    await open(field)
    await select(field, 'Vocabulary')
    await apply(field)
    await ownerDialog
        .getByRole('button', { name: 'Save assignments', exact: true })
        .click()
    await expect(ownerDialog).not.toBeVisible()
    await expect
        .poll(
            async () =>
                (await state(page)).find(
                    (deck) => deck.name === 'English basics',
                )?.topics,
        )
        .toContain('Vocabulary')
})

test('ancestor branch removal previews the exact affected assignments, supports Cancel and Undo, and retains siblings', async ({
    page,
}) => {
    const name = 'Ancestor browser deck ' + Date.now()
    await page.goto('/admin/decks/create')
    await page.getByRole('textbox', { name: /^Name/ }).fill(name)
    const topics = fieldAt(page)
    await expect(topics.locator('[data-tree-config]')).toHaveAttribute(
        'data-tree-config',
        /"selectAncestors":true/,
    )
    await open(topics)
    await select(topics, 'Vocabulary', 'Grammar', 'Algebra')
    const english = await find(topics, 'English')
    await expect(english).toBeChecked()
    await english.click()
    await expect(
        removalDialog(topics).locator('.taxonomy-removal-preview'),
    ).toContainText('Remove 3 selected terms?')
    await expect(english).toBeChecked()
    await page.keyboard.press('Escape')
    await expect(picker(topics)).toBeVisible()
    await expect(
        removalDialog(topics).locator('.taxonomy-removal-preview'),
    ).not.toBeVisible()
    await expect(english).toBeChecked()
    await apply(topics)
    await expect(branchRemoval(topics, 'English', 3)).toContainText(
        'Remove branch (3)',
    )
    await branchRemoval(topics, 'English', 3).click()
    const preview = removalDialog(topics).locator('.taxonomy-removal-preview')
    for (const term of ['English', 'Grammar', 'Vocabulary'])
        await expect(preview).toContainText(term)
    await expect(preview).not.toContainText('Algebra')
    await preview
        .getByRole('button', { name: 'Cancel removal', exact: true })
        .click()
    await expect(remove(topics, 'Vocabulary')).toBeVisible()
    await branchRemoval(topics, 'English', 3).click()
    await preview
        .getByRole('button', { name: 'Remove 3 terms', exact: true })
        .click()
    await expect(remove(topics, 'Vocabulary')).toHaveCount(0)
    await expect(remove(topics, 'Languages')).toBeVisible()
    await expect(remove(topics, 'Algebra')).toBeVisible()
    await topics.getByRole('button', { name: 'Undo', exact: true }).click()
    await expect(remove(topics, 'Vocabulary')).toBeVisible()
    await remove(topics, 'Grammar').click()
    await expect(branchRemoval(topics, 'English', 2)).toBeVisible()
    await branchRemoval(topics, 'English', 2).click()
    await preview
        .getByRole('button', { name: 'Remove 2 terms', exact: true })
        .click()
    await page.getByRole('button', { name: 'Create', exact: true }).click()
    await expect
        .poll(async () =>
            (await state(page))
                .find((deck) => deck.name === name)
                ?.topics.sort(),
        )
        .toEqual(['Algebra', 'Languages', 'Mathematics', 'Science'])
    const deck = (await state(page)).find((row) => row.name === name)
    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(review(topics)).toContainText('Mathematics')
    await expect(remove(topics, 'Vocabulary')).toHaveCount(0)
})

test('ancestor configuration expands existing assignments in pending state and a leaf removal retains its parents', async ({
    page,
}) => {
    const before = (await state(page)).find(
        (deck) => deck.name === 'Math warm-up',
    )
    await page.goto('/admin/decks/' + before.id + '/edit')
    await expect(
        page.getByRole('switch', { name: 'Include parent topics' }),
    ).toHaveCount(0)
    const topics = fieldAt(page)
    await expect(branchRemoval(topics, 'Languages', 3)).toBeVisible()
    await expect(branchRemoval(topics, 'English', 2)).toBeVisible()
    await expect(remove(topics, 'Grammar')).toBeVisible()
    expect(
        (await state(page)).find((deck) => deck.id === before.id).topics,
    ).toEqual(before.topics)
    await remove(topics, 'Grammar').click()
    await expect(remove(topics, 'English')).toBeVisible()
    await expect(branchRemoval(topics, 'Languages', 2)).toBeVisible()
})

test('dialog and branch review fit narrow widths in dark RTL mode and remain accessible', async ({
    page,
}) => {
    await page.setViewportSize({ width: 390, height: 700 })
    await page.emulateMedia({ reducedMotion: 'reduce' })
    await page.goto('/admin/decks/create')
    await page.evaluate(() => {
        document.documentElement.dir = 'rtl'
        document.documentElement.classList.add('dark')
    })
    const topics = fieldAt(page)
    await open(topics)
    await select(topics, 'Vocabulary')
    await expect(await find(topics, 'English')).toBeChecked()
    const bounds = await picker(topics).boundingBox()
    expect(bounds.x).toBeGreaterThanOrEqual(0)
    expect(bounds.y).toBeGreaterThanOrEqual(0)
    expect(bounds.x + bounds.width).toBeLessThanOrEqual(390)
    expect(bounds.y + bounds.height).toBeLessThanOrEqual(700)
    const dialogAxe = await new AxeBuilder({ page })
        .include('.taxonomy-assignment-dialog')
        .analyze()
    expect(dialogAxe.violations).toEqual([])
    await apply(topics)
    await expect(review(topics)).toContainText('Languages')
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true)
    const reviewAxe = await new AxeBuilder({ page })
        .include('.taxonomy-parent-tree')
        .analyze()
    expect(reviewAxe.violations).toEqual([])
})

test('selection counts do not shift picker row positions or heights', async ({
    page,
}) => {
    await page.goto('/admin/decks/create')
    const topics = fieldAt(page)
    await open(topics)
    const measure = () =>
        picker(topics)
            .locator('.taxonomy-assignment-row')
            .evaluateAll((rows) =>
                rows.map((row) => {
                    const bounds = row.getBoundingClientRect()
                    return { top: bounds.top, height: bounds.height }
                }),
            )
    const before = await measure()
    expect(before.length).toBeGreaterThan(0)
    await select(topics, 'Vocabulary')
    await picker(topics).getByRole('searchbox').fill('')
    await expect(assignment(topics, 'Languages')).toBeVisible()
    expect(await measure()).toEqual(before)
    await (await find(topics, 'Vocabulary')).uncheck()
    await picker(topics).getByRole('searchbox').fill('')
    await expect(assignment(topics, 'Languages')).toBeVisible()
    expect(await measure()).toEqual(before)
})

test('a 25-level selected branch stays compact, exposes its path and previews exactly its assignments', async ({
    page,
}) => {
    const deck = (await state(page)).find(
        (row) => row.demo_key === 'large-tree-deep',
    )
    expect(deck.large_tree).toHaveLength(25)
    await page.setViewportSize({ width: 390, height: 700 })
    await page.goto('/admin/decks/' + deck.id + '/edit')
    const field = fieldAt(page, 2)
    await expect(field.locator('.taxonomy-parent-trigger')).toContainText(
        '25 terms selected',
    )
    const leafName = review(field)
        .locator('.taxonomy-review-name')
        .filter({ hasText: /^Deep level 25/ })
    for (let step = 0; step < 15 && !(await leafName.count()); step++) {
        const ids = await review(field)
            .locator('[data-review-id]')
            .evaluateAll((rows) => rows.map((row) => row.dataset.reviewId))
        expect(ids.length).toBeLessThanOrEqual(3)
        expect(new Set(ids).size).toBe(ids.length)
        await review(field)
            .getByRole('button', { name: /^Browse selected / })
            .click()
    }
    await expect(leafName).toBeVisible()
    const path = review(field).locator('.taxonomy-review-path')
    await expect(path).toBeVisible()
    expect(await path.getByRole('button').count()).toBeGreaterThanOrEqual(21)
    await expect(path.locator('[aria-current="location"]')).toHaveText(
        await review(field)
            .locator('[data-review-id]')
            .first()
            .locator('.taxonomy-review-name')
            .textContent(),
    )
    expect(
        await page.evaluate(
            () => document.documentElement.scrollWidth <= window.innerWidth,
        ),
    ).toBe(true)
    const leafRow = leafName.locator('..')
    await leafRow.getByRole('button', { name: /^Remove Deep level 25/ }).click()
    await expect(field.locator('.taxonomy-parent-trigger')).toContainText(
        '24 terms selected',
    )
    await field.getByRole('button', { name: 'Undo', exact: true }).click()
    await expect(field.locator('.taxonomy-parent-trigger')).toContainText(
        '25 terms selected',
    )
    await review(field)
        .getByRole('button', { name: 'Selected branches', exact: true })
        .click()
    await field.scrollIntoViewIfNeeded()
    const fieldBeforeRemoval = await field.boundingBox()
    await branchRemoval(field, 'Science', 25).click()
    await expect(removalDialog(field)).toBeVisible()
    expect(await field.boundingBox()).toEqual(fieldBeforeRemoval)
    const preview = removalDialog(field).locator('.taxonomy-removal-preview')
    await expect(preview.locator('ul > li')).toHaveCount(25)
    await expect(
        preview.locator('strong').filter({ hasText: /^Science$/ }),
    ).toHaveCount(1)
    await expect(
        preview.locator('strong').filter({ hasText: /^Deep level 25/ }),
    ).toHaveCount(1)
    await preview
        .getByRole('button', { name: 'Cancel removal', exact: true })
        .click()
    expect(
        (await state(page)).find((row) => row.id === deck.id).large_tree,
    ).toEqual(deck.large_tree)
})

test('large reviews and duplicate-name search load progressively with distinct hierarchy paths', async ({
    page,
}) => {
    const decks = await state(page)
    const broad = decks.find((row) => row.demo_key === 'large-tree-broad')
    expect(broad.large_tree).toHaveLength(90)
    await page.goto('/admin/decks/' + broad.id + '/edit')
    const field = fieldAt(page, 2)
    await expect(review(field).locator('[data-review-id]')).toHaveCount(50)
    await review(field)
        .getByRole('button', { name: 'Show more', exact: true })
        .click()
    await expect(review(field).locator('[data-review-id]')).toHaveCount(90)
    const ids = await review(field)
        .locator('[data-review-id]')
        .evaluateAll((rows) => rows.map((row) => row.dataset.reviewId))
    expect(new Set(ids).size).toBe(90)

    const empty = decks.find((row) => row.demo_key === 'large-tree-empty')
    await page.goto('/admin/decks/' + empty.id + '/edit')
    await open(field)
    await picker(field).getByRole('searchbox').fill('Overview')
    await expect(assignment(field, 'Overview')).toHaveCount(50)
    await picker(field)
        .getByRole('button', { name: 'Show more', exact: true })
        .click()
    await expect(assignment(field, 'Overview')).toHaveCount(72)
    const paths = await picker(field)
        .locator('.taxonomy-assignment-search-path')
        .allTextContents()
    expect(new Set(paths).size).toBe(72)
    await expect(
        assignment(field, 'Overview').first(),
    ).toHaveAccessibleDescription(paths[0])
    await assignment(field, 'Overview').first().check()
    await apply(field)
    await expect(field.locator('.taxonomy-parent-trigger')).toContainText(
        '3 terms selected',
    )
    await expect(review(field).locator('[data-review-id]')).toHaveCount(3)
    await expect(
        review(field)
            .locator('.taxonomy-review-name')
            .filter({ hasText: /^Overview$/ }),
    ).toHaveCount(1)
    expect(
        (await state(page)).find((row) => row.id === empty.id).large_tree,
    ).toEqual([])
})

test('reactive field cardinality remounts the matching picker without breaking other instances', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const field = fieldAt(page)
    await open(field)
    await picker(field)
        .getByRole('button', { name: 'Cancel', exact: true })
        .click()
    await page
        .getByRole('button', { name: 'Toggle multiple fixture', exact: true })
        .click()
    await expect(field.locator('.taxonomy-parent-trigger')).toHaveAttribute(
        'aria-haspopup',
        'tree',
    )
    await field.locator('.taxonomy-parent-trigger').click()
    await field
        .getByRole('treeitem', { name: 'Vocabulary', exact: true })
        .click()
    await expect(field.locator('.taxonomy-parent-trigger')).toContainText(
        'Vocabulary',
    )
    await page
        .getByRole('button', { name: 'Toggle multiple fixture', exact: true })
        .click()
    await expect(field.locator('.taxonomy-parent-trigger')).toHaveAttribute(
        'aria-haspopup',
        'dialog',
    )
    await open(field)
    await select(field, 'Grammar', 'Vocabulary')
    await apply(field)
    await expect(remove(field, 'Grammar')).toBeVisible()
    await expect(remove(field, 'Vocabulary')).toBeVisible()
    await open(fieldAt(page, 1))
    await select(fieldAt(page, 1), 'Vocabulary')
    await apply(fieldAt(page, 1))
    await expect(remove(fieldAt(page, 1), 'Vocabulary')).toBeVisible()
    await expect(
        fieldAt(page, 2).locator('.taxonomy-parent-trigger'),
    ).toContainText('Choose terms')
})

test('canceling a draft removal preserves applied selection and does not announce a removal outside the picker', async ({
    page,
}) => {
    await page.goto('/admin/browser-assignment-page')
    const field = fieldAt(page)
    await open(field)
    await (await find(field, 'Grammar')).click()
    await expect(
        picker(field)
            .getByRole('status')
            .filter({ hasText: '1 terms removed.' }),
    ).toBeVisible()
    await picker(field)
        .getByRole('button', { name: 'Cancel', exact: true })
        .click()
    await expect(remove(field, 'Grammar')).toBeVisible()
    const notice = field.locator(':scope > .taxonomy-assignment-notice')
    await expect(notice.getByRole('status')).toHaveText('')
    await expect(
        notice.getByRole('button', { name: 'Undo', exact: true }),
    ).not.toBeVisible()
})

test('deep browsing, search, selection and removal overlays keep the dialog controls and underlying field in place', async ({
    page,
}) => {
    const deck = (await state(page)).find(
        (row) => row.demo_key === 'large-tree-empty',
    )
    await page.setViewportSize({ width: 390, height: 700 })
    await page.goto('/admin/decks/' + deck.id + '/edit')
    const field = fieldAt(page, 2)
    await open(field)
    const controls = async () =>
        Promise.all([
            picker(field).boundingBox(),
            picker(field).locator('.taxonomy-picker-header').boundingBox(),
            picker(field).getByRole('searchbox').boundingBox(),
            picker(field).locator('.taxonomy-picker-navigation').boundingBox(),
            picker(field).locator('.taxonomy-picker-footer').boundingBox(),
        ])
    const rootRows = () =>
        picker(field)
            .locator('.taxonomy-assignment-row')
            .evaluateAll((rows) =>
                rows.map((row) => {
                    const bounds = row.getBoundingClientRect()
                    const options = row.closest('.taxonomy-picker-options')
                    return {
                        top:
                            bounds.top -
                            options.getBoundingClientRect().top +
                            options.scrollTop,
                        height: bounds.height,
                    }
                }),
            )
    const initialRows = await rootRows()

    const initial = await controls()
    const underlyingField = await field.boundingBox()
    await expect(
        picker(field).getByRole('button', { name: 'Back', exact: true }),
    ).toBeDisabled()
    await picker(field).getByRole('searchbox').fill('Deep level 24')
    await expect(
        picker(field).getByRole('button', {
            name: 'Browse Deep level 24',
            exact: true,
        }),
    ).toBeVisible()
    expect(await controls()).toEqual(initial)
    await picker(field)
        .getByRole('button', { name: 'Browse Deep level 24', exact: true })
        .click()
    const path = picker(field).locator('.taxonomy-picker-path')
    const current = path.locator('[aria-current="location"]:visible')
    await expect(current).toHaveText('Deep level 24')
    await expect(
        path.getByRole('button', { name: 'Science', exact: true }),
    ).toBeVisible()
    expect(await controls()).toEqual(initial)
    const pathBounds = await path.boundingBox()
    const currentBounds = await current.boundingBox()
    expect(currentBounds.x).toBeGreaterThanOrEqual(pathBounds.x - 1)
    expect(currentBounds.x + currentBounds.width).toBeLessThanOrEqual(
        pathBounds.x + pathBounds.width + 1,
    )
    const leaf = picker(field).getByRole('checkbox').first()
    await leaf.check()
    await expect(
        picker(field)
            .getByRole('status')
            .filter({ hasText: /^25 terms selected$/ }),
    ).toBeVisible()
    expect(await controls()).toEqual(initial)
    await path.getByRole('button', { name: 'All terms', exact: true }).click()
    await expect(assignment(field, 'Science')).toBeChecked()
    const scienceId = await assignment(field, 'Science').getAttribute(
        'data-assignment-id',
    )
    await expect(
        picker(field).locator(
            '[data-assignment-row="' +
                scienceId +
                '"] .taxonomy-summary-compact',
        ),
    ).toHaveText('24 below')
    expect(await rootRows()).toEqual(initialRows)
    expect(await controls()).toEqual(initial)
    await picker(field).getByRole('searchbox').fill('Deep level 24')
    await picker(field)
        .getByRole('button', { name: 'Browse Deep level 24', exact: true })
        .click()

    await leaf.uncheck()
    await expect(
        picker(field)
            .getByRole('status')
            .filter({ hasText: '1 terms removed.' }),
    ).toBeVisible()
    expect(await controls()).toEqual(initial)

    await picker(field).getByRole('searchbox').fill('Science')
    await assignment(field, 'Science').click()
    await expect(removalDialog(field)).toBeVisible()
    await expect(removalDialog(field).locator('ul > li')).toHaveCount(24)
    expect(await controls()).toEqual(initial)
    expect(await field.boundingBox()).toEqual(underlyingField)
    await page.keyboard.press('Escape')
    await expect(removalDialog(field)).not.toBeVisible()
    await expect(picker(field)).toBeVisible()
    await expect(assignment(field, 'Science')).toBeChecked()
    expect(await controls()).toEqual(initial)
    await page.evaluate(() => { document.documentElement.dir = 'rtl' })
    const rtlControls = await controls()
    await picker(field).getByRole('button', { name: 'Back', exact: true }).click()
    await expect(current).toHaveText('Deep level 24')
    const rtlPathBounds = await path.boundingBox()
    const rtlCurrentBounds = await current.boundingBox()
    expect(await path.evaluate(element => element.scrollLeft)).toBeLessThan(0)
    expect(rtlCurrentBounds.x).toBeGreaterThanOrEqual(rtlPathBounds.x - 1)
    expect(rtlCurrentBounds.x + rtlCurrentBounds.width).toBeLessThanOrEqual(rtlPathBounds.x + rtlPathBounds.width + 1)
    expect(await controls()).toEqual(rtlControls)

    await picker(field)
        .getByRole('button', { name: 'Cancel', exact: true })
        .click()
    await expect(field.locator('.taxonomy-parent-trigger')).toContainText(
        'Choose terms',
    )
})
