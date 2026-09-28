@foreach ($nodes as $node)
    <li>
        <div class="flex items-center gap-2">
            <span class="font-medium">
                {{ $node['term']->name }}
            </span>
        </div>

        @if ($node['children'] !== [])
            <ul class="mt-2 ml-6 space-y-2 border-l pl-4">
                @include(
                    'filament-taxonomies::resources.taxonomies.partials.term-tree',
                    ['nodes' => $node['children']]
                )
            </ul>
        @endif
    </li>
@endforeach