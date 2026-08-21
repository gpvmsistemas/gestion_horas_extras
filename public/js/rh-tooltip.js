/**
 * RhTooltip — tooltip flotante del Registro de Horas (port del tooltip de
 * calendario de view_hours.js / duplicate_hours.js de hoursapp, con los
 * tokens visuales de la suite). Un único nodo reutilizado, sin dependencias.
 *
 *   RhTooltip.show(celda, '<div class="rh-tip-fecha">…</div>…');
 *   RhTooltip.hide();
 */
(function () {
    'use strict';
    var el = null;
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
    window.RhTooltip = {
        show: function (target, html) {
            var t = ensure();
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
        },
        hide: function () { if (el) el.hidden = true; },
        esc: function (s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
    };
})();
