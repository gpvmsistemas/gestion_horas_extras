(function () {
    if (typeof Swal === 'undefined') return;
    var token = document.querySelector('meta[name="csrf-token"]');
    if (!token) return;
    var csrf = token.getAttribute('content');
    var cfg = window.EMP_ANNOUNCEMENTS || {};
    var root = cfg.urlRoot || '';

    function postAnnouncement(id, action) {
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        return fetch(root + '/employee/' + action + '/' + id, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); });
    }

    function dismiss(id) {
        return postAnnouncement(id, 'dismissAnnouncement');
    }

    function recordShown(id) {
        return postAnnouncement(id, 'recordAnnouncementShown');
    }

    function showNext(queue) {
        if (!queue.length) return;
        var item = queue.shift();
        var html = '';
        if (item.video_url) {
            html += '<video src="' + item.video_url.replace(/"/g, '&quot;') + '" class="announcement-modal-video" controls playsinline preload="metadata"></video>';
        } else if (item.image_url) {
            html += '<img src="' + item.image_url.replace(/"/g, '&quot;') + '" class="announcement-modal-img" alt="">';
        }
        html += '<div class="text-start announcement-body">' + item.body + '</div>';
        var opts = {
            title: item.title,
            html: html,
            showCloseButton: true,
            confirmButtonText: 'Cerrar',
            width: item.video_url ? '38rem' : '32rem',
            customClass: { popup: 'announcement-swal' },
        };
        if (item.link_url) {
            opts.showDenyButton = true;
            opts.denyButtonText = item.link_label || 'Ver más';
        }
        recordShown(item.id).finally(function () {
            Swal.fire(opts).then(function (result) {
                if (result.isDenied && item.link_url) {
                    window.open(item.link_url, '_blank', 'noopener');
                }
                dismiss(item.id).finally(function () {
                    showNext(queue);
                });
            });
        });
    }

    fetch(root + '/employee/pendingAnnouncements')
        .then(function (r) { return r.json(); })
        .then(function (data) {
            if (data.items && data.items.length) showNext(data.items.slice());
        })
        .catch(function () {});
})();
