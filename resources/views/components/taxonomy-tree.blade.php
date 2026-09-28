@props([
    'nodes',
    'parentId' => null,
])

<ul
    class="m-0 list-none space-y-2 p-0"
    x-sort="$wire.moveTerm(Number($item), $position, @js($parentId))"
    x-sort:config="{ animation: 100 }"
>
    @foreach ($nodes as $node)
        @php
            $hasChildren = $node['children'] !== [];

            $storageKey = sprintf(
                'eyawiin-filament-taxonomies:tree:%s:term:%s:expanded',
                $node['term']->taxonomy_id,
                $node['term']->getKey(),
            );
        @endphp

        <li
            wire:key="taxonomy-term-{{ $node['term']->getKey() }}"
            x-data="{ expanded: $persist(true).as(@js($storageKey)) }"
            x-on:taxonomy-tree-set-expanded.window="expanded = $event.detail.expanded"
            x-on:taxonomy-tree-expand-term.window="
                if ($event.detail.termId === @js((int) $node['term']->getKey())) {
                    expanded = true
                }
            "
            x-sort:item="{{ $node['term']->getKey() }}"
        >
            <div
                class="
                    flex items-center gap-3 rounded-xl border border-gray-200
                    bg-white px-4 py-3 shadow-sm
                    dark:border-white/10 dark:bg-gray-900
                "
            >
                <div class="flex shrink-0 items-center">
                    @if ($hasChildren)
                      <div
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
                    <div class="invisible pointer-events-none" aria-hidden="true">
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

                <div
                    x-sort:handle
                    class="flex h-8 w-8 shrink-0 cursor-grab touch-none select-none items-center justify-center text-gray-400 active:cursor-grabbing dark:text-gray-500"
                    title="Drag to reorder"
                >
                    <x-filament::icon
                        icon="heroicon-o-bars-3"
                        class="h-5 w-5"
                    />
                </div>

                <div class="flex shrink-0 items-center gap-1">
                    {{ ($this->editTermAction)([
                        'term' => $node['term']->getKey(),
                    ]) }}

                    {{ ($this->deleteTermAction)([
                        'term' => $node['term']->getKey(),
                    ]) }}
                </div>
            </div>

            @if ($hasChildren)
                <div
                    x-show="expanded"
                    x-collapse.duration.100ms
                >
                    <div class="pt-2 ps-8">
                        <x-filament-taxonomies::taxonomy-tree
                            :nodes="$node['children']"
                            :parent-id="(int) $node['term']->getKey()"
                        />
                    </div>
                </div>
            @endif
        </li>
    @endforeach
</ul>