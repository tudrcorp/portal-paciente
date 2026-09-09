<div class="flex flex-col size-20 items-center justify-center">
    {{-- <x-app-logo-icon class="size-5 fill-current text-white dark:text-black" /> --}}
    <img id="theme-logo" src="{{ asset('images/logoNewTDG.png') }}" class=" fill-current text-white dark:text-black" alt="">
    {{-- <div class="ms-1 grid flex-1 text-start text-sm">
        <span class="mb-0.5 truncate leading-tight font-semibold">Portal del Paciente</span>
    </div> --}}
    <script>
        function updateLogo() {
            const logo = document.getElementById('theme-logo');
            const isDark = document.documentElement.classList.contains('dark');

            if (isDark) {
                logo.src = "{{ asset('images/logoWhiteTDG.png') }}";
            } else {
                logo.src = "{{ asset('images/logoNewTDG.png') }}";
            }
        }

        // Ejecutar al cargar la página
        document.addEventListener('DOMContentLoaded', updateLogo);

        // Opcional: escuchar cambios de tema si usas un toggle
        // Por ejemplo, si cambias la clase 'dark' en <html> dinámicamente:
        const observer = new MutationObserver(updateLogo);
        observer.observe(document.documentElement, {
            attributes: true
            , attributeFilter: ['class']
        });

    </script>

</div>
