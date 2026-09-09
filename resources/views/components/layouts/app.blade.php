<x-layouts.app.sidebar :title="$title ?? null">
    <flux:main class="portal-surface-page portal-liquid-root">
        <div class="portal-main-inner flex flex-1 flex-col gap-6 px-3 py-4 sm:px-4 md:p-6 lg:p-8">
            {{ $slot }}
        </div>
    </flux:main>
</x-layouts.app.sidebar>
