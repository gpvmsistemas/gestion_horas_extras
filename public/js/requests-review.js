(function () {
    'use strict';

    var initialized = false;

    function initRequestReview() {
        if (initialized) {
            return;
        }

        var panelEl = document.getElementById('requestReviewPanel');
        if (!panelEl) {
            return;
        }

        if (typeof bootstrap === 'undefined' || !bootstrap.Offcanvas) {
            return;
        }

        initialized = true;

        if (panelEl.parentElement && panelEl.parentElement !== document.body) {
            document.body.appendChild(panelEl);
        }

        var pendingMap = {};
        (window.REQUESTS_PENDING || []).forEach(function (r) {
            pendingMap[r.id] = r;
        });

        var panel = bootstrap.Offcanvas.getOrCreateInstance(panelEl);
        var form = document.getElementById('requestReviewForm');

        function formatDate(iso) {
            if (!iso) return '';
            var p = iso.split('-');
            if (p.length !== 3) return iso;
            return p[2] + '/' + p[1] + '/' + p[0];
        }

        function escapeHtml(str) {
            return String(str)
                .replace(/&/g, '&amp;')
                .replace(/</g, '&lt;')
                .replace(/>/g, '&gt;')
                .replace(/"/g, '&quot;');
        }

        function openRequest(id) {
            var req = pendingMap[id];
            if (!req) {
                console.warn('Solicitud no encontrada en bandeja:', id);
                return;
            }

            document.getElementById('reqReviewId').value = req.id;
            document.getElementById('requestReviewPanelLabel').textContent = req.full_name;
            document.getElementById('reqReviewSubtitle').textContent = req.type_name;

            var initial = (req.full_name || '?').charAt(0).toUpperCase();
            document.getElementById('reqReviewPerson').innerHTML =
                '<span class="req-review-avatar">' + initial + '</span>' +
                '<div><strong>' + escapeHtml(req.full_name) + '</strong>' +
                '<div class="small text-muted">' + escapeHtml(req.type_name) + '</div></div>';

            var range = formatDate(req.start_date);
            if (req.end_date && req.end_date !== req.start_date) {
                range += ' – ' + formatDate(req.end_date);
            }
            document.getElementById('reqReviewMeta').innerHTML =
                '<div class="req-review-chip"><i class="fas fa-calendar me-1"></i>' + range + '</div>';

            document.getElementById('reqReviewReason').textContent = req.reason || '—';
            document.getElementById('reqReviewNotes').value = req.admin_notes || '';

            var vacationBox = document.getElementById('reqVacationPreview');
            var exceptionBlock = document.getElementById('reqVacationExceptionBlock');
            var exceptionInput = document.getElementById('reqVacationException');
            if (exceptionInput) exceptionInput.value = '';
            if (req.vacation_preview && vacationBox) {
                var vp = req.vacation_preview;
                var html = '<strong>Imputación FIFO:</strong> ';
                if (!vp.ok) {
                    html += '<span class="text-danger">' + escapeHtml(vp.message || 'No se puede aprobar.') + '</span>';
                } else {
                    html += escapeHtml(String(vp.days)) + ' día(s). ';
                    html += (vp.allocations || []).map(function (a) {
                        return escapeHtml(a.period_label + ': ' + a.days);
                    }).join(' · ');
                    html += '<br><span class="text-muted">Disponible: ' + escapeHtml(String(vp.total_available))
                        + '. Saldo posterior estimado: ' + escapeHtml(String(vp.remaining_after))
                        + '. El resto seguirá pendiente.</span>';
                    if (vp.warnings && vp.warnings.length) {
                        html += '<hr class="my-2"><strong>Requiere excepción:</strong><br>' + vp.warnings.map(escapeHtml).join('<br>');
                    }
                }
                vacationBox.innerHTML = html;
                vacationBox.style.display = 'block';
                if (exceptionBlock) exceptionBlock.style.display = vp.requires_override ? 'block' : 'none';
            } else {
                if (vacationBox) { vacationBox.style.display = 'none'; vacationBox.innerHTML = ''; }
                if (exceptionBlock) exceptionBlock.style.display = 'none';
            }

            var certBlock = document.getElementById('reqReviewCertBlock');
            var certLink = document.getElementById('reqReviewCertLink');
            if (req.certificate_url) {
                certBlock.style.display = 'block';
                certLink.innerHTML = '<a href="' + escapeHtml(req.certificate_url) + '" target="_blank" rel="noopener" class="btn btn-sm btn-outline-secondary"><i class="fas fa-file-download me-1"></i> Ver certificado</a>';
            } else {
                certBlock.style.display = 'none';
                certLink.innerHTML = '';
            }

            var fileInput = document.getElementById('reqReviewCertificate');
            if (fileInput) {
                fileInput.value = '';
            }

            panel.show();
        }

        document.body.addEventListener('click', function (e) {
            var trigger = e.target.closest('[data-request-id].admin-mini-item--action, .js-open-request-review');
            if (!trigger) {
                return;
            }
            var id = parseInt(trigger.getAttribute('data-request-id'), 10);
            if (id) {
                e.preventDefault();
                openRequest(id);
            }
        });

        if (form) {
            form.addEventListener('submit', function (e) {
                var submitter = e.submitter;
                var action = submitter && submitter.value ? submitter.value : '';
                var titles = {
                    approve: ['¿Aprobar solicitud?', 'Se aprobará y actualizará saldos si corresponde (vacaciones).', 'question', 'Aprobar'],
                    reject: ['¿Rechazar solicitud?', 'La solicitud quedará rechazada.', 'warning', 'Rechazar'],
                    dismiss: ['¿Descartar de prioridad?', 'Seguirá pendiente pero no aparecerá en la campana ni en prioridad inmediata.', 'info', 'Descartar'],
                    save_certificate: ['¿Guardar datos?', 'Se guardarán notas y/o certificado sin cambiar el estado.', 'question', 'Guardar']
                };
                var cfg = titles[action];
                if (!cfg || typeof Swal === 'undefined') {
                    return;
                }
                e.preventDefault();
                Swal.fire({
                    title: cfg[0],
                    text: cfg[1],
                    icon: cfg[2],
                    showCancelButton: true,
                    confirmButtonColor: '#c4156f',
                    cancelButtonColor: '#6b7280',
                    confirmButtonText: cfg[3],
                    cancelButtonText: 'Cancelar'
                }).then(function (result) {
                    if (result.isConfirmed) {
                        var hidden = form.querySelector('input[type="hidden"][name="action"]');
                        if (!hidden) {
                            hidden = document.createElement('input');
                            hidden.type = 'hidden';
                            hidden.name = 'action';
                            form.appendChild(hidden);
                        }
                        hidden.value = action;
                        form.submit();
                    }
                });
            });
        }

        var openId = parseInt(window.REQUESTS_OPEN_ID, 10);
        if (openId && pendingMap[openId]) {
            openRequest(openId);
        }
    }

    function boot() {
        initRequestReview();
        if (!initialized) {
            window.addEventListener('load', initRequestReview);
        }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', boot);
    } else {
        boot();
    }
})();
