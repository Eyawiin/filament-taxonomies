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
    tree.activeId = 1
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

test('whole disabled and read-only fields reject selection and expansion', () => {
    for (const mode of ['disabled', 'readOnly']) {
        const tree = taxonomyParentTree({ state: 1, nodes, [mode]: true })
        tree.choose(null)
        tree.choose(4)
        tree.toggleNode(1)
        tree.show()
        assert.equal(tree.state, 1)
        assert.equal(tree.open, false)
        assert.equal(tree.expanded.includes(1), true)
    }
})

test('collapsing an active descendant and replacing options recover a visible root or parent', () => {
    const tree = taxonomyParentTree({ state: 4, nodes })
    tree.activeId = 3
    tree.toggleNode(1)
    assert.equal(tree.activeId, 1)
    tree.activeId = 5
    tree.configure({
        nodes: nodes.slice(0, 4),
        disabled: false,
        readOnly: false,
        labels: {},
    })
    assert.equal(tree.activeId, null)
    assert.equal(tree.selectedLabel, 'France')
})

test('missing selected values are marked unavailable rather than displayed as root', () => {
    const tree = taxonomyParentTree({
        state: 999,
        nodes,
        labels: { unavailable: 'Unavailable', root: 'Root' },
    })
    assert.equal(tree.selectedLabel, 'Unavailable')
    tree.state = null
    assert.equal(tree.selectedLabel, 'Root')
})

test('ArrowRight during search never jumps from a branch to an unrelated matching sibling', () => {
    const tree = taxonomyParentTree({
        state: null,
        nodes: nodes.map((node) =>
            node.id === 5 ? { ...node, name: 'Country theme' } : node,
        ),
    })
    tree.search = 'country'
    tree.activeId = 1
    tree.focusNode = (id) => {
        tree.activeId = id
    }
    tree.navigate({
        key: 'ArrowRight',
        preventDefault() {},
        stopPropagation() {},
    })
    assert.equal(tree.activeId, 1)
})

test('visibility is reused for row lookups and refreshed by search, expansion and configuration', () => {
    let reads = 0
    const measured = nodes.map((node) => ({
        ...node,
        get name() {
            reads++
            return node.name
        },
    }))
    const tree = taxonomyParentTree({ state: null, nodes: measured })
    tree.search = 'rome'
    assert.deepEqual(
        tree.visibleNodes.map((node) => node.id),
        [1, 2, 3],
    )
    for (let index = 0; index < 1000; index++)
        assert.equal(tree.isVisible(3), true)
    assert.equal(reads, nodes.length)
    tree.search = ''
    tree.toggleNode(1)
    assert.equal(tree.isVisible(3), false)
    tree.search = 'renamed'
    tree.configure({
        nodes: nodes.map((node) => ({
            ...node,
            name: node.id === 3 ? 'Renamed' : node.name,
        })),
        disabled: false,
        readOnly: false,
    })
    assert.deepEqual(
        tree.visibleNodes.map((node) => node.id),
        [1, 2, 3],
    )
    tree.search = ''
    assert.equal(tree.isVisible(3), false)
    tree.toggleNode(1)
    assert.equal(tree.isVisible(3), true)
})
