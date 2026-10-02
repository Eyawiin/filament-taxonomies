<x-filament-panels::page>
    <form wire:submit="save">
        {{ $this->form }}
        <button type="submit">Validate</button>
    </form>
    <button wire:click="$toggle('disabled')">Toggle disabled</button>
    <button wire:click="$toggle('readOnly')">Toggle read only</button>
    <button wire:click="setParent">Set parent</button>
    <button wire:click="rename">Rename parent</button>
    <button id="outside">Outside field</button>
</x-filament-panels::page>
