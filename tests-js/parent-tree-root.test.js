import test from 'node:test'
import assert from 'node:assert/strict'
import taxonomyParentTree from '../resources/js/components/taxonomy-parent-tree.js'

test('F4 Home then Enter can clear the parent through keyboard navigation', () => {
    const tree = taxonomyParentTree({
        state: 2,
        nodes: [
            {
                id: 1,
                name: 'First',
                ancestors: [],
                hasChildren: false,
                disabled: false,
            },
            {
                id: 2,
                name: 'Selected',
                ancestors: [],
                hasChildren: false,
                disabled: false,
            },
        ],
    })
    // Exercise the real focus/navigation methods; only browser focus scheduling is stubbed.
    tree.$nextTick = (callback) => callback()
    tree.$root = { querySelector: () => ({ focus() {} }) }
    tree.$refs = { trigger: { focus() {} } }
    tree.open = true
    const key = (key) =>
        tree.navigate({ key, preventDefault() {}, stopPropagation() {} })

    key('Home')
    key('Enter')

    assert.equal(
        tree.state,
        null,
        'No parent must be reachable without a pointer',
    )
    assert.equal(tree.open, false)
})
