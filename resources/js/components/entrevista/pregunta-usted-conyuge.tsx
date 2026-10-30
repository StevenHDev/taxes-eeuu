import { useTranslation } from 'react-i18next';
import { Checkbox } from '@/components/ui/checkbox';
import { Label } from '@/components/ui/label';
import type { PreguntaPaso, ValorEntrevista } from '@/types/entrevista';
import { PreguntaCampo } from './pregunta-campo';
import { PreguntaSiNo } from './pregunta-si-no';

/**
 * "Usted o cónyuge" fuera de la lista de casillas (por ejemplo A6). Sin
 * cónyuge es un sí o no; con cónyuge, una casilla para cada uno ("Yo",
 * "Mi cónyuge") y ninguna marcada es "no".
 */
export function PreguntaUstedConyuge({
    pregunta,
    valor,
    conConyuge,
    error,
    onCambiar,
}: {
    pregunta: PreguntaPaso;
    valor: ValorEntrevista;
    conConyuge: boolean;
    error?: string;
    onCambiar: (valor: ValorEntrevista) => void;
}) {
    const { t } = useTranslation();

    if (!conConyuge) {
        return (
            <PreguntaSiNo
                pregunta={pregunta}
                valor={
                    Array.isArray(valor)
                        ? valor.includes('usted')
                            ? 'si'
                            : 'no'
                        : valor
                }
                error={error}
                onCambiar={(v) => onCambiar(v === 'si' ? ['usted'] : 'no')}
            />
        );
    }

    const lista = Array.isArray(valor) ? valor : [];

    const cambiar = (quien: 'usted' | 'conyuge', activo: boolean) => {
        const nueva = (['usted', 'conyuge'] as const).filter((q) =>
            q === quien ? activo : lista.includes(q),
        );
        onCambiar(nueva.length > 0 ? nueva : 'no');
    };

    return (
        <PreguntaCampo pregunta={pregunta} error={error}>
            <div className="grid gap-2 sm:grid-cols-2">
                {(['usted', 'conyuge'] as const).map((quien) => (
                    <div
                        key={quien}
                        className="flex items-center gap-3 rounded-md border border-border px-3 hover:bg-accent"
                    >
                        <Checkbox
                            id={`${pregunta.codigo}-${quien}`}
                            checked={lista.includes(quien)}
                            onCheckedChange={(v) => cambiar(quien, v === true)}
                        />
                        <Label
                            htmlFor={`${pregunta.codigo}-${quien}`}
                            className="flex min-h-11 flex-1 cursor-pointer items-center font-normal"
                        >
                            {t(`entrevista.quien.${quien}`)}
                        </Label>
                    </div>
                ))}
            </div>
            <p className="text-xs text-muted-foreground">
                {t('entrevista.ningunoMarcado')}
            </p>
        </PreguntaCampo>
    );
}
