<x-filament-panels::page>
    @php
        $hasTerms = $this->hasTerms();
        $lazy = $hasTerms && $this->isLazy();
        $search = $this->getTermSearch();
        $tree = $hasTerms ? $this->getTermTree() : null;
    @endphp

    @if (! $hasTerms)
        <p class="text-sm text-gray-600 dark:text-gray-400">
            {{ __('filament-taxonomies::taxonomies.manage_terms.empty') }}
        </p>
    @else
        <x-filament::section>
            <x-slot name="heading">
                {{ __('filament-taxonomies::taxonomies.manage_terms.section') }}
            </x-slot>

            <div
                x-load
                x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc(
                    'taxonomy-tree',
                    package: 'eyawiin/filament-taxonomies',
                ) }}"
                x-data="taxonomyTreeDrag({
                    dropTerm: (termId, targetId, placement) =>
                        $wire.dropTerm(termId, targetId, placement),
                    expandTerm: (termId) => $wire.expandTerm(termId),
                    lazy: @js($lazy),
                    moveFailed: @js(__('filament-taxonomies::taxonomies.manage_terms.move_failed')),
                })"
                x-bind:aria-busy="saving"
                x-on:taxonomy-tree-move-term="
                    drop($event.detail.termId, $event.detail.targetId, $event.detail.placement)
                "
                x-on:taxonomy-tree-expand-term="expand($event.detail.termId)"
            >
                <div class="taxonomy-tree-toolbar">
                    <x-filament::input.wrapper
                        prefix-icon="heroicon-m-magnifying-glass"
                        wire:target="termSearch"
                        class="taxonomy-tree-search"
                    >
                        <x-filament::input
                            type="search"
                            wire:model.live.debounce.400ms="termSearch"
                            :placeholder="__('filament-taxonomies::taxonomies.manage_terms.search')"
                            :aria-label="__('filament-taxonomies::taxonomies.manage_terms.search_label')"
                        />
                    </x-filament::input.wrapper>

                    <span
                        class="taxonomy-tree-status"
                        role="status"
                        x-text="saving ? @js(__('filament-taxonomies::taxonomies.manage_terms.saving')) : ''"
                    ></span>

                    @if ($search === '')
                        <div class="taxonomy-tree-tools">
                            @if ($lazy)
                                {{-- Expanding everything would load the whole tree, which is what lazy loading avoids. --}}
                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-arrows-pointing-in"
                                    wire:click="collapseAllTerms"
                                >
                                    {{ __('filament-taxonomies::taxonomies.manage_terms.collapse_all') }}
                                </x-filament::button>
                            @else
                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-arrows-pointing-out"
                                    x-on:click="$dispatch('taxonomy-tree-set-expanded', { expanded: true })"
                                >
                                    {{ __('filament-taxonomies::taxonomies.manage_terms.expand_all') }}
                                </x-filament::button>

                                <x-filament::button
                                    type="button"
                                    size="sm"
                                    color="gray"
                                    icon="heroicon-o-arrows-pointing-in"
                                    x-on:click="$dispatch('taxonomy-tree-set-expanded', { expanded: false })"
                                >
                                    {{ __('filament-taxonomies::taxonomies.manage_terms.collapse_all') }}
                                </x-filament::button>
                            @endif
                        </div>
                    @endif
                </div>

                @if ($search !== '')
                    <p class="taxonomy-tree-search-summary" role="status">
                        @if ($tree['total'] === 0)
                            {{ __('filament-taxonomies::taxonomies.manage_terms.no_matches', ['search' => $search]) }}
                        @elseif ($tree['total'] > \Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms::SEARCH_LIMIT)
                            {{ __('filament-taxonomies::taxonomies.manage_terms.matches_limited', ['shown' => \Eyawiin\FilamentTaxonomies\Resources\Taxonomies\Pages\ManageTaxonomyTerms::SEARCH_LIMIT, 'total' => $tree['total']]) }}
                        @else
                            {{ trans_choice('filament-taxonomies::taxonomies.manage_terms.matches', $tree['total'], ['count' => $tree['total']]) }}
                        @endif
                        {{ __('filament-taxonomies::taxonomies.manage_terms.search_reorder') }}
                    </p>
                @endif

                <p class="mb-3 text-sm text-danger-600" role="alert" x-show="error" x-text="error" x-cloak></p>

                @foreach (['placement', 'drop', 'move'] as $errorKey)
                    @error($errorKey)
                        <p class="mb-3 text-sm text-danger-600" role="alert">{{ $message }}</p>
                    @enderror
                @endforeach

                @if ($tree['nodes'] !== [])
                    <x-filament-taxonomies::taxonomy-tree
                        :nodes="$tree['nodes']"
                        :remaining="$tree['remaining']"
                        :searching="$search !== ''"
                    />
                @endif
            </div>
        </x-filament::section>
    @endif
</x-filament-panels::page>
