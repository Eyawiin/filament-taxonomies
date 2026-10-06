import { test, expect } from '@playwright/test'

test('clean consumer uses published assets for term CRUD, parent selection, repeated dragging and policy controls', async ({
    page,
}) => {
    const errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    const state = async () =>
        (await page.request.get('/__fixture-state')).json()
    let terms = await state()
    const alpha = terms.find((term) => term.slug === 'alpha')
    const beta = terms.find((term) => term.slug === 'beta')
    const locked = terms.find((term) => term.slug === 'locked')
    await page.goto('/admin/taxonomies/' + alpha.taxonomy_id + '/manage-terms')
    const row = (id) =>
        page.locator('[data-term-id="' + id + '"] > [data-taxonomy-row]')
    await expect(
        page.locator('[data-term-id="' + locked.id + '"]'),
    ).toHaveAttribute('data-can-move', 'false')
    await expect(
        row(locked.id).locator('[data-taxonomy-drag-handle]'),
    ).toHaveCount(0)
    await expect(
        row(locked.id).getByRole('button', { name: 'Edit Term', exact: true }),
    ).toBeDisabled()
    await page.getByRole('button', { name: 'Create Term', exact: true }).click()
    const modal = page
        .getByRole('dialog')
        .filter({ has: page.locator('.taxonomy-parent-tree') })
    await modal.getByRole('textbox', { name: /^Name/ }).fill('Created child')
    await modal.getByRole('textbox', { name: /^Slug/ }).fill('created-child')
    const field = modal.locator('.taxonomy-parent-tree')
    await field.locator('.taxonomy-parent-trigger').click()
    await field.locator('[data-node-id="' + alpha.id + '"]').click()
    await modal.getByRole('button', { name: 'Submit', exact: true }).click()
    await expect(modal).toBeHidden()
    await expect
        .poll(
            async () =>
                (await state()).find((term) => term.slug === 'created-child')
                    ?.parent_id,
        )
        .toBe(alpha.id)
    terms = await state()
    const child = terms.find((term) => term.slug === 'created-child')
    await row(child.id)
        .getByRole('button', { name: 'Edit Term', exact: true })
        .click()
    await modal.getByRole('textbox', { name: /^Name/ }).fill('Renamed child')
    await modal.getByRole('button', { name: 'Submit', exact: true }).click()
    await expect
        .poll(
            async () =>
                (await state()).find((term) => term.id === child.id)?.name,
        )
        .toBe('Renamed child')

    async function drag(source, target) {
        const handle = row(source).locator('[data-taxonomy-drag-handle]')
        const sourceBox = await handle.boundingBox()
        const targetBox = await row(target).boundingBox()
        await page.mouse.move(
            sourceBox.x + sourceBox.width / 2,
            sourceBox.y + sourceBox.height / 2,
        )
        await page.mouse.down()
        await page.mouse.move(sourceBox.x + 8, sourceBox.y + 8, { steps: 5 })
        await page.mouse.move(
            targetBox.x + targetBox.width / 2,
            targetBox.y + 3,
            { steps: 12 },
        )
        await page.mouse.up()
    }
    await drag(beta.id, alpha.id)
    await expect
        .poll(
            async () =>
                (await state()).find((term) => term.id === beta.id)?.position,
        )
        .toBe(0)
    await drag(alpha.id, beta.id)
    await expect
        .poll(
            async () =>
                (await state()).find((term) => term.id === alpha.id)?.position,
        )
        .toBe(0)
    await row(child.id)
        .getByRole('button', { name: 'Delete Term', exact: true })
        .click()
    const confirmation = page.getByRole('alertdialog').filter({
        has: page.getByRole('button', { name: 'Delete', exact: true }),
    })
    await confirmation
        .getByRole('button', { name: 'Delete', exact: true })
        .click()
    await expect
        .poll(async () => (await state()).some((term) => term.id === child.id))
        .toBe(false)
    expect(errors).toEqual([])
})

test('copied package fields create, reopen and clear assignments through a consumer resource', async ({
    page,
}) => {
    const errors = []
    page.on('pageerror', (error) => errors.push(error.message))
    const state = async () => (await page.request.get('/__field-state')).json()
    await page.goto('/admin/decks/create')
    await page
        .getByRole('textbox', { name: /^Name/ })
        .fill('Installed package deck')
    const topics = page.locator('.taxonomy-parent-tree').nth(0)
    const level = page.locator('.taxonomy-parent-tree').nth(1)
    const open = async (field) => {
        await field.locator('.taxonomy-parent-trigger').click()
        await expect(field.locator('input[type=search]')).toBeFocused()
    }
    const picker = topics.locator('.taxonomy-assignment-dialog')
    const chooseTopic = async () => {
        await open(topics)
        await expect(picker).toBeVisible()
        await picker.getByRole('searchbox').fill('Topics leaf')
        const leaf = picker.getByRole('checkbox', {
            name: 'Topics leaf',
            exact: true,
        })
        await expect(leaf).not.toBeChecked()
        await leaf.check()
    }
    await chooseTopic()
    await picker.getByRole('button', { name: 'Cancel', exact: true }).click()
    await expect(picker).toBeHidden()
    await expect(topics.locator('.taxonomy-selection-review')).toBeHidden()
    await chooseTopic()
    await picker.getByRole('button', { name: 'Apply', exact: true }).click()
    await expect(picker).toBeHidden()
    await expect(topics.locator('.taxonomy-selection-review')).toContainText(
        'Topics leaf',
    )
    await open(level)
    await level
        .getByRole('treeitem', { name: 'Levels leaf', exact: true })
        .click()
    await page.getByRole('button', { name: 'Create', exact: true }).click()
    await expect
        .poll(async () => (await state())[0]?.topics)
        .toEqual(['Topics leaf'])
    const deck = (await state())[0]
    await page.goto('/admin/decks/' + deck.id + '/edit')
    await expect(topics.locator('.taxonomy-selection-review')).toContainText(
        'Topics leaf',
    )
    await expect(level.locator('.taxonomy-parent-trigger')).toContainText(
        'Levels leaf',
    )
    await topics
        .getByRole('button', { name: 'Remove Topics leaf', exact: true })
        .click()
    await page
        .getByRole('button', { name: 'Save changes', exact: true })
        .click()
    await expect.poll(async () => (await state())[0]?.topics).toEqual([])
    expect((await state())[0].levels).toEqual(['Levels leaf'])
    expect(errors).toEqual([])
})
