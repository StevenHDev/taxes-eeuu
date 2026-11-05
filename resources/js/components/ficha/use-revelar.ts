import { useState } from 'react';

function csrf(): string {
    return (
        document
            .querySelector('meta[name="csrf-token"]')
            ?.getAttribute('content') ?? ''
    );
}

export type Revelar = {
    /** `undefined` = todavía no se reveló; `null` = el dato no tiene valor. */
    valor: string | null | undefined;
    revelando: boolean;
    /** El servidor pidió confirmar la contraseña (423). */
    pideContrasena: boolean;
    /** Falló por otra razón; la pantalla muestra `ficha.revelarError`. */
    fallo: boolean;
    revelar: () => Promise<void>;
};

/**
 * Revela un dato sensible con los endpoints que ya existen (POST, responden
 * `{ valor }`; 423 = hay que confirmar la contraseña). No guarda nada fuera
 * del estado del componente.
 */
export function useRevelar(url: string): Revelar {
    const [valor, setValor] = useState<string | null | undefined>(undefined);
    const [revelando, setRevelando] = useState(false);
    const [pideContrasena, setPideContrasena] = useState(false);
    const [fallo, setFallo] = useState(false);

    const revelar = async () => {
        if (revelando) {
            return;
        }

        setRevelando(true);
        setFallo(false);

        try {
            const respuesta = await fetch(url, {
                method: 'POST',
                headers: {
                    Accept: 'application/json',
                    'X-CSRF-TOKEN': csrf(),
                },
            });

            if (respuesta.status === 423) {
                setPideContrasena(true);

                return;
            }

            if (!respuesta.ok) {
                setFallo(true);

                return;
            }

            setPideContrasena(false);
            setValor((await respuesta.json()).valor ?? null);
        } catch {
            setFallo(true);
        } finally {
            setRevelando(false);
        }
    };

    return { valor, revelando, pideContrasena, fallo, revelar };
}
