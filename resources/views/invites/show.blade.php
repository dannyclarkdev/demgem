<x-layouts.guest title="Invitation">
    <p class="eyebrow">You are invited to join</p>
    <h1 class="mt-1 font-display text-2xl font-semibold tracking-tight">{{ $invite->campaign->name }}</h1>
    <p class="mt-1 text-sm text-ink-muted">as a <span class="font-medium text-ink">{{ $invite->role->label() }}</span>. {{ $invite->role->description() }}</p>

    @if (auth()->guest())
        <p class="mt-5 text-sm text-ink-muted">You need an account to join. This link unlocks the register page; log in instead if you already have one.</p>
        <x-ui.button :href="route('register')" class="mt-4 w-full">Create an account</x-ui.button>
        <x-ui.button :href="route('login')" variant="secondary" class="mt-2 w-full">Log in</x-ui.button>
    @elseif ($membership)
        <x-ui.alert class="mt-5">You are already a member of this campaign as {{ $membership->role->label() }}.</x-ui.alert>
        <x-ui.button :href="route('campaigns.show', $invite->campaign)" class="mt-4 w-full">Open campaign</x-ui.button>
    @else
        <form method="POST" action="{{ route('invites.accept', $invite->token) }}" class="mt-6">
            @csrf
            <x-ui.button type="submit" class="w-full">Join {{ $invite->campaign->name }}</x-ui.button>
        </form>
    @endif

    @auth
        <x-slot:footer>Signed in as {{ auth()->user()->name }}. <x-ui.link :href="route('campaigns.index')">Your campaigns</x-ui.link></x-slot:footer>
    @endauth
</x-layouts.guest>
