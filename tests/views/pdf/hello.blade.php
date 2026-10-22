<x-doc::document title="Hello">
    <x-doc::page>
        <x-slot:header><x-doc::text>Header</x-doc::text></x-slot:header>
        <x-slot:footer><x-doc::page-number /></x-slot:footer>
        <x-doc::text>Hello {{ $name }}</x-doc::text>
    </x-doc::page>
</x-doc::document>
