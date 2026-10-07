@props([
    'nodes',
    'remaining' => 0,
    'parentId' => null,
    'searching' => false,
])

<ul class="m-0 list-none space-y-2 p-0">
    @foreach ($nodes as $node)
        @php
            $term = $node['term'];
            $termId = (int) $term->getKey();
            $hasChildren = $node['hasChildren'];
            // Null: every branch is rendered and the browser expands them. Otherwise the server decides.
            $managed = $node['expanded'] !== null;
            $expanded = (bool) $node['expanded'];
            $updateAuthorization = \Eyawiin\FilamentTaxonomies\Authorization\TaxonomyTermAuthorization::update($term);
            $canMove = $updateAuthorization->allowed() && ! $searching;
            $moveHint = $searching
                ? __('filament-taxonomies::taxonomies.manage_terms.search_reorder')
                : __('filament-taxonomies::taxonomies.manage_terms.move_denied');

            $storageKey = sprintf(
                'eyawiin-filament-taxonomies:tree:%s:term:%s:expanded',
                $term->taxonomy_id,
                $termId,
            );
        @endphp

        <li
            {{-- A server-side toggle replaces the row, so drag state and attributes start fresh. --}}
            wire:key="taxonomy-term-{{ $termId }}{{ $managed ? ($expanded ? '-open' : '-closed') : '' }}"
            data-taxonomy-term
            data-can-move="{{ $canMove ? 'true' : 'false' }}"
            data-term-id="{{ $termId }}"
            data-has-children="{{ $hasChildren ? 'true' : 'false' }}"
            @unless ($managed)
                x-data="{ expanded: $persist(true).as(@js($storageKey)) }"
                x-on:taxonomy-tree-set-expanded.window="expanded = $event.detail.expanded"
                x-on:taxonomy-tree-expand-term.window="
                    if ($event.detail.termId === @js($termId)) {
                        expanded = true
                    }
                "
            @endunless
        >
            <div
                wire:ignore.self
                data-taxonomy-row
                data-has-children="{{ $hasChildren ? 'true' : 'false' }}"
                @if ($managed)
                    data-expanded="{{ $expanded ? 'true' : 'false' }}"
                @else
                    x-bind:data-expanded="expanded ? 'true' : 'false'"
                @endif
                draggable="{{ $canMove ? 'true' : 'false' }}"
                class="
                    relative flex items-center gap-3 rounded-xl border border-gray-200
                    bg-white px-4 py-3 shadow-sm
                    dark:border-white/10 dark:bg-gray-900
                    data-[dragging=true]:opacity-50
                    data-[drop-placement=inside]:outline-2
                    data-[drop-placement=inside]:outline-primary-500
                    data-[drop-invalid=true]:outline-2
                    data-[drop-invalid=true]:outline-danger-500
                    data-[drop-disabled=true]:opacity-50
                    before:pointer-events-none before:absolute before:inset-x-0
                    before:-top-1 before:h-0.5 before:bg-primary-500 before:opacity-0
                    data-[drop-placement=before]:before:opacity-100
                    after:pointer-events-none after:absolute after:inset-x-0
                    after:-bottom-1 after:h-0.5 after:bg-primary-500 after:opacity-0
                    data-[drop-placement=after]:after:opacity-100
                "
            >
                <div
                    @if ($canMove) data-taxonomy-drag-handle @endif
                    class="flex h-8 w-8 shrink-0 touch-none select-none items-center justify-center text-gray-400 dark:text-gray-500 {{ $canMove ? 'cursor-grab active:cursor-grabbing' : 'cursor-not-allowed opacity-40' }}"
                    title="{{ $canMove ? __('filament-taxonomies::taxonomies.manage_terms.drag') : $moveHint }}"
                >
                    <x-filament::icon
                        icon="heroicon-o-bars-3"
                        class="h-5 w-5"
                    />
                </div>

                <div class="flex shrink-0 items-center">
                    @if (! $hasChildren || ($searching && ! $expanded))
                        <div
                            wire:key="taxonomy-term-placeholder-{{ $termId }}"
                            class="invisible pointer-events-none"
                            aria-hidden="true"
                        >
                            <x-filament::icon-button
                                icon="heroicon-o-chevron-right"
                                :label="__('filament-taxonomies::taxonomies.manage_terms.toggle_children')"
                                size="sm"
                                color="gray"
                                tabindex="-1"
                            />
                        </div>
                    @elseif ($searching)
                        {{-- Matches stay visible while searching, so their branches cannot be collapsed. --}}
                        <div class="rotate-90 opacity-50" aria-hidden="true">
                            <x-filament::icon-button
                                icon="heroicon-o-chevron-right"
                                :label="__('filament-taxonomies::taxonomies.manage_terms.toggle_children')"
                                size="sm"
                                color="gray"
                                tabindex="-1"
                                disabled
                            />
                        </div>
                    @elseif ($managed)
                        <div class="{{ $expanded ? 'rotate-90' : 'rotate-0' }}">
                            <x-filament::icon-button
                                icon="heroicon-o-chevron-right"
                                :label="__('filament-taxonomies::taxonomies.manage_terms.toggle_children')"
                                size="sm"
                                color="gray"
                                wire:click="toggleTerm({{ $termId }})"
                                :aria-expanded="$expanded ? 'true' : 'false'"
                            />
                        </div>
                    @else
                        <div
                            wire:key="taxonomy-term-toggle-{{ $termId }}"
                            wire:ignore.self
                            class="transition-transform duration-100 ease-out"
                            x-bind:class="expanded ? 'rotate-90' : 'rotate-0'"
                        >
                            <x-filament::icon-button
                                icon="heroicon-o-chevron-right"
                                :label="__('filament-taxonomies::taxonomies.manage_terms.toggle_children')"
                                size="sm"
                                color="gray"
                                x-on:click="expanded = ! expanded"
                                x-bind:aria-expanded="expanded"
                            />
                        </div>
                    @endif
                </div>

                <div class="flex min-w-0 flex-1 items-center gap-3">
                    <x-filament::icon
                        icon="heroicon-o-tag"
                        class="h-5 w-5 shrink-0 text-gray-400"
                    />

                    <div class="min-w-0">
                        <div @class([
                            'truncate text-sm font-medium',
                            'text-gray-950 dark:text-white' => $node['match'] !== false,
                            'text-gray-500 dark:text-gray-400' => $node['match'] === false,
                        ])>
                            {{ $term->name }}
                        </div>

                        <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $term->slug }}
                        </div>
                    </div>

                    @if ($hasChildren && (! $managed || ! $expanded))
                        <span
                            class="taxonomy-tree-count"
                            title="{{ trans_choice('filament-taxonomies::taxonomies.manage_terms.children', $node['childCount'], ['count' => $node['childCount']]) }}"
                            @unless ($managed) x-show="! expanded" x-cloak @endunless
                        >
                            <span aria-hidden="true">{{ $node['childCount'] }}</span>
                            <span class="taxonomy-sr-only">{{ trans_choice('filament-taxonomies::taxonomies.manage_terms.children', $node['childCount'], ['count' => $node['childCount']]) }}</span>
                        </span>
                    @endif
                </div>

                <div class="taxonomy-row-actions flex shrink-0 items-center">
                    @foreach (['up' => [$node['previousId'], 'before', 'first_term'], 'down' => [$node['nextId'], 'after', 'last_term']] as $direction => [$targetId, $placement, $boundary])
                        @php
                            $canMoveThisWay = $canMove && $targetId !== null;
                        @endphp
                        <x-filament::icon-button
                            :icon="'heroicon-o-arrow-' . $direction"
                            :label="__('filament-taxonomies::taxonomies.manage_terms.move_' . $direction, ['name' => $term->name])"
                            :disabled="! $canMoveThisWay"
                            :tooltip="! $canMove ? $moveHint : ($targetId === null ? __('filament-taxonomies::taxonomies.manage_terms.' . $boundary) : null)"
                            size="sm"
                            :color="$canMoveThisWay ? 'primary' : 'gray'"
                            class="taxonomy-move-button"
                            :x-on:click="$canMoveThisWay ? '$dispatch(\'taxonomy-tree-move-term\', { termId: ' . $termId . ', targetId: ' . $targetId . ', placement: \'' . $placement . '\' })' : null"
                        />
                    @endforeach

                    {{-- Rendering uses loaded visible records. Mounted actions retain their fresh authorization callbacks. --}}
                    {{ (($this->editTermAction)(['term' => $termId]))
                        ->authorize($updateAuthorization) }}

                    {{ (($this->deleteTermAction)(['term' => $termId]))
                        ->authorize(\Eyawiin\FilamentTaxonomies\Authorization\TaxonomyTermAuthorization::delete($term)) }}
                </div>
            </div>

            @if ($hasChildren && ! $managed)
                <div wire:ignore.self x-show="expanded" x-collapse.duration.100ms>
                    <div class="ps-8 pt-2">
                        <x-filament-taxonomies::taxonomy-tree
                            :nodes="$node['children']"
                            :remaining="$node['remaining']"
                            :parent-id="$termId"
                            :searching="$searching"
                        />
                    </div>
                </div>
            @elseif ($hasChildren && $expanded)
                <div class="ps-8 pt-2">
                    <x-filament-taxonomies::taxonomy-tree
                        :nodes="$node['children']"
                        :remaining="$node['remaining']"
                        :parent-id="$termId"
                        :searching="$searching"
                    />
                </div>
            @endif
        </li>
    @endforeach

    @if ($remaining > 0)
        <li wire:key="taxonomy-term-more-{{ $parentId ?? 'root' }}" class="taxonomy-tree-more">
            <x-filament::link
                tag="button"
                color="gray"
                size="sm"
                icon="heroicon-m-chevron-double-down"
                :wire:click="'showMoreTerms(' . ($parentId ?? 'null') . ')'"
            >
                {{ trans_choice('filament-taxonomies::taxonomies.manage_terms.show_more', $remaining, ['count' => $remaining]) }}
            </x-filament::link>
        </li>
    @endif
</ul>
