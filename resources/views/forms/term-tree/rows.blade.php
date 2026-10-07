{{-- Rows are rendered by Alpine; wire:ignore on an ancestor keeps Livewire from morphing them. --}}
<div
    id="{{ $id }}-tree"
    role="tree"
    class="taxonomy-term-rows"
    aria-label="{{ $labels['tree_label'] }}"
    @if ($configuration['multiple']) aria-multiselectable="true" @endif
    x-bind:aria-busy="searching ? 'true' : 'false'"
    x-on:keydown="onKeydown($event)"
>
    <template x-for="row in view.rows" :key="row.key">
        <div role="none" class="taxonomy-term-item">
            <template x-if="row.type === 'node'">
                <div
                    role="treeitem"
                    class="taxonomy-term-row"
                    x-bind:data-row-key="row.key"
                    x-bind:aria-level="row.level"
                    x-bind:aria-posinset="row.posinset"
                    x-bind:aria-setsize="row.setsize"
                    x-bind:aria-expanded="row.expanded === null ? null : String(row.expanded)"
                    x-bind:aria-checked="multiple ? String(isSelected(row.id)) : null"
                    x-bind:aria-selected="multiple ? null : String(isSelected(row.id))"
                    x-bind:aria-disabled="canToggle(row.id) ? null : 'true'"
                    x-bind:aria-labelledby="'{{ $id }}-name-' + row.key"
                    x-bind:aria-describedby="'{{ $id }}-details-' + row.key"
                    x-bind:data-blocked="blocked ? 'true' : 'false'"
                    x-bind:data-unavailable="row.node.disabled ? 'true' : 'false'"
                    x-bind:tabindex="row.key === focusKey ? 0 : -1"
                    x-bind:style="{ '--taxonomy-level': row.level }"
                    x-on:click="activeKey = row.key; activate(row)"
                    x-on:focus="activeKey = row.key"
                >
                    <button
                        type="button"
                        tabindex="-1"
                        class="taxonomy-term-toggle"
                        x-bind:class="{ 'is-leaf': row.expanded === null }"
                        x-bind:aria-label="text(row.expanded ? 'collapse' : 'expand', { name: row.node.name })"
                        x-bind:disabled="row.expanded === null"
                        x-on:click.stop="activeKey = row.key; toggleNode(row.id)"
                    >
                        <x-filament::icon icon="heroicon-m-chevron-right" class="taxonomy-term-chevron" />
                    </button>
                    <span
                        class="taxonomy-term-check"
                        aria-hidden="true"
                        x-show="multiple"
                        x-bind:data-checked="isSelected(row.id) ? 'true' : 'false'"
                    ></span>
                    <span class="taxonomy-term-text">
                        <span class="taxonomy-term-name" dir="auto" x-bind:id="'{{ $id }}-name-' + row.key" x-text="row.node.name"></span>
                        <span x-bind:id="'{{ $id }}-details-' + row.key" class="taxonomy-term-details">
                            <span class="taxonomy-term-path" dir="auto" x-show="row.path" x-text="row.path"></span>
                            <span class="taxonomy-term-reason" x-show="row.node.disabled" x-text="row.node.reason || labels.denied"></span>
                            <span
                                class="taxonomy-term-summary"
                                x-show="multiple && !row.expanded && selectedBelow(row.id)"
                                x-text="text('selected_below', { count: selectedBelow(row.id) })"
                            ></span>
                        </span>
                    </span>
                    <x-filament::icon
                        icon="heroicon-m-check"
                        class="taxonomy-term-selected-icon"
                        x-show="! multiple && isSelected(row.id)"
                    />
                </div>
            </template>

            <template x-if="row.type === 'empty'">
                <div
                    role="treeitem"
                    class="taxonomy-term-row taxonomy-term-empty-option"
                    data-row-key="empty"
                    aria-level="1"
                    x-bind:aria-posinset="row.posinset"
                    x-bind:aria-setsize="row.setsize"
                    x-bind:aria-selected="String(values().length === 0)"
                    x-bind:aria-disabled="blocked ? 'true' : null"
                    x-bind:tabindex="row.key === focusKey ? 0 : -1"
                    style="--taxonomy-level: 1"
                    x-on:click="activeKey = row.key; activate(row)"
                    x-on:focus="activeKey = row.key"
                >
                    <span class="taxonomy-term-toggle is-leaf" aria-hidden="true"></span>
                    <span class="taxonomy-term-text">
                        <span class="taxonomy-term-name" x-text="emptyOption"></span>
                    </span>
                    <x-filament::icon
                        icon="heroicon-m-check"
                        class="taxonomy-term-selected-icon"
                        x-show="values().length === 0"
                    />
                </div>
            </template>

            <template x-if="row.type === 'more' || row.type === 'error'">
                <div
                    role="treeitem"
                    class="taxonomy-term-row taxonomy-term-action-row"
                    x-bind:data-row-key="row.key"
                    x-bind:aria-level="row.level"
                    x-bind:aria-busy="row.loading ? 'true' : 'false'"
                    x-bind:tabindex="row.key === focusKey ? 0 : -1"
                    x-bind:style="{ '--taxonomy-level': row.level }"
                    x-on:click="activeKey = row.key; activate(row)"
                    x-on:focus="activeKey = row.key"
                >
                    <span class="taxonomy-term-toggle is-leaf" aria-hidden="true"></span>
                    <x-filament::loading-indicator class="taxonomy-term-row-spinner" x-show="row.loading" />
                    <span
                        class="taxonomy-term-action"
                        x-text="row.loading ? labels.loading : (row.failed || row.type === 'error' ? labels.load_failed + ' ' + labels.retry : labels.show_more)"
                    ></span>
                </div>
            </template>

            <template x-if="row.type === 'loading'">
                <div
                    class="taxonomy-term-row taxonomy-term-loading"
                    role="none"
                    x-bind:style="{ '--taxonomy-level': row.level }"
                >
                    <span class="taxonomy-term-toggle is-leaf" aria-hidden="true"></span>
                    <x-filament::loading-indicator class="taxonomy-term-row-spinner" />
                    <span x-text="labels.loading"></span>
                </div>
            </template>
        </div>
    </template>

    <p class="taxonomy-term-status" role="status" x-show="view.status" x-text="view.status"></p>
</div>
