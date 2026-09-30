import test from 'node:test'
import assert from 'node:assert/strict'
import taxonomyParentTree from '../resources/js/components/taxonomy-parent-tree.js'

const nodes = [
    {
        id: 1,
        name: 'Country',
        ancestors: [],
        hasChildren: true,
        disabled: false,
    },
    { id: 2, name: 'Italy', ancestors: [1], hasChildren: true, disabled: true },
    {
        id: 3,
        name: 'Rome',
        ancestors: [1, 2],
        hasChildren: false,
        disabled: true,
    },
    {
        id: 4,
        name: 'France',
        ancestors: [1],
        hasChildren: false,
        disabled: false,
    },
    {
        id: 5,
        name: 'Theme',
        ancestors: [],
        hasChildren: false,
        disabled: false,
    },
]

test('collapsing a branch hides its descendants and search restores their context', () => {
    const tree = taxonomyParentTree({ state: null, nodes })
    tree.toggleNode(1)
    assert.deepEqual(
        tree.visibleNodes.map((node) => node.id),
        [1, 5],
    )
    tree.search = 'ROME'
    assert.deepEqual(
        tree.visibleNodes.map((node) => node.id),
        [1, 2, 3],
    )
    tree.search = ''
    assert.deepEqual(
        tree.visibleNodes.map((node) => node.id),
        [1, 5],
    )
    tree.toggleNode(1)
    assert.equal(tree.visibleNodes.length, 5)
})

test('selection rejects disabled and unknown nodes, supports valid parents and root', () => {
    const tree = taxonomyParentTree({ state: 1, nodes })
    tree.$refs = { trigger: { focus() {} } }
    tree.choose(2)
    tree.choose(3)
    tree.choose(999)
    assert.equal(tree.state, 1)
    tree.choose(4)
    assert.equal(tree.state, 4)
    assert.equal(tree.selectedLabel, 'France')
    tree.choose(null)
    assert.equal(tree.selectedLabel, 'No parent (root term)')
})

test('keyboard navigation expands branches, traverses nodes and rejects disabled selection', () => {
    const tree = taxonomyParentTree({ state: null, nodes })
    tree.focusNode = (id) => {
        tree.activeId = id
    }
    tree.$refs = { trigger: { focus() {} } }
    const key = (key) =>
        tree.navigate({ key, preventDefault() {}, stopPropagation() {} })
    key('ArrowLeft')
    assert.deepEqual(
        tree.visibleNodes.map((node) => node.id),
        [1, 5],
    )
    key('ArrowRight')
    key('ArrowRight')
    assert.equal(tree.activeId, 2)
    key('Enter')
    assert.equal(tree.state, null)
    key('End')
    key('Enter')
    assert.equal(tree.state, 5)
})
