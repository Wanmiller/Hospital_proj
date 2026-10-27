# Interface dependencies

- Tabler Core **1.7.1**: https://github.com/tabler/tabler/releases/tag/%40tabler%2Fcore%401.7.1 — MIT license in `tabler/LICENSE`.
- Tabler Icons **3.35.0**: https://github.com/tabler/tabler-icons/tree/v3.35.0 — selected outline SVG files, MIT license in `tabler-icons/LICENSE`.

Tabler CSS/JS and the SVG icons are served locally. CSS font imports were removed and the interface uses system fonts, so no runtime CDN/font requests are needed. Tabler includes Bootstrap JavaScript; do not load a second Bootstrap bundle. Project-specific adjustments are in `assets/css/style.css`. The previous Flatly/Bootstrap assets have been removed.
