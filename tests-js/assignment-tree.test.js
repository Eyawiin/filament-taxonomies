import test from 'node:test'
import assert from 'node:assert/strict'
import tree from '../resources/js/components/taxonomy-parent-tree.js'

const nodes = [
    { id: 1, name: 'Topic', ancestors: [], hasChildren: true, disabled: false },
    {
        id: 2,
        name: 'Child',
        ancestors: [1],
        hasChildren: false,
        disabled: false,
    },
    {
        id: 3,
        name: 'Restricted',
        ancestors: [],
        hasChildren: false,
        disabled: true,
    },
]
const create = () => {
    const picker = tree({
        state: [],
        nodes,
        multiple: true,
        labels: {
            root: 'No terms',
            selected: ':count terms selected',
            unavailable: 'Unavailable',
        },
    })
    picker.focusNode = (id) => {
        picker.activeId = id
    }
    picker.$refs = { trigger: { focus() {} } }
    picker.open = true
    return picker
}
test('multiple selection toggles only the chosen term and stays open', () => {
    const picker = create()
    picker.choose(1)
    assert.deepEqual(picker.state, [1])
    assert.equal(picker.open, true)
    picker.choose(2)
    assert.deepEqual(picker.state, [1, 2])
    picker.choose(1)
    assert.deepEqual(picker.state, [2])
    assert.equal(picker.selectedLabel, 'Child')
    assert.equal(picker.selectionFeedback, '1 terms selected')
})
test('expansion and focus do not change selection, and keyboard toggles and clears', () => {
    const picker = create()
    picker.choose(2)
    picker.toggleNode(1)
    assert.deepEqual(picker.state, [2])
    const press = (key) =>
        picker.navigate({ key, preventDefault() {}, stopPropagation() {} })
    picker.activeId = 3
    press(' ')
    assert.deepEqual(picker.state, [2])
    picker.activeId = null
    press('Enter')
    assert.deepEqual(picker.state, [])
    assert.equal(picker.selectedLabel, 'No terms')
})
test('removed selections stay visible as unavailable and do not become another term', () => {
    const picker = create()
    picker.state = [2, '9007199254740992']
    picker.configure({ nodes: nodes.filter((node) => node.id !== 2) })
    assert.deepEqual(picker.state, [2, '9007199254740992'])
    assert.equal(picker.selectedLabel, 'Unavailable, Unavailable')
})
test('read-only and disabled multiple pickers reject clearing as well as toggling', () => {
    for (const mode of ['readOnly', 'disabled']) {
        const picker = create()
        picker.state = [2]
        picker[mode] = true
        picker.choose(null)
        picker.choose(1)
        assert.deepEqual(picker.state, [2])
    }
})

test('tag removal preserves opaque IDs and unrelated selections without opening the tree', () => {
    const picker = create()
    picker.open = false
    picker.state = ['2', '9007199254740992', 1]
    assert.deepEqual(picker.selectedTerms, [
        { id: '2', name: 'Child' },
        { id: '9007199254740992', name: 'Unavailable' },
        { id: 1, name: 'Topic' },
    ])
    picker.removeTerm(2)
    assert.deepEqual(picker.state, ['9007199254740992', 1])
    assert.equal(picker.open, false)
    for (const mode of ['readOnly', 'disabled']) {
        picker[mode] = true
        picker.removeTerm(1)
        assert.deepEqual(picker.state, ['9007199254740992', 1])
        picker[mode] = false
    }
})

test('tag names reflect configuration updates without assigning contextual ancestors', () => {
    const picker = create()
    picker.state = [2]
    picker.configure({
        nodes: nodes.map((node) =>
            node.id === 2 ? { ...node, name: 'Renamed child' } : node,
        ),
    })
    assert.deepEqual(picker.selectedTerms, [{ id: 2, name: 'Renamed child' }])
    assert.equal(picker.selectedBelowCount(1), 1)
    assert.equal(picker.isSelected(1), false)
    assert.deepEqual(picker.state, [2])
})
