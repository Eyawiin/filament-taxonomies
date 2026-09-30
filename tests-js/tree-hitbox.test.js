import assert from 'node:assert/strict'
import { test } from 'node:test'
import { getDropPlacement } from '../resources/js/drag/tree-hitbox.js'

for (const [offset, expected] of [
    [0, 'before'],
    [24, 'before'],
    [25, 'inside'],
    [50, 'inside'],
    [75, 'inside'],
    [76, 'after'],
    [100, 'after'],
]) {
    test(`row offset ${offset}% means ${expected}`, () => {
        assert.equal(
            getDropPlacement({ pointerY: 200 + offset, top: 200, height: 100 }),
            expected,
        )
    })
}

test('placement scales with row height', () => {
    assert.equal(
        getDropPlacement({ pointerY: 112, top: 100, height: 48 }),
        'inside',
    )
    assert.equal(
        getDropPlacement({ pointerY: 140, top: 100, height: 48 }),
        'after',
    )
})
