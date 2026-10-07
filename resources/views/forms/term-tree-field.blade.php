@php
    $configuration = $getTreeConfiguration();
    $statePath = $getStatePath();
    $id = $getId();
    $layout = $configuration['layout'];
    $labels = $configuration['labels'];
@endphp

<x-dynamic-component :component="$getFieldWrapperView()" :field="$field">
    <div
        {{-- A different layout or loading mode needs a fresh component rather than a morph. --}}
        wire:key="{{ $id }}-terms-{{ $layout }}-{{ $configuration['multiple'] ? 'multiple' : 'single' }}-{{ $configuration['lazy'] ? 'lazy' : 'loaded' }}"
        class="taxonomy-term-field"
        data-layout="{{ $layout }}"
        x-load
        x-load-src="{{ \Filament\Support\Facades\FilamentAsset::getAlpineComponentSrc('taxonomy-term-field', package: 'eyawiin/filament-taxonomies') }}"
        x-data="taxonomyTermField({
            state: $wire.{{ $applyStateBindingModifiers("\$entangle('{$statePath}')") }},
            ...@js($configuration),
            call: (method, args) => Livewire.fireAction(
                $wire.__instance,
                'callSchemaComponentMethod',
                [@js($getKey()), method, args],
                { async: true },
            ),
        })"
        @if ($layout === 'dropdown')
            x-on:click.outside="close(false)"
            x-on:focusout="leave($event)"
            x-on:keydown.escape="if (open) { $event.preventDefault(); $event.stopPropagation(); close() }"
        @endif
    >
        <span hidden data-tree-config="{{ json_encode($configuration) }}"></span>

        @include('filament-taxonomies::forms.term-tree.' . $layout)
    </div>
</x-dynamic-component>
