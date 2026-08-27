/**
 * RhCalPicker — calendario de selección del Registro de Horas (port del
 * calendario de delete_hours_massive / duplicate_hours_massive de hoursapp).
 *
 * Pinta un mes navegable sobre las clases .rh-cal de la suite: días con horas
 * marcados (punto + conteo), feriados teñidos, y selección por día o por
 * semana (lun–dom) con hover de vista previa. Soporta GRUPOS de selección
 * (p. ej. fuente/destino de la duplicación) con clases y reglas propias.
 *
 * Uso:
 *   var pk = RhCalPicker(el, {
 *     month: '2026-08', today: '2026-08-21',
 *     groups: { del: { cls: 'pk-sel-del', onlyMarked: true } },
 *     activeGroup: function () { return 'del'; },
 *     mode: function () { return 'single'; },   // 'single' | 'week'
 *     onChange: function (sel) {}               // {grupo: [iso,...]} ordenadas
 *   });
 *   pk.setData({ marked: {iso:n}, holidays: {iso:nombre} });
 *   pk.getSelection('del'); pk.clear(); pk.setMonth('2026-09');
 */
(function () {
    'use strict';

    var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
    var pad = function (n) { return String(n).padStart(2, '0'); };

    function dow(iso) {
        var p = iso.split('-').map(Number);
        return ((new Date(p[0], p[1] - 1, p[2]).getDay() + 6) % 7) + 1; // 1=Lu…7=Do
    }
    function addDays(iso, n) {
        var p = iso.split('-').map(Number);
        var d = new Date(p[0], p[1] - 1, p[2] + n);
        return d.getFullYear() + '-' + pad(d.getMonth() + 1) + '-' + pad(d.getDate());
    }
    function weekDays(iso) {
        var monday = addDays(iso, 1 - dow(iso));
        var out = [];
        for (var i = 0; i < 7; i++) out.push(addDays(monday, i));
        return out;
    }

    window.RhCalPicker = function (container, opts) {
        var month = opts.month || new Date().toISOString().slice(0, 7);
        var marked = {};
        var holidays = {};
        var selections = {};
        Object.keys(opts.groups || {}).forEach(function (g) { selections[g] = {}; });

        function groupCfg() { return (opts.groups || {})[opts.activeGroup()] || { cls: 'pk-sel-del', onlyMarked: true }; }
        function eligible(iso) { return !groupCfg().onlyMarked || !!marked[iso]; }
        function anySelected(iso) {
            var g = null;
            Object.keys(selections).forEach(function (k) { if (selections[k][iso]) g = k; });
            return g;
        }
        function emit() {
            if (opts.onChange) {
                var out = {};
                Object.keys(selections).forEach(function (g) { out[g] = Object.keys(selections[g]).sort(); });
                opts.onChange(out);
            }
        }

        function render() {
            var y = +month.slice(0, 4), m = +month.slice(5, 7);
            var html = '<div class="rh-pk-nav">'
                + '<button type="button" class="btn btn-sm btn-outline-primary pk-prev" aria-label="Mes anterior">‹</button>'
                + '<strong>' + MESES[m - 1] + ' ' + y + '</strong>'
                + '<button type="button" class="btn btn-sm btn-outline-primary pk-next" aria-label="Mes siguiente">›</button></div>'
                + '<div class="rh-cal" role="grid">';
            ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'].forEach(function (h) { html += '<div class="rh-cal-head">' + h + '</div>'; });
            var first = month + '-01';
            for (var i = 1; i < dow(first); i++) html += '<div class="rh-cal-day is-empty"></div>';
            var dim = new Date(y, m, 0).getDate();
            for (var d = 1; d <= dim; d++) {
                var iso = month + '-' + pad(d);
                var cls = ['rh-cal-day'];
                var extra = '';
                var selGroup = anySelected(iso);
                var has = !!marked[iso];
                if (selGroup) {
                    cls.push((opts.groups[selGroup] || {}).cls || 'pk-sel-del');
                } else if (holidays[iso]) {
                    cls.push('pk-hol');
                }
                if (has) {
                    cls.push('pk-has');
                    extra = '<span class="pk-dot"' + (window.RhTooltip ? '' : ' title="' + marked[iso] + ' bloque(s)"') + '></span>'
                        + '<span class="rh-cal-hrs">' + marked[iso] + ' bloq.</span>';
                } else {
                    cls.push('pk-off');
                }
                if (iso === (opts.today || '')) cls.push('is-today');
                var clickable = eligible(iso) || selGroup;
                var attrs = clickable ? ' tabindex="0" role="button" data-pk="' + iso + '"' : '';
                // Con RhTooltip disponible, el tooltip rico reemplaza al title nativo.
                var holTitle = (holidays[iso] && !window.RhTooltip)
                    ? ' title="Feriado: ' + String(holidays[iso]).replace(/"/g, '&quot;') + '"' : '';
                var infoAttr = (has || holidays[iso]) ? ' data-pk-info="' + iso + '"' : '';
                html += '<div class="' + cls.join(' ') + '"' + attrs + holTitle + infoAttr + '>' + d + extra
                    + (holidays[iso] ? '<span class="pk-star">★</span>' : '') + '</div>';
            }
            html += '</div>';
            container.innerHTML = html;
            container.querySelector('.pk-prev').addEventListener('click', function () { shift(-1); });
            container.querySelector('.pk-next').addEventListener('click', function () { shift(1); });
        }

        function shift(n) {
            var y = +month.slice(0, 4), m = +month.slice(5, 7) + n;
            if (m < 1) { m = 12; y--; } if (m > 12) { m = 1; y++; }
            month = y + '-' + pad(m);
            if (opts.onMonthChange) opts.onMonthChange(month);
            render();
        }

        function toggle(iso) {
            // Delegación completa: la vista puede implementar su propia
            // semántica (p. ej. fuente/destino de la duplicación masiva).
            if (opts.onDayClick && opts.onDayClick(iso, api) === true) {
                render();
                emit();
                return;
            }
            var g = opts.activeGroup();
            if (!selections[g]) selections[g] = {};
            var mode = opts.mode ? opts.mode() : 'single';
            if (mode === 'week') {
                var dias = weekDays(iso).filter(eligible);
                if (!dias.length) return;
                var todosSel = dias.every(function (d) { return selections[g][d]; });
                dias.forEach(function (d) {
                    if (todosSel) delete selections[g][d];
                    else { quitarDeOtros(d, g); selections[g][d] = true; }
                });
            } else {
                if (selections[g][iso]) delete selections[g][iso];
                else if (eligible(iso)) { quitarDeOtros(iso, g); selections[g][iso] = true; }
                else return;
            }
            render();
            emit();
        }
        function quitarDeOtros(iso, g) {
            Object.keys(selections).forEach(function (k) { if (k !== g) delete selections[k][iso]; });
        }

        container.addEventListener('click', function (ev) {
            var c = ev.target.closest && ev.target.closest('[data-pk]');
            if (c) toggle(c.dataset.pk);
        });
        container.addEventListener('keydown', function (ev) {
            if (ev.key !== 'Enter' && ev.key !== ' ') return;
            var c = ev.target.closest && ev.target.closest('[data-pk]');
            if (c) { ev.preventDefault(); toggle(c.dataset.pk); }
        });
        // Vista previa de semana al pasar el mouse (solo modo semana) + tooltip
        // rico con conteo de bloques y feriado (port del canónico).
        var DIAS_TIP = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        container.addEventListener('mouseover', function (ev) {
            var c = ev.target.closest && ev.target.closest('[data-pk]');
            container.querySelectorAll('.pk-hover').forEach(function (e) { e.classList.remove('pk-hover'); });
            if (c && (opts.mode ? opts.mode() : 'single') === 'week') {
                weekDays(c.dataset.pk).filter(eligible).forEach(function (d) {
                    var cell = container.querySelector('[data-pk="' + d + '"]');
                    if (cell) cell.classList.add('pk-hover');
                });
            }
            if (window.RhTooltip) {
                var i = ev.target.closest && ev.target.closest('[data-pk-info]');
                if (i) {
                    var iso = i.dataset.pkInfo;
                    var esc = RhTooltip.esc;
                    var html = '<div class="rh-tip-fecha">' + DIAS_TIP[dow(iso)] + ' ' + iso.slice(8, 10) + '/' + iso.slice(5, 7) + '/' + iso.slice(0, 4) + '</div>';
                    if (marked[iso]) html += '<div class="rh-tip-rango"><i class="far fa-clock me-1"></i>' + marked[iso] + ' bloque(s) registrados</div>';
                    if (holidays[iso]) html += '<div class="rh-tip-feriado"><i class="fas fa-star me-1"></i>Feriado: ' + esc(holidays[iso]) + '</div>';
                    RhTooltip.show(i, html);
                }
            }
        });
        container.addEventListener('mouseout', function () {
            if (window.RhTooltip) RhTooltip.hide();
        });
        container.addEventListener('mouseleave', function () {
            container.querySelectorAll('.pk-hover').forEach(function (e) { e.classList.remove('pk-hover'); });
            if (window.RhTooltip) RhTooltip.hide();
        });

        var api = {
            isMarked: function (iso) { return !!marked[iso]; },
            isHoliday: function (iso) { return !!holidays[iso]; },
            holidayName: function (iso) { return holidays[iso] || null; },
            weekDaysOf: weekDays,
            setSelection: function (g, arr) {
                selections[g] = {};
                (arr || []).forEach(function (d) { selections[g][d] = true; });
            },
            has: function (g, iso) { return !!(selections[g] || {})[iso]; },
            remove: function (g, iso) { if (selections[g]) delete selections[g][iso]; },
            add: function (g, iso) { (selections[g] = selections[g] || {})[iso] = true; },
            refresh: function () { render(); emit(); }
        };

        render();
        return Object.assign(api, {
            setData: function (data) {
                marked = data.marked || {};
                holidays = data.holidays || {};
                // La selección solo puede contener días elegibles del grupo.
                Object.keys(selections).forEach(function (g) {
                    var only = (opts.groups[g] || {}).onlyMarked;
                    if (only) Object.keys(selections[g]).forEach(function (d) { if (!marked[d]) delete selections[g][d]; });
                });
                render();
                emit();
            },
            getSelection: function (g) { return Object.keys(selections[g] || {}).sort(); },
            clear: function (g) {
                if (g) selections[g] = {};
                else Object.keys(selections).forEach(function (k) { selections[k] = {}; });
                render();
                emit();
            },
            setMonth: function (ym) { month = ym; render(); },
            getMonth: function () { return month; },
            render: render
        });
    };
})();
