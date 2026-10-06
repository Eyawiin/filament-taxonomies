@php
    $configuration = $getTreeConfiguration();
@endphp
@if (($configuration['multiple'] ?? false))
    @include('filament-taxonomies::forms.assignment-picker')
@else
    @php
        $nodes = $configuration['nodes'];
    @endphp
    <x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
        <div
            class="taxonomy-parent-tree"
            wire:key="{{ $getId() }}-single-tree"
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
                    <span x-text="selectedLabel"></span>
                    <x-filament::icon icon="heroicon-m-chevron-down" class="h-4 w-4" />
                </button>
            </x-filament::input.wrapper>
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
                    aria-label="{{ $configuration['labels']['tree_label'] }}"
                    class="taxonomy-parent-options"
                    x-on:keydown="navigate($event)"
                >
                    <div role="treeitem" data-node-id="root"
                        aria-level="1" aria-posinset="1" aria-setsize="{{ 1 + count(array_filter($nodes, fn ($node) => $node['ancestors'] === [])) }}"
                        x-bind:aria-selected="isSelected(null)"
                        x-bind:tabindex="activeId === null ? 0 : -1"
                        class="taxonomy-parent-node taxonomy-parent-root"
                        x-on:focus.stop="activeId = null" x-on:click.stop="choose(null)">
                        {{ $configuration['labels']['root'] }}
                    </div>
                    @include('filament-taxonomies::forms.parent-tree-branch', ['ancestors' => [], 'treeId' => $getId()])
                    <p class="taxonomy-parent-empty" x-show="visibleNodes.length === 0" role="status">{{ $configuration['labels']['empty'] }}</p>
                </div>
            </div>
        </div>
    </x-dynamic-component>
@endif
