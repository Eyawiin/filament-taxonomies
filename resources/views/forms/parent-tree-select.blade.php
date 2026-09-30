<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="taxonomy-parent-tree"
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('taxonomy-parent-tree', package: 'eyawiin/filament-taxonomies') }}"
        x-data="taxonomyParentTree({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            nodes: @js($nodes),
        })"
        x-on:click.outside="open = false"
        x-on:keydown.escape.stop.prevent="close()"
        x-on:keydown.tab="open = false"
    >
        <x-filament::input.wrapper :disabled="$isDisabled()" :valid="! $errors->has($getStatePath())">
            <button
                id="{{ $getId() }}"
                x-ref="trigger"
                type="button"
                class="taxonomy-parent-trigger"
                aria-haspopup="tree"
                aria-controls="{{ $getId() }}-tree"
                x-bind:aria-expanded="open"
                @disabled($isDisabled())
                x-on:click="toggle()"
                x-on:keydown.arrow-down.prevent="show()"
            >
                <span x-text="selectedLabel"></span>
                <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
            </button>
        </x-filament::input.wrapper>

        <div class="taxonomy-parent-dropdown" x-show="open" x-cloak>
            <input
                x-ref="search"
                type="search"
                class="taxonomy-parent-search"
                placeholder="Search terms…"
                aria-label="Search parent terms"
                x-model="search"
                x-on:keydown.arrow-down.prevent="focusFirst()"
            />
            <button type="button" class="taxonomy-parent-root" x-on:click="choose(null)">
                No parent (root term)
            </button>
            <div
                id="{{ $getId() }}-tree"
                role="tree"
                aria-label="Parent terms"
                class="taxonomy-parent-options"
                x-on:keydown="navigate($event)"
            >
                <template x-for="node in visibleNodes" :key="node.id">
                    <div
                        role="treeitem"
                        x-bind:data-node-id="node.id"
                        x-bind:aria-level="node.ancestors.length + 1"
                        x-bind:aria-expanded="node.hasChildren ? isExpanded(node.id) : null"
                        x-bind:aria-selected="String(state) === String(node.id)"
                        x-bind:aria-disabled="node.disabled"
                        x-bind:tabindex="activeId === node.id ? 0 : -1"
                        x-bind:style="{ paddingInlineStart: (8 + node.ancestors.length * 20) + 'px' }"
                        class="taxonomy-parent-node"
                        x-on:focus="activeId = node.id"
                        x-on:click="choose(node.id)"
                    >
                        <button
                            type="button"
                            tabindex="-1"
                            class="taxonomy-parent-toggle"
                            x-bind:class="{ 'is-leaf': !node.hasChildren }"
                            x-bind:aria-label="(isExpanded(node.id) ? 'Collapse ' : 'Expand ') + node.name"
                            x-bind:disabled="!node.hasChildren"
                            x-on:click.stop="toggleNode(node.id)"
                        >
                            <span x-bind:class="{ 'is-expanded': isExpanded(node.id) }">›</span>
                        </button>
                        <span class="taxonomy-parent-name" x-text="node.name"></span>
                        <span class="taxonomy-parent-reason" x-show="node.disabled" x-text="node.reason"></span>
                    </div>
                </template>
                <p class="taxonomy-parent-empty" x-show="visibleNodes.length === 0">No matching terms</p>
            </div>
        </div>
    </div>
</x-dynamic-component>
