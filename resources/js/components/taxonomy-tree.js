import Sortable from 'sortablejs'

export default function taxonomyTreeDrag({ moveTerm }) {
    return {
        sortables: new Map(),
        observer: null,
        dragging: false,
        lastPointerX: null,
        nestThreshold: 24,

        init() {
            this.refreshSortables()

            this.observer = new MutationObserver(() => {
                if (this.dragging) {
                    return
                }

                this.$nextTick(() => {
                    this.refreshSortables()
                })
            })

            this.observer.observe(this.$root, {
                childList: true,
                subtree: true,
            })
        },

        destroy() {
            this.observer?.disconnect()

            for (const sortable of this.sortables.values()) {
                sortable.destroy()
            }

            this.sortables.clear()
        },

        refreshSortables() {
            const elements = new Set(
                this.$root.querySelectorAll('[data-taxonomy-sortable]'),
            )

            for (const [element, sortable] of this.sortables.entries()) {
                if (elements.has(element)) {
                    continue
                }

                sortable.destroy()
                this.sortables.delete(element)
            }

            for (const element of elements) {
                if (this.sortables.has(element)) {
                    continue
                }

                const sortable = Sortable.create(element, {
                    group: 'taxonomy-terms',
                    draggable: '[data-taxonomy-term]',
                    handle: '[data-taxonomy-drag-handle]',

                    animation: 100,
                    fallbackOnBody: true,
                    swapThreshold: 0.65,

                    // Empty child lists are controlled through horizontal
                    // indentation rather than invisible hit areas.
                    emptyInsertThreshold: 0,

                    onStart: () => {
                        this.dragging = true
                        this.lastPointerX = null
                    },

                    onMove: (event, originalEvent) => {
                        if (event.dragged.contains(event.to)) {
                            return false
                        }

                        const pointerX = originalEvent?.clientX

                        if (typeof pointerX !== 'number') {
                            return true
                        }

                        if (this.lastPointerX === null) {
                            this.lastPointerX = pointerX

                            return true
                        }

                        const horizontalDelta = pointerX - this.lastPointerX

                        if (horizontalDelta >= this.nestThreshold) {
                            if (this.indent(event.dragged)) {
                                this.lastPointerX = pointerX

                                return false
                            }
                        }

                        if (horizontalDelta <= -this.nestThreshold) {
                            if (this.outdent(event.dragged)) {
                                this.lastPointerX = pointerX

                                return false
                            }
                        }

                        return true
                    },

                    onEnd: async (event) => {
                        this.dragging = false
                        this.lastPointerX = null

                        const termId = Number(event.item.dataset.termId)

                        const destination = event.item.parentElement

                        const rawParentId = destination.dataset.parentId

                        const parentId =
                            rawParentId === '' ? null : Number(rawParentId)

                        const siblings = Array.from(
                            destination.children,
                        ).filter((element) =>
                            element.matches('[data-taxonomy-term]'),
                        )

                        const position = siblings.indexOf(event.item)

                        await moveTerm(termId, position, parentId)

                        this.$nextTick(() => {
                            this.refreshSortables()
                        })
                    },
                })

                this.sortables.set(element, sortable)
            }
        },

        indent(item) {
            const previousSibling = this.previousTaxonomySibling(item)

            if (previousSibling === null) {
                return false
            }

            // Never allow the dragged subtree to become its own parent.
            if (item.contains(previousSibling)) {
                return false
            }

            const childList = this.childSortable(previousSibling)

            if (childList === null) {
                return false
            }

            const parentId = Number(previousSibling.dataset.termId)

            // If it was collapsed, open it so the indentation is
            // immediately visible.
            window.dispatchEvent(
                new CustomEvent('taxonomy-tree-expand-term', {
                    detail: {
                        termId: parentId,
                    },
                }),
            )

            const wrapper = childList.closest(
                '[data-taxonomy-children-wrapper]',
            )

            wrapper?.classList.add('pt-2')

            childList.append(item)

            return true
        },

        outdent(item) {
            const currentList = item.parentElement

            if (!currentList?.matches('[data-taxonomy-sortable]')) {
                return false
            }

            const rawParentId = currentList.dataset.parentId

            // Already at root.
            if (rawParentId === '') {
                return false
            }

            const parentTerm = this.$root.querySelector(
                `[data-taxonomy-term][data-term-id="${rawParentId}"]`,
            )

            if (parentTerm === null) {
                return false
            }

            parentTerm.after(item)

            this.removeEmptyChildSpacing(currentList)

            return true
        },

        previousTaxonomySibling(item) {
            let sibling = item.previousElementSibling

            while (sibling !== null) {
                if (sibling.matches('[data-taxonomy-term]')) {
                    return sibling
                }

                sibling = sibling.previousElementSibling
            }

            return null
        },

        childSortable(term) {
            const wrapper = Array.from(term.children).find((element) =>
                element.matches('[data-taxonomy-children-wrapper]'),
            )

            if (wrapper === undefined) {
                return null
            }

            return (
                Array.from(wrapper.children).find((element) =>
                    element.matches('[data-taxonomy-sortable]'),
                ) ?? null
            )
        },

        removeEmptyChildSpacing(list) {
            const hasChildren = Array.from(list.children).some((element) =>
                element.matches('[data-taxonomy-term]'),
            )

            if (hasChildren) {
                return
            }

            list.closest('[data-taxonomy-children-wrapper]')?.classList.remove(
                'pt-2',
            )
        },
    }
}
