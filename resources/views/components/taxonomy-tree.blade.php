<ul class="m-0 list-none space-y-2 p-0">
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
            @if ($hasChildren)
                x-data="{
                    expanded: $persist(true).as(@js($storageKey))
                }"
            @endif
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
                      <div class="w-8"></div>
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
                        />
                    </div>
                </div>
            @endif
        </li>
    @endforeach
</ul>