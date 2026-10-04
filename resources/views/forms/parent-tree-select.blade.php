@php
    $configuration = $getTreeConfiguration();
    $nodes = $configuration['nodes'];
@endphp
<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        class="taxonomy-parent-tree"
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('taxonomy-parent-tree', package: 'eyawiin/filament-taxonomies') }}"
        x-data="taxonomyParentTree({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$getStatePath()}')") }},
            ...@js($configuration),
        })"
        x-on:click.outside="close(false)"
        x-on:focusout="leave($event)"
        x-on:keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); close() }"
    >
        <span hidden data-tree-config="{{ json_encode($configuration) }}"></span>
        <x-filament::input.wrapper :disabled="$isDisabled()" :valid="! $errors->has($getStatePath())">
            <div class="taxonomy-selection-control" x-bind:data-multiple="multiple" x-bind:data-has-selection="selectedTerms.length > 0" x-on:click="toggle()">
                <div class="taxonomy-selection-tags" x-show="multiple && selectedTerms.length" x-cloak>
                    @include('filament-taxonomies::forms.selected-term-branch', ['ancestors' => [], 'treeId' => $getId()])
                    <template x-for="term in (multiple ? selectedTerms.filter(term => !nodes.some(node => String(node.id) === String(term.id))) : [])" x-bind:key="String(term.id)">
                        <x-filament::badge color="gray" class="taxonomy-selection-tag">
                            <span class="taxonomy-selection-tag-name" x-text="term.name"></span>
                            <x-slot name="deleteButton"
                                x-bind:aria-label="removeLabel(term.name)"
                                x-bind:disabled="blocked"
                                x-on:click.stop="removeTerm(term.id)"
                            ></x-slot>
                        </x-filament::badge>
                    </template>
                </div>
                <button
                    id="{{ $getId() }}"
                    x-ref="trigger"
                    type="button"
                    class="taxonomy-parent-trigger"
                    aria-haspopup="tree"
                    aria-controls="{{ $getId() }}-tree"
                    x-bind:aria-expanded="open"
                    x-bind:aria-disabled="blocked"
                    x-bind:data-readonly="readOnly"
                    x-bind:disabled="disabled"
                    @disabled($isDisabled())
                    x-on:click.stop="toggle()"
                    x-on:keydown.arrow-down.prevent="show(true)"
                >
                    <span x-text="multiple && selectedTerms.length ? selectionFeedback : selectedLabel" x-bind:class="{ 'fi-sr-only': multiple && selectedTerms.length }"></span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
                </button>
            </div>
        </x-filament::input.wrapper>
        <span class="taxonomy-selection-feedback" x-show="multiple" x-text="selectionFeedback" role="status" aria-live="polite"></span>
        <div class="taxonomy-parent-dropdown" x-ref="popup" x-show="open" x-bind:style="popupStyle" x-cloak>
            <div class="taxonomy-parent-search-bar">
                <x-filament::icon icon="heroicon-m-magnifying-glass" aria-hidden="true" />
                <input
                    x-ref="search"
                    type="search"
                    class="taxonomy-parent-search"
                    placeholder="{{ $configuration['labels']['search'] }}"
                    aria-label="{{ $configuration['labels']['search_label'] }}"
                    x-model="search"
                    x-on:keydown.arrow-down.prevent="enterTree()"
                />
            </div>
            <div
                id="{{ $getId() }}-tree"
                role="tree"
                x-bind:aria-describedby="includesAncestors ? '{{ $getId() }}-ancestor-help' : null"
                x-bind:aria-multiselectable="multiple ? 'true' : null"
                aria-label="{{ $configuration['labels']['tree_label'] }}"
                class="taxonomy-parent-options"
                x-on:keydown="navigate($event)"
            >
                <div role="treeitem" data-node-id="root"
                    aria-level="1" aria-posinset="1" aria-setsize="{{ 1 + count(array_filter($nodes, fn ($node) => $node['ancestors'] === [])) }}"
                    x-bind:aria-selected="multiple ? null : isSelected(null)"
                    x-bind:aria-checked="multiple ? isSelected(null) : null"
                    x-bind:tabindex="activeId === null ? 0 : -1"
                    class="taxonomy-parent-node taxonomy-parent-root"
                    x-on:focus.stop="activeId = null" x-on:click.stop="choose(null)">
                    {{ $configuration['labels']['root'] }}
                </div>
                @include('filament-taxonomies::forms.parent-tree-branch', ['ancestors' => [], 'treeId' => $getId()])
                <p class="taxonomy-parent-empty" x-show="visibleNodes.length === 0" role="status">{{ $configuration['labels']['empty'] }}</p>
            </div>
            <p id="{{ $getId() }}-ancestor-help" class="taxonomy-parent-help" x-show="includesAncestors" x-text="labels.ancestor_help" x-cloak></p>
        </div>
    </div>
</x-dynamic-component>
