<ul class="m-0 list-none p-0">
    @foreach ($nodes as $node)
        <li class="my-2">
            <div class="flex items-center gap-2">
                <x-filament::icon
                    icon="heroicon-o-tag"
                    class="h-5 w-5 text-gray-400"
                />

                <span class="text-sm font-medium">
                    {{ $node['term']->name }}
                </span>
            </div>

            @if ($node['children'] !== [])
                <div class="ml-7">
                    <x-filament-taxonomies::taxonomy-tree
                        :nodes="$node['children']"
                    />
                </div>
            @endif
        </li>
    @endforeach
</ul>