@props(['label' => 'Continue with Discord'])
{{-- Shown only when the install has a Discord app; the routes 404 otherwise. --}}
@if (\App\Http\Controllers\Auth\DiscordAuthController::isConfigured())
    <a href="{{ route('auth.discord.redirect') }}" {{ $attributes->merge(['class' => 'inline-flex h-10 w-full items-center justify-center gap-2 rounded-md border border-line-strong bg-raised px-4 text-sm font-medium text-ink hover:border-ink-faint']) }}>
        <x-ui.icon name="discord" class="size-4" />
        {{ $label }}
    </a>
    <div class="relative my-5 text-center text-xs text-ink-faint">
        <span class="absolute inset-x-0 top-1/2 border-t border-line"></span>
        <span class="relative bg-panel px-3">or with your email</span>
    </div>
@endif
