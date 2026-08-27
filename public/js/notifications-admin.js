(function () {
    var data = window.NOTIF_TARGETING || { users: [], picker_users: [], areas: [], companies: [] };
    var selectedIds = new Set();

    var targetAll = document.getElementById('targetAll');
    var targetDetails = document.getElementById('targetDetails');
    var previewList = document.getElementById('recipientPreviewList');
    var previewEmpty = document.getElementById('recipientPreviewEmpty');
    var countBadge = document.getElementById('recipientCountBadge');
    var btnUpdate = document.getElementById('btnUpdateRecipients');
    var recipientSearch = document.getElementById('recipientSearch');

    if (!previewList) {
        // Solo placeholders / targetAll en otras pantallas
        if (targetAll && targetDetails) {
            function sync() {
                var on = targetAll.checked;
                targetDetails.classList.toggle('opacity-50', on);
                targetDetails.querySelectorAll('input, select, button').forEach(function (el) {
                    if (el.id !== 'targetAll') el.disabled = on;
                });
            }
            targetAll.addEventListener('change', sync);
            sync();
        }
        bindPlaceholderTags();
        return;
    }

    function notifPlaceholderFields() {
        var fields = [];
        ['notifTitle', 'notifBody'].forEach(function (id) {
            var el = document.getElementById(id);
            if (el) fields.push(el);
        });
        document.querySelectorAll('textarea[name="body"], input[name="title"]').forEach(function (el) {
            if (fields.indexOf(el) === -1) fields.push(el);
        });
        return fields;
    }

    function activePlaceholderField() {
        var active = document.activeElement;
        if (active && (active.tagName === 'TEXTAREA' || (active.tagName === 'INPUT' && active.type === 'text'))) {
            return active;
        }
        return document.getElementById('notifBody')
            || document.getElementById('notifTitle')
            || document.querySelector('textarea[name="body"]');
    }

    function insertTagAt(field, tag, index) {
        if (!field || !tag) return;
        var start = typeof index === 'number' ? index : field.selectionStart;
        var end = typeof index === 'number' ? index : field.selectionEnd;
        var val = field.value;
        field.value = val.slice(0, start) + tag + val.slice(end);
        field.focus();
        var pos = start + tag.length;
        field.selectionStart = field.selectionEnd = pos;
    }

    function dropIndexInField(field, e) {
        if (field.setSelectionRange) {
            field.focus();
            try {
                if (document.caretPositionFromPoint) {
                    var pos = document.caretPositionFromPoint(e.clientX, e.clientY);
                    if (pos && pos.offsetNode === field) {
                        return pos.offset;
                    }
                }
            } catch (err) { /* ignore */ }
            var rect = field.getBoundingClientRect();
            var lineHeight = parseInt(window.getComputedStyle(field).lineHeight, 10) || 18;
            var line = Math.max(0, Math.min(
                (field.value.match(/\n/g) || []).length,
                Math.floor((e.clientY - rect.top + field.scrollTop - 4) / lineHeight)
            ));
            var lines = field.value.split('\n');
            var idx = 0;
            for (var i = 0; i < line && i < lines.length; i++) {
                idx += lines[i].length + 1;
            }
            var lastLine = lines[line] || '';
            var approxChars = Math.round(((e.clientX - rect.left - 8) / rect.width) * lastLine.length);
            return Math.min(field.value.length, idx + Math.max(0, approxChars));
        }
        return field.value.length;
    }

    function bindPlaceholderTags() {
        document.querySelectorAll('.notif-insert-tag').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                if (btn.classList.contains('notif-dragging')) return;
                insertTagAt(activePlaceholderField(), btn.getAttribute('data-tag') || '');
            });
            btn.addEventListener('dragstart', function (e) {
                var tag = btn.getAttribute('data-tag') || '';
                if (!tag) return;
                e.dataTransfer.setData('text/plain', tag);
                e.dataTransfer.effectAllowed = 'copy';
                btn.classList.add('notif-dragging');
            });
            btn.addEventListener('dragend', function () {
                btn.classList.remove('notif-dragging');
            });
        });

        notifPlaceholderFields().forEach(function (field) {
            field.addEventListener('dragover', function (e) {
                e.preventDefault();
                e.dataTransfer.dropEffect = 'copy';
                field.classList.add('notif-drop-active');
            });
            field.addEventListener('dragleave', function () {
                field.classList.remove('notif-drop-active');
            });
            field.addEventListener('drop', function (e) {
                e.preventDefault();
                field.classList.remove('notif-drop-active');
                var tag = e.dataTransfer.getData('text/plain');
                if (!tag) return;
                insertTagAt(field, tag, dropIndexInField(field, e));
            });
        });
    }

    function syncTargetAll() {
        if (!targetAll || !targetDetails) return;
        var on = targetAll.checked;
        targetDetails.classList.toggle('opacity-50', on);
        targetDetails.querySelectorAll('input, select, button').forEach(function (el) {
            el.disabled = on;
        });
        if (btnUpdate) btnUpdate.disabled = on;
    }

    function getCheckedValues(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector))
            .filter(function (el) { return el.checked; })
            .map(function (el) { return parseInt(el.value, 10); });
    }

    function getCheckedStrings(selector) {
        return Array.prototype.slice.call(document.querySelectorAll(selector))
            .filter(function (el) { return el.checked; })
            .map(function (el) { return el.value; });
    }

    function computeIdsFromFilters() {
        if (targetAll && targetAll.checked) {
            return data.users.map(function (u) { return u.id; });
        }
        var ids = new Set();
        var companyIds = getCheckedValues('.notif-filter-company');
        var areaIds = getCheckedValues('.notif-filter-area');
        var employeeGroups = getCheckedStrings('.notif-filter-group');

        companyIds.forEach(function (cid) {
            data.users.forEach(function (u) {
                if (u.company_id === cid) ids.add(u.id);
            });
        });

        areaIds.forEach(function (aid) {
            var area = data.areas.find(function (a) { return a.id === aid; });
            data.users.forEach(function (u) {
                if (u.area_id === null || u.area_id !== aid) return;
                if (area && area.company_id !== null && area.company_id > 0 && u.company_id !== area.company_id) return;
                ids.add(u.id);
            });
        });

        employeeGroups.forEach(function (group) {
            data.users.forEach(function (u) {
                if (u.employee_group === group) ids.add(u.id);
            });
        });

        return Array.from(ids);
    }

    function userById(id) {
        var u = data.users.find(function (x) { return x.id === id; });
        if (u) return u;
        return (data.picker_users || []).find(function (x) { return x.id === id; });
    }

    function populateAddRecipientSelect() {
        var sel = document.getElementById('addRecipientSelect');
        if (!sel) return;
        var list = (data.picker_users && data.picker_users.length) ? data.picker_users : data.users;
        var q = (document.getElementById('addRecipientSearch') || {}).value || '';
        q = q.toLowerCase().trim();
        sel.innerHTML = '<option value="">Elegir…</option>';
        list.slice().sort(function (a, b) {
            return (a.name || '').localeCompare(b.name || '', 'es');
        }).forEach(function (u) {
            var label = u.name + ' — ' + (u.company_name || 'Sin empresa') + ' · ' + (u.area_name || 'Sin área');
            if (u.role === 'admin') label += ' [admin]';
            if (q && label.toLowerCase().indexOf(q) === -1) return;
            var opt = document.createElement('option');
            opt.value = String(u.id);
            opt.textContent = label;
            sel.appendChild(opt);
        });
    }

    function renderPreview() {
        var q = (recipientSearch && recipientSearch.value || '').toLowerCase().trim();
        var ids = Array.from(selectedIds).sort(function (a, b) { return a - b; });
        previewList.innerHTML = '';

        if (ids.length === 0) {
            previewEmpty.style.display = 'block';
            previewList.style.display = 'none';
        } else {
            previewEmpty.style.display = 'none';
            previewList.style.display = 'block';
            ids.forEach(function (id) {
                var u = userById(id);
                if (!u) return;
                var label = u.name + ' — ' + (u.company_name || '') + (u.area_name ? ' · ' + u.area_name : ' · Sin área');
                if (q && label.toLowerCase().indexOf(q) === -1) return;

                var row = document.createElement('label');
                row.className = 'notif-recipient-row';
                row.innerHTML =
                    '<input type="checkbox" name="recipient_ids[]" value="' + id + '" checked class="form-check-input me-2 recipient-cb">' +
                    '<span>' + escapeHtml(label) + '</span>';
                previewList.appendChild(row);
            });
        }

        if (countBadge) {
            countBadge.textContent = selectedIds.size + ' seleccionado' + (selectedIds.size === 1 ? '' : 's');
        }
    }

    function escapeHtml(s) {
        var d = document.createElement('div');
        d.textContent = s;
        return d.innerHTML;
    }

    function updateFromFilters() {
        if (targetAll && targetAll.checked) {
            selectedIds = new Set(data.users.map(function (u) { return u.id; }));
        } else {
            selectedIds = new Set(computeIdsFromFilters());
        }
        renderPreview();
        if (selectedIds.size === 0 && !(targetAll && targetAll.checked)) {
            previewEmpty.textContent = 'Ningún empleado coincide con los filtros. Verificá que tengan rol «empleado», estén activos y tengan área asignada si filtrás por área.';
        }
    }

    if (btnUpdate) {
        btnUpdate.addEventListener('click', updateFromFilters);
    }
    if (targetAll) {
        targetAll.addEventListener('change', function () {
            syncTargetAll();
            if (targetAll.checked) updateFromFilters();
        });
    }
    syncTargetAll();

    document.getElementById('btnSelectAllRecipients').addEventListener('click', function () {
        previewList.querySelectorAll('.recipient-cb').forEach(function (cb) {
            cb.checked = true;
            selectedIds.add(parseInt(cb.value, 10));
        });
        renderPreview();
    });

    document.getElementById('btnSelectNoneRecipients').addEventListener('click', function () {
        selectedIds.clear();
        renderPreview();
    });

    previewList.addEventListener('change', function (e) {
        if (!e.target.classList.contains('recipient-cb')) return;
        var id = parseInt(e.target.value, 10);
        if (e.target.checked) selectedIds.add(id);
        else selectedIds.delete(id);
        if (countBadge) countBadge.textContent = selectedIds.size + ' seleccionados';
    });

    if (recipientSearch) {
        recipientSearch.addEventListener('input', renderPreview);
    }

    var btnAdd = document.getElementById('btnAddRecipient');
    if (btnAdd) {
        btnAdd.addEventListener('click', function () {
            var sel = document.getElementById('addRecipientSelect');
            var id = parseInt(sel.value, 10);
            if (!id) return;
            selectedIds.add(id);
            sel.value = '';
            renderPreview();
        });
    }
    var addSearch = document.getElementById('addRecipientSearch');
    if (addSearch) {
        addSearch.addEventListener('input', populateAddRecipientSelect);
    }
    populateAddRecipientSelect();

    var form = document.querySelector('form[action*="broadcastForm"], form[action*="announcementForm"]');
    if (form) {
        form.addEventListener('submit', function (e) {
            if (targetAll && targetAll.checked) return;
            if (selectedIds.size === 0) {
                e.preventDefault();
                alert('Seleccioná al menos un destinatario en la vista previa (Actualizar vista previa y/o Agregar empleado).');
                return;
            }
            form.querySelectorAll('input.notif-recipient-hidden').forEach(function (el) { el.remove(); });
            selectedIds.forEach(function (id) {
                var h = document.createElement('input');
                h.type = 'hidden';
                h.name = 'recipient_ids[]';
                h.value = String(id);
                h.className = 'notif-recipient-hidden';
                form.appendChild(h);
            });
        });
    }

    bindPlaceholderTags();
    renderPreview();
})();
