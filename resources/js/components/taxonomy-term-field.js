const ROOT = 'root'
const CHIP_LIMIT = 20
const SEARCH_DELAY = 250
// Fully loaded trees search in the browser; cap the rendered matches like the server does.
const SEARCH_LIMIT = 50

const keyOf = (id) => (id === null || id === undefined ? ROOT : String(id))
const same = (a, b) => String(a) === String(b)

/** Lowercase without accents, matching TaxonomyOutline::fold(). */
export function fold(value) {
    return String(value ?? '')
        .toLowerCase()
        .normalize('NFD')
        .replace(/\p{Mn}+/gu, '')
}

/**
 * The rules of TaxonomyOutline::search(): every word occurs in the name or an ancestor's
 * name, at least one in the name. Names containing more of the words come first, then
 * exact, prefix and word-start matches, then tree order.
 */
export function searchNodes(nodes, query, nameOf) {
    const words = fold(query).trim().split(/\s+/u).filter(Boolean)
    if (!words.length) return []
    const phrase = words.join(' ')
    const wordStart = new RegExp(
        '(?<![\\p{L}\\p{N}])' + words[0].replace(/[.*+?^${}()|[\]\\]/g, '\\$&'),
        'u',
    )
    const matches = []
    nodes.forEach((node, order) => {
        const name = fold(node.name)
        const missing = words.filter((word) => !name.includes(word))
        if (missing.length === words.length) return
        if (missing.length) {
            const path = node.ancestors.map((id) => fold(nameOf(id))).join(' ')
            if (missing.some((word) => !path.includes(word))) return
        }
        const rank =
            name === phrase
                ? 0
                : name.startsWith(phrase)
                  ? 1
                  : wordStart.test(name)
                    ? 2
                    : 3
        matches.push([missing.length, rank, order, node])
    })
    matches.sort((a, b) => a[0] - b[0] || a[1] - b[1] || a[2] - b[2])

    return matches.map((match) => match[3])
}

/**
 * Tree field for taxonomy terms: an inline checkbox tree or a searchable dropdown. Small
 * taxonomies arrive complete; large ones load branches and search results through `call`.
 */
export default function taxonomyTermField(config) {
    const call =
        config.call ??
        (() => Promise.reject(new Error('This field cannot load terms.')))
    const searchDelay = config.searchDelay ?? SEARCH_DELAY
    // Plain structures outside Alpine's proxies; `version` tells the templates to re-read them.
    const nodes = new Map()
    const branches = new Map()
    const names = new Map()
    const missing = new Set()
    let order = []
    let generation = 0
    let destroyed = false
    let searchTimer = null
    let searchRequest = 0
    let viewMemo = null
    let selectionMemo = null
    let configurationKey = ''

    const remember = (list) => {
        for (const node of list ?? []) nodes.set(String(node.id), node)
    }
    const rememberNames = (map) => {
        for (const [id, name] of Object.entries(map ?? {}))
            names.set(String(id), name)
    }
    const signature = (configuration) =>
        JSON.stringify([
            configuration.nodes,
            !!configuration.more,
            configuration.layout,
            !!configuration.lazy,
            !!configuration.multiple,
            !!configuration.selectAncestors,
            configuration.emptyOption ?? null,
            !!configuration.searchable,
            configuration.labels,
        ])
    const load = (configuration) => {
        nodes.clear()
        branches.clear()
        names.clear()
        missing.clear()
        remember(configuration.nodes)
        order = (configuration.nodes ?? []).map((node) => String(node.id))
        if (configuration.lazy) {
            branches.set(ROOT, {
                ids: order,
                more: !!configuration.more,
                status: 'loaded',
            })
        } else {
            branches.set(ROOT, { ids: [], more: false, status: 'loaded' })
            for (const node of configuration.nodes ?? []) {
                const parent = keyOf(node.ancestors.at(-1))
                if (!branches.has(parent))
                    branches.set(parent, {
                        ids: [],
                        more: false,
                        status: 'loaded',
                    })
                branches.get(parent).ids.push(String(node.id))
            }
        }
        remember(configuration.selection)
        rememberNames(configuration.names)
    }

    load(config)
    configurationKey = signature(config)

    return {
        state: config.state,
        layout: config.layout ?? 'tree',
        lazy: !!config.lazy,
        multiple: !!config.multiple,
        selectAncestors: !!config.selectAncestors,
        emptyOption: config.emptyOption ?? null,
        disabled: !!config.disabled,
        readOnly: !!config.readOnly,
        searchable: !!config.searchable,
        labels: config.labels ?? {},
        version: 0,
        expanded: config.lazy
            ? []
            : (config.nodes ?? [])
                  .filter((node) => node.hasChildren)
                  .map((node) => String(node.id)),
        search: '',
        results: null,
        searching: false,
        open: false,
        activeKey: null,
        showAllChips: false,
        popupStyle: {},
        observer: null,
        reposition: null,

        init() {
            this.settleSelection()
            this.$watch('search', () => this.runSearch())
            this.$watch('state', () => {
                this.settleSelection()
                if (this.open) this.$nextTick(() => this.position())
            })
            this.$nextTick(() => {
                if (destroyed) return
                const element = this.$root.querySelector('[data-tree-config]')
                if (element) {
                    this.observer = new MutationObserver(() =>
                        this.configure(JSON.parse(element.dataset.treeConfig)),
                    )
                    this.observer.observe(element, {
                        attributes: true,
                        attributeFilter: ['data-tree-config'],
                    })
                }
                if (this.layout === 'dropdown') {
                    this.reposition = () => {
                        if (this.open) this.position()
                    }
                    window.addEventListener('resize', this.reposition)
                    window.addEventListener('scroll', this.reposition, true)
                }
            })
        },

        destroy() {
            destroyed = true
            clearTimeout(searchTimer)
            this.observer?.disconnect()
            if (this.reposition) {
                window.removeEventListener('resize', this.reposition)
                window.removeEventListener('scroll', this.reposition, true)
            }
        },

        configure(configuration) {
            this.disabled = !!configuration.disabled
            this.readOnly = !!configuration.readOnly
            const next = signature(configuration)
            if (next === configurationKey) {
                remember(configuration.selection)
                rememberNames(configuration.names)
            } else {
                // Permissions or the tree changed: never keep loaded branches or results.
                const known = new Set(nodes.keys())
                configurationKey = next
                generation++
                load(configuration)
                this.layout = configuration.layout ?? 'tree'
                this.lazy = !!configuration.lazy
                this.multiple = !!configuration.multiple
                this.selectAncestors = !!configuration.selectAncestors
                this.emptyOption = configuration.emptyOption ?? null
                this.searchable = !!configuration.searchable
                this.labels = configuration.labels ?? {}
                this.expanded = this.lazy
                    ? []
                    : (configuration.nodes ?? [])
                          .filter(
                              (node) =>
                                  node.hasChildren &&
                                  (!known.has(String(node.id)) ||
                                      this.isExpanded(node.id)),
                          )
                          .map((node) => String(node.id))
                this.results = null
                if (this.search.trim()) this.runSearch()
            }
            this.version++
            if (this.blocked) this.close(false)
            this.settleSelection()
        },

        get blocked() {
            return this.disabled || this.readOnly
        },

        text(label, replacements = {}) {
            let value = this.labels[label] ?? ''
            for (const [name, replacement] of Object.entries(replacements))
                value = value.replaceAll(':' + name, replacement)
            return value
        },

        node(id) {
            return id === null || id === undefined
                ? null
                : (nodes.get(String(id)) ?? null)
        },
        nameOf(id) {
            return nodes.get(String(id))?.name ?? names.get(String(id)) ?? ''
        },
        pathLabel(node) {
            return node
                ? node.ancestors
                      .map((id) => this.nameOf(id))
                      .filter(Boolean)
                      .join(' › ')
                : ''
        },

        // Selection ----------------------------------------------------------

        values() {
            if (Array.isArray(this.state)) return this.state
            return this.state === null ||
                this.state === undefined ||
                this.state === ''
                ? []
                : [this.state]
        },
        isSelected(id) {
            return this.values().some((value) => same(value, id))
        },
        get selectedCount() {
            return this.values().length
        },
        isRemovable(value) {
            const node = this.node(value)
            return !!node && !node.disabled
        },
        /** Values removed with a term; null when that would drop a restricted or unavailable assignment. */
        removalFor(id) {
            const ids = [id]
            if (this.selectAncestors)
                for (const value of this.values())
                    if (
                        this.node(value)?.ancestors.some((ancestor) =>
                            same(ancestor, id),
                        )
                    )
                        ids.push(value)
            return ids.every((value) => this.isRemovable(value)) ? ids : null
        },
        canToggle(id) {
            const node = this.node(id)
            if (this.blocked || !node || node.disabled) return false
            return (
                !this.multiple ||
                !this.isSelected(id) ||
                this.removalFor(node.id) !== null
            )
        },
        toggle(id) {
            if (!this.canToggle(id)) return
            const node = this.node(id)
            if (!this.multiple) {
                this.state = node.id
                if (this.layout === 'dropdown') this.close()
                return
            }
            if (this.isSelected(node.id)) {
                const removed = new Set(this.removalFor(node.id).map(String))
                this.state = this.values().filter(
                    (value) => !removed.has(String(value)),
                )
                return
            }
            const selected = new Set(this.values().map(String))
            const additions = [
                ...(this.selectAncestors ? node.ancestors : []),
                node.id,
            ].filter((value) => !selected.has(String(value)))
            this.state = [...this.values(), ...additions]
        },
        chooseEmpty() {
            if (this.blocked) return
            this.state = this.multiple ? [] : null
            if (this.layout === 'dropdown') this.close()
        },
        /** What clearing leaves: restricted and unavailable assignments, and the ancestors they require. */
        clearedValues() {
            const kept = this.values().filter(
                (value) => !this.isRemovable(value),
            )
            const required = new Set(
                this.selectAncestors
                    ? kept
                          .flatMap((value) => this.node(value)?.ancestors ?? [])
                          .map(String)
                    : [],
            )
            return this.values().filter(
                (value) =>
                    !this.isRemovable(value) || required.has(String(value)),
            )
        },
        clear() {
            if (this.blocked) return
            if (!this.multiple) {
                this.chooseEmpty()
                return
            }
            this.state = this.clearedValues()
        },
        get canClear() {
            return (
                !this.blocked &&
                this.clearedValues().length < this.values().length
            )
        },

        /** Ancestor closure, and details for IDs this browser has not seen yet. */
        settleSelection() {
            if (
                !this.blocked &&
                this.selectAncestors &&
                Array.isArray(this.state)
            ) {
                const values = [...this.state]
                const selected = new Set(values.map(String))
                for (const value of this.state) {
                    const node = this.node(value)
                    if (!node || node.disabled) continue
                    for (const ancestor of node.ancestors)
                        if (!selected.has(String(ancestor))) {
                            selected.add(String(ancestor))
                            values.push(ancestor)
                        }
                }
                // A no-op must not write back into the entangled state.
                if (values.length !== this.state.length) this.state = values
            }
            const unknown = this.values()
                .map(String)
                .filter((id) => !nodes.has(id) && !missing.has(id))
            if (!unknown.length) return
            if (!this.lazy) {
                unknown.forEach((id) => missing.add(id))
                this.version++
                return
            }
            unknown.forEach((id) => missing.add(id))
            const current = generation
            call('getTreeSelection', { ids: unknown })
                .then((response) => {
                    if (destroyed || current !== generation) return
                    remember(response?.nodes)
                    rememberNames(response?.names)
                    this.version++
                    this.settleSelection()
                })
                .catch(() => {})
        },

        selectedBelow(id) {
            const state = this.state
            const version = this.version
            if (
                !selectionMemo ||
                selectionMemo.state !== state ||
                selectionMemo.version !== version
            ) {
                const counts = new Map()
                for (const value of this.values())
                    for (const ancestor of this.node(value)?.ancestors ?? [])
                        counts.set(
                            String(ancestor),
                            (counts.get(String(ancestor)) ?? 0) + 1,
                        )
                selectionMemo = { state, version, counts }
            }
            return selectionMemo.counts.get(String(id)) ?? 0
        },

        /** Selected terms for the dropdown; with ancestor selection, only the deepest of each path. */
        get chips() {
            this.version
            let values = this.values()
            if (this.selectAncestors) {
                const covered = new Set(
                    values
                        .flatMap((value) => this.node(value)?.ancestors ?? [])
                        .map(String),
                )
                values = values.filter((value) => !covered.has(String(value)))
            }
            return values.map((value) => {
                const node = this.node(value)
                return node
                    ? {
                          key: String(value),
                          id: node.id,
                          name: node.name,
                          path: this.pathLabel(node),
                          removable:
                              !this.blocked &&
                              this.removalFor(node.id) !== null,
                      }
                    : {
                          key: String(value),
                          id: value,
                          name: this.labels.unavailable ?? '',
                          path: '',
                          removable: false,
                      }
            })
        },
        get visibleChips() {
            const chips = this.chips
            return this.showAllChips ? chips : chips.slice(0, CHIP_LIMIT)
        },
        removeChip(id) {
            if (!this.isSelected(id) || !this.canToggle(id)) return
            // Keep focus in the field when the chip and its button disappear.
            this.$refs.trigger?.focus()
            this.toggle(id)
        },

        get singleNode() {
            this.version
            const values = this.values()
            return values.length === 1 ? this.node(values[0]) : null
        },
        get singleLabel() {
            const values = this.values()
            if (!values.length)
                return this.labels.placeholder_single ?? this.emptyOption ?? ''
            return this.singleNode?.name ?? this.labels.unavailable ?? ''
        },
        get singlePath() {
            return this.pathLabel(this.singleNode)
        },
        /** Nothing chosen yet; the parent field's empty choice is a real option instead. */
        get isPlaceholder() {
            return (
                this.selectedCount === 0 &&
                (this.multiple || this.labels.placeholder_single !== undefined)
            )
        },
        get triggerLabel() {
            if (!this.multiple) return this.singleLabel
            return this.selectedCount
                ? this.text('selected', { count: this.selectedCount })
                : (this.labels.placeholder ?? '')
        },

        // Tree rows --------------------------------------------------------

        isExpanded(id) {
            return this.expanded.some((value) => same(value, id))
        },
        get hasBranches() {
            this.version
            return [...nodes.values()].some((node) => node.hasChildren)
        },
        toggleNode(id) {
            const node = this.node(id)
            if (!node?.hasChildren || this.search.trim()) return
            if (this.isExpanded(id)) {
                this.expanded = this.expanded.filter(
                    (value) => !same(value, id),
                )
                return
            }
            this.expanded = [...this.expanded, String(node.id)]
            if (this.lazy && !branches.has(String(node.id)))
                this.fetchChildren(node.id)
        },
        expandAll() {
            if (this.lazy) return
            this.expanded = [...nodes.values()]
                .filter((node) => node.hasChildren)
                .map((node) => String(node.id))
        },
        collapseAll() {
            this.expanded = []
        },
        async fetchChildren(parent, offset = 0) {
            const key = keyOf(parent)
            const current = branches.get(key)
            if (current?.status === 'loading') return
            const ids = offset ? (current?.ids ?? []) : []
            branches.set(key, {
                ids,
                more: current?.more ?? false,
                status: 'loading',
            })
            this.version++
            const requested = generation
            try {
                const response = await call('getTreeChildren', {
                    parent,
                    offset,
                })
                if (destroyed || requested !== generation) return
                remember(response?.nodes)
                branches.set(key, {
                    ids: [
                        ...ids,
                        ...(response?.nodes ?? []).map((node) =>
                            String(node.id),
                        ),
                    ],
                    more: !!response?.more,
                    status: 'loaded',
                })
            } catch {
                if (destroyed || requested !== generation) return
                branches.set(key, {
                    ids,
                    more: current?.more ?? false,
                    status: 'error',
                })
            }
            this.version++
        },
        loadMore(key) {
            const branch = branches.get(key)
            if (branch)
                this.fetchChildren(
                    key === ROOT ? null : Number(key),
                    branch.ids.length,
                )
        },

        runSearch() {
            const query = this.search.trim()
            clearTimeout(searchTimer)
            const request = ++searchRequest
            this.activeKey = null
            if (!query || !this.lazy) {
                this.results = null
                this.searching = false
                return
            }
            this.searching = true
            const requested = generation
            searchTimer = setTimeout(async () => {
                let results
                try {
                    const response = await call('searchTree', { search: query })
                    remember(response?.nodes)
                    rememberNames(response?.names)
                    results = {
                        query,
                        ids: (response?.nodes ?? []).map((node) =>
                            String(node.id),
                        ),
                        total: response?.total ?? 0,
                        failed: false,
                    }
                } catch {
                    results = { query, ids: [], total: 0, failed: true }
                }
                if (
                    destroyed ||
                    request !== searchRequest ||
                    requested !== generation
                )
                    return
                this.results = results
                this.searching = false
                this.version++
            }, searchDelay)
        },

        /** Rows to render, memoized until the tree, expansion or search changes. */
        get view() {
            const version = this.version
            const expanded = this.expanded
            const query = this.search.trim()
            const results = this.results
            if (
                viewMemo &&
                viewMemo.version === version &&
                viewMemo.expanded === expanded &&
                viewMemo.query === query &&
                viewMemo.results === results
            )
                return viewMemo.view
            const view = query ? this.searchView(query) : this.treeView()
            view.keys = new Set(view.rows.map((row) => row.key))
            viewMemo = { version, expanded, query, results, view }
            return view
        },
        treeView() {
            const rows = []
            const open = new Set(this.expanded.map(String))
            const offset = this.emptyOption === null ? 0 : 1
            const root = branches.get(ROOT)
            if (offset)
                rows.push({
                    key: 'empty',
                    type: 'empty',
                    level: 1,
                    posinset: 1,
                    setsize: root?.more ? -1 : (root?.ids.length ?? 0) + 1,
                })
            const walk = (key, level) => {
                const branch = branches.get(key)
                if (!branch) return
                const shift = level === 1 ? offset : 0
                const setsize = branch.more ? -1 : branch.ids.length + shift
                branch.ids.forEach((id, index) => {
                    const node = nodes.get(id)
                    if (!node) return
                    const isOpen = node.hasChildren && open.has(id)
                    rows.push({
                        key: 'n' + id,
                        type: 'node',
                        id: node.id,
                        node,
                        level,
                        posinset: index + 1 + shift,
                        setsize,
                        expanded: node.hasChildren ? isOpen : null,
                        path: '',
                    })
                    if (!isOpen) return
                    const children = branches.get(id)
                    if (
                        !children ||
                        (children.status === 'loading' && !children.ids.length)
                    )
                        rows.push({
                            key: 'l' + id,
                            type: 'loading',
                            level: level + 1,
                        })
                    else if (
                        children.status === 'error' &&
                        !children.ids.length
                    )
                        rows.push({
                            key: 'e' + id,
                            type: 'error',
                            parent: id,
                            level: level + 1,
                        })
                    else walk(id, level + 1)
                })
                if (
                    branch.more ||
                    (branch.status !== 'loaded' && branch.ids.length)
                )
                    rows.push({
                        key: 'm' + key,
                        type: 'more',
                        parent: key,
                        level,
                        loading: branch.status === 'loading',
                        failed: branch.status === 'error',
                    })
            }
            walk(ROOT, 1)
            return {
                rows,
                status: rows.some((row) => row.type === 'node')
                    ? ''
                    : (this.labels.empty ?? ''),
            }
        },
        searchView(query) {
            let matches
            let total
            if (this.lazy) {
                if (!this.results || this.results.query !== query)
                    return { rows: [], status: this.labels.searching ?? '' }
                if (this.results.failed)
                    return { rows: [], status: this.labels.load_failed ?? '' }
                matches = this.results.ids
                    .map((id) => nodes.get(id))
                    .filter(Boolean)
                total = this.results.total
            } else {
                const found = searchNodes(
                    order.map((id) => nodes.get(id)).filter(Boolean),
                    query,
                    (id) => this.nameOf(id),
                )
                matches = found.slice(0, SEARCH_LIMIT)
                total = found.length
            }
            const rows = matches.map((node, index) => ({
                key: 's' + node.id,
                type: 'node',
                id: node.id,
                node,
                level: 1,
                posinset: index + 1,
                setsize: matches.length,
                expanded: null,
                path: this.pathLabel(node),
            }))
            return {
                rows,
                status: !rows.length
                    ? (this.labels.no_results ?? '')
                    : total > rows.length
                      ? this.text('more_results', {
                            shown: rows.length,
                            total,
                        })
                      : '',
            }
        },

        // Keyboard and focus ------------------------------------------------

        /** The row that takes Tab focus: the active one while it is shown, otherwise the first. */
        get focusKey() {
            const view = this.view
            return view.keys.has(this.activeKey)
                ? this.activeKey
                : (view.rows[0]?.key ?? null)
        },
        focusRow(key) {
            if (key === null || key === undefined) return
            this.activeKey = key
            const find = () =>
                this.$root.querySelector('[data-row-key="' + key + '"]')
            // Focus at once when the row exists: $nextTick waits for a timer, and a quick
            // Enter would otherwise still reach the search box.
            const row = find()
            if (row) row.focus()
            else
                this.$nextTick(() => {
                    if (!destroyed) find()?.focus()
                })
        },
        focusFirstRow() {
            this.focusRow(this.view.rows[0]?.key)
        },
        /** Enter in the search box chooses the first match, so typing a name and Enter is enough. */
        chooseFirstMatch() {
            if (!this.search.trim()) return
            const row = this.view.rows.find(
                (candidate) => candidate.type === 'node',
            )
            if (row) this.toggle(row.id)
        },
        activate(row) {
            if (row.type === 'node') this.toggle(row.id)
            else if (row.type === 'empty') this.chooseEmpty()
            else if (row.type === 'more') this.loadMore(row.parent)
            else if (row.type === 'error')
                this.fetchChildren(
                    row.parent === ROOT ? null : Number(row.parent),
                )
        },
        onKeydown(event) {
            const keys = [
                'ArrowDown',
                'ArrowUp',
                'ArrowRight',
                'ArrowLeft',
                'Home',
                'End',
                'Enter',
                ' ',
            ]
            if (!keys.includes(event.key) || event.target.matches('input'))
                return
            const rows = this.view.rows
            const index = Math.max(
                0,
                rows.findIndex((row) => row.key === this.focusKey),
            )
            const row = rows[index]
            if (!row) return
            event.preventDefault()
            event.stopPropagation()
            const rtl =
                globalThis.getComputedStyle?.(this.$root)?.direction === 'rtl'
            const key =
                rtl && event.key === 'ArrowRight'
                    ? 'ArrowLeft'
                    : rtl && event.key === 'ArrowLeft'
                      ? 'ArrowRight'
                      : event.key
            switch (key) {
                case 'ArrowDown':
                    this.focusRow(
                        rows[Math.min(index + 1, rows.length - 1)].key,
                    )
                    break
                case 'ArrowUp':
                    if (index === 0 && this.searchable)
                        this.$refs.search?.focus()
                    else this.focusRow(rows[Math.max(index - 1, 0)].key)
                    break
                case 'Home':
                    this.focusRow(rows[0].key)
                    break
                case 'End':
                    this.focusRow(rows.at(-1).key)
                    break
                case 'ArrowRight':
                    if (row.expanded === false) this.toggleNode(row.id)
                    else if (row.expanded && rows[index + 1]?.level > row.level)
                        this.focusRow(rows[index + 1].key)
                    break
                case 'ArrowLeft':
                    if (row.expanded) this.toggleNode(row.id)
                    else if (row.level > 1)
                        this.focusRow(
                            rows
                                .slice(0, index)
                                .reverse()
                                .find(
                                    (candidate) => candidate.level < row.level,
                                )?.key,
                        )
                    break
                default:
                    this.activate(row)
            }
        },

        // Dropdown ----------------------------------------------------------

        toggleOpen() {
            if (this.open) this.close()
            else this.show()
        },
        show(enterTree = false) {
            if (this.blocked || this.open) return
            this.search = ''
            const selected = this.singleNode
            if (!this.lazy && selected)
                this.expanded = [
                    ...new Set([
                        ...this.expanded,
                        ...selected.ancestors.map(String),
                    ]),
                ]
            this.activeKey = selected
                ? 'n' + selected.id
                : this.emptyOption !== null && !this.multiple
                  ? 'empty'
                  : null
            this.open = true
            this.$nextTick(() => {
                if (destroyed || !this.open) return
                this.position()
                if (enterTree || !this.searchable) this.focusRow(this.focusKey)
                else this.$refs.search?.focus()
            })
        },
        close(restore = true) {
            if (!this.open) return
            this.open = false
            this.search = ''
            if (restore && !this.disabled) this.$refs.trigger?.focus()
        },
        leave(event) {
            if (this.open && !this.$root.contains(event.relatedTarget))
                this.close(false)
        },
        /** Opens below the trigger, over any chips, or above it when the viewport has more room there. */
        position() {
            const anchor = (
                this.$refs.trigger ?? this.$root
            ).getBoundingClientRect()
            const root = this.$root.getBoundingClientRect()
            const below = window.innerHeight - anchor.bottom - 12
            const above = anchor.top - 12
            const up = below < 240 && above > below
            this.popupStyle = {
                top: up ? 'auto' : anchor.bottom - root.top + 4 + 'px',
                bottom: up ? root.bottom - anchor.top + 4 + 'px' : 'auto',
                maxHeight:
                    Math.max(160, Math.min(448, up ? above : below)) + 'px',
            }
        },
    }
}
