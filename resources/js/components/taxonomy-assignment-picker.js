import taxonomyParentTree from './taxonomy-parent-tree.js'

const PAGE = 50
const REVIEW_DEPTH = 2
const key = (values) => JSON.stringify(values)
const signature = (config) =>
    key([
        config.nodes,
        !!config.disabled,
        !!config.readOnly,
        !!config.multiple,
        !!config.selectAncestors,
        config.labels,
    ])

export default function taxonomyAssignmentPicker(config) {
    const shared = taxonomyParentTree(config)
    let destroyed = false
    let index = null
    let draftSummary = null
    let configurationKey = signature(config)

    // Preserve shared getters: object spread would freeze reactive selection state.
    return Object.defineProperties(
        shared,
        Object.getOwnPropertyDescriptors({
            draft: [],
            draftOrigin: '',
            browseId: null,
            reviewId: null,
            listLimit: PAGE,
            reviewLimit: PAGE,
            removal: null,
            undo: null,
            savedUndo: null,
            savedNotice: '',
            notice: '',

            init() {
                this.expandAncestorSelection()
                this.$watch('search', () => {
                    this.listLimit = PAGE
                    this.$nextTick(() => {
                        if (this.$refs.options) this.$refs.options.scrollTop = 0
                        if (this.open) this.revealCurrentPath('pickerPath')
                    })
                })
                this.$watch('removal', () => {
                    this.$nextTick(() => this.syncRemovalDialog())
                })
                this.$watch('state', () => {
                    this.invalidateSelectionSummary()
                    this.expandAncestorSelection()
                    if (this.open && key(this.state) !== this.draftOrigin) {
                        this.close(false)
                        this.notice = this.labels.changed
                    }
                    this.reconcile()
                })
                this.$nextTick(() => {
                    if (destroyed) return
                    const element =
                        this.$root.querySelector('[data-tree-config]')
                    this.observer = new MutationObserver(() =>
                        this.configure(JSON.parse(element.dataset.treeConfig)),
                    )
                    this.observer.observe(element, {
                        attributes: true,
                        attributeFilter: ['data-tree-config'],
                    })
                })
            },

            destroy() {
                destroyed = true
                this.observer?.disconnect()
                this.$refs.removalDialog?.close()
                this.$refs.dialog?.close()
            },

            configure(configuration) {
                const next = signature(configuration)
                if (next === configurationKey) return
                configurationKey = next
                // A pending preview/Undo must never survive a changed permission or tree.
                this.close(false)
                this.removal = null
                this.undo = null
                this.savedUndo = null
                Object.assign(this, configuration)
                index = null
                this.expandAncestorSelection()
                this.reconcile()
            },

            reconcile() {
                if (
                    this.undo &&
                    key(this.values(this.undo.scope)) !== this.undo.after
                )
                    this.undo = null
                if (
                    this.removal &&
                    key(this.values(this.removal.scope)) !== this.removal.before
                )
                    this.removal = null
                if (
                    this.reviewId !== null &&
                    (!this.node(this.reviewId) ||
                        !(
                            this.isSelected(this.reviewId) ||
                            this.selectedBelowCount(this.reviewId)
                        ))
                )
                    this.reviewId = null
            },

            get nodeIndex() {
                if (index?.nodes !== this.nodes) {
                    const byId = new Map(
                        this.nodes.map((node) => [String(node.id), node]),
                    )
                    const children = new Map()
                    for (const node of this.nodes) {
                        const parent = String(node.ancestors.at(-1) ?? '')
                        if (!children.has(parent)) children.set(parent, [])
                        children.get(parent).push(node)
                    }
                    index = { nodes: this.nodes, byId, children }
                }
                return index
            },

            node(id) {
                return this.nodeIndex.byId.get(String(id))
            },
            children(id) {
                return this.nodeIndex.children.get(String(id ?? '')) ?? []
            },
            values(scope = 'state') {
                return Array.isArray(this[scope]) ? this[scope] : []
            },
            has(id, scope = 'state') {
                return this.values(scope).some(
                    (value) => String(value) === String(id),
                )
            },
            text(label, replacements = {}) {
                let value = this.labels[label] ?? label
                for (const [name, replacement] of Object.entries(replacements))
                    value = value.replaceAll(':' + name, replacement)
                return value
            },
            path(id) {
                const node = this.node(id)
                return node
                    ? node.ancestors.map((id) => this.node(id)).filter(Boolean)
                    : []
            },
            pathLabel(id) {
                return this.path(id)
                    .map((node) => node.name)
                    .join(' › ')
            },

            ancestorIds(id) {
                const node = this.node(id)
                if (!node || node.disabled) return null
                if (!this.includesAncestors) return []
                return node.ancestors.every(
                    (id) => this.node(id) && !this.node(id).disabled,
                )
                    ? node.ancestors
                    : null
            },

            draftBelow(id) {
                if (
                    draftSummary?.draft !== this.draft ||
                    draftSummary.nodes !== this.nodes
                ) {
                    const selected = new Set(this.draft.map(String))
                    const counts = new Map()
                    for (const node of this.nodes) {
                        if (selected.has(String(node.id)))
                            for (const ancestor of node.ancestors)
                                counts.set(
                                    ancestor,
                                    (counts.get(ancestor) ?? 0) + 1,
                                )
                    }
                    draftSummary = {
                        draft: this.draft,
                        nodes: this.nodes,
                        counts,
                    }
                }
                return draftSummary.counts.get(id) ?? 0
            },

            get pickerMatches() {
                const query = this.search.trim().toLocaleLowerCase()
                return query
                    ? this.nodes.filter((node) =>
                          (this.pathLabel(node.id) + ' ' + node.name)
                              .toLocaleLowerCase()
                              .includes(query),
                      )
                    : this.children(this.browseId)
            },
            get pickerRows() {
                return this.pickerMatches.slice(0, this.listLimit)
            },
            get reviewMatches() {
                const root = this.node(this.reviewId)
                const baseDepth = root?.ancestors.length ?? 0
                return this.nodes
                    .filter((node) => {
                        if (!(
                            this.isSelected(node.id) ||
                            this.selectedBelowCount(node.id)
                        ))
                            return false
                        if (
                            root &&
                            node.id !== root.id &&
                            !node.ancestors.includes(root.id)
                        )
                            return false
                        return node.ancestors.length - baseDepth <= REVIEW_DEPTH
                    })
                    .map((node) => ({
                        ...node,
                        level: node.ancestors.length - baseDepth,
                        deeper:
                            node.ancestors.length - baseDepth ===
                                REVIEW_DEPTH &&
                            this.selectedBelowCount(node.id) > 0,
                    }))
            },
            get reviewRows() {
                return this.reviewMatches.slice(0, this.reviewLimit)
            },
            get unavailableTerms() {
                return this.selectedTerms.filter((term) => !this.node(term.id))
            },

            browse(id) {
                this.browseId = id
                this.search = ''
                this.listLimit = PAGE
                this.$nextTick(() => {
                    if (!destroyed && this.open) {
                        this.$refs.search.focus()
                        if (this.$refs.options) this.$refs.options.scrollTop = 0
                        this.revealCurrentPath('pickerPath')
                    }
                })
            },
            review(id) {
                this.reviewId = id
                this.reviewLimit = PAGE
                this.$nextTick(() => {
                    if (!destroyed) {
                        this.$refs.reviewHeading.focus({ preventScroll: true })
                        if (this.$refs.reviewRows)
                            this.$refs.reviewRows.scrollTop = 0
                        this.revealCurrentPath('reviewPath')
                    }
                })
            },
            revealCurrentPath(ref) {
                const strip = this.$refs[ref]
                const current = strip?.querySelector(
                    '[aria-current="location"]',
                )
                if (!current) return
                const bounds = strip.getBoundingClientRect()
                const crumb = current.getBoundingClientRect()
                // Scroll this strip only; scrolling ancestors would move the form.
                if (crumb.right > bounds.right)
                    strip.scrollLeft += crumb.right - bounds.right
                else if (crumb.left < bounds.left)
                    strip.scrollLeft += crumb.left - bounds.left
            },

            trapFocus(event) {
                const controls = [
                    ...(
                        event.currentTarget ?? this.$refs.dialog
                    ).querySelectorAll(
                        'button:not(:disabled), input:not(:disabled), summary, [tabindex="0"]',
                    ),
                ].filter((element) => element.getClientRects().length)
                const first = controls[0]
                const last = controls.at(-1)
                if (
                    (event.shiftKey && document.activeElement === first) ||
                    (!event.shiftKey && document.activeElement === last)
                ) {
                    event.preventDefault()
                    ;(event.shiftKey ? last : first)?.focus()
                }
            },

            show() {
                if (this.blocked || this.open) return
                this.removal = null
                this.savedUndo = this.undo
                this.savedNotice = this.notice
                this.undo = null
                this.notice = ''
                this.draft = [...this.values()]
                this.draftOrigin = key(this.state)
                this.browseId = null
                this.search = ''
                this.listLimit = PAGE
                this.open = true
                this.$nextTick(() => {
                    if (destroyed || !this.open || this.blocked) return
                    this.$refs.dialog.showModal()
                    this.$refs.search.focus()
                    if (this.$refs.options) this.$refs.options.scrollTop = 0
                    this.revealCurrentPath('pickerPath')
                })
            },
            close(restore = true) {
                if (this.open) {
                    this.notice = this.savedNotice
                    this.savedNotice = ''
                    this.undo = this.savedUndo
                    this.savedUndo = null
                }
                this.open = false
                this.removal = null
                this.$refs.removalDialog?.close()
                this.$refs.dialog?.close()
                if (restore && !this.disabled) this.$refs.trigger.focus()
            },
            apply() {
                if (
                    this.blocked ||
                    !this.open ||
                    key(this.state) !== this.draftOrigin ||
                    this.removal
                )
                    return
                const before = [...this.values()]
                const after = [...this.draft]
                if (key(after) === key(before)) {
                    this.close()
                    return
                }
                this.close()
                this.state = after
                const count = before.filter(
                    (id) =>
                        !after.some((value) => String(value) === String(id)),
                ).length
                this.undo = count
                    ? { scope: 'state', before, after: key(after) }
                    : null
                this.notice = count
                    ? this.text('removed', { count })
                    : this.labels.applied
                this.reconcile()
            },

            affected(id, scope) {
                if (!this.has(id, scope)) return []
                const values = this.values(scope).filter(
                    (value) =>
                        String(value) === String(id) ||
                        (this.includesAncestors &&
                            this.node(value)?.ancestors.some(
                                (ancestor) => String(ancestor) === String(id),
                            )),
                )
                // Restricted/unavailable existing assignments must be preserved.
                return values.some(
                    (id) => !this.node(id) || this.node(id).disabled,
                )
                    ? []
                    : values
            },
            canRemove(id, scope = 'state') {
                return !this.blocked && this.affected(id, scope).length > 0
            },
            removeAction(id) {
                const count =
                    1 +
                    (this.includesAncestors ? this.selectedBelowCount(id) : 0)
                return this.text(
                    this.includesAncestors && this.selectedBelowCount(id)
                        ? 'remove_branch'
                        : 'remove',
                    { name: this.node(id)?.name, count },
                )
            },
            requestRemoval(id, scope = 'state') {
                if (!this.canRemove(id, scope)) return
                const ids = this.affected(id, scope)
                if (ids.length > 1)
                    this.previewRemoval(ids, scope, this.node(id).name)
                else this.removeValues(ids, scope)
            },
            clearDraft() {
                if (this.blocked || this.removal) return
                const ids = this.draft.filter(
                    (id) => this.node(id) && !this.node(id).disabled,
                )
                if (!ids.length) return
                // Keep ancestors required by restricted descendants.
                const required = new Set(
                    this.includesAncestors
                        ? this.draft
                              .filter((id) => !ids.includes(id))
                              .flatMap((id) => this.node(id)?.ancestors ?? [])
                              .map(String)
                        : [],
                )
                const removable = ids.filter((id) => !required.has(String(id)))
                if (removable.length)
                    this.previewRemoval(removable, 'draft', this.labels.clear)
            },
            previewRemoval(ids, scope, name) {
                this.removal = {
                    ids,
                    scope,
                    before: key(this.values(scope)),
                    name,
                }
                this.$nextTick(() => this.syncRemovalDialog())
            },
            syncRemovalDialog() {
                const dialog = this.$refs.removalDialog
                if (destroyed || !dialog) return
                if (!this.removal || this.blocked) {
                    dialog.close()
                    return
                }
                if (!dialog.open) dialog.showModal()
                dialog.querySelector('[data-removal-confirm]')?.focus()
            },
            cancelRemoval() {
                this.removal = null
                this.$refs.removalDialog?.close()
                this.focusSurvivor()
            },
            confirmRemoval() {
                const preview = this.removal
                if (
                    !preview ||
                    this.blocked ||
                    key(this.values(preview.scope)) !== preview.before ||
                    preview.ids.some(
                        (id) => !this.node(id) || this.node(id).disabled,
                    )
                ) {
                    this.cancelRemoval()
                    return
                }
                this.removeValues(preview.ids, preview.scope)
            },
            focusSurvivor() {
                if (this.disabled) return
                if (this.open) this.$refs.search.focus()
                else this.$refs.trigger.focus()
            },
            removeValues(ids, scope) {
                if (this.blocked) return
                const hadPreview = !!this.removal
                const before = [...this.values(scope)]
                const removed = new Set(ids.map(String))
                this[scope] = before.filter((id) => !removed.has(String(id)))
                this.undo = { scope, before, after: key(this[scope]) }
                this.removal = null
                this.$refs.removalDialog?.close()
                if (scope === 'state' || hadPreview) this.focusSurvivor()
                this.notice = this.text('removed', { count: ids.length })
                this.reconcile()
            },
            undoRemoval() {
                const undo = this.undo
                if (
                    this.blocked ||
                    !undo ||
                    key(this.values(undo.scope)) !== undo.after
                )
                    return
                const restored = undo.before.filter(
                    (id) => !this.has(id, undo.scope),
                )
                if (restored.some((id) => this.ancestorIds(id) === null)) {
                    this.undo = null
                    return
                }
                this.focusSurvivor()
                this[undo.scope] = [...undo.before]
                this.undo = null
                this.notice = this.labels.restored
            },
            toggleDraft(id) {
                if (
                    this.blocked ||
                    !this.node(id) ||
                    this.node(id).disabled ||
                    this.removal
                )
                    return
                if (this.has(id, 'draft')) this.requestRemoval(id, 'draft')
                else {
                    const ancestors = this.ancestorIds(id)
                    if (ancestors === null) return
                    const selected = new Set(this.draft.map(String))
                    this.draft = [
                        ...this.draft,
                        ...[...ancestors, id].filter(
                            (value) => !selected.has(String(value)),
                        ),
                    ]
                    this.undo = null
                    this.notice = ''
                }
            },
        }),
    )
}
