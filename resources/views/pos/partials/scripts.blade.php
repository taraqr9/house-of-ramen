{{--
    Shared POS helpers:
    - posRequest(): fetch with CSRF + JSON, rejects with the server's message
      (App\Exceptions\PosException returns {message} with 422).
    - posToast(): SweetAlert2 toast.
    - posBeep(): short Web Audio tone - no sound file needed. Browsers only
      allow audio after the user has interacted with the page, so screens
      that beep show an "Enable sound" button.
    - posUuid(): per-submission idempotency key.
--}}
<script>
    window.posRequest = function (url, method, body) {
        const token = document.querySelector('meta[name="csrf-token"]').getAttribute('content');

        return fetch(url, {
            method: method,
            headers: {
                'X-CSRF-TOKEN': token,
                'Accept': 'application/json',
                'Content-Type': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
            },
            body: body ? JSON.stringify(body) : undefined,
        }).then(async (response) => {
            let data = {};
            try { data = await response.json(); } catch (e) { /* non-JSON */ }

            if (!response.ok) {
                let message = data.message || 'Request failed, please try again.';
                if (data.errors) {
                    message = Object.values(data.errors).flat()[0] || message;
                }
                throw new Error(message);
            }

            return data;
        });
    };

    window.posToast = function (icon, title) {
        Swal.fire({toast: true, position: 'top-end', icon: icon, title: title, showConfirmButton: false, timer: icon === 'error' ? 3500 : 1500, timerProgressBar: true});
    };

    window.posAudioCtx = null;
    window.posSoundEnabled = false;

    window.posEnableSound = function () {
        try {
            window.posAudioCtx = window.posAudioCtx || new (window.AudioContext || window.webkitAudioContext)();
            window.posAudioCtx.resume();
            window.posSoundEnabled = true;
            window.posBeep();
        } catch (e) {
            window.posSoundEnabled = false;
        }
        return window.posSoundEnabled;
    };

    window.posBeep = function () {
        if (!window.posSoundEnabled || !window.posAudioCtx) return;
        const ctx = window.posAudioCtx;
        [0, 0.18].forEach((offset) => {
            const osc = ctx.createOscillator();
            const gain = ctx.createGain();
            osc.type = 'sine';
            osc.frequency.value = 880;
            gain.gain.setValueAtTime(0.25, ctx.currentTime + offset);
            gain.gain.exponentialRampToValueAtTime(0.001, ctx.currentTime + offset + 0.15);
            osc.connect(gain).connect(ctx.destination);
            osc.start(ctx.currentTime + offset);
            osc.stop(ctx.currentTime + offset + 0.16);
        });
    };

    window.posUuid = function () {
        if (window.crypto && crypto.randomUUID) return crypto.randomUUID();
        return 'k' + Date.now().toString(36) + Math.random().toString(36).slice(2, 12);
    };

    window.posEscape = function (value) {
        return String(value ?? '').replace(/[&<>"']/g, (c) => ({'&': '&amp;', '<': '&lt;', '>': '&gt;', '"': '&quot;', "'": '&#39;'}[c]));
    };

    window.posMinutesAgo = function (iso) {
        if (!iso) return '';
        const mins = Math.max(0, Math.floor((Date.now() - new Date(iso).getTime()) / 60000));
        return mins < 1 ? 'just now' : mins + ' min ago';
    };
</script>
