import test from 'node:test'
import assert from 'node:assert/strict'
import tree from '../resources/js/components/taxonomy-parent-tree.js'

const nodes = [
    { id: 1, name: 'Root', ancestors: [], hasChildren: true, disabled: false },
    {
        id: 2,
        name: 'Parent',
        ancestors: [1],
        hasChildren: true,
        disabled: false,
    },
    {
        id: 3,
        name: 'Child',
        ancestors: [1, 2],
        hasChildren: false,
        disabled: false,
    },
    {
        id: 4,
        name: 'Sibling',
        ancestors: [1, 2],
        hasChildren: false,
        disabled: false,
    },
    {
        id: 5,
        name: 'Other',
        ancestors: [],
        hasChildren: false,
        disabled: false,
    },
]
function create(config = {}) {
    const picker = tree({
        state: [],
        nodes,
        multiple: true,
        selectAncestors: true,
        ...config,
    })
    picker.focusNode = (id) => {
        picker.activeId = id
    }
    picker.$refs = { trigger: { focus() {} } }
    return picker
}

test('choosing a child includes its ancestors but no siblings, with truthful descendant counts', () => {
    const picker = create()
    picker.choose(3)
    assert.deepEqual(picker.state, [1, 2, 3])
    assert.equal(picker.isSelected(4), false)
    assert.equal(picker.selectedBelowCount(1), 2)
    assert.equal(picker.selectedBelowLabel(2), '1 selected below')
    picker.choose(4)
    assert.deepEqual(picker.state, [1, 2, 3, 4])
    assert.equal(picker.selectedBelowCount(2), 2)
    picker.toggleNode(2)
    assert.deepEqual(picker.state, [1, 2, 3, 4])
})

test('removing a child keeps ancestor assignments; removing a parent clears only its branch', () => {
    for (const remove of ['choose', 'removeTerm']) {
        const picker = create({ state: [1, 2, 3, 4, 5, '9007199254740992'] })
        picker[remove](3)
        assert.deepEqual(picker.state, [1, 2, 4, 5, '9007199254740992'])
        picker[remove](2)
        assert.deepEqual(picker.state, [1, 5, '9007199254740992'])
        assert.equal(picker.selectedBelowCount(1), 0)
        picker.choose(null)
        assert.deepEqual(picker.state, [])
    }
})

test('enabling ancestor mode expands existing selections once without losing unavailable IDs', () => {
    const picker = create({
        state: ['3', '9007199254740992'],
        selectAncestors: false,
    })
    picker.configure({ nodes, selectAncestors: true })
    assert.deepEqual(picker.state, ['3', '9007199254740992', 1, 2])
    const state = picker.state
    picker.expandAncestorSelection()
    assert.equal(picker.state, state)
    picker.configure({ nodes, selectAncestors: false })
    picker.removeTerm(2)
    assert.deepEqual(picker.state, ['3', '9007199254740992', 1])
})

test('missing or forbidden ancestors prevent child selection and never enter assignment state', () => {
    for (const unavailable of [
        nodes.filter((node) => node.id !== 2),
        nodes.map((node) =>
            node.id === 2 ? { ...node, disabled: true } : node,
        ),
    ]) {
        const picker = create({ nodes: unavailable })
        picker.choose(3)
        assert.deepEqual(picker.state, [])
        picker.state = [3]
        picker.expandAncestorSelection()
        assert.deepEqual(picker.state, [3])
    }
})

test('single, read-only and disabled fields never propagate ancestor selections', () => {
    for (const config of [
        { multiple: false, state: 3 },
        { readOnly: true, state: [3] },
        { disabled: true, state: [3] },
    ]) {
        const picker = create(config)
        picker.expandAncestorSelection()
        assert.deepEqual(picker.state, config.state)
    }
})
