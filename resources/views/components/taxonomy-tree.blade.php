@props([
    'nodes',
])

<ul class="m-0 list-none space-y-2 p-0">
    @foreach ($nodes as $nodeIndex => $node)
        @php
            $previousNode = $nodes[$nodeIndex - 1] ?? null;
            $nextNode = $nodes[$nodeIndex + 1] ?? null;
            $hasChildren = $node['children'] !== [];
            $termId = (int) $node['term']->getKey();
            $updateAuthorization = \Eyawiin\FilamentTaxonomies\Authorization\TaxonomyTermAuthorization::update($node['term']);
            $canMove = $updateAuthorization->allowed();

            $storageKey = sprintf(
                'eyawiin-filament-taxonomies:tree:%s:term:%s:expanded',
                $node['term']->taxonomy_id,
                $termId,
            );
        @endphp

        <li
            wire:key="taxonomy-term-{{ $node['term']->getKey() }}"
            data-taxonomy-term
            data-can-move="{{ $canMove ? 'true' : 'false' }}"
            data-term-id="{{ $node['term']->getKey() }}"
            x-data="{ expanded: $persist(true).as(@js($storageKey)) }"
            x-on:taxonomy-tree-set-expanded.window="expanded = $event.detail.expanded"
            x-on:taxonomy-tree-expand-term.window="
                if ($event.detail.termId === @js($termId)) {
                    expanded = true
                }
            "
        >
            <div
                wire:ignore.self
                data-taxonomy-row
                data-has-children="{{ $hasChildren ? 'true' : 'false' }}"
                x-bind:data-expanded="expanded ? 'true' : 'false'"
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
                    title="{{ $canMove ? 'Drag to reorder' : 'You do not have permission to move this term' }}"
                >
                    <x-filament::icon
                        icon="heroicon-o-bars-3"
                        class="h-5 w-5"
                    />
                </div>
                
                <div class="flex shrink-0 items-center">
                    @if ($hasChildren)
                      <div
                          wire:key="taxonomy-term-toggle-{{ $termId }}"
                          wire:ignore.self
                          class="transition-transform duration-100 ease-out"
                          x-bind:class="expanded ? 'rotate-90' : 'rotate-0'"
                      >
                          <x-filament::icon-button
                              icon="heroicon-o-chevron-right"
                              label="Toggle children"
                              size="sm"
                              color="gray"
                              x-on:click="expanded = ! expanded"
                              x-bind:aria-expanded="expanded"
                          />
                      </div>
                  @else
                    <div
                        wire:key="taxonomy-term-placeholder-{{ $termId }}"
                        class="invisible pointer-events-none"
                        aria-hidden="true"
                    >
                        <x-filament::icon-button
                            icon="heroicon-o-chevron-right"
                            label="Toggle children"
                            size="sm"
                            color="gray"
                            tabindex="-1"
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
                        <div class="truncate text-sm font-medium text-gray-950 dark:text-white">
                            {{ $node['term']->name }}
                        </div>

                        <div class="truncate text-xs text-gray-500 dark:text-gray-400">
                            {{ $node['term']->slug }}
                        </div>
                    </div>
                </div>

                <div class="taxonomy-row-actions flex shrink-0 items-center">
                    <x-filament::icon-button
                        icon="heroicon-o-arrow-up"
                        label="Move {{ $node['term']->name }} up"
                        :disabled="! $canMove || $previousNode === null"
                        :tooltip="! $canMove ? 'You do not have permission to move this term' : ($previousNode === null ? 'Already the first term at this level' : null)"
                        size="sm"
                        :color="! $canMove || $previousNode === null ? 'gray' : 'primary'"
                        class="taxonomy-move-button"
                        x-on:click="$dispatch('taxonomy-tree-move-term', {
                            termId: Number($el.closest('[data-taxonomy-term]').dataset.termId),
                            targetId: Number($el.closest('[data-taxonomy-term]').previousElementSibling.dataset.termId),
                            placement: 'before',
                        })"
                    />

                    <x-filament::icon-button
                        icon="heroicon-o-arrow-down"
                        label="Move {{ $node['term']->name }} down"
                        :disabled="! $canMove || $nextNode === null"
                        :tooltip="! $canMove ? 'You do not have permission to move this term' : ($nextNode === null ? 'Already the last term at this level' : null)"
                        size="sm"
                        :color="! $canMove || $nextNode === null ? 'gray' : 'primary'"
                        class="taxonomy-move-button"
                        x-on:click="$dispatch('taxonomy-tree-move-term', {
                            termId: Number($el.closest('[data-taxonomy-term]').dataset.termId),
                            targetId: Number($el.closest('[data-taxonomy-term]').nextElementSibling.dataset.termId),
                            placement: 'after',
                        })"
                    />

                    {{-- Rendering uses loaded visible records. Mounted actions retain their fresh authorization callbacks. --}}
                    {{ (($this->editTermAction)(['term' => $termId]))
                        ->authorize($updateAuthorization) }}

                    {{ (($this->deleteTermAction)(['term' => $termId]))
                        ->authorize(\Eyawiin\FilamentTaxonomies\Authorization\TaxonomyTermAuthorization::delete($node['term'])) }}
                </div>
            </div>

            @if ($hasChildren)
                <div wire:ignore.self x-show="expanded" x-collapse.duration.100ms>
                    <div class="ps-8 pt-2">
                        <x-filament-taxonomies::taxonomy-tree :nodes="$node['children']" />
                    </div>
                </div>
            @endif
        </li>
    @endforeach
</ul>