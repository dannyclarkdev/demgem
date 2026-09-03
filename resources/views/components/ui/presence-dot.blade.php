{{--
    The one new kit component of slice 5, and it earns its place by appearing beside
    every member's name on two screens. Lit means that person has the campaign open.

    It is a dot and not a pulse: it sits on a screen a table stares at for four hours,
    and something that moves forever in the corner of the eye is a cost, not a feature.

    Unlit is a hollow ring rather than a grey disc. A grey disc at eight pixels is
    invisible against the light theme's canvas, and an empty socket reads as "not
    here" in both themes.
--}}
@props(['here' => false, 'label' => null])
<span
    {{ $attributes->merge(['class' => 'inline-block size-2 shrink-0 rounded-full '.($here ? 'bg-success shadow-[0_0_0_2px] shadow-success/25' : 'border border-ink-faint')]) }}
    @if ($label) title="{{ $label }}" @endif
    aria-hidden="true"
></span>
