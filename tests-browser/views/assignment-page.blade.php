<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        <button type="submit">Validate</button>
    </form>
    <button wire:click="$toggle('disabled')">Toggle disabled</button>
    <button wire:click="$toggle('readOnly')">Toggle read only</button>
    <button wire:click="toggleMultiple">Toggle multiple fixture</button>
    <button wire:click="removeSelection">Rename selected term</button>
</x-filament-panels::page>
