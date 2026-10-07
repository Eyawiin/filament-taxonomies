<x-filament::input.wrapper
    :disabled="$isDisabled()"
    :valid="! $errors->has($statePath)"
    class="taxonomy-term-control"
    x-ref="control"
>
    <div wire:ignore class="taxonomy-term-control-body">
        <button
            id="{{ $id }}"
            x-ref="trigger"
            type="button"
            class="taxonomy-term-trigger"
            aria-haspopup="tree"
            aria-controls="{{ $id }}-popup"
            x-bind:aria-expanded="String(open)"
            x-bind:aria-disabled="String(blocked)"
            x-bind:data-readonly="String(readOnly)"
            x-bind:disabled="disabled"
            @disabled($isDisabled())
            x-on:click.stop="toggleOpen()"
            x-on:keydown.arrow-down.prevent="show(true)"
        >
            <span class="taxonomy-term-trigger-label">
                <span class="taxonomy-term-trigger-path" dir="auto" x-show="! multiple && singlePath" x-text="singlePath + ' ›'"></span>
                <span
                    dir="auto"
                    x-bind:class="{ 'taxonomy-term-placeholder': isPlaceholder }"
                    x-text="triggerLabel"
                ></span>
            </span>
            <x-filament::icon icon="heroicon-m-chevron-down" class="taxonomy-term-trigger-icon" />
        </button>

        @if ($configuration['multiple'])
            <div class="taxonomy-term-chips" x-show="chips.length" x-cloak>
                <template x-for="chip in visibleChips" :key="chip.key">
                    <x-filament::badge color="gray" class="taxonomy-term-chip">
                        <span class="taxonomy-term-chip-path" dir="auto" x-show="chip.path" x-text="chip.path + ' ›'"></span>
                        <span class="taxonomy-term-chip-name" dir="auto" x-text="chip.name"></span>

                        <x-slot
                            name="deleteButton"
                            x-show="chip.removable"
                            x-on:click.stop="removeChip(chip.id)"
                            x-bind:aria-label="text('remove', { name: chip.name })"
                            x-bind:title="text('remove', { name: chip.name })"
                        ></x-slot>
                    </x-filament::badge>
                </template>
                <x-filament::link
                    tag="button"
                    color="gray"
                    size="sm"
                    x-show="showAllChips || chips.length > visibleChips.length"
                    x-on:click.stop="showAllChips = ! showAllChips"
                >
                    <span x-text="showAllChips ? labels.show_less : text('show_all', { count: chips.length })"></span>
                </x-filament::link>
            </div>
        @endif
    </div>
</x-filament::input.wrapper>

<div
    wire:ignore
    id="{{ $id }}-popup"
    class="taxonomy-term-popup"
    x-show="open"
    x-cloak
    x-bind:style="popupStyle"
>
    @include('filament-taxonomies::forms.term-tree.search')

    <p class="taxonomy-term-help" x-show="selectAncestors" x-text="labels.ancestor_help"></p>

    @include('filament-taxonomies::forms.term-tree.rows')

    @if ($configuration['multiple'])
        <div class="taxonomy-term-footer">
            <span x-text="text('selected', { count: selectedCount })"></span>
            <x-filament::link tag="button" color="gray" size="sm" x-show="canClear" x-on:click="clear()">
                <span x-text="labels.clear_label"></span>
            </x-filament::link>
            <x-filament::button color="gray" size="sm" class="taxonomy-term-done" x-on:click="close()">
                <span x-text="labels.done"></span>
            </x-filament::button>
        </div>
    @endif
</div>
