# JP-UI-03A Responsive, Zoom, and Overflow QA

## Viewports captured

| Viewport | Size | Homepage | About | Support | FAQ/CMS/legal |
|----------|------|:--------:|:-----:|:-------:|:-------------:|
| Desktop large | 1440×1200 | ✓ | ✓ | ✓ | ✓ |
| Desktop | 1280×900 | ✓ | zoom | zoom | zoom |
| Tablet | 1024×900 | ✓ | ✓ | ✓ | — |
| Tablet portrait | 768×1024 | ✓ | — | — | — |
| Mobile | 390×844 | ✓ | ✓ | ✓ | ✓ |
| Mobile | 375×812 | ✓ | — | — | — |
| Mobile narrow | 320×700 | ✓ | ✓ | ✓ | — |

## Zoom captures

| Zoom | Viewport | Themes | Result |
|------|----------|--------|--------|
| 125% | 1280×900 | light, dark | Pass |
| 150% | 1280×900 | light, dark | Pass |
| 150% | 1280×900 | About, Support, legal | Pass |

## Horizontal overflow

Every required capture asserts:

```js
document.documentElement.scrollWidth - document.documentElement.clientWidth <= 1
```

Additional Playwright coverage at **320**, **375**, **390**, and **150% zoom** on homepage, about, and support.

**Result:** 0 overflow failures across 119 captures and targeted overflow tests.

## Focus visibility

Targeted tests confirm skip link focus, theme switch focus in dark mode, mobile drawer Escape close, and keyboard-operable theme switch (JP-UI-02 + JP-UI-03A specs).

## Reduced motion

Homepage and about reduced-motion behavior verified: decorative flight-path animation disabled under `prefers-reduced-motion: reduce` without hiding content.
