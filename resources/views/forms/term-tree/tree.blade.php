<x-filament::input.wrapper
    :disabled="$isDisabled()"
    :valid="! $errors->has($statePath)"
    class="taxonomy-term-panel"
>
    <div wire:ignore class="taxonomy-term-panel-body">
        <div class="taxonomy-term-toolbar" x-show="searchable || (! lazy && hasBranches)">
            @include('filament-taxonomies::forms.term-tree.search')

            <div class="taxonomy-term-tools" x-show="! lazy && hasBranches && ! search.trim()">
                <x-filament::link tag="button" color="gray" size="sm" x-on:click="expandAll()">
                    <span x-text="labels.expand_all"></span>
                </x-filament::link>
                <x-filament::link tag="button" color="gray" size="sm" x-on:click="collapseAll()">
                    <span x-text="labels.collapse_all"></span>
                </x-filament::link>
            </div>
        </div>

        <p class="taxonomy-term-help" x-show="selectAncestors && ! blocked" x-text="labels.ancestor_help"></p>

        @include('filament-taxonomies::forms.term-tree.rows')

        <div class="taxonomy-term-footer" x-show="multiple">
            <span x-text="text('selected', { count: selectedCount })"></span>
            <x-filament::link tag="button" color="gray" size="sm" x-show="canClear" x-on:click="clear()">
                <span x-text="labels.clear_label"></span>
            </x-filament::link>
        </div>
    </div>
</x-filament::input.wrapper>
