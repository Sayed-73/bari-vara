<script>
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', function () {
            navigator.serviceWorker.register('{{ asset('sw.js') }}').catch(function () {
                // Swallow registration errors silently to avoid breaking the app.
            });
        });
    }
</script>

