{{-- Xquisite Monitoring – JS error beacon --}}
@if(config('services.monitoring.enabled'))
<script>
(function () {
    var endpoint = 'https://xquisite.brightfinance-x.co.za/js-error';
    var token    = @js(config('services.monitoring.token'));
    var project  = @js(config('app.name'));

    function report(message, source, line, col, stack) {
        try {
            var headers = { 'Content-Type': 'application/json' };
            if (token) { headers['Authorization'] = 'Bearer ' + token; }

            fetch(endpoint, {
                method: 'POST',
                headers: headers,
                body: JSON.stringify({
                    message: message || 'Unknown error',
                    source:  source  || null,
                    line:    line    || null,
                    col:     col     || null,
                    stack:   stack   || null,
                    url:     window.location.href,
                    project: project
                }),
                keepalive: true
            });
        } catch (_) {}
    }

    window.addEventListener('error', function (e) {
        report(e.message, e.filename, e.lineno, e.colno, e.error ? e.error.stack : null);
    });

    window.addEventListener('unhandledrejection', function (e) {
        var reason = e.reason;
        var message = reason && reason.message ? reason.message : String(reason);
        var stack = reason && reason.stack ? reason.stack : null;
        report('Unhandled promise rejection: ' + message, null, null, null, stack);
    });
})();
</script>
@endif
