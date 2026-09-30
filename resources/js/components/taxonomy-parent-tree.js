export default function taxonomyParentTree({ state, nodes }) {
    return {
        state,
        nodes,
        open: false,
        search: '',
        expanded: nodes
            .filter((node) => node.hasChildren)
            .map((node) => node.id),
        activeId: nodes[0]?.id ?? null,

        get selectedLabel() {
            return (
                this.nodes.find(
                    (node) => String(node.id) === String(this.state),
                )?.name ?? 'No parent (root term)'
            )
        },

        get visibleNodes() {
            const query = this.search.trim().toLocaleLowerCase()
            if (query) {
                const visible = new Set()
                for (const node of this.nodes) {
                    if (node.name.toLocaleLowerCase().includes(query)) {
                        visible.add(node.id)
                        node.ancestors.forEach((id) => visible.add(id))
                    }
                }
                return this.nodes.filter((node) => visible.has(node.id))
            }
            return this.nodes.filter((node) =>
                node.ancestors.every((id) => this.expanded.includes(id)),
            )
        },

        isExpanded(id) {
            return this.search.trim() !== '' || this.expanded.includes(id)
        },

        toggleNode(id) {
            if (this.search.trim()) return
            this.expanded = this.expanded.includes(id)
                ? this.expanded.filter((value) => value !== id)
                : [...this.expanded, id]
        },

        toggle() {
            if (this.open) this.close()
            else this.show()
        },

        show() {
            this.open = true
            this.search = ''
            this.$nextTick(() => this.$refs.search.focus())
        },

        close() {
            this.open = false
            this.$refs.trigger.focus()
        },

        choose(id) {
            if (
                id !== null &&
                (!this.nodes.some((node) => node.id === id) ||
                    this.nodes.find((node) => node.id === id).disabled)
            )
                return
            this.state = id
            this.close()
        },

        focusNode(id) {
            if (id == null) return
            this.activeId = id
            this.$nextTick(() =>
                this.$root
                    .querySelector('[data-node-id="' + id + '"]')
                    ?.focus(),
            )
        },

        focusFirst() {
            this.focusNode(this.visibleNodes[0]?.id)
        },

        navigate(event) {
            if (
                ![
                    'ArrowDown',
                    'ArrowUp',
                    'ArrowRight',
                    'ArrowLeft',
                    'Home',
                    'End',
                    'Enter',
                    ' ',
                ].includes(event.key)
            )
                return
            event.preventDefault()
            event.stopPropagation()
            const visible = this.visibleNodes
            const index = visible.findIndex((node) => node.id === this.activeId)
            const node = visible[index]
            if (!node) return
            switch (event.key) {
                case 'ArrowDown':
                    this.focusNode(
                        visible[Math.min(index + 1, visible.length - 1)]?.id,
                    )
                    break
                case 'ArrowUp':
                    this.focusNode(visible[Math.max(index - 1, 0)]?.id)
                    break
                case 'Home':
                    this.focusFirst()
                    break
                case 'End':
                    this.focusNode(visible.at(-1)?.id)
                    break
                case 'ArrowRight':
                    if (node.hasChildren && !this.isExpanded(node.id))
                        this.toggleNode(node.id)
                    else if (node.hasChildren)
                        this.focusNode(visible[index + 1]?.id)
                    break
                case 'ArrowLeft':
                    if (
                        node.hasChildren &&
                        this.isExpanded(node.id) &&
                        !this.search.trim()
                    )
                        this.toggleNode(node.id)
                    else this.focusNode(node.ancestors.at(-1))
                    break
                default:
                    this.choose(node.id)
            }
        },
    }
}
