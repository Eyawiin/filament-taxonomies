import { createTreeDrag } from '../drag/pragmatic-tree.js'

export default function taxonomyTreeDrag({ dropTerm }) {
    let adapter
    let observer
    let destroyed = false

    return {
        saving: false,
        error: '',

        init() {
            adapter = createTreeDrag({
                root: this.$root,
                isBusy: () => this.saving,
                onDrop: (termId, targetId, placement) =>
                    this.drop(termId, targetId, placement),
            })

            observer = new MutationObserver(() => {
                this.$nextTick(() => {
                    if (!destroyed) adapter.refresh()
                })
            })
            observer.observe(this.$root, { childList: true, subtree: true })
        },

        async drop(termId, targetId, placement) {
            if (this.saving) return

            this.saving = true
            this.error = ''

            try {
                await dropTerm(termId, targetId, placement)
            } catch {
                this.error =
                    'The move could not be saved. Refresh the tree and try again.'
            } finally {
                this.saving = false
                this.$nextTick(() => {
                    if (!destroyed) adapter.refresh()
                })
            }
        },

        destroy() {
            destroyed = true
            observer?.disconnect()
            adapter?.destroy()
        },
    }
}
