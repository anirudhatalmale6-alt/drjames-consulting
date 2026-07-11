# D. R. James Consulting, LLC — Home page

Hand-coded reproduction of the supplied design. No page builder, no framework.

* **Typeface** — the design uses DejaVu Serif. It is self-hosted as a subset WOFF2
  (15 KB regular + 14 KB bold) so the page renders identically on Windows, macOS,
  iOS and Android instead of silently falling back to Times/Georgia.
* **Fidelity** — every element (nav, emblem, logo lockup, tagline rule, headline,
  gold rules and gems, footer columns, badge, contact strip, copyright) lands on
  the same pixel coordinates as the design at the 1600px canvas width.
* **Responsive** — laptop / tablet / mobile layouts included; the desktop
  composition is preserved and reflows to a stacked layout on small screens.
* **Weight** — one HTML file, one CSS file, two fonts, two images. No JS beyond a
  9-line mobile menu toggle.

## Files
```
index.html
assets/css/style.css
assets/fonts/dejavu-serif-400.woff2
assets/fonts/dejavu-serif-700.woff2
assets/img/emblem.png
assets/img/intuit-badge.png
```
