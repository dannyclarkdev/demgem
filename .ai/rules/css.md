---
paths:
  - 'resources/css/**'
---

# Css

## A page animation with fill-mode both breaks every position:fixed child
.animate-rise on the layout's content wrapper used `both` fill. A filled `transform: none` still computes to a matrix, and any transform makes an element the containing block for its position:fixed descendants. The tools drawer and its button anchored to the page instead of the viewport, so the GM had to scroll to the bottom of the Run screen to roll a die.

Use `backwards` fill for entrance animations, so nothing is left applied after they finish. If you add a transform to a wrapper, check every fixed child inside it.
