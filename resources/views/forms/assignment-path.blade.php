@php($pathQuery = $pathAction === 'browse' ? 'search.trim()' : 'false')
<nav class="fi-breadcrumbs taxonomy-assignment-path {{ $pathClass }}" x-ref="{{ $pathRef }}" aria-label="{{ $configuration['labels']['location'] }}">
    <ol class="fi-breadcrumbs-list">
        <li class="fi-breadcrumbs-item">
            <x-filament::link tag="button" color="gray" x-bind:aria-current="!({{ $pathQuery }}) && {{ $pathId }} === null ? 'location' : null" x-on:click="{{ $pathAction }}(null)">
                <span x-text="labels.{{ $pathRoot }}"></span>
            </x-filament::link>
        </li>
        <template x-for="crumb in ({{ $pathQuery }}) ? [] : [...path({{ $pathId }}), node({{ $pathId }})].filter(Boolean)" :key="crumb.id">
            <li class="fi-breadcrumbs-item">
                <x-filament::icon icon="heroicon-m-chevron-right" class="fi-breadcrumbs-item-separator fi-ltr" aria-hidden="true" />
                <x-filament::icon icon="heroicon-m-chevron-left" class="fi-breadcrumbs-item-separator fi-rtl" aria-hidden="true" />
                <x-filament::link tag="button" color="gray" x-bind:aria-current="crumb.id === {{ $pathId }} ? 'location' : null" x-on:click="{{ $pathAction }}(crumb.id)">
                    <span dir="auto" x-text="crumb.name"></span>
                </x-filament::link>
            </li>
        </template>
        @if ($pathAction === 'browse')
            <li class="fi-breadcrumbs-item" x-show="search.trim()">
                <x-filament::icon icon="heroicon-m-chevron-right" class="fi-breadcrumbs-item-separator fi-ltr" aria-hidden="true" />
                <x-filament::icon icon="heroicon-m-chevron-left" class="fi-breadcrumbs-item-separator fi-rtl" aria-hidden="true" />
                <span x-bind:aria-current="search.trim() ? 'location' : null" x-text="labels.search_results"></span>
            </li>
        @endif
    </ol>
</nav>
