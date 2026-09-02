<x-layouts.guest title="Register">
    <h1 class="font-display text-xl font-semibold">Create your account</h1>
    <p class="mt-1 text-sm text-ink-muted">One account for every table you run or play at.</p>

    <form method="POST" action="{{ route('register') }}" class="mt-6 space-y-4">
        @csrf
        <x-ui.input label="Name" name="name" :value="old('name')" required autofocus autocomplete="name" />
        <x-ui.input label="Email" name="email" type="email" :value="old('email')" required autocomplete="email" />
        <x-ui.input label="Password" name="password" type="password" required autocomplete="new-password" />
        <x-ui.input label="Confirm password" name="password_confirmation" type="password" required autocomplete="new-password" />
        <x-ui.button type="submit" class="w-full">Create account</x-ui.button>
    </form>

    <x-slot:footer>Already registered? <x-ui.link :href="route('login')">Log in</x-ui.link></x-slot:footer>
</x-layouts.guest>
