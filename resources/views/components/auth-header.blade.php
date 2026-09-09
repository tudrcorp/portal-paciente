@props([
    'title',
    'description',
])

<div class="flex w-full flex-col gap-2 text-center">
    <flux:heading size="xl" class="auth-portal-title text-balance font-semibold tracking-tight">
        {{ $title }}
    </flux:heading>
    <flux:subheading class="auth-portal-description text-pretty text-base leading-relaxed">
        {{ $description }}
    </flux:subheading>
</div>
