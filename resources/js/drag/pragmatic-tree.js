import {
    draggable,
    dropTargetForElements,
    monitorForElements,
} from '@atlaskit/pragmatic-drag-and-drop/element/adapter'
import { combine } from '@atlaskit/pragmatic-drag-and-drop/combine'
import { getDropPlacement } from './tree-hitbox.js'

export function createTreeDrag({ root, isBusy, onDrop }) {
    const tree = Symbol('taxonomy-tree')
    const registrations = new Map()
    let dragging = false
    let indicator = null
    let invalidIndicator = null
    let pendingExpand = null
    const unavailableRows = new Set()

    function clearIndicator() {
        if (indicator) delete indicator.dataset.dropPlacement
        if (invalidIndicator) delete invalidIndicator.dataset.dropInvalid
        indicator = null
        invalidIndicator = null
    }

    function markUnavailable(source) {
        const sourceTerm = source.element.closest('[data-taxonomy-term]')
        const rows = [
            source.element,
            ...sourceTerm.querySelectorAll('[data-taxonomy-row]'),
        ]

        for (const row of rows) {
            row.dataset.dropDisabled = 'true'
            unavailableRows.add(row)
        }
    }

    function clearUnavailable() {
        for (const row of unavailableRows) delete row.dataset.dropDisabled
        unavailableRows.clear()
    }

    function clearPendingExpand() {
        if (pendingExpand) clearTimeout(pendingExpand.timeout)
        pendingExpand = null
    }

    function scheduleExpand(target, placement) {
        const shouldExpand =
            placement === 'inside' &&
            target.element.dataset.hasChildren === 'true' &&
            target.element.dataset.expanded === 'false'

        if (!shouldExpand) {
            clearPendingExpand()
            return
        }

        if (
            pendingExpand?.element === target.element &&
            pendingExpand.placement === placement
        )
            return

        clearPendingExpand()
        pendingExpand = {
            element: target.element,
            placement,
            timeout: setTimeout(() => {
                const termId = Number(
                    target.element.closest('[data-taxonomy-term]').dataset
                        .termId,
                )
                target.element.dispatchEvent(
                    new CustomEvent('taxonomy-tree-expand-term', {
                        bubbles: true,
                        detail: { termId },
                    }),
                )
                pendingExpand = null
            }, 600),
        }
    }

    function showTarget({ source, location }) {
        clearIndicator()
        const target = location.current.dropTargets[0]
        if (target?.data.tree === tree) {
            indicator = target.element
            indicator.dataset.dropPlacement = target.data.placement
            scheduleExpand(target, target.data.placement)
            return
        }

        clearPendingExpand()
        const { clientX, clientY } = location.current.input
        const hoveredRow = document
            .elementFromPoint(clientX, clientY)
            ?.closest('[data-taxonomy-row]')
        const sourceTerm = source.element.closest('[data-taxonomy-term]')

        if (hoveredRow && sourceTerm?.contains(hoveredRow)) {
            invalidIndicator = hoveredRow
            invalidIndicator.dataset.dropInvalid = 'true'
        }
    }

    function refresh() {
        const rows = new Set(root.querySelectorAll('[data-taxonomy-row]'))

        if (!dragging) {
            for (const [row, registration] of registrations) {
                if (
                    rows.has(row) &&
                    row.querySelector('[data-taxonomy-drag-handle]') ===
                        registration.handle
                )
                    continue

                registration.cleanup()
                registrations.delete(row)
            }
        }

        for (const row of rows) {
            const term = row.closest('[data-taxonomy-term]')
            row.dataset.hasChildren = term.querySelector(':scope > div[x-show]')
                ? 'true'
                : 'false'

            if (registrations.has(row)) continue

            const handle = row.querySelector('[data-taxonomy-drag-handle]')
            const termId = Number(term.dataset.termId)
            if (!Number.isSafeInteger(termId) || termId < 1) continue

            const cleanup = combine(
                draggable({
                    element: row,
                    dragHandle: handle,
                    canDrag: () =>
                        !isBusy() && term.dataset.canMove !== 'false',
                    getInitialData: () => ({ tree, termId }),
                }),
                dropTargetForElements({
                    element: row,
                    canDrop: ({ source }) =>
                        !isBusy() &&
                        source.data.tree === tree &&
                        !source.element
                            .closest('[data-taxonomy-term]')
                            .contains(row),
                    getData: ({ input }) => ({
                        tree,
                        termId,
                        placement: getDropPlacement({
                            pointerY: input.clientY,
                            ...row.getBoundingClientRect().toJSON(),
                        }),
                    }),
                    getDropEffect: () => 'move',
                }),
            )

            registrations.set(row, { handle, cleanup })
        }
    }

    const stopMonitoring = monitorForElements({
        canMonitor: ({ source }) => source.data.tree === tree,
        onDragStart: ({ source }) => {
            dragging = true
            source.element.dataset.dragging = 'true'
            markUnavailable(source)
        },
        onDrag: showTarget,
        onDropTargetChange: showTarget,
        onDrop: ({ source, location }) => {
            dragging = false
            delete source.element.dataset.dragging
            clearUnavailable()
            clearIndicator()
            clearPendingExpand()
            const target = location.current.dropTargets[0]

            if (
                target?.data.tree === tree &&
                !isBusy() &&
                source.element.closest('[data-taxonomy-term]').dataset
                    .canMove !== 'false'
            ) {
                onDrop(
                    source.data.termId,
                    target.data.termId,
                    target.data.placement,
                )
            }

            refresh()
        },
    })

    refresh()

    return {
        refresh,
        destroy() {
            stopMonitoring()
            clearUnavailable()
            clearIndicator()
            clearPendingExpand()
            for (const { cleanup } of registrations.values()) cleanup()
            registrations.clear()
        },
    }
}
