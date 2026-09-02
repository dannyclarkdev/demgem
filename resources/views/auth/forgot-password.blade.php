<x-layouts.guest title="Forgot password">
    <h1 class="font-display text-xl font-semibold">Forgot your password?</h1>
    <p class="mt-1 text-sm text-ink-muted">Enter your email. We send you a link to choose a new one.</p>

    @if (session('status'))
        <x-ui.alert variant="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('password.email') }}" class="mt-6 space-y-4">
        @csrf
        <x-ui.input label="Email" name="email" type="email" :value="old('email')" required autofocus autocomplete="email" />
        <x-ui.button type="submit" class="w-full">Email reset link</x-ui.button>
    </form>

    <x-slot:footer><x-ui.link :href="route('login')">Back to log in</x-ui.link></x-slot:footer>
</x-layouts.guest>
