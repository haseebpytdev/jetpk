/**
 * Logo-based brand color suggestions for Admin > Branding Settings.
 * Canvas raster analysis with SVG fill fallback; safe DOM only (no innerHTML).
 */
(function (global) {
    'use strict';

    var LOGO_AUTO_SCHEMES = ['logo_auto_1', 'logo_auto_2', 'logo_auto_3'];

    var PALETTE_META = {
        logo_auto_1: { label: 'Balanced Brand Palette', description: 'Logo color as primary with a deeper secondary and bright accent.' },
        logo_auto_2: { label: 'Professional Contrast Palette', description: 'Logo color as accent with navy secondary and a refined primary.' },
        logo_auto_3: { label: 'Light Modern Palette', description: 'Light primary, deep readable secondary, and conversion-friendly accent.' },
    };

    function clamp(n, min, max) {
        return Math.max(min, Math.min(max, n));
    }

    function rgbToHsl(r, g, b) {
        r /= 255;
        g /= 255;
        b /= 255;
        var max = Math.max(r, g, b);
        var min = Math.min(r, g, b);
        var h = 0;
        var s = 0;
        var l = (max + min) / 2;
        if (max !== min) {
            var d = max - min;
            s = l > 0.5 ? d / (2 - max - min) : d / (max + min);
            switch (max) {
                case r:
                    h = (g - b) / d + (g < b ? 6 : 0);
                    break;
                case g:
                    h = (b - r) / d + 2;
                    break;
                default:
                    h = (r - g) / d + 4;
            }
            h /= 6;
        }
        return { h: h * 360, s: s, l: l };
    }

    function hslToRgb(h, s, l) {
        h = ((h % 360) + 360) % 360;
        var c = (1 - Math.abs(2 * l - 1)) * s;
        var x = c * (1 - Math.abs(((h / 60) % 2) - 1));
        var m = l - c / 2;
        var r = 0;
        var g = 0;
        var b = 0;
        if (h < 60) {
            r = c;
            g = x;
        } else if (h < 120) {
            r = x;
            g = c;
        } else if (h < 180) {
            g = c;
            b = x;
        } else if (h < 240) {
            g = x;
            b = c;
        } else if (h < 300) {
            r = x;
            b = c;
        } else {
            r = c;
            b = x;
        }
        return {
            r: Math.round((r + m) * 255),
            g: Math.round((g + m) * 255),
            b: Math.round((b + m) * 255),
        };
    }

    function rgbToHex(r, g, b) {
        return (
            '#' +
            [r, g, b]
                .map(function (v) {
                    var h = clamp(Math.round(v), 0, 255).toString(16);
                    return h.length === 1 ? '0' + h : h;
                })
                .join('')
        ).toUpperCase();
    }

    function hexToRgb(hex) {
        hex = String(hex || '').replace('#', '').trim();
        if (hex.length !== 6) {
            return null;
        }
        return {
            r: parseInt(hex.slice(0, 2), 16),
            g: parseInt(hex.slice(2, 4), 16),
            b: parseInt(hex.slice(4, 6), 16),
        };
    }

    function adjustHsl(hex, fn) {
        var rgb = hexToRgb(hex);
        if (!rgb) {
            return '#2563EB';
        }
        var hsl = rgbToHsl(rgb.r, rgb.g, rgb.b);
        var next = fn(hsl);
        var out = hslToRgb(next.h, next.s, next.l);
        return rgbToHex(out.r, out.g, out.b);
    }

    function darkenHex(hex, amount) {
        return adjustHsl(hex, function (hsl) {
            return { h: hsl.h, s: hsl.s, l: clamp(hsl.l - amount, 0, 1) };
        });
    }

    function lightenHex(hex, amount) {
        return adjustHsl(hex, function (hsl) {
            return { h: hsl.h, s: hsl.s, l: clamp(hsl.l + amount, 0, 1) };
        });
    }

    function saturateHex(hex, amount) {
        return adjustHsl(hex, function (hsl) {
            return { h: hsl.h, s: clamp(hsl.s + amount, 0, 1), l: hsl.l };
        });
    }

    function shiftHueHex(hex, degrees) {
        return adjustHsl(hex, function (hsl) {
            return { h: hsl.h + degrees, s: hsl.s, l: hsl.l };
        });
    }

    function isNearWhite(hsl) {
        return hsl.l >= 0.92 && hsl.s <= 0.12;
    }

    function isNearBlack(hsl) {
        return hsl.l <= 0.08;
    }

    function colorScore(hsl, count) {
        return count * (0.35 + hsl.s * 1.4) * (0.5 + (1 - Math.abs(hsl.l - 0.45)));
    }

    function bucketKey(r, g, b) {
        return (
            (Math.floor(r / 16) << 16) |
            (Math.floor(g / 16) << 8) |
            Math.floor(b / 16)
        );
    }

    function extractColorsFromImageData(data, width, height) {
        var buckets = {};
        var totalOpaque = 0;
        var step = width > 96 ? 2 : 1;

        for (var y = 0; y < height; y += step) {
            for (var x = 0; x < width; x += step) {
                var i = (y * width + x) * 4;
                var a = data[i + 3];
                if (a < 128) {
                    continue;
                }
                var r = data[i];
                var g = data[i + 1];
                var b = data[i + 2];
                var key = bucketKey(r, g, b);
                if (!buckets[key]) {
                    buckets[key] = { r: 0, g: 0, b: 0, count: 0 };
                }
                buckets[key].r += r;
                buckets[key].g += g;
                buckets[key].b += b;
                buckets[key].count += 1;
                totalOpaque += 1;
            }
        }

        if (totalOpaque === 0) {
            return null;
        }

        var entries = Object.keys(buckets).map(function (key) {
            var b = buckets[key];
            var r = Math.round(b.r / b.count);
            var g = Math.round(b.g / b.count);
            var bl = Math.round(b.b / b.count);
            var hsl = rgbToHsl(r, g, bl);
            return {
                r: r,
                g: g,
                b: bl,
                hex: rgbToHex(r, g, bl),
                hsl: hsl,
                count: b.count,
                share: b.count / totalOpaque,
            };
        });

        var meaningful = entries.filter(function (e) {
            if (isNearWhite(e.hsl) && e.share < 0.35) {
                return false;
            }
            if (isNearBlack(e.hsl) && e.share < 0.35) {
                return false;
            }
            return true;
        });

        if (meaningful.length === 0) {
            meaningful = entries.filter(function (e) {
                return !isNearWhite(e.hsl);
            });
        }
        if (meaningful.length === 0) {
            meaningful = entries;
        }

        meaningful.sort(function (a, b) {
            return colorScore(b.hsl, b.count) - colorScore(a.hsl, a.count);
        });

        var dominant = meaningful[0];
        var accentCandidate = meaningful[1] || dominant;
        for (var j = 1; j < meaningful.length; j += 1) {
            if (Math.abs(meaningful[j].hsl.h - dominant.hsl.h) > 25 && meaningful[j].hsl.s > 0.2) {
                accentCandidate = meaningful[j];
                break;
            }
        }

        return { dominant: dominant, accentCandidate: accentCandidate };
    }

    function buildPalettes(extracted) {
        var dom = extracted.dominant.hex;
        var accentSrc = extracted.accentCandidate.hex;
        var navy = '#1E293B';
        var slate = '#334155';

        return {
            logo_auto_1: {
                id: 'logo_auto_1',
                label: PALETTE_META.logo_auto_1.label,
                description: PALETTE_META.logo_auto_1.description,
                primary: dom,
                secondary: darkenHex(dom, 0.38),
                accent: saturateHex(shiftHueHex(dom, 150), 0.15),
            },
            logo_auto_2: {
                id: 'logo_auto_2',
                label: PALETTE_META.logo_auto_2.label,
                description: PALETTE_META.logo_auto_2.description,
                primary: lightenHex(dom, 0.12),
                secondary: navy,
                accent: saturateHex(dom, 0.1),
            },
            logo_auto_3: {
                id: 'logo_auto_3',
                label: PALETTE_META.logo_auto_3.label,
                description: PALETTE_META.logo_auto_3.description,
                primary: lightenHex(dom, 0.22),
                secondary: slate,
                accent: saturateHex(accentSrc, 0.2),
            },
        };
    }

    function parseSvgColors(text) {
        var doc = new DOMParser().parseFromString(text, 'image/svg+xml');
        if (doc.querySelector('parsererror')) {
            return null;
        }
        var counts = {};
        var attrs = ['fill', 'stroke', 'stop-color'];
        doc.querySelectorAll('*').forEach(function (el) {
            attrs.forEach(function (attr) {
                var val = el.getAttribute(attr);
                if (!val || val === 'none' || val.indexOf('url(') === 0) {
                    return;
                }
                var rgb = parseColorString(val);
                if (rgb) {
                    var key = bucketKey(rgb.r, rgb.g, rgb.b);
                    counts[key] = (counts[key] || 0) + 1;
                }
            });
        });
        var keys = Object.keys(counts);
        if (keys.length === 0) {
            return null;
        }
        keys.sort(function (a, b) {
            return counts[b] - counts[a];
        });
        var best = keys[0];
        var r = ((best >> 16) & 15) * 16 + 8;
        var g = ((best >> 8) & 15) * 16 + 8;
        var b = (best & 15) * 16 + 8;
        var hex = rgbToHex(r, g, b);
        var hsl = rgbToHsl(r, g, b);
        var dominant = { hex: hex, hsl: hsl, count: counts[best] };
        var accentCandidate = dominant;
        if (keys.length > 1) {
            var k2 = keys[1];
            var r2 = ((k2 >> 16) & 15) * 16 + 8;
            var g2 = ((k2 >> 8) & 15) * 16 + 8;
            var b2 = (k2 & 15) * 16 + 8;
            accentCandidate = { hex: rgbToHex(r2, g2, b2), hsl: rgbToHsl(r2, g2, b2), count: counts[k2] };
        }
        return { dominant: dominant, accentCandidate: accentCandidate };
    }

    function parseColorString(str) {
        str = String(str).trim().toLowerCase();
        var hexMatch = str.match(/^#([0-9a-f]{6})$/i);
        if (hexMatch) {
            return hexToRgb(hexMatch[1]);
        }
        var rgbMatch = str.match(/^rgba?\(\s*(\d+)\s*,\s*(\d+)\s*,\s*(\d+)/);
        if (rgbMatch) {
            return { r: parseInt(rgbMatch[1], 10), g: parseInt(rgbMatch[2], 10), b: parseInt(rgbMatch[3], 10) };
        }
        var named = {
            red: [255, 0, 0],
            blue: [0, 0, 255],
            green: [0, 128, 0],
            white: [255, 255, 255],
            black: [0, 0, 0],
            orange: [255, 165, 0],
            navy: [0, 0, 128],
        };
        if (named[str]) {
            return { r: named[str][0], g: named[str][1], b: named[str][2] };
        }
        return null;
    }

    function loadImageElement(src) {
        return new Promise(function (resolve, reject) {
            var img = new Image();
            img.onload = function () {
                resolve(img);
            };
            img.onerror = function () {
                reject(new Error('load_failed'));
            };
            if (src.indexOf('blob:') !== 0 && src.indexOf('data:') !== 0) {
                img.crossOrigin = 'anonymous';
            }
            img.src = src;
        });
    }

    function analyzeRasterImage(img) {
        var maxDim = 128;
        var w = img.naturalWidth || img.width;
        var h = img.naturalHeight || img.height;
        if (!w || !h) {
            return Promise.reject(new Error('empty_image'));
        }
        var scale = Math.min(1, maxDim / Math.max(w, h));
        var cw = Math.max(1, Math.round(w * scale));
        var ch = Math.max(1, Math.round(h * scale));
        var canvas = document.createElement('canvas');
        canvas.width = cw;
        canvas.height = ch;
        var ctx = canvas.getContext('2d', { willReadFrequently: true });
        if (!ctx) {
            return Promise.reject(new Error('no_canvas'));
        }
        ctx.drawImage(img, 0, 0, cw, ch);
        var data;
        try {
            data = ctx.getImageData(0, 0, cw, ch).data;
        } catch (e) {
            return Promise.reject(new Error('tainted_canvas'));
        }
        var extracted = extractColorsFromImageData(data, cw, ch);
        if (!extracted) {
            return Promise.reject(new Error('no_colors'));
        }
        return Promise.resolve(buildPalettes(extracted));
    }

    function analyzeFromUrl(url) {
        return loadImageElement(url).then(analyzeRasterImage);
    }

    function analyzeFromFile(file) {
        if (!file) {
            return Promise.reject(new Error('no_file'));
        }
        var type = (file.type || '').toLowerCase();
        if (type === 'image/svg+xml' || (file.name && file.name.toLowerCase().endsWith('.svg'))) {
            return file.text().then(function (text) {
                var extracted = parseSvgColors(text);
                if (!extracted) {
                    return Promise.reject(new Error('svg_parse_failed'));
                }
                return buildPalettes(extracted);
            });
        }
        var blobUrl = URL.createObjectURL(file);
        return loadImageElement(blobUrl)
            .then(analyzeRasterImage)
            .finally(function () {
                URL.revokeObjectURL(blobUrl);
            });
    }

    function clearChildren(el) {
        while (el.firstChild) {
            el.removeChild(el.firstChild);
        }
    }

    function createTextEl(tag, className, text) {
        var el = document.createElement(tag);
        if (className) {
            el.className = className;
        }
        el.textContent = text;
        return el;
    }

    function renderCard(container, palette, selectedId, onSelect) {
        var card = document.createElement('button');
        card.type = 'button';
        card.className =
            'logo-palette-card card h-100 text-start border' +
            (selectedId === palette.id ? ' border-primary shadow-sm' : '');
        card.setAttribute('data-logo-palette-id', palette.id);
        card.setAttribute('aria-pressed', selectedId === palette.id ? 'true' : 'false');

        var body = document.createElement('div');
        body.className = 'card-body p-2 p-sm-3';

        body.appendChild(createTextEl('div', 'fw-semibold small mb-1', palette.label));
        body.appendChild(createTextEl('div', 'text-muted small mb-2', palette.description));

        var swatches = document.createElement('div');
        swatches.className = 'd-flex gap-1 mb-2';
        ['primary', 'secondary', 'accent'].forEach(function (key) {
            var sw = document.createElement('span');
            sw.className = 'logo-palette-swatch rounded';
            sw.style.backgroundColor = palette[key];
            sw.title = palette[key];
            swatches.appendChild(sw);
        });
        body.appendChild(swatches);

        var strip = document.createElement('div');
        strip.className = 'logo-palette-header-strip rounded mb-2 px-2 py-1 small text-white';
        strip.style.backgroundColor = palette.primary;
        strip.textContent = 'Header strip';
        body.appendChild(strip);

        var row = document.createElement('div');
        row.className = 'd-flex align-items-center gap-2 flex-wrap';

        var btn = document.createElement('span');
        btn.className = 'logo-palette-sample-btn btn btn-sm text-white';
        btn.style.backgroundColor = palette.primary;
        btn.textContent = 'Book now';
        row.appendChild(btn);

        var badge = document.createElement('span');
        badge.className = 'badge';
        badge.style.backgroundColor = palette.accent;
        badge.style.color = '#fff';
        badge.textContent = 'New';
        row.appendChild(badge);

        body.appendChild(row);
        card.appendChild(body);

        card.addEventListener('click', function () {
            onSelect(palette);
        });

        var col = document.createElement('div');
        col.className = 'col-md-4 col-sm-6';
        col.appendChild(card);
        container.appendChild(col);
    }

    function init(config) {
        var root = document.getElementById(config.rootId || 'logo-palette-root');
        if (!root) {
            return;
        }

        var statusEl = document.getElementById(config.statusId || 'logo-palette-status');
        var cardsEl = document.getElementById(config.cardsId || 'logo-palette-cards');
        var schemeSelect = document.querySelector(config.schemeSelectSelector || '[data-brand-scheme-select]');
        var logoInput = document.querySelector(config.logoInputSelector || 'input[name="logo"]');
        var pickers = {
            primary: document.getElementById('primary_color'),
            secondary: document.getElementById('secondary_color'),
            accent: document.getElementById('accent_color'),
        };
        var previews = {
            primary: document.getElementById('primary_color_preview'),
            secondary: document.getElementById('secondary_color_preview'),
            accent: document.getElementById('accent_color_preview'),
        };

        var selectedId = config.activeScheme || '';
        var onSchemeEditableChange = config.onSchemeEditableChange;

        function setStatus(message, isError) {
            if (!statusEl) {
                return;
            }
            statusEl.textContent = message || '';
            statusEl.className = 'small mb-2 ' + (isError ? 'text-danger' : 'text-muted');
        }

        function applyPalette(palette) {
            selectedId = palette.id;
            if (schemeSelect) {
                var opt = schemeSelect.querySelector('option[value="' + palette.id + '"]');
                if (!opt) {
                    opt = document.createElement('option');
                    opt.value = palette.id;
                    opt.textContent = palette.label;
                    opt.hidden = true;
                    schemeSelect.appendChild(opt);
                }
                schemeSelect.value = palette.id;
                if (typeof onSchemeEditableChange === 'function') {
                    onSchemeEditableChange(schemeSelect.value);
                }
            }
            ['primary', 'secondary', 'accent'].forEach(function (key) {
                var field = key + '_color';
                if (pickers[key]) {
                    pickers[key].value = palette[key].toLowerCase();
                    pickers[key].dispatchEvent(new Event('input', { bubbles: true }));
                }
                if (previews[key]) {
                    previews[key].value = palette[key].toUpperCase();
                }
            });
            renderCards(lastPalettes);
        }

        var lastPalettes = null;

        function renderCards(palettes) {
            if (!cardsEl) {
                return;
            }
            clearChildren(cardsEl);
            if (!palettes) {
                return;
            }
            LOGO_AUTO_SCHEMES.forEach(function (id) {
                if (palettes[id]) {
                    renderCard(cardsEl, palettes[id], selectedId, applyPalette);
                }
            });
        }

        function showLoading() {
            if (!cardsEl) {
                return;
            }
            clearChildren(cardsEl);
            LOGO_AUTO_SCHEMES.forEach(function () {
                var col = document.createElement('div');
                col.className = 'col-md-4';
                var placeholder = document.createElement('div');
                placeholder.className = 'card border-dashed';
                placeholder.setAttribute('aria-hidden', 'true');
                var body = document.createElement('div');
                body.className = 'card-body p-3 text-center text-muted small';
                body.textContent = 'Analyzing…';
                placeholder.appendChild(body);
                col.appendChild(placeholder);
                cardsEl.appendChild(col);
            });
        }

        function runAnalysis(source) {
            showLoading();
            setStatus('Analyzing logo colors…', false);
            var promise =
                typeof source === 'string'
                    ? analyzeFromUrl(source)
                    : source instanceof File
                      ? analyzeFromFile(source)
                      : Promise.reject(new Error('no_source'));

            promise
                .then(function (palettes) {
                    lastPalettes = palettes;
                    setStatus('Choose a palette below. You can fine-tune colors before saving.', false);
                    renderCards(palettes);
                    if (LOGO_AUTO_SCHEMES.indexOf(selectedId) !== -1 && palettes[selectedId]) {
                        applyPalette(palettes[selectedId]);
                    }
                })
                .catch(function (err) {
                    lastPalettes = null;
                    clearChildren(cardsEl);
                    var code = err && err.message ? err.message : 'unknown';
                    if (code === 'tainted_canvas' || code === 'load_failed') {
                        setStatus(
                            'Could not read this logo in the browser (cross-origin or format). Use PNG/WebP or pick a preset/custom colors.',
                            true
                        );
                    } else if (code === 'svg_parse_failed') {
                        setStatus(
                            'SVG colors could not be detected. Use PNG/WebP or pick a preset/custom colors.',
                            true
                        );
                    } else {
                        setStatus(
                            'No usable logo colors found. Upload a PNG/WebP logo or use presets/custom colors.',
                            true
                        );
                    }
                });
        }

        if (schemeSelect) {
            schemeSelect.addEventListener('change', function () {
                if (LOGO_AUTO_SCHEMES.indexOf(schemeSelect.value) === -1) {
                    selectedId = '';
                    if (lastPalettes) {
                        renderCards(lastPalettes);
                    }
                } else {
                    selectedId = schemeSelect.value;
                    if (lastPalettes && lastPalettes[selectedId]) {
                        renderCards(lastPalettes);
                    }
                }
            });
        }

        if (logoInput) {
            logoInput.addEventListener('change', function () {
                var file = logoInput.files && logoInput.files[0];
                if (file) {
                    runAnalysis(file);
                }
            });
        }

        var logoUrl = config.logoUrl || root.getAttribute('data-logo-url') || '';
        if (logoUrl) {
            runAnalysis(logoUrl);
        } else {
            setStatus('Upload a logo below to generate color suggestions.', false);
        }
    }

    global.OtaAdminBrandingLogoPalette = {
        init: init,
        LOGO_AUTO_SCHEMES: LOGO_AUTO_SCHEMES,
    };
})(typeof window !== 'undefined' ? window : this);
