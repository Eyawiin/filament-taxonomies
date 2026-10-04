const ROOT = null

export default function taxonomyParentTree({
    state,
    nodes,
    disabled = false,
    readOnly = false,
    labels = {},
}) {
    // Configuration nodes and expansion lists are replaced by this component.
    // Keep memoization outside Alpine's reactive state to avoid effect loops.
    let visibility = null
    let destroyed = false

    return {
        state,
        nodes,
        disabled,
        readOnly,
        labels,
        open: false,
        search: '',
        expanded: nodes
            .filter((node) => node.hasChildren)
            .map((node) => node.id),
        activeId: ROOT,
        popupStyle: {},
        observer: null,
        reposition: null,

        init() {
            this.$watch('search', () => this.recoverFocus())
            this.$watch('state', () => {
                if (!this.open) this.activeId = this.selectionId()
            })
            this.$nextTick(() => {
                if (destroyed) return
                const config = this.$root.querySelector('[data-tree-config]')
                this.observer = new MutationObserver(() =>
                    this.configure(JSON.parse(config.dataset.treeConfig)),
                )
                this.observer.observe(config, {
                    attributes: true,
                    attributeFilter: ['data-tree-config'],
                })
                this.reposition = () => {
                    if (this.open) this.positionPopup()
                }
                window.addEventListener('resize', this.reposition)
                window.addEventListener('scroll', this.reposition, true)
            })
        },

        destroy() {
            destroyed = true
            this.observer?.disconnect()
            window.removeEventListener('resize', this.reposition)
            window.removeEventListener('scroll', this.reposition, true)
        },

        configure(config) {
            const oldIds = new Set(this.nodes.map((node) => node.id))
            this.expanded = this.expanded.filter((id) =>
                config.nodes.some((node) => node.id === id),
            )
            this.expanded.push(
                ...config.nodes
                    .filter((node) => node.hasChildren && !oldIds.has(node.id))
                    .map((node) => node.id),
            )
            Object.assign(this, config)
            if (this.blocked) {
                const focused = this.$root?.contains(
                    globalThis.document?.activeElement,
                )
                this.close(Boolean(focused && !this.disabled))
                if (focused && this.disabled)
                    globalThis.document?.activeElement?.blur()
            }
            this.recoverFocus()
        },

        get blocked() {
            return this.disabled || this.readOnly
        },

        selectionId() {
            return (
                this.nodes.find(
                    (node) => String(node.id) === String(this.state),
                )?.id ?? ROOT
            )
        },

        get selectedLabel() {
            if (this.state === null || this.state === '')
                return this.labels.root ?? 'No parent (root term)'
            return (
                this.nodes.find(
                    (node) => String(node.id) === String(this.state),
                )?.name ??
                this.labels.unavailable ??
                'Selected parent is unavailable'
            )
        },

        get visibility() {
            const nodes = this.nodes
            const expanded = this.expanded
            const query = this.search.trim().toLocaleLowerCase()
            if (
                visibility?.nodes === nodes &&
                visibility.expanded === expanded &&
                visibility.query === query
            )
                return visibility

            const ids = new Set()
            if (query) {
                for (const node of nodes) {
                    if (node.name.toLocaleLowerCase().includes(query)) {
                        ids.add(node.id)
                        node.ancestors.forEach((id) => ids.add(id))
                    }
                }
            } else {
                const expandedIds = new Set(expanded)
                for (const node of nodes) {
                    if (node.ancestors.every((id) => expandedIds.has(id)))
                        ids.add(node.id)
                }
            }
            visibility = {
                nodes,
                expanded,
                query,
                ids,
                visible: nodes.filter((node) => ids.has(node.id)),
            }
            return visibility
        },

        get visibleNodes() {
            return this.visibility.visible
        },

        get navigationNodes() {
            return [
                {
                    id: ROOT,
                    name: this.labels.root ?? 'No parent (root term)',
                    ancestors: [],
                    hasChildren: false,
                    disabled: false,
                },
                ...this.visibleNodes,
            ]
        },

        isVisible(id) {
            return this.visibility.ids.has(id)
        },
        isExpanded(id) {
            return this.search.trim() !== '' || this.expanded.includes(id)
        },

        recoverFocus(preferred = ROOT) {
            if (this.activeId === ROOT || this.isVisible(this.activeId)) return
            const hadFocus =
                this.$root?.contains(globalThis.document?.activeElement) &&
                globalThis.document?.activeElement?.matches('[data-node-id]')
            this.activeId = this.isVisible(preferred) ? preferred : ROOT
            if (hadFocus) this.focusNode(this.activeId)
        },

        toggleNode(id) {
            if (this.blocked || this.search.trim()) return
            this.expanded = this.expanded.includes(id)
                ? this.expanded.filter((value) => value !== id)
                : [...this.expanded, id]
            this.recoverFocus(id)
        },

        toggle() {
            if (this.open) this.close()
            else this.show()
        },

        show(enterTree = false) {
            if (this.blocked) return
            this.search = ''
            const selected = this.nodes.find(
                (node) => node.id === this.selectionId(),
            )
            if (selected)
                this.expanded = [
                    ...new Set([...this.expanded, ...selected.ancestors]),
                ]
            this.activeId = this.selectionId()
            this.open = true
            this.$nextTick(() => {
                this.positionPopup()
                if (enterTree) this.focusNode(this.activeId)
                else this.$refs.search.focus()
            })
        },

        positionPopup() {
            const rect = this.$refs.trigger.getBoundingClientRect()
            const below = window.innerHeight - rect.bottom - 12
            const above = rect.top - 12
            const up = below < 200 && above > below
            this.popupStyle = {
                top: up ? 'auto' : 'calc(100% + 0.25rem)',
                bottom: up ? 'calc(100% + 0.25rem)' : 'auto',
                maxHeight:
                    Math.max(80, Math.min(384, up ? above : below)) + 'px',
            }
        },

        close(restore = true) {
            this.open = false
            if (restore) this.$refs.trigger.focus()
        },

        leave(event) {
            if (!this.$root.contains(event.relatedTarget)) this.close(false)
        },

        choose(id) {
            if (
                this.blocked ||
                (id !== ROOT &&
                    !this.nodes.some(
                        (node) => node.id === id && !node.disabled,
                    ))
            )
                return
            this.state = id
            this.close()
        },

        focusNode(id) {
            if (id !== ROOT && !this.isVisible(id)) return
            this.activeId = id
            this.$nextTick(() =>
                this.$root
                    .querySelector('[data-node-id="' + (id ?? 'root') + '"]')
                    ?.focus(),
            )
        },

        focusFirst() {
            this.focusNode(ROOT)
        },
        enterTree() {
            this.focusNode(this.search.trim() ? ROOT : this.selectionId())
        },

        navigate(event) {
            if (
                this.blocked ||
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
            const visible = this.navigationNodes
            const index = visible.findIndex((node) => node.id === this.activeId)
            const node = visible[index]
            if (!node) return
            const rtl =
                this.$root &&
                globalThis.getComputedStyle?.(this.$root)?.direction === 'rtl'
            const key =
                rtl && event.key === 'ArrowRight'
                    ? 'ArrowLeft'
                    : rtl && event.key === 'ArrowLeft'
                      ? 'ArrowRight'
                      : event.key
            switch (key) {
                case 'ArrowDown':
                    this.focusNode(
                        visible[Math.min(index + 1, visible.length - 1)].id,
                    )
                    break
                case 'ArrowUp':
                    this.focusNode(visible[Math.max(index - 1, 0)].id)
                    break
                case 'Home':
                    this.focusFirst()
                    break
                case 'End':
                    this.focusNode(visible.at(-1).id)
                    break
                case 'ArrowRight':
                    if (node.hasChildren && !this.isExpanded(node.id))
                        this.toggleNode(node.id)
                    else if (node.hasChildren) {
                        const child = visible.find(
                            (candidate) =>
                                candidate.ancestors.at(-1) === node.id,
                        )
                        if (child) this.focusNode(child.id)
                    }
                    break
                case 'ArrowLeft':
                    if (
                        node.hasChildren &&
                        this.isExpanded(node.id) &&
                        !this.search.trim()
                    )
                        this.toggleNode(node.id)
                    else if (node.ancestors.length)
                        this.focusNode(node.ancestors.at(-1))
                    break
                default:
                    this.choose(node.id)
            }
        },
    }
}
