import test from 'node:test'
import assert from 'node:assert/strict'
import picker from '../resources/js/components/taxonomy-assignment-picker.js'

const nodes = [
    {
        id: 1,
        name: 'Science',
        ancestors: [],
        hasChildren: true,
        disabled: false,
    },
    {
        id: 2,
        name: 'Maths',
        ancestors: [1],
        hasChildren: true,
        disabled: false,
    },
    {
        id: 3,
        name: 'Algebra',
        ancestors: [1, 2],
        hasChildren: false,
        disabled: false,
    },
    {
        id: 4,
        name: 'Geometry',
        ancestors: [1, 2],
        hasChildren: false,
        disabled: false,
    },
    {
        id: 5,
        name: 'Languages',
        ancestors: [],
        hasChildren: false,
        disabled: false,
    },
]
function create(config = {}) {
    const field = picker({
        state: [],
        nodes,
        multiple: true,
        selectAncestors: true,
        labels: {},
        ...config,
    })
    field.$refs = {
        trigger: { focus() {} },
        search: { focus() {} },
        reviewHeading: { focus() {} },
        dialog: { close() {}, showModal() {} },
    }
    field.$root = {
        querySelector() {
            return { focus() {} }
        },
    }
    field.$nextTick = (callback) => callback()
    return field
}

test('picker stages ancestor selection and cancellation discards changes; Apply updates only the pending field', () => {
    const field = create({ state: [5] })
    field.show()
    field.toggleDraft(3)
    assert.deepEqual(field.draft, [5, 1, 2, 3])
    assert.deepEqual(field.state, [5])
    field.close()
    field.show()
    assert.deepEqual(field.draft, [5])
    field.toggleDraft(4)
    field.apply()
    assert.deepEqual(field.state, [5, 1, 2, 4])
    assert.equal(field.open, false)
    assert.equal(field.isSelected(4), true)
    assert.equal(field.selectionFeedback, '4 terms selected')
})

test('branch removal previews exact assigned IDs; leaf removal keeps ancestors; Undo restores opaque value types', () => {
    const field = create({ state: ['1', 2, '3', 4, 5] })
    field.requestRemoval(2)
    assert.deepEqual(field.removal.ids, [2, '3', 4])
    assert.deepEqual(field.state, ['1', 2, '3', 4, 5])
    field.cancelRemoval()
    assert.deepEqual(field.state, ['1', 2, '3', 4, 5])
    field.requestRemoval(2)
    field.confirmRemoval()
    assert.deepEqual(field.state, ['1', 5])
    field.undoRemoval()
    assert.deepEqual(field.state, ['1', 2, '3', 4, 5])
    field.requestRemoval(3)
    assert.deepEqual(field.state, ['1', 2, 4, 5])
    assert.equal(field.removal, null)
})

test('independent assignment removes only parent while showing unassigned ancestors as context', () => {
    const field = create({ state: [1, 2, 3], selectAncestors: false })
    field.requestRemoval(2)
    assert.deepEqual(field.state, [1, 3])
    const maths = field.reviewRows.find((row) => row.id === 2)
    assert.ok(maths)
    assert.equal(field.isSelected(maths.id), false)
    assert.equal(field.canRemove(2), false)
    assert.equal(field.selectedBelowCount(2), 1)
})

test('clear and branch removal preserve unavailable/restricted assignments and their required ancestors', () => {
    const restricted = nodes.map((node) =>
        node.id === 3 ? { ...node, disabled: true } : node,
    )
    const field = create({
        nodes: restricted,
        labels: { remove_branch: 'Remove branch :name (:count)' },
        state: [1, 2, 3, 4, 5, '9007199254740992'],
    })
    assert.equal(field.canRemove(2), false)
    assert.equal(field.removeAction(2), 'Remove branch Maths (3)')
    assert.equal(field.canRemove('9007199254740992'), false)
    field.show()
    field.clearDraft()
    assert.deepEqual(field.removal.ids, [4, 5])
    field.confirmRemoval()
    field.apply()
    assert.deepEqual(field.state, [1, 2, 3, '9007199254740992'])
})

test('permission or hierarchy updates invalidate preview and undo, while identical server configuration preserves draft', () => {
    const config = {
        state: [1, 2, 3],
        nodes,
        multiple: true,
        selectAncestors: true,
        disabled: false,
        readOnly: false,
        labels: {},
    }
    const field = create(config)
    field.show()
    field.toggleDraft(4)
    const { state, ...same } = config
    field.configure(same)
    assert.equal(field.open, true)
    assert.deepEqual(field.draft, [1, 2, 3, 4])
    field.configure({ ...same, readOnly: true })
    assert.equal(field.open, false)
    assert.equal(field.blocked, true)
    assert.deepEqual(field.state, [1, 2, 3])
    field.configure(same)
    field.requestRemoval(2)
    field.state = [1, 2, 4]
    field.confirmRemoval()
    assert.deepEqual(field.state, [1, 2, 4])
    field.requestRemoval(4)
    field.configure({ ...same, nodes: nodes.filter((node) => node.id !== 4) })
    assert.equal(field.undo, null)
})

test('deep and broad trees render a bounded review and one level of picker rows, with complete paths', () => {
    const deep = Array.from({ length: 30 }, (_, index) => ({
        id: index + 1,
        name: 'Level ' + (index + 1),
        ancestors: Array.from({ length: index }, (_, ancestor) => ancestor + 1),
        hasChildren: true,
        disabled: false,
    }))
    const broad = Array.from({ length: 200 }, (_, index) => ({
        id: index + 100,
        name: 'Repeated title',
        ancestors: [1],
        hasChildren: false,
        disabled: false,
    }))
    const field = create({
        nodes: [...deep, ...broad],
        state: [...deep, ...broad].map((node) => node.id),
    })
    assert.equal(field.reviewRows.length, 50)
    assert.ok(field.reviewRows.every((row) => row.level <= 2))
    field.review(28)
    assert.deepEqual(
        field.reviewRows.map((row) => row.id),
        [28, 29, 30],
    )
    assert.equal(field.path(30).length, 29)
    field.show()
    assert.deepEqual(
        field.pickerRows.map((row) => row.id),
        [1],
    )
    field.browse(1)
    assert.equal(field.pickerMatches.length, 201)
    assert.equal(field.pickerRows.length, 50)
    field.search = 'Level 30'
    assert.deepEqual(
        field.pickerRows.map((row) => row.id),
        [30],
    )
    assert.ok(field.pathLabel(30).includes('Level 1 › Level 2'))
})

test('draft removal can be undone before applying; changing draft invalidates Undo', () => {
    const field = create({ state: [1, 2, 3, 5] })
    field.show()
    field.toggleDraft(2)
    assert.equal(field.removal.scope, 'draft')
    field.confirmRemoval()
    assert.deepEqual(field.draft, [1, 5])
    assert.deepEqual(field.state, [1, 2, 3, 5])
    field.undoRemoval()
    assert.deepEqual(field.draft, [1, 2, 3, 5])
    field.toggleDraft(3)
    field.toggleDraft(4)
    assert.equal(field.undo, null)
})

test('state watcher refreshes summaries after an in-place external assignment update', () => {
    const field = create({ state: [1] })
    const watches = {}
    field.$watch = (name, callback) => {
        watches[name] = callback
    }
    field.$nextTick = () => {}
    field.init()
    assert.equal(field.selectedBelowCount(1), 0)
    field.state.push(2)
    watches.state()
    assert.equal(field.selectedBelowCount(1), 1)
    assert.ok(field.reviewRows.some((row) => row.id === 2))
})

test('Cancel discards draft removal feedback and retains an earlier actual Undo', () => {
    const field = create({
        state: [1, 2, 3, 5],
        labels: { removed: ':count terms removed.' },
    })
    field.show()
    field.toggleDraft(3)
    assert.equal(field.notice, '1 terms removed.')
    field.close()
    assert.deepEqual(field.state, [1, 2, 3, 5])
    assert.equal(field.notice, '')
    field.requestRemoval(3)
    field.show()
    field.toggleDraft(5)
    field.close()
    assert.equal(field.notice, '1 terms removed.')
    field.undoRemoval()
    assert.deepEqual(field.state, [1, 2, 3, 5])
})
