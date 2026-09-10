(function () {
    var token = document.querySelector('meta[name="csrf-token"]');
    if (!token) return;
    var csrf = token.getAttribute('content');
    var cfg = window.EMP_ANNOUNCEMENTS || window.PWA_CONFIG || {};
    var root = (cfg.urlRoot || '').replace(/\/$/, '');
    if (!root) return;

    function updateBadge(unread) {
        var badge = document.getElementById('empNotifyBadge');
        var count = document.getElementById('empNotifyMenuCount');
        var btn = document.getElementById('empNotifyBtn');
        unread = parseInt(unread, 10) || 0;

        if (unread === 0) {
            if (badge) badge.remove();
            if (btn) btn.classList.remove('has-alerts');
            if (count) count.remove();
            return;
        }

        if (btn) btn.classList.add('has-alerts');
        if (badge) {
            badge.textContent = unread > 99 ? '99+' : unread;
        } else if (btn) {
            var span = document.createElement('span');
            span.className = 'topbar-notify-badge';
            span.id = 'empNotifyBadge';
            span.textContent = unread > 99 ? '99+' : unread;
            btn.appendChild(span);
        }
        if (count) {
            count.textContent = unread;
        }
    }

    function markRead(id) {
        if (!id) return;
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fetch(root + '/employee/markNotificationRead/' + id, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (!data.ok) return;
                updateBadge(data.unread);
            })
            .catch(function () {});
    }

    function pollUnread() {
        fetch(root + '/employee/notificationUnreadCount', { credentials: 'same-origin' })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && typeof data.unread !== 'undefined') {
                    updateBadge(data.unread);
                }
            })
            .catch(function () {});
    }

    document.querySelectorAll('.emp-notif-item[data-notif-id]').forEach(function (el) {
        el.addEventListener('click', function () {
            markRead(el.getAttribute('data-notif-id'));
        });
    });

    pollUnread();
    setInterval(pollUnread, 60000);
    document.addEventListener('visibilitychange', function () {
        if (!document.hidden) pollUnread();
    });
})();
