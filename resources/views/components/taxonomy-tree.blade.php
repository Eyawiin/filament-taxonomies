<ul class="m-0 list-none space-y-2 p-0">
    @foreach ($nodes as $node)
        <li>
            <div
                class="
                    flex items-center gap-3 rounded-xl border border-gray-200
                    bg-white px-4 py-3 shadow-sm
                    dark:border-white/10 dark:bg-gray-900
                "
            >
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

                <div class="shrink-0">
                    {{ ($this->editTermAction)([
                        'term' => $node['term']->getKey(),
                    ]) }}
                </div>
            </div>

            @if ($node['children'] !== [])
                <div class="mt-2 ml-8">
                    <x-filament-taxonomies::taxonomy-tree
                        :nodes="$node['children']"
                    />
                </div>
            @endif
        </li>
    @endforeach
</ul>