import test from 'node:test'
import assert from 'node:assert/strict'
import field, {
    fold,
    searchNodes,
} from '../resources/js/components/taxonomy-term-field.js'

globalThis.window ??= {
    addEventListener() {},
    removeEventListener() {},
    innerHeight: 800,
}

const labels = {
    selected: ':count selected',
    selected_below: ':count selected below',
    placeholder: 'Select terms',
    placeholder_single: 'Select a term',
    unavailable: 'Unavailable term',
    empty: 'Empty taxonomy',
    no_results: 'No matching terms',
    more_results: 'Showing :shown of :total',
    searching: 'Searching…',
    load_failed: 'Failed',
    remove: 'Remove :name',
}

const node = (id, name, ancestors = [], extra = {}) => ({
    id,
    name,
    ancestors,
    hasChildren: false,
    disabled: false,
    reason: '',
    ...extra,
})

// Science > Maths > (Algebra, Geometry); Languages
const nodes = [
    node(1, 'Science', [], { hasChildren: true }),
    node(2, 'Maths', [1], { hasChildren: true }),
    node(3, 'Algebra', [1, 2]),
    node(4, 'Geometry', [1, 2]),
    node(5, 'Languages'),
]

function create(config = {}) {
    const instance = field({
        state: [],
        nodes,
        layout: 'tree',
        multiple: true,
        labels,
        ...config,
    })
    const box = () => ({ top: 0, bottom: 40, height: 40 })
    instance.$refs = {
        trigger: { focus() {}, getBoundingClientRect: box },
        search: { focus() {} },
    }
    instance.$root = {
        querySelector: () => null,
        contains: () => false,
        getBoundingClientRect: box,
    }
    instance.watchers = {}
    instance.$watch = (name, callback) => {
        instance.watchers[name] = callback
    }
    instance.$nextTick = (callback) => callback()
    return instance
}

const rowIds = (instance) =>
    instance.view.rows.filter((row) => row.type === 'node').map((row) => row.id)
const flush = () => new Promise((resolve) => setTimeout(resolve, 0))

test('folding and search follow the server rules', () => {
    assert.equal(fold('Pokémon ÉCLAIR'), 'pokemon eclair')
    const tree = [
        node(1, 'Games'),
        node(2, 'Pokémon', [1]),
        node(3, 'Paldea Evolved', [1, 2]),
        node(4, 'Places'),
        node(5, 'Paldea', [4]),
    ]
    const names = new Map(tree.map((term) => [term.id, term.name]))
    const ids = (query) =>
        searchNodes(tree, query, (id) => names.get(id)).map((term) => term.id)
    assert.deepEqual(ids('POKEMON'), [2])
    assert.deepEqual(ids('paldea'), [5, 3])
    assert.deepEqual(ids('pokemon paldea'), [3])
    assert.deepEqual(ids('  '), [])
})

test('independent selection toggles only the chosen term', () => {
    const instance = create()
    instance.toggle(3)
    instance.toggle(5)
    assert.deepEqual(instance.state, [3, 5])
    assert.equal(instance.selectedBelow(1), 1)
    instance.toggle(3)
    assert.deepEqual(instance.state, [5])
    instance.toggle(999)
    assert.deepEqual(instance.state, [5])
    assert.equal(
        instance.text('selected', { count: instance.selectedCount }),
        '1 selected',
    )
})

test('ancestor selection adds parents and removing a parent removes its branch', () => {
    const instance = create({ selectAncestors: true })
    instance.toggle(3)
    assert.deepEqual(instance.state, [1, 2, 3])
    instance.toggle(4)
    assert.deepEqual(instance.state, [1, 2, 3, 4])
    assert.equal(instance.selectedBelow(2), 2)
    instance.toggle(4)
    assert.deepEqual(instance.state, [1, 2, 3])
    instance.toggle(1)
    assert.deepEqual(instance.state, [])
})

test('restricted and unavailable assignments survive removal and clearing', () => {
    const restricted = [
        ...nodes.slice(0, 3),
        node(4, 'Geometry', [1, 2], {
            disabled: true,
            reason: 'Not permitted',
        }),
        nodes[4],
    ]
    const instance = create({
        nodes: restricted,
        selectAncestors: true,
        state: [1, 2, 3, 4, 5, 77],
    })
    assert.equal(instance.canToggle(2), false)
    instance.toggle(2)
    assert.deepEqual(instance.state, [1, 2, 3, 4, 5, 77])
    assert.equal(instance.canToggle(3), true)
    instance.clear()
    assert.deepEqual(instance.state, [1, 2, 4, 77])
    assert.equal(instance.canClear, false)
})

test('chips show the deepest selected terms with their paths', () => {
    const instance = create({ selectAncestors: true, state: [1, 2, 3, 5, 99] })
    assert.deepEqual(
        instance.chips.map((chip) => [chip.name, chip.path, chip.removable]),
        [
            ['Algebra', 'Science › Maths', true],
            ['Languages', '', true],
            ['Unavailable term', '', false],
        ],
    )
    instance.removeChip(3)
    assert.deepEqual(instance.state, [1, 2, 5, 99])
    assert.equal(instance.chips[0].name, 'Maths')
})

test('single fields choose one term, offer the empty choice and close the dropdown', () => {
    const instance = create({
        state: null,
        multiple: false,
        layout: 'dropdown',
        emptyOption: 'None',
    })
    assert.equal(instance.triggerLabel, 'Select a term')
    assert.equal(instance.isPlaceholder, true)
    instance.show()
    assert.equal(instance.open, true)
    assert.equal(instance.view.rows[0].type, 'empty')
    instance.toggle(3)
    assert.equal(instance.state, 3)
    assert.equal(instance.open, false)
    assert.equal(instance.singleLabel, 'Algebra')
    assert.equal(instance.singlePath, 'Science › Maths')
    instance.chooseEmpty()
    assert.equal(instance.state, null)
    instance.state = [3, 4]
    assert.equal(instance.singleLabel, 'Unavailable term')
})

test('the tree shows expanded branches, positions and searches with paths', () => {
    const instance = create()
    assert.deepEqual(rowIds(instance), [1, 2, 3, 4, 5])
    const algebra = instance.view.rows.find((row) => row.id === 3)
    assert.deepEqual(
        [algebra.level, algebra.posinset, algebra.setsize],
        [3, 1, 2],
    )
    instance.toggleNode(2)
    assert.deepEqual(rowIds(instance), [1, 2, 5])
    instance.collapseAll()
    assert.deepEqual(rowIds(instance), [1, 5])
    instance.expandAll()
    assert.deepEqual(rowIds(instance), [1, 2, 3, 4, 5])
    instance.search = 'ALG'
    assert.deepEqual(rowIds(instance), [3])
    assert.equal(instance.view.rows[0].path, 'Science › Maths')
    instance.search = 'zzz'
    assert.equal(instance.view.status, 'No matching terms')
    assert.equal(create({ nodes: [] }).view.status, 'Empty taxonomy')
})

test('disabled and read-only fields cannot change or open', () => {
    for (const mode of ['disabled', 'readOnly']) {
        const instance = create({
            state: [5],
            layout: 'dropdown',
            [mode]: true,
        })
        instance.toggle(3)
        instance.clear()
        instance.show()
        assert.deepEqual(instance.state, [5])
        assert.equal(instance.open, false)
        assert.equal(instance.chips[0].removable, false)
    }
})

test('lazy trees load branches and further pages, and recover from errors', async () => {
    const calls = []
    let fail = true
    const instance = create({
        lazy: true,
        layout: 'dropdown',
        nodes: [node(1, 'Science', [], { hasChildren: true }), nodes[4]],
        more: true,
        call: async (method, args) => {
            calls.push([method, args])
            if (method === 'getTreeChildren' && args.parent === null)
                return { nodes: [node(6, 'Music')], more: false }
            if (fail) {
                fail = false
                throw new Error('offline')
            }
            return { nodes: [nodes[1]], more: false }
        },
    })
    assert.deepEqual(rowIds(instance), [1, 5])
    assert.equal(instance.view.rows.at(-1).type, 'more')

    instance.toggleNode(1)
    assert.equal(instance.view.rows[1].type, 'loading')
    await flush()
    assert.equal(instance.view.rows[1].type, 'error')
    instance.activate(instance.view.rows[1])
    await flush()
    assert.deepEqual(rowIds(instance), [1, 2, 5])

    instance.loadMore('root')
    await flush()
    assert.deepEqual(rowIds(instance), [1, 2, 5, 6])
    assert.deepEqual(calls.at(-1), [
        'getTreeChildren',
        { parent: null, offset: 2 },
    ])
})

test('lazy search and unknown selections are answered by the server', async () => {
    const instance = create({
        lazy: true,
        searchDelay: 0,
        nodes: [node(1, 'Science', [], { hasChildren: true })],
        call: async (method, args) => {
            if (method === 'searchTree')
                return {
                    nodes: [node(3, 'Algebra', [1, 2])],
                    names: { 1: 'Science', 2: 'Maths' },
                    total: 7,
                }
            if (method === 'getTreeSelection')
                return {
                    nodes: args.ids.includes('4')
                        ? [node(4, 'Geometry', [1, 2])]
                        : [],
                    names: { 2: 'Maths' },
                }
            return { nodes: [], more: false }
        },
    })
    instance.search = 'alg'
    instance.runSearch()
    assert.equal(instance.view.status, 'Searching…')
    await flush()
    await flush()
    assert.deepEqual(rowIds(instance), [3])
    assert.equal(instance.view.rows[0].path, 'Science › Maths')
    assert.equal(instance.view.status, 'Showing 1 of 7')

    instance.state = [4, 88]
    instance.settleSelection()
    await flush()
    assert.deepEqual(
        instance.chips.map((chip) => chip.name),
        ['Geometry', 'Unavailable term'],
    )
})

test('configuration updates keep expansion unless the tree itself changed', async () => {
    const instance = create()
    instance.toggleNode(2)
    instance.configure({
        nodes,
        layout: 'tree',
        multiple: true,
        labels,
        disabled: true,
    })
    assert.equal(instance.disabled, true)
    assert.deepEqual(rowIds(instance), [1, 2, 5])
    instance.configure({
        nodes: [...nodes, node(6, 'Music', [], { hasChildren: true })],
        layout: 'tree',
        multiple: true,
        labels,
    })
    assert.deepEqual(rowIds(instance), [1, 2, 5, 6])
    assert.equal(instance.isExpanded(6), true)
})

test('arrow keys move through rows, open and close branches, and select', () => {
    const instance = create({ selectAncestors: true })
    instance.focusRow = (key) => {
        instance.activeKey = key
    }
    const press = (key) =>
        instance.onKeydown({
            key,
            target: { matches: () => false },
            preventDefault() {},
            stopPropagation() {},
        })
    instance.activeKey = 'n1'
    press('ArrowLeft')
    assert.deepEqual(rowIds(instance), [1, 5])
    press('ArrowRight')
    press('ArrowRight')
    assert.equal(instance.activeKey, 'n2')
    press('ArrowRight')
    assert.equal(instance.activeKey, 'n3')
    press(' ')
    assert.deepEqual(instance.state, [1, 2, 3])
    press('ArrowLeft')
    assert.equal(instance.activeKey, 'n2')
    press('End')
    assert.equal(instance.activeKey, 'n5')
    press('Home')
    assert.equal(instance.activeKey, 'n1')
})
