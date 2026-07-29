import inertia from '@inertiajs/vite';
import { wayfinder } from '@laravel/vite-plugin-wayfinder';
import tailwindcss from '@tailwindcss/vite';
import react from '@vitejs/plugin-react';
import laravel from 'laravel-vite-plugin';
import { bunny } from 'laravel-vite-plugin/fonts';
import { defineConfig } from 'vite';

export default defineConfig({
    plugins: [
        laravel({
            input: ['resources/css/app.css', 'resources/js/app.tsx'],
            refresh: true,
            fonts: [
                // Poppins queda como cara de marca, solo en display grande —
                // es donde una geométrica rinde. Ya no carga 400/500 porque
                // el texto corrido pasó a Instrument Sans.
                bunny('Poppins', {
                    weights: [600, 700],
                }),
                // Caballo de batalla de la interfaz: legible a 13-14px, que es
                // donde vive casi todo el producto.
                bunny('Instrument Sans', {
                    weights: [400, 500, 600],
                }),
                // Todo dato (SSN, montos, fechas, valores enmascarados): cifras
                // tabulares y 0/O inconfundibles.
                bunny('IBM Plex Mono', {
                    weights: [400, 500],
                }),
            ],
        }),
        inertia(),
        react({
            babel: {
                plugins: ['babel-plugin-react-compiler'],
            },
        }),
        tailwindcss(),
        wayfinder({
            formVariants: true,
        }),
    ],
});
