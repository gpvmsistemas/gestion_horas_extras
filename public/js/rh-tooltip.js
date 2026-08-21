/**
 * RhTooltip — tooltip flotante del Registro de Horas (port del tooltip de
 * calendario de view_hours.js / duplicate_hours.js de hoursapp, con los
 * tokens visuales de la suite). Un único nodo reutilizado, sin dependencias.
 *
 * Uso directo (tooltips ricos de los calendarios):
 *   RhTooltip.show(celda, '<div class="rh-tip-fecha">…</div>…'[, 'danger']);
 *   RhTooltip.hide();
 *
 * Modo automático: en las vistas que cargan este script, todo elemento con
 * `title` se convierte al vuelo en un tooltip INMEDIATO y estilizado (el
 * title nativo se retira para matar el retardo del navegador). El color se
 * deriva de las clases del elemento (danger/warning/primary/success/info),
 * o se fija con data-rh-variant="...". Los calendarios con tooltip rico
 * propio quedan excluidos de la conversión automática.
 */
(function () {
    'use strict';
    var el = null;
    var current = null;

    function ensure() {
        if (!el) {
            el = document.createElement('div');
            el.className = 'rh-tip';
            el.setAttribute('role', 'tooltip');
            el.hidden = true;
            document.body.appendChild(el);
        }
        return el;
    }

    function esc(s) {
        return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
            return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
        });
    }

    /** Color según las clases del elemento (o de su ícono interno). */
    function variantFor(target) {
        var cls = target.className || '';
        var inner = target.querySelector && target.querySelector('[class*="danger"],[class*="warning"],[class*="success"],[class*="primary"],[class*="info"]');
        if (inner) cls += ' ' + inner.className;
        if (/danger/.test(cls)) return 'danger';
        if (/warning/.test(cls)) return 'warning';
        if (/success/.test(cls)) return 'success';
        if (/info/.test(cls)) return 'info';
        if (/primary/.test(cls)) return 'primary';
        return '';
    }

    function show(target, html, variant) {
        var t = ensure();
        t.className = 'rh-tip' + (variant ? ' rh-tip-' + variant : '');
        t.innerHTML = html;
        t.hidden = false;
        var r = target.getBoundingClientRect();
        var tr = t.getBoundingClientRect();
        var left = r.left + r.width / 2 - tr.width / 2;
        var top = r.top - tr.height - 8;
        if (left < 5) left = 5;
        if (left + tr.width > window.innerWidth - 5) left = window.innerWidth - tr.width - 5;
        if (top < 5) top = r.bottom + 8;
        t.style.left = left + 'px';
        t.style.top = top + 'px';
    }

    function hide() {
        if (el) el.hidden = true;
        current = null;
    }

    window.RhTooltip = { show: show, hide: hide, esc: esc };

    // ── Conversión automática de title → tooltip inmediato con color ──
    document.addEventListener('mouseover', function (ev) {
        if (!ev.target.closest) return;
        // Los calendarios manejan su propio tooltip rico.
        if (ev.target.closest('[data-cal-date], [data-pk-info], [data-f], [data-b]')) return;
        var t = ev.target.closest('[data-rh-tip], [title]');
        if (!t || t === current) return;
        if (t.hasAttribute('title')) {
            var raw = t.getAttribute('title');
            t.removeAttribute('title'); // mata el retardo del tooltip nativo
            if (raw !== '') t.setAttribute('data-rh-tip', raw);
        }
        var txt = t.getAttribute('data-rh-tip');
        if (!txt) return;
        current = t;
        show(t, esc(txt), t.getAttribute('data-rh-variant') || variantFor(t));
    });
    document.addEventListener('mouseout', function (ev) {
        if (current && (!ev.relatedTarget || !current.contains(ev.relatedTarget))) {
            hide();
        }
    });
    document.addEventListener('scroll', hide, true);
})();
