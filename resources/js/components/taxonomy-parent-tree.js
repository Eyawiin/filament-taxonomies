const ROOT = null

export default function taxonomyParentTree({
    state,
    nodes,
    disabled = false,
    readOnly = false,
    labels = {},
    multiple = false,
    selectAncestors = false,
}) {
    // Configuration nodes and expansion lists are replaced by this component.
    // Keep memoization outside Alpine's reactive state to avoid effect loops.
    let visibility = null
    let selectionSummary = null
    let destroyed = false

    return {
        state,
        nodes,
        disabled,
        readOnly,
        labels,
        multiple,
        selectAncestors,
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
            this.expandAncestorSelection()
            this.$watch('search', () => this.recoverFocus())
            this.$watch('state', () => {
                selectionSummary = null
                this.expandAncestorSelection()
                if (!this.open) this.activeId = this.selectionId()
                else
                    this.$nextTick(() => {
                        if (!destroyed && this.open) this.positionPopup()
                    })
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
            this.expandAncestorSelection()
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
                this.nodes.find((node) => this.isSelected(node.id))?.id ?? ROOT
            )
        },

        isSelected(id) {
            if (id === ROOT)
                return (
                    this.state === null ||
                    this.state === '' ||
                    (Array.isArray(this.state) && this.state.length === 0)
                )
            return this.multiple
                ? Array.isArray(this.state) &&
                      this.state.some((value) => String(value) === String(id))
                : !Array.isArray(this.state) &&
                      String(this.state) === String(id)
        },

        get selectedLabel() {
            if (this.isSelected(ROOT))
                return this.labels.root ?? 'No parent (root term)'
            if (!this.multiple && Array.isArray(this.state))
                return (
                    this.labels.unavailable ?? 'Selected parent is unavailable'
                )
            const values =
                this.multiple && Array.isArray(this.state)
                    ? this.state
                    : [this.state]
            return values
                .map(
                    (value) =>
                        this.nodes.find(
                            (node) => String(node.id) === String(value),
                        )?.name ??
                        this.labels.unavailable ??
                        'Selected parent is unavailable',
                )
                .join(', ')
        },

        get selectedTerms() {
            if (this.isSelected(ROOT)) return []
            const values = Array.isArray(this.state) ? this.state : [this.state]
            const nodes = new Map(
                this.nodes.map((node) => [String(node.id), node]),
            )
            return values.map((id) => {
                const node = nodes.get(String(id))
                return {
                    id,
                    name:
                        node?.name ??
                        this.labels.unavailable ??
                        'Selected term is unavailable',
                }
            })
        },

        removeLabel(name) {
            return (this.labels.remove ?? 'Remove :name').replace(':name', name)
        },

        removeTerm(id) {
            if (this.blocked || !this.multiple || !this.isSelected(id)) return
            // Move focus before Alpine removes the button that invoked this.
            this.$refs.trigger.focus()
            this.state = this.withoutTerm(id)
        },

        get includesAncestors() {
            return this.multiple && this.selectAncestors
        },

        ancestorIds(id) {
            const node = this.nodes.find(
                (node) => String(node.id) === String(id),
            )
            if (!node || node.disabled) return null
            if (!this.includesAncestors) return []
            return node.ancestors.every((ancestor) =>
                this.nodes.some(
                    (candidate) =>
                        candidate.id === ancestor && !candidate.disabled,
                ),
            )
                ? node.ancestors
                : null
        },

        expandAncestorSelection() {
            if (
                this.blocked ||
                !this.includesAncestors ||
                !Array.isArray(this.state)
            )
                return
            const values = [...this.state]
            const selected = new Set(values.map(String))
            for (const id of this.state) {
                for (const ancestor of this.ancestorIds(id) ?? []) {
                    if (!selected.has(String(ancestor))) {
                        selected.add(String(ancestor))
                        values.push(ancestor)
                    }
                }
            }
            // A no-op must not write back into the entangled state watcher.
            if (values.length !== this.state.length) this.state = values
        },

        withoutTerm(id) {
            const removed = new Set([String(id)])
            if (this.includesAncestors) {
                for (const node of this.nodes) {
                    if (
                        node.ancestors.some(
                            (ancestor) => String(ancestor) === String(id),
                        )
                    )
                        removed.add(String(node.id))
                }
            }
            return this.state.filter((value) => !removed.has(String(value)))
        },

        selectedBelowCount(id) {
            if (!this.multiple || !Array.isArray(this.state)) return 0
            if (
                selectionSummary?.state !== this.state ||
                selectionSummary.nodes !== this.nodes
            ) {
                const selected = new Set(this.state.map(String))
                const counts = new Map()
                for (const node of this.nodes) {
                    if (!selected.has(String(node.id))) continue
                    for (const ancestor of node.ancestors)
                        counts.set(ancestor, (counts.get(ancestor) ?? 0) + 1)
                }
                selectionSummary = {
                    state: this.state,
                    nodes: this.nodes,
                    counts,
                }
            }
            return selectionSummary.counts.get(id) ?? 0
        },

        selectedBelowLabel(id) {
            return (
                this.labels.selected_below ?? ':count selected below'
            ).replace(':count', this.selectedBelowCount(id))
        },

        get selectionFeedback() {
            const count = Array.isArray(this.state)
                ? this.state.length
                : this.isSelected(ROOT)
                  ? 0
                  : 1
            return (this.labels.selected ?? ':count terms selected').replace(
                ':count',
                count,
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
            const focusOrigin = globalThis.document?.activeElement
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
                if (destroyed || !this.open || this.blocked) return
                this.positionPopup()
                // Preserve focus if the user has already navigated elsewhere.
                if (globalThis.document?.activeElement !== focusOrigin) return
                if (enterTree) this.focusNode(this.activeId)
                else this.$refs.search.focus()
            })
        },

        positionPopup() {
            const rect = this.$root.getBoundingClientRect()
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
            if (this.multiple) {
                const current = Array.isArray(this.state) ? this.state : []
                if (id === ROOT) this.state = []
                else if (this.isSelected(id)) this.state = this.withoutTerm(id)
                else {
                    const ancestors = this.ancestorIds(id)
                    if (ancestors === null) return
                    const selected = new Set(current.map(String))
                    this.state = [
                        ...current,
                        ...[...ancestors, id].filter(
                            (value) => !selected.has(String(value)),
                        ),
                    ]
                }
                this.focusNode(id)
            } else {
                this.state = id
                this.close()
            }
        },

        focusNode(id) {
            if (id !== ROOT && !this.isVisible(id)) return
            this.activeId = id
            this.$nextTick(() => {
                if (
                    destroyed ||
                    !this.open ||
                    this.blocked ||
                    this.activeId !== id ||
                    (id !== ROOT && !this.isVisible(id))
                )
                    return
                this.$root
                    .querySelector('[data-node-id="' + (id ?? 'root') + '"]')
                    ?.focus()
            })
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
