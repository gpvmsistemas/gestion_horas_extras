(function () {
    var cfg = window.PWA_CONFIG || {};
    var root = (cfg.urlRoot || '').replace(/\/$/, '');
    if (!root) {
        return;
    }

    var scope = cfg.scope || (root + '/');
    var installPromptEvent = null;
    var installStorageKey = 'pwa_install_dismissed_v1';
    var pushStorageKey = 'pwa_push_dismissed_v1';

    if ('serviceWorker' in navigator) {
        navigator.serviceWorker.register(root + '/sw.js', { scope: scope }).catch(function () {});
    }

    var tokenEl = document.querySelector('meta[name="csrf-token"]');
    var csrf = tokenEl ? tokenEl.getAttribute('content') : '';

    function urlBase64ToUint8Array(base64String) {
        var padding = '='.repeat((4 - (base64String.length % 4)) % 4);
        var base64 = (base64String + padding).replace(/-/g, '+').replace(/_/g, '/');
        var raw = window.atob(base64);
        var arr = new Uint8Array(raw.length);
        for (var i = 0; i < raw.length; i++) {
            arr[i] = raw.charCodeAt(i);
        }
        return arr;
    }

    function postSubscription(subscription, action) {
        if (!csrf) return Promise.resolve({ ok: false });
        var json = subscription.toJSON();
        var fd = new FormData();
        fd.append('csrf_token', csrf);
        fd.append('action', action);
        fd.append('endpoint', json.endpoint || '');
        if (json.keys) {
            fd.append('public_key', json.keys.p256dh || '');
            fd.append('auth_token', json.keys.auth || '');
        }
        return fetch(root + '/employee/pushSubscription', {
            method: 'POST',
            body: fd,
            credentials: 'same-origin',
        }).then(function (r) { return r.json(); });
    }

    function subscribePush() {
        if (!cfg.pushEnabled || !cfg.vapidPublicKey) {
            return Promise.reject(new Error('disabled'));
        }
        if (!('PushManager' in window) || !('Notification' in window)) {
            return Promise.reject(new Error('unsupported'));
        }
        return Notification.requestPermission().then(function (perm) {
            if (perm !== 'granted') {
                throw new Error('denied');
            }
            return navigator.serviceWorker.ready;
        }).then(function (reg) {
            return reg.pushManager.getSubscription().then(function (existing) {
                if (existing) {
                    return existing;
                }
                return reg.pushManager.subscribe({
                    userVisibleOnly: true,
                    applicationServerKey: urlBase64ToUint8Array(cfg.vapidPublicKey),
                });
            });
        }).then(function (subscription) {
            return postSubscription(subscription, 'subscribe');
        });
    }

    function unsubscribePush() {
        return navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription().then(function (sub) {
                if (!sub) {
                    return { ok: true };
                }
                var endpoint = sub.endpoint;
                return sub.unsubscribe().then(function () {
                    if (!csrf) return { ok: true };
                    var fd = new FormData();
                    fd.append('csrf_token', csrf);
                    fd.append('action', 'unsubscribe');
                    fd.append('endpoint', endpoint);
                    return fetch(root + '/employee/pushSubscription', {
                        method: 'POST',
                        body: fd,
                        credentials: 'same-origin',
                    }).then(function (r) { return r.json(); });
                });
            });
        });
    }

    function syncExistingSubscription() {
        if (!cfg.pushEnabled || !cfg.vapidPublicKey || Notification.permission !== 'granted') {
            return;
        }
        navigator.serviceWorker.ready.then(function (reg) {
            return reg.pushManager.getSubscription();
        }).then(function (sub) {
            if (sub) {
                return postSubscription(sub, 'subscribe');
            }
            return null;
        }).catch(function () {});
    }

    function updatePushStatusText(active) {
        var el = document.getElementById('pwaPushStatusText');
        if (!el) return;
        if (active) {
            el.innerHTML = '<span class="text-success"><i class="fas fa-check-circle me-1"></i>Notificaciones push activas en este dispositivo.</span>';
        } else {
            el.innerHTML = '<span class="text-secondary">Todavía no activaste las notificaciones push.</span>';
        }
    }

    window.RRHH_PWA = {
        subscribePush: subscribePush,
        unsubscribePush: unsubscribePush,
        isPushSupported: function () {
            return 'PushManager' in window && 'Notification' in window;
        },
        getPermission: function () {
            return Notification.permission;
        },
    };

    syncExistingSubscription();

    // Banner opt-in push
    var pushBanner = document.getElementById('pwaPushBanner');
    var btnEnable = document.getElementById('pwaPushEnableBtn');
    var btnDismiss = document.getElementById('pwaPushDismissBtn');

    function hidePushBanner() {
        if (pushBanner) pushBanner.classList.add('d-none');
    }

    if (pushBanner && cfg.pushEnabled) {
        var showPushBanner = window.RRHH_PWA.isPushSupported()
            && Notification.permission === 'default';
        try {
            if (localStorage.getItem(pushStorageKey) === '1') showPushBanner = false;
        } catch (e) {}
        if (showPushBanner) {
            pushBanner.classList.remove('d-none');
        } else {
            hidePushBanner();
        }
    }

    if (btnEnable) {
        btnEnable.addEventListener('click', function () {
            btnEnable.disabled = true;
            subscribePush().then(function (res) {
                if (res && res.ok) {
                    hidePushBanner();
                    updatePushStatusText(true);
                }
                btnEnable.disabled = false;
            }).catch(function () {
                btnEnable.disabled = false;
            });
        });
    }

    if (btnDismiss) {
        btnDismiss.addEventListener('click', function () {
            try { localStorage.setItem(pushStorageKey, '1'); } catch (e) {}
            hidePushBanner();
        });
    }

    // Panel en /employee/notifications
    var settingsEnable = document.getElementById('pwaPushSettingsEnable');
    var settingsDisable = document.getElementById('pwaPushSettingsDisable');
    if (settingsEnable) {
        settingsEnable.addEventListener('click', function () {
            settingsEnable.disabled = true;
            subscribePush().then(function (res) {
                if (res && res.ok) updatePushStatusText(true);
                settingsEnable.disabled = false;
            }).catch(function () {
                settingsEnable.disabled = false;
            });
        });
    }
    if (settingsDisable) {
        settingsDisable.addEventListener('click', function () {
            settingsDisable.disabled = true;
            unsubscribePush().then(function () {
                updatePushStatusText(false);
                settingsDisable.disabled = false;
            }).catch(function () {
                settingsDisable.disabled = false;
            });
        });
    }

    // Instalar PWA
    var installBanner = document.getElementById('pwaInstallBanner');
    var installBtn = document.getElementById('pwaInstallBtn');
    var installDismiss = document.getElementById('pwaInstallDismissBtn');
    var installSettingsCard = document.getElementById('pwaInstallSettingsCard');
    var installSettingsBtn = document.getElementById('pwaInstallSettingsBtn');
    var installActiveText = document.getElementById('pwaInstallActiveText');
    var installHelpModalEl = document.getElementById('pwaInstallHelpModal');
    var topbarInstallBtn = document.getElementById('pwaTopbarInstallBtn');
    var mobileInstallFab = document.getElementById('pwaMobileInstallFab');
    var mobileInstallBtn = document.getElementById('pwaMobileInstallBtn');

    function isStandalone() {
        return document.documentElement.classList.contains('pwa-is-installed')
            || window.matchMedia('(display-mode: standalone)').matches
            || window.matchMedia('(display-mode: fullscreen)').matches
            || window.navigator.standalone === true;
    }

    function markInstalled() {
        document.documentElement.classList.add('pwa-is-installed');
    }

    function markNeedsInstall() {
        document.documentElement.classList.remove('pwa-is-installed');
    }

    function isIos() {
        return /iPad|iPhone|iPod/.test(navigator.userAgent)
            || (navigator.platform === 'MacIntel' && navigator.maxTouchPoints > 1);
    }

    function hideInstallBanner() {
        if (installBanner) installBanner.classList.add('d-none');
    }

    function showInstallBanner() {
        if (installBanner) installBanner.classList.remove('d-none');
    }

    function updateInstallUi() {
        if (isStandalone()) {
            markInstalled();
            hideInstallBanner();
            if (installSettingsCard) installSettingsCard.classList.add('d-none');
            return;
        }
        markNeedsInstall();
        if (installSettingsCard) installSettingsCard.classList.remove('d-none');
        if (installActiveText) installActiveText.classList.add('d-none');
        try {
            if (sessionStorage.getItem(installStorageKey) !== '1') {
                showInstallBanner();
            } else {
                hideInstallBanner();
            }
        } catch (e) {
            showInstallBanner();
        }
    }

    function showInstallHelpModal() {
        if (!installHelpModalEl) return;
        var ios = document.getElementById('pwaInstallHelpIos');
        var android = document.getElementById('pwaInstallHelpAndroid');
        var desktop = document.getElementById('pwaInstallHelpDesktop');
        if (ios) ios.classList.add('d-none');
        if (android) android.classList.add('d-none');
        if (desktop) desktop.classList.add('d-none');
        if (isIos()) {
            if (ios) ios.classList.remove('d-none');
        } else if (/Android/i.test(navigator.userAgent)) {
            if (android) android.classList.remove('d-none');
        } else {
            if (desktop) desktop.classList.remove('d-none');
        }
        if (typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(installHelpModalEl).show();
        } else {
            installHelpModalEl.classList.add('show');
            installHelpModalEl.style.display = 'block';
        }
    }

    function promptInstall() {
        if (isStandalone()) {
            return Promise.resolve('installed');
        }
        if (installPromptEvent) {
            installPromptEvent.prompt();
            return installPromptEvent.userChoice.then(function (choice) {
                if (choice.outcome === 'accepted') {
                    hideInstallBanner();
                }
                installPromptEvent = null;
                return choice.outcome;
            });
        }
        showInstallHelpModal();
        return Promise.resolve('manual');
    }

    window.addEventListener('beforeinstallprompt', function (e) {
        e.preventDefault();
        installPromptEvent = e;
        updateInstallUi();
    });

    window.addEventListener('appinstalled', function () {
        installPromptEvent = null;
        markInstalled();
        hideInstallBanner();
        if (installSettingsCard) installSettingsCard.classList.add('d-none');
    });

    updateInstallUi();

    function bindInstallButton(btn) {
        if (!btn) return;
        btn.addEventListener('click', function () {
            promptInstall();
        });
    }

    bindInstallButton(installBtn);
    bindInstallButton(installSettingsBtn);
    bindInstallButton(topbarInstallBtn);
    bindInstallButton(mobileInstallBtn);

    if (installDismiss) {
        installDismiss.addEventListener('click', function () {
            try { sessionStorage.setItem(installStorageKey, '1'); } catch (e) {}
            hideInstallBanner();
        });
    }

    window.RRHH_PWA.isStandalone = isStandalone;
    window.RRHH_PWA.isIos = isIos;
    window.RRHH_PWA.promptInstall = promptInstall;
})();
