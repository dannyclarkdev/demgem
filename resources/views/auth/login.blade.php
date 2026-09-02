<x-layouts.guest title="Log in">
    <h1 class="font-display text-xl font-semibold">Welcome back</h1>
    <p class="mt-1 text-sm text-ink-muted">Log in to open your campaigns.</p>

    @if (session('status'))
        <x-ui.alert variant="success" class="mt-4">{{ session('status') }}</x-ui.alert>
    @endif

    <form method="POST" action="{{ route('login') }}" class="mt-6 space-y-4">
        @csrf
        <x-ui.input label="Email" name="email" type="email" :value="old('email')" required autofocus autocomplete="email" />
        <x-ui.input label="Password" name="password" type="password" required autocomplete="current-password" />
        <div class="flex items-center justify-between">
            <x-ui.checkbox label="Remember me" name="remember" />
            <x-ui.link :href="route('password.request')" class="text-sm">Forgot password?</x-ui.link>
        </div>
        <x-ui.button type="submit" class="w-full">Log in</x-ui.button>
    </form>

    <x-slot:footer>New here? <x-ui.link :href="route('register')">Create an account</x-ui.link></x-slot:footer>
</x-layouts.guest>
