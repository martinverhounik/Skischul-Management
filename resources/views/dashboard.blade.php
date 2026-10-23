<x-layouts.app :title="__('Dashboard')">
    <x-ui.card :title="__('Hello, :name', ['name' => auth()->user()->name])">
        <p class="max-w-prose text-sm">{{ __('The dashboard with this week\'s numbers and the planning calendar will appear here once bookings are built.') }}</p>
    </x-ui.card>
</x-layouts.app>
