@php
    $siblings = array_values(array_filter($nodes, fn ($node) => $node['ancestors'] === $ancestors));
@endphp
@foreach ($siblings as $index => $node)
    <div role="treeitem"
        wire:key="{{ $treeId }}-parent-node-{{ $node['id'] }}"
        data-node-id="{{ $node['id'] }}"
        x-show="isVisible({{ $node['id'] }})"
        aria-level="{{ count($ancestors) + 1 }}"
        aria-posinset="{{ $index + ($ancestors === [] ? 2 : 1) }}"
        aria-setsize="{{ count($siblings) + ($ancestors === [] ? 1 : 0) }}"
        aria-labelledby="{{ $treeId }}-name-{{ $node['id'] }}"
        @if ($node['disabled'])
            aria-disabled="true" aria-describedby="{{ $treeId }}-reason-{{ $node['id'] }}"
        @else
            x-bind:aria-selected="String(state) === '{{ $node['id'] }}'"
        @endif
        @if ($node['hasChildren']) x-bind:aria-expanded="isExpanded({{ $node['id'] }})" @endif
        x-bind:tabindex="activeId === {{ $node['id'] }} ? 0 : -1"
        x-on:focus.stop="activeId = {{ $node['id'] }}"
        x-on:click.stop="choose({{ $node['id'] }})"
        class="taxonomy-parent-item"
    >
        <div class="taxonomy-parent-node" style="padding-inline-start: {{ 8 + count($ancestors) * 20 }}px"
            @if ($node['disabled']) aria-disabled="true" @endif>
            <button type="button" tabindex="-1" class="taxonomy-parent-toggle {{ $node['hasChildren'] ? '' : 'is-leaf' }}"
                @disabled(! $node['hasChildren'])
                x-bind:aria-label="(isExpanded({{ $node['id'] }}) ? labels.collapse : labels.expand).replace(':name', @js($node['name']))"
                x-on:click.stop="toggleNode({{ $node['id'] }})">
                <span x-bind:class="{ 'is-expanded': isExpanded({{ $node['id'] }}) }">›</span>
            </button>
            <span id="{{ $treeId }}-name-{{ $node['id'] }}" class="taxonomy-parent-name">{{ $node['name'] }}</span>
            @if ($node['disabled'])
                <span id="{{ $treeId }}-reason-{{ $node['id'] }}" class="taxonomy-parent-reason">{{ $node['reason'] }}</span>
            @endif
        </div>
        @if ($node['hasChildren'])
            <div role="group" x-show="isExpanded({{ $node['id'] }})">
                @include('filament-taxonomies::forms.parent-tree-branch', ['ancestors' => [...$ancestors, $node['id']]])
            </div>
        @endif
    </div>
@endforeach
