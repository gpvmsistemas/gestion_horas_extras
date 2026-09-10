(function () {
    'use strict';

    function ageLabelFromBirth(isoDate) {
        if (!isoDate || !/^\d{4}-\d{2}-\d{2}$/.test(isoDate)) return '';
        var parts = isoDate.split('-');
        var birth = new Date(Number(parts[0]), Number(parts[1]) - 1, Number(parts[2]));
        var today = new Date();
        today.setHours(0, 0, 0, 0);
        if (birth > today) return '';
        var years = today.getFullYear() - birth.getFullYear();
        var months = today.getMonth() - birth.getMonth();
        var days = today.getDate() - birth.getDate();
        if (months < 0 || (months === 0 && days < 0)) years -= 1;
        if (years >= 1) return years === 1 ? '1 año' : years + ' años';
        months = (today.getFullYear() - birth.getFullYear()) * 12 + (today.getMonth() - birth.getMonth());
        if (today.getDate() < birth.getDate()) months -= 1;
        if (months >= 1) return months === 1 ? '1 mes' : months + ' meses';
        var diffMs = today - birth;
        var dayCount = Math.max(0, Math.floor(diffMs / 86400000));
        return dayCount === 1 ? '1 día' : dayCount + ' días';
    }

    function updateAgeLabel(row) {
        var birthInput = row.querySelector('.employee-child-birth');
        var ageWrap = row.querySelector('.employee-child-age-label');
        if (!birthInput || !ageWrap) return;
        var label = ageLabelFromBirth(birthInput.value);
        ageWrap.textContent = label ? 'Edad: ' + label : '';
    }

    function updateCountLabel(block) {
        var label = block.querySelector('.employee-children-count-label');
        var rows = block.querySelectorAll('.employee-children-rows .employee-child-row:not([hidden])');
        if (!label) return;
        var n = rows.length;
        label.textContent = n === 1 ? '1 hijo/a registrado' : n + ' hijos/as registrados';
    }

    function reindexRows(container) {
        container.querySelectorAll('.employee-child-row:not([data-child-row-template])').forEach(function (row, index) {
            row.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace(/children_rows\[[^\]]+\]/, 'children_rows[' + index + ']');
            });
        });
    }

    function bindRow(block, row) {
        row.querySelectorAll('.employee-child-birth').forEach(function (input) {
            input.addEventListener('change', function () { updateAgeLabel(row); });
            input.addEventListener('input', function () { updateAgeLabel(row); });
        });
        var removeBtn = row.querySelector('.employee-child-remove');
        if (removeBtn) {
            removeBtn.addEventListener('click', function () {
                var list = block.querySelector('.employee-children-rows');
                row.remove();
                reindexRows(list);
                updateCountLabel(block);
            });
        }
        updateAgeLabel(row);
    }

    function initBlock(block) {
        var toggle = block.querySelector('#has_children');
        var panel = block.querySelector('#employeeChildrenPanel');
        var list = block.querySelector('.employee-children-rows');
        var template = block.querySelector('[data-child-row-template]');
        var addBtn = block.querySelector('#employeeChildAddBtn');
        if (!toggle || !panel || !list || !template || !addBtn) return;

        function syncPanel() {
            panel.classList.toggle('d-none', !toggle.checked);
        }
        toggle.addEventListener('change', syncPanel);
        syncPanel();

        list.querySelectorAll('.employee-child-row:not([data-child-row-template])').forEach(function (row) {
            bindRow(block, row);
        });

        addBtn.addEventListener('click', function () {
            if (!toggle.checked) {
                toggle.checked = true;
                syncPanel();
            }
            var index = list.querySelectorAll('.employee-child-row:not([data-child-row-template])').length;
            var clone = template.cloneNode(true);
            clone.removeAttribute('hidden');
            clone.removeAttribute('data-child-row-template');
            clone.querySelectorAll('[name]').forEach(function (el) {
                el.name = el.name.replace('__INDEX__', String(index));
                if (el.classList.contains('employee-child-birth')) el.value = '';
                if (el.classList.contains('employee-child-sex')) el.value = '';
            });
            list.appendChild(clone);
            bindRow(block, clone);
            updateCountLabel(block);
            var birth = clone.querySelector('.employee-child-birth');
            if (birth) birth.focus();
        });

        updateCountLabel(block);
    }

    document.addEventListener('DOMContentLoaded', function () {
        document.querySelectorAll('.employee-children-block').forEach(initBlock);
    });
})();
