@php
    $siblings = array_values(array_filter($nodes, fn ($node) => $node['ancestors'] === $ancestors));
@endphp
@foreach ($siblings as $node)
    <template x-if="multiple && (isSelected({{ $node['id'] }}) || selectedBelowCount({{ $node['id'] }}) > 0)">
        <div class="taxonomy-selection-branch" wire:key="{{ $treeId }}-tag-branch-{{ $node['id'] }}" x-bind:class="{ 'is-group': selectedBelowCount({{ $node['id'] }}) > 0 }"
            role="group" aria-label="{{ $node['name'] }}">
            <template x-if="isSelected({{ $node['id'] }})">
                <x-filament::badge color="gray" class="taxonomy-selection-tag">
                    <span class="taxonomy-selection-tag-name">{{ $node['name'] }}</span>
                    <x-slot name="deleteButton"
                        :aria-label="__('filament-taxonomies::assignment-tree.remove', ['name' => $node['name']])"
                        x-bind:disabled="blocked"
                        x-on:click.stop="removeTerm({{ $node['id'] }})"
                    ></x-slot>
                </x-filament::badge>
            </template>
            <span class="taxonomy-selection-context" x-show="!isSelected({{ $node['id'] }})">
                <span class="fi-sr-only" x-text="labels.ancestor"></span>
                {{ $node['name'] }}
            </span>
            @if ($node['hasChildren'])
                <div class="taxonomy-selection-children" x-show="selectedBelowCount({{ $node['id'] }}) > 0">
                    @include('filament-taxonomies::forms.selected-term-branch', ['ancestors' => [...$ancestors, $node['id']]])
                </div>
            @endif
        </div>
    </template>
@endforeach
