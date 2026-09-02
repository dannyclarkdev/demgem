@if (session('status'))
    <x-ui.alert variant="success" class="mb-6">{{ session('status') }}</x-ui.alert>
@endif
@if (session('error'))
    <x-ui.alert variant="danger" class="mb-6">{{ session('error') }}</x-ui.alert>
@endif
