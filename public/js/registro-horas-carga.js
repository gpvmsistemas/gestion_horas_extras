/**
 * Registro de Horas — vista previa EN VIVO de la pantalla de Carga.
 *
 * Espejo cliente de RegistroHorasService (splitRange / blockMinutes /
 * computeDay org moderna) + overlay del servidor vía registroHoras/previewData
 * (conflictos, días bloqueados por estado y feriados por sucursal).
 * La estimación es informativa: el guardado real recalcula todo en el servidor.
 * Vanilla JS (el footer carga jQuery después de las vistas) + Bootstrap 5.
 */
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var cfg = document.getElementById('rhLiveConfig');
        var form = document.getElementById('rhCargaForm');
        var live = document.getElementById('rhLive');
        if (!cfg || !form || !live) return;

        var URL_PREVIEW = cfg.dataset.url;
        var ORG = cfg.dataset.org || 'paviotti';
        var REAL = cfg.dataset.real === '1';
        var TODAY = cfg.dataset.today || '';

        var el = function (id) { return document.getElementById(id); };
        var whoEl = el('rhLiveWho');
        var totalsEl = el('rhTotals');
        var scenarioEl = el('rhScenario');
        var calendarsEl = el('rhCalendars');
        var detailEl = el('rhDayDetail');
        var serverStateEl = el('rhServerState');

        live.hidden = false; // panel solo-JS

        // ── Utilidades (fechas SIEMPRE locales: new Date(y, m-1, d), nunca "YYYY-MM-DD") ──
        var pad = function (n) { return String(n).padStart(2, '0'); };
        var toMin = function (t) { var p = t.split(':'); return (+p[0]) * 60 + (+p[1]); };

        function blockMinutes(start, end) { // espejo de RegistroHorasService::blockMinutes
            var s = toMin(start);
            var e = (end === '23:59') ? 1440 : toMin(end);
            if (e < s) e += 1440;
            return e - s; // start === end => 0 (nunca 24 h fantasma)
        }
        function addDays(iso, n) {
            var p = iso.split('-').map(Number);
            var dt = new Date(p[0], p[1] - 1, p[2] + n);
            return dt.getFullYear() + '-' + pad(dt.getMonth() + 1) + '-' + pad(dt.getDate());
        }
        function dow(iso) { // 1=Lu … 7=Do, como date('N')
            var p = iso.split('-').map(Number);
            return ((new Date(p[0], p[1] - 1, p[2]).getDay() + 6) % 7) + 1;
        }
        function daysBetween(a, b) {
            var pa = a.split('-').map(Number), pb = b.split('-').map(Number);
            return Math.round((new Date(pb[0], pb[1] - 1, pb[2]) - new Date(pa[0], pa[1] - 1, pa[2])) / 86400000);
        }
        function splitRange(dateISO, start, end) { // espejo de splitRange PHP
            if (end > start) return [[dateISO, start, end]];
            var segs = [[dateISO, start, '23:59']];
            if (end !== '00:00') segs.push([addDays(dateISO, 1), '00:00', end]);
            return segs;
        }
        function daysInMonth(ym) {
            var p = ym.split('-').map(Number);
            return new Date(p[0], p[1], 0).getDate();
        }
        var MESES = ['Enero', 'Febrero', 'Marzo', 'Abril', 'Mayo', 'Junio', 'Julio', 'Agosto', 'Septiembre', 'Octubre', 'Noviembre', 'Diciembre'];
        var DIAS = ['', 'Lunes', 'Martes', 'Miércoles', 'Jueves', 'Viernes', 'Sábado', 'Domingo'];
        function monthLabelEs(ym) { var p = ym.split('-').map(Number); return MESES[p[1] - 1] + ' ' + p[0]; }
        function fmtH(min) {
            var h = Math.round(min / 60 * 100) / 100;
            var s = h.toFixed(2).replace(/0+$/, '').replace(/\.$/, '');
            return s === '' ? '0' : s;
        }
        function esc(s) {
            return String(s == null ? '' : s).replace(/[&<>"']/g, function (c) {
                return { '&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;' }[c];
            });
        }
        function icon(name, title) {
            return '<i class="fas fa-' + name + ' rh-cal-ico" title="' + esc(title) + '"></i>';
        }
        function ddmm(iso) { return iso.slice(8, 10) + '/' + iso.slice(5, 7); }
        // Origen de las extras estimadas de un día ("Feriado: 2 h · Umbral: 1 h").
        function extrasOrigen(info) {
            var parts = [];
            if (info.exHol > 0) parts.push('Feriado' + (info.holidayName ? ' (' + info.holidayName + ')' : '') + ': ' + fmtH(info.exHol) + ' h');
            if (info.exThr > 0) parts.push('Excedente del umbral diario de ' + (dow(info.date) >= 6 ? 5 : 8) + ' h: ' + fmtH(info.exThr) + ' h');
            return 'Origen de las extras — ' + parts.join(' · ');
        }
        // Motivo de la extra de UN segmento del detalle del día.
        function extraSegMotivo(seg, dateISO, holidayName) {
            if (seg.isHoliday) {
                return 'Feriado' + (holidayName ? ' — ' + holidayName : '') + ': todas las horas del bloque cuentan como extra.';
            }
            var thr = dow(dateISO) >= 6 ? 5 : 8;
            var dias = thr === 5 ? 'sábados y domingos' : 'lunes a viernes';
            return 'Excedente sobre el umbral diario de ' + thr + ' h (' + dias + ').'
                + ((seg.start === '00:00' || seg.end === '23:59') ? ' Tramo ' + seg.start + '–' + seg.end + ' de un turno que cruza medianoche.' : '');
        }

        // ── Lectura y validación del formulario ──
        function readInput() {
            var emp = el('rhEmployee');
            return {
                employeeId: emp && emp.value ? parseInt(emp.value, 10) : 0,
                employeeName: emp && emp.selectedIndex > 0 ? emp.options[emp.selectedIndex].text.split(' — ')[0] : '',
                startDate: (el('rhStart') || {}).value || '',
                endDate: (el('rhEnd') || {}).value || '',
                t1s: (el('rhT1s') || {}).value || '',
                t1e: (el('rhT1e') || {}).value || '',
                branch: (el('rhBranch') || {}).value || '',
                twoTurns: !!(el('rhTwoTurns') || {}).checked,
                t2s: (el('rhT2s') || {}).value || '',
                t2e: (el('rhT2e') || {}).value || '',
                branch2: (el('rhBranch2') || {}).value || ''
            };
        }
        var ISO = /^\d{4}-\d{2}-\d{2}$/;
        var HM = /^\d{2}:\d{2}$/;

        // ── Plan (espejo de store(): también divide el último día, derramando en end+1) ──
        function buildPlan(input) {
            var plan = {};
            for (var d = input.startDate; d <= input.endDate; d = addDays(d, 1)) {
                splitRange(d, input.t1s, input.t1e).forEach(function (seg) {
                    (plan[seg[0]] = plan[seg[0]] || []).push({ start: seg[1], end: seg[2], branch: input.branch, turn: 1 });
                });
                if (input.twoTurns && HM.test(input.t2s) && HM.test(input.t2e) && input.t2s !== input.t2e) {
                    splitRange(d, input.t2s, input.t2e).forEach(function (seg) {
                        (plan[seg[0]] = plan[seg[0]] || []).push({ start: seg[1], end: seg[2], branch: input.branch2 || input.branch, turn: 2 });
                    });
                }
            }
            return plan;
        }

        // ── Extras del día, regla Moderna (espejo de computeDay) ──
        function computeDayModerna(dateISO, segments) {
            segments.sort(function (a, b) { return a.start < b.start ? -1 : a.start > b.start ? 1 : 0; });
            var thresholdMin = (dow(dateISO) >= 6 ? 5 : 8) * 60;
            var acc = 0, totalMin = 0, extraMin = 0;
            segments.forEach(function (seg) {
                var segExtra;
                if (seg.isHoliday) {
                    segExtra = seg.minutes; // feriado: todo extra, no consume umbral
                } else {
                    var newAcc = acc + seg.minutes;
                    segExtra = Math.max(0, newAcc - Math.max(thresholdMin, acc));
                    acc = newAcc;
                }
                seg.extraMin = segExtra;
                extraMin += segExtra;
                totalMin += seg.minutes;
            });
            return { totalMin: totalMin, extraMin: extraMin };
        }

        function segmentsOverlap(a, b) {
            var a0 = toMin(a.start), a1 = (a.end === '23:59') ? 1440 : toMin(a.end);
            var b0 = toMin(b.start), b1 = (b.end === '23:59') ? 1440 : toMin(b.end);
            return a0 < b1 && b0 < a1;
        }

        // ── Totales del período ──
        function computeTotals(plan, serverData, scenario) {
            var byDate = {};
            var days = [];
            var totH = 0, totEx = 0, totExHol = 0, totExThr = 0, nConf = 0, nBlock = 0, nHol = 0, nDays = 0, nOverlap = 0;
            var blocked = (serverData && serverData.blocked) || {};
            var conflicts = (serverData && serverData.conflicts) || {};
            var hol1 = (serverData && serverData.holidays) || {};
            var hol2 = (serverData && serverData.holidays2) || {};
            Object.keys(plan).sort().forEach(function (date) {
                var blockedReason = blocked[date];
                if (blockedReason) {
                    nBlock++;
                    var infoB = { date: date, blocked: blockedReason };
                    days.push(infoB); byDate[date] = infoB;
                    return; // el store() real omite el día completo
                }
                var segs = plan[date].map(function (s) {
                    return {
                        start: s.start, end: s.end, branch: s.branch, turn: s.turn,
                        minutes: blockMinutes(s.start, s.end),
                        isHoliday: s.turn === 2 ? !!hol2[date] : !!hol1[date]
                    };
                });
                // Aviso de solape entre turno 1 y turno 2 el mismo día.
                var t1 = segs.filter(function (s) { return s.turn === 1; });
                var t2 = segs.filter(function (s) { return s.turn === 2; });
                var overlap = t1.some(function (a) { return t2.some(function (b) { return segmentsOverlap(a, b); }); });
                if (overlap) nOverlap++;

                var conflictBlocks = conflicts[date] || [];
                if (conflictBlocks.length) {
                    nConf++;
                    if (scenario === 'append') {
                        conflictBlocks.forEach(function (c) {
                            segs.push({
                                start: c.start, end: c.end, branch: c.branch, existing: true,
                                minutes: blockMinutes(c.start, c.end),
                                isHoliday: !!hol1[date]
                            });
                        });
                    }
                }
                var r = (ORG === 'moderna')
                    ? computeDayModerna(date, segs)
                    : { totalMin: segs.reduce(function (a, s) { return a + s.minutes; }, 0), extraMin: 0 };
                var isHol = !!hol1[date] || !!hol2[date];
                if (isHol) nHol++;
                nDays++;
                var newMin = segs.filter(function (s) { return !s.existing; }).reduce(function (a, s) { return a + s.minutes; }, 0);
                // Origen de las extras del día: feriado vs excedente del umbral.
                var exHol = segs.reduce(function (a, s) { return a + (s.isHoliday ? (s.extraMin || 0) : 0); }, 0);
                var exThr = r.extraMin - exHol;
                totH += newMin;
                totEx += r.extraMin;
                totExHol += exHol;
                totExThr += exThr;
                var info = {
                    date: date, segs: segs, newMin: newMin, extraMin: r.extraMin,
                    exHol: exHol, exThr: exThr,
                    conflict: conflictBlocks.length > 0, holiday: isHol,
                    holidayName: hol1[date] || hol2[date] || '', overlap: overlap
                };
                days.push(info); byDate[date] = info;
            });
            return { days: days, byDate: byDate, totH: totH, totEx: totEx, totExHol: totExHol, totExThr: totExThr, nDays: nDays, nConf: nConf, nBlock: nBlock, nHol: nHol, nOverlap: nOverlap };
        }

        // ── Render ──
        function chip(html, cls) {
            return '<span class="rh-live-chip ' + (cls || '') + '">' + html + '</span>';
        }
        function dayCell(iso, dayNum, info) {
            var cls = ['rh-cal-day'];
            var badges = [];
            var hrs = '';
            var aria = '';
            if (info) {
                if (info.blocked) {
                    cls.push('is-blocked');
                    badges.push(icon('umbrella-beach', info.blocked));
                    hrs = '<span class="rh-cal-hrs">' + esc(info.blocked) + '</span>';
                    aria = DIAS[dow(iso)] + ' ' + ddmm(iso) + ': bloqueado por ' + info.blocked;
                } else {
                    cls.push('is-proposed');
                    hrs = '<span class="rh-cal-hrs">' + fmtH(info.newMin) + ' hs</span>';
                    aria = DIAS[dow(iso)] + ' ' + ddmm(iso) + ': ' + fmtH(info.newMin) + ' horas propuestas';
                    if (info.extraMin > 0) {
                        cls.push('has-extra');
                        hrs += '<span class="rh-cal-ex" title="' + esc(extrasOrigen(info)) + '">+' + fmtH(info.extraMin) + ' ex</span>';
                        aria += ', ' + fmtH(info.extraMin) + ' extra estimadas';
                    }
                    if (info.holiday) { cls.push('is-holiday'); badges.push(icon('star', 'Feriado')); aria += ', feriado'; }
                    if (info.conflict) { cls.push('has-conflict'); badges.push(icon('exclamation-triangle', 'Ya hay horas cargadas')); aria += ', ya hay horas cargadas'; }
                    if (info.segs.some(function (s) { return !s.existing && (s.start === '00:00' || s.end === '23:59'); })) {
                        badges.push(icon('moon', 'Turno que cruza medianoche'));
                    }
                }
            }
            if (iso === TODAY) cls.push('is-today');
            var attrs = info
                ? ' tabindex="0" role="button" data-date="' + iso + '" aria-label="' + esc(aria) + '"'
                : ' role="gridcell"';
            return '<div class="' + cls.join(' ') + '"' + attrs + '>' + dayNum + hrs + badges.join('') + '</div>';
        }
        function renderCalendars(state) {
            var dates = state.days.map(function (d) { return d.date; });
            if (!dates.length) { calendarsEl.innerHTML = ''; return; }
            var first = dates[0].slice(0, 7);
            var last = dates[dates.length - 1].slice(0, 7);
            var months = [];
            var cur = first;
            while (cur <= last && months.length < 5) {
                months.push(cur);
                var p = cur.split('-').map(Number);
                cur = (p[1] === 12 ? (p[0] + 1) + '-01' : p[0] + '-' + pad(p[1] + 1));
            }
            var html = '';
            months.forEach(function (ym) {
                html += '<div class="rh-live-month"><div class="rh-live-month-title">' + monthLabelEs(ym) + '</div>';
                html += '<div class="rh-cal" role="grid" aria-label="Previsualización ' + monthLabelEs(ym) + '">';
                ['Lu', 'Ma', 'Mi', 'Ju', 'Vi', 'Sá', 'Do'].forEach(function (h) {
                    html += '<div class="rh-cal-head" role="columnheader">' + h + '</div>';
                });
                var firstIso = ym + '-01';
                for (var i = 1; i < dow(firstIso); i++) html += '<div class="rh-cal-day is-empty" role="gridcell" aria-hidden="true"></div>';
                var dim = daysInMonth(ym);
                for (var d = 1; d <= dim; d++) {
                    var iso = ym + '-' + pad(d);
                    html += dayCell(iso, d, state.byDate[iso]);
                }
                html += '</div></div>';
            });
            calendarsEl.innerHTML = html;
        }
        function renderDetail(iso, state) {
            var info = state.byDate[iso];
            if (!info) { detailEl.innerHTML = ''; return; }
            var html = '<div class="rh-day-card ' + (info.holiday ? 'is-holiday' : '') + '">';
            html += '<div class="fw-semibold mb-2">' + DIAS[dow(iso)] + ' ' + ddmm(iso) + '</div>';
            if (info.blocked) {
                html += '<div class="small"><i class="fas fa-umbrella-beach me-1 text-muted"></i>Bloqueado por ' + esc(info.blocked) + ': este día se omite al guardar.</div>';
            } else {
                html += '<div class="d-flex flex-column gap-1">';
                info.segs.forEach(function (s) {
                    html += '<div class="rh-range-row"><span>'
                        + (s.existing ? '<span class="badge bg-light text-dark border me-2">ya cargado</span>' : '<span class="badge bg-primary me-2">T' + s.turn + '</span>')
                        + s.start + ' – ' + s.end
                        + (s.branch ? ' · ' + esc(s.branch) : '')
                        + '</span><span class="small text-muted">' + fmtH(s.minutes) + ' hs'
                        + (s.extraMin > 0
                            ? ' · <span class="text-warning fw-semibold" title="' + esc(extraSegMotivo(s, iso, info.holidayName)) + '"'
                              + ' data-rh-variant="' + (s.isHoliday ? 'danger' : 'warning') + '">+' + fmtH(s.extraMin) + ' extra</span>'
                            : '')
                        + '</span></div>';
                });
                html += '</div>';
                if (info.holiday) {
                    html += '<div class="small mt-2 text-danger"><i class="fas fa-star me-1"></i>Feriado' + (info.holidayName ? ' — ' + esc(info.holidayName) : '')
                        + (ORG === 'moderna' ? ': las horas de ese bloque cuentan como extra.' : '.') + '</div>';
                }
                if (info.conflict) {
                    html += '<div class="small mt-1 text-warning"><i class="fas fa-exclamation-triangle me-1"></i>Ya hay horas cargadas este día: al guardar vas a poder sobrescribir o agregar como turno adicional.</div>';
                }
                if (info.overlap) {
                    html += '<div class="small mt-1 text-danger"><i class="fas fa-exclamation-circle me-1"></i>Los dos turnos se superponen en el horario. Verificá las horas.</div>';
                }
            }
            html += '</div>';
            detailEl.innerHTML = html;
        }
        function renderTotals(state, input) {
            var chips = [];
            chips.push(chip('<i class="fas fa-calendar-plus text-primary"></i>Días a cargar: ' + state.nDays));
            chips.push(chip('<i class="fas fa-clock text-primary"></i>Horas del período: ' + fmtH(state.totH * 1) + ' hs'));
            if (ORG === 'moderna') {
                var origen = [];
                if (state.totExHol > 0) origen.push('Feriados: ' + fmtH(state.totExHol) + ' h');
                if (state.totExThr > 0) origen.push('Excedente del umbral diario: ' + fmtH(state.totExThr) + ' h');
                var tEx = origen.length ? ' title="Origen — ' + esc(origen.join(' · ')) + '" data-rh-variant="warning"' : '';
                chips.push('<span class="rh-live-chip"' + tEx + '><i class="fas fa-bolt text-warning"></i>Extras estimadas: ' + fmtH(state.totEx) + ' hs</span>');
            } else {
                chips.push(chip('<i class="fas fa-bolt text-muted"></i>Extras: clasificación 50/100 de la suite'));
            }
            var warn = [];
            if (state.nConf) warn.push(state.nConf + ' con horas ya cargadas');
            if (state.nBlock) warn.push(state.nBlock + ' bloqueado(s)');
            if (state.nHol) warn.push(state.nHol + ' feriado(s)');
            if (state.nOverlap) warn.push(state.nOverlap + ' con turnos superpuestos');
            if (warn.length) chips.push(chip('<i class="fas fa-exclamation-triangle text-warning"></i>' + warn.join(' · '), 'border-warning'));
            if (state.t2Invalid) {
                // El servidor rechaza TODO el envío con un turno 2 inválido: la
                // vista previa no puede mostrarlo como un plan válido solo-T1.
                chips.push(chip('<i class="fas fa-exclamation-circle text-danger"></i>Segundo turno incompleto o con entrada = salida: el guardado lo va a rechazar', 'border-danger'));
            }
            totalsEl.innerHTML = chips.join('');
            if (whoEl) {
                whoEl.textContent = input.employeeName
                    ? input.employeeName + ' · ' + state.nDays + ' día(s) · ' + ddmm(input.startDate) + ' al ' + ddmm(input.endDate)
                    : 'Elegí un empleado y el rango de fechas para ver la vista previa.';
            }
        }
        function renderEmpty(message) {
            totalsEl.innerHTML = '';
            calendarsEl.innerHTML = '<div class="text-center text-muted py-4"><i class="far fa-calendar-alt fs-3 d-block mb-2"></i>' + esc(message) + '</div>';
            detailEl.innerHTML = '';
            if (whoEl) whoEl.textContent = 'Elegí un empleado y el rango de fechas para ver la vista previa.';
            if (scenarioEl) scenarioEl.hidden = true;
        }

        // ── Overlay del servidor ──
        var serverData = null;
        var currentKey = '';
        var cache = new Map();
        var aborter = null;
        function overlayKey(input, extEnd) {
            return input.employeeId + '|' + input.startDate + '|' + extEnd + '|' + input.branch + '|' + (input.twoTurns ? input.branch2 : '');
        }
        function setServerState(html, cls) {
            serverStateEl.innerHTML = html;
            serverStateEl.className = 'form-text mt-2 ' + (cls || '');
        }
        function fetchOverlay(input) {
            if (!REAL) {
                serverData = null;
                setServerState('<i class="fas fa-info-circle me-1"></i>Vista de maqueta: sin verificación contra registros.');
                return;
            }
            var crosses = (input.t1e <= input.t1s) || (input.twoTurns && HM.test(input.t2s) && HM.test(input.t2e) && input.t2e <= input.t2s);
            var extEnd = input.endDate;
            if (crosses && daysBetween(input.startDate, input.endDate) + 1 < 93) {
                extEnd = addDays(input.endDate, 1);
            }
            // Rango de 93 días exactos con nocturno: el día de derrame no entra
            // en la consulta (tope del endpoint) — se avisa que quedó sin verificar.
            var capped = crosses && extEnd === input.endDate;
            var key = overlayKey(input, extEnd);
            currentKey = key;
            var okMsg = '<i class="fas fa-check text-success me-1"></i>Verificado contra registros existentes.'
                + (capped ? ' <span class="text-warning">El día de derrame posterior al rango quedó sin verificar (tope de 93 días); el guardado lo valida igual.</span>' : '');
            if (cache.has(key)) {
                serverData = cache.get(key);
                recalcRender();
                setServerState(okMsg);
                return;
            }
            if (aborter) aborter.abort();
            aborter = new AbortController();
            setServerState('<span class="spinner-border spinner-border-sm me-1"></span>Verificando conflictos y feriados…');
            var qs = URL_PREVIEW + '?employee_id=' + input.employeeId
                + '&start=' + input.startDate + '&end=' + extEnd
                + '&branch=' + encodeURIComponent(input.branch)
                + (input.twoTurns && input.branch2 ? '&branch2=' + encodeURIComponent(input.branch2) : '');
            fetch(qs, { headers: { 'Accept': 'application/json' }, credentials: 'same-origin', signal: aborter.signal })
                .then(function (r) { if (!r.ok) throw new Error('http'); return r.json(); })
                .then(function (data) {
                    if (currentKey !== key) return; // respuesta vieja
                    if (data && data.ok) {
                        serverData = data;
                        cache.set(key, data);
                        recalcRender();
                        setServerState(okMsg);
                    } else {
                        serverData = null;
                        recalcRender();
                        var msgs = {
                            schema: 'El módulo no está migrado en esta base.',
                            rango: 'El rango supera 93 días: acortalo para previsualizar.',
                            empleado: 'Empleado no disponible (inactivo o de otra organización).'
                        };
                        var m = msgs[(data || {}).error];
                        if (m) setServerState('<i class="fas fa-exclamation-triangle text-warning me-1"></i>' + esc(m));
                        else setServerState('');
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    if (currentKey !== key) return;
                    serverData = null;
                    recalcRender();
                    setServerState('<i class="fas fa-exclamation-triangle text-warning me-1"></i>No se pudieron verificar conflictos ni feriados; la estimación muestra solo horas.');
                });
        }

        // ── Recalcular + render ──
        function scenario() {
            var r = document.querySelector('input[name="rh_scenario"]:checked');
            return r ? r.value : 'append';
        }
        function recalcRender() {
            var input = readInput();
            if (!ISO.test(input.startDate) || !ISO.test(input.endDate) || !HM.test(input.t1s) || !HM.test(input.t1e) || !input.employeeId) {
                renderEmpty('Elegí un empleado y el rango de fechas para ver la vista previa.');
                return null;
            }
            if (input.startDate > input.endDate) {
                renderEmpty('La fecha "Desde" no puede ser posterior a "Hasta".');
                return null;
            }
            if (daysBetween(input.startDate, input.endDate) + 1 > 93) {
                renderEmpty('El rango no puede superar 93 días.');
                return null;
            }
            if (input.t1s === input.t1e) {
                renderEmpty('La entrada y la salida no pueden ser iguales.');
                return null;
            }
            // Overlay viejo de OTRO empleado: no pintar sus conflictos/bloqueos
            // mientras llega la respuesta nueva.
            if (serverData && currentKey && currentKey.split('|')[0] !== String(input.employeeId)) {
                serverData = null;
                setServerState('');
            }
            var plan = buildPlan(input);
            var state = computeTotals(plan, serverData, scenario());
            state.t2Invalid = input.twoTurns && (!HM.test(input.t2s) || !HM.test(input.t2e) || input.t2s === input.t2e);
            renderTotals(state, input);
            renderCalendars(state);
            if (scenarioEl) scenarioEl.hidden = state.nConf === 0;
            // conservar el detalle abierto si sigue en el plan
            var open = detailEl.querySelector('.rh-day-card');
            if (open) detailEl.innerHTML = '';
            lastState = state;
            return input;
        }
        var lastState = null;

        var recalcTimer = null, fetchTimer = null;
        function recalc() {
            clearTimeout(recalcTimer);
            recalcTimer = setTimeout(function () {
                var input = recalcRender();
                if (input) {
                    clearTimeout(fetchTimer);
                    fetchTimer = setTimeout(function () { fetchOverlay(input); }, 400);
                }
            }, 250);
        }

        // ── Wiring ──
        ['rhEmployee', 'rhStart', 'rhEnd', 'rhT1s', 'rhT1e', 'rhBranch', 'rhTwoTurns', 'rhT2s', 'rhT2e', 'rhBranch2'].forEach(function (id) {
            var f = el(id);
            if (f) {
                f.addEventListener('change', recalc);
                f.addEventListener('input', recalc);
            }
        });
        document.querySelectorAll('input[name="rh_scenario"]').forEach(function (r) {
            r.addEventListener('change', function () { recalcRender(); });
        });

        // Turno 2 visible sin JS; acá se sincroniza con el switch.
        var twoTurns = el('rhTwoTurns');
        var secondPanel = el('rhSecondTurn');
        function syncSecond() { if (secondPanel) secondPanel.hidden = !(twoTurns && twoTurns.checked); }
        if (twoTurns) twoTurns.addEventListener('change', syncSecond);
        syncSecond();

        // Empleado → sugerir su sucursal si aún no se eligió una.
        var employee = el('rhEmployee');
        var branchSel = el('rhBranch');
        if (employee && branchSel) {
            employee.addEventListener('change', function () {
                var opt = employee.options[employee.selectedIndex];
                if (!branchSel.value && opt && opt.dataset.branch) {
                    for (var i = 0; i < branchSel.options.length; i++) {
                        if (branchSel.options[i].value === opt.dataset.branch) {
                            branchSel.value = opt.dataset.branch;
                            break;
                        }
                    }
                }
            });
        }

        // "Hasta" se autocompleta con "Desde" (carga de un día en dos clics).
        var startInput = el('rhStart');
        var endInput = el('rhEnd');
        if (startInput && endInput) {
            startInput.addEventListener('change', function () {
                if (!endInput.value && startInput.value) endInput.value = startInput.value;
                endInput.min = startInput.value || '';
            });
        }

        // Detalle del día: delegación click + teclado.
        function handleDay(target) {
            var cell = target.closest('[data-date]');
            if (cell && lastState) renderDetail(cell.dataset.date, lastState);
        }
        calendarsEl.addEventListener('click', function (ev) { handleDay(ev.target); });
        calendarsEl.addEventListener('keydown', function (ev) {
            if (ev.key === 'Enter' || ev.key === ' ') {
                ev.preventDefault();
                handleDay(ev.target);
            }
        });

        // Doble envío: spinner en el botón (el POST navega normal, sin AJAX).
        form.addEventListener('submit', function () {
            var btn = el('rhSubmitBtn');
            if (btn) {
                btn.disabled = true;
                btn.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span>Guardando…';
            }
        });

        // Primer render (incluye rehidratación del panel de conflicto post-POST).
        recalcRender();
        var initial = readInput();
        if (initial.employeeId && ISO.test(initial.startDate) && ISO.test(initial.endDate) && initial.startDate <= initial.endDate) {
            fetchOverlay(initial);
        } else {
            setServerState('');
        }
    });
})();
