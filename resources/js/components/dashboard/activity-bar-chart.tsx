type Punto = { fecha: string; cantidad: number };

function diaCorto(fecha: string): string {
    return new Date(`${fecha}T00:00:00`).toLocaleDateString('es-AR', {
        weekday: 'short',
    });
}

/**
 * Gráfico de barras de la actividad diaria (campos recibidos por día).
 * Dibuja líneas guía horizontales de fondo y una barra por día con degradado
 * en el color primario de la marca. Cada barra crece desde la base con un
 * leve escalonado en la carga (clase `dash-bar` + animation-delay).
 */
export function ActivityBarChart({ data }: { data: Punto[] }) {
    const max = Math.max(1, ...data.map((d) => d.cantidad));

    return (
        <div className="relative @container">
            {/* Líneas guía */}
            <div className="pointer-events-none absolute inset-x-0 top-0 flex h-40 flex-col justify-between">
                {[0, 1, 2, 3].map((i) => (
                    <div
                        key={i}
                        className="border-t border-dashed border-border/50"
                    />
                ))}
            </div>

            <div className="relative flex h-40 items-end gap-1.5 @sm:gap-3">
                {data.map((d, i) => (
                    <div
                        key={d.fecha}
                        className="group flex flex-1 flex-col items-center justify-end"
                    >
                        <span className="mb-1.5 text-[11px] font-medium tabular-nums text-muted-foreground opacity-0 transition-opacity duration-200 group-hover:opacity-100">
                            {d.cantidad}
                        </span>
                        <div className="flex w-full items-end justify-center">
                            <div
                                className="dash-bar w-full max-w-10 rounded-md bg-gradient-to-t from-primary/65 to-primary shadow-sm transition-colors duration-300 group-hover:from-primary group-hover:to-primary/85"
                                style={{
                                    height: `${Math.max(6, (d.cantidad / max) * 128)}px`,
                                    animationDelay: `${i * 70}ms`,
                                }}
                                title={`${d.cantidad} campo(s)`}
                            />
                        </div>
                    </div>
                ))}
            </div>

            <div className="mt-2.5 flex gap-1.5 @sm:gap-3">
                {data.map((d) => (
                    <span
                        key={d.fecha}
                        className="flex-1 text-center text-[11px] font-medium capitalize text-muted-foreground"
                    >
                        {diaCorto(d.fecha)}
                    </span>
                ))}
            </div>
        </div>
    );
}
