<div class="taxonomy-term-search" x-show="searchable">
    <x-filament::icon icon="heroicon-m-magnifying-glass" class="taxonomy-term-search-icon" />
    <input
        x-ref="search"
        type="search"
        class="taxonomy-term-search-input"
        x-model="search"
        placeholder="{{ $labels['search'] }}"
        aria-label="{{ $labels['search_label'] }}"
        aria-controls="{{ $id }}-tree"
        x-bind:disabled="disabled"
        x-on:keydown.arrow-down.prevent="focusFirstRow()"
        x-on:keydown.enter.prevent="chooseFirstMatch()"
    />
    <x-filament::loading-indicator class="taxonomy-term-search-spinner" x-show="searching" x-cloak />
</div>
