@props(['href'])
<a href="{{ $href }}" {{ $attributes->merge(['class' => 'font-medium text-ember underline-offset-4 hover:text-ember-strong hover:underline']) }}>{{ $slot }}</a>
