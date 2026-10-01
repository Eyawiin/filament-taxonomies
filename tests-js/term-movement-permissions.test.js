import assert from 'node:assert/strict'
import test from 'node:test'
import { runInNewContext } from 'node:vm'
import { build } from 'esbuild'

// Exercise our adapter callbacks; the external drag engine and browser DOM are stubbed.
const { outputFiles } = await build({
    entryPoints: ['resources/js/drag/pragmatic-tree.js'],
    bundle: true,
    write: false,
    format: 'iife',
    globalName: 'treeDrag',
    plugins: [
        {
            name: 'drag-engine-stub',
            setup(builder) {
                builder.onResolve({ filter: /^@atlaskit\// }, ({ path }) => ({
                    path,
                    namespace: 'drag-engine-stub',
                }))
                builder.onLoad(
                    { filter: /.*/, namespace: 'drag-engine-stub' },
                    ({ path }) => ({
                        contents: path.endsWith('/combine')
                            ? 'export const combine = (...cleanups) => () => cleanups.forEach(cleanup => cleanup())'
                            : 'export const { draggable, dropTargetForElements, monitorForElements } = globalThis.engine',
                    }),
                )
            },
        },
    ],
})

function fixture() {
    const draggables = []
    const targets = []
    let monitor
    let busy = false
    const drops = []
    const context = {
        engine: {
            draggable(options) {
                draggables.push(options)
                return () => {}
            },
            dropTargetForElements(options) {
                targets.push(options)
                return () => {}
            },
            monitorForElements(options) {
                monitor = options
                return () => {}
            },
        },
    }
    runInNewContext(outputFiles[0].text, context)

    const terms = [1, 2].map((id) => ({
        dataset: { termId: String(id), canMove: 'true' },
        querySelector: () => null,
        contains(row) {
            return row === this.row
        },
    }))
    const rows = terms.map((term) => {
        const row = {
            dataset: {},
            closest: () => term,
            querySelector: () => null,
            getBoundingClientRect: () => ({
                toJSON: () => ({ top: 0, height: 100 }),
            }),
        }
        term.row = row
        return row
    })
    const adapter = context.treeDrag.createTreeDrag({
        root: { querySelectorAll: () => rows },
        isBusy: () => busy,
        onDrop: (...arguments_) => drops.push(arguments_),
    })
    const source = { element: rows[0], data: draggables[0].getInitialData() }
    const target = {
        element: rows[1],
        data: targets[1].getData({ input: { clientY: 50 } }),
    }
    const drop = () =>
        monitor.onDrop({
            source,
            location: { current: { dropTargets: [target] } },
        })

    return {
        adapter,
        terms,
        draggables,
        targets,
        source,
        drops,
        drop,
        setBusy: (value) => {
            busy = value
        },
    }
}

test('drag permission is read from the current term state, including later revocation', () => {
    const tree = fixture()
    assert.equal(tree.draggables[0].canDrag(), true)
    tree.terms[0].dataset.canMove = 'false'
    assert.equal(tree.draggables[0].canDrag(), false)
    tree.terms[0].dataset.canMove = 'true'
    assert.equal(tree.draggables[0].canDrag(), true)
    tree.setBusy(true)
    assert.equal(tree.draggables[0].canDrag(), false)
    tree.adapter.destroy()
})

test('a visible term denied updates remains a valid destination for an allowed source', () => {
    const tree = fixture()
    tree.terms[1].dataset.canMove = 'false'
    assert.equal(tree.draggables[1].canDrag(), false)
    assert.equal(tree.targets[1].canDrop({ source: tree.source }), true)
    tree.drop()
    assert.deepEqual(tree.drops, [[1, 2, 'inside']])
    tree.adapter.destroy()
})

test('revoking source permission during a drag prevents submission', () => {
    const tree = fixture()
    tree.terms[0].dataset.canMove = 'false'
    tree.drop()
    assert.deepEqual(tree.drops, [])
    tree.terms[0].dataset.canMove = 'true'
    tree.drop()
    assert.deepEqual(tree.drops, [[1, 2, 'inside']])
    tree.adapter.destroy()
})
