import { humanizarClave } from '@/lib/humanizar-clave';

// Traduce la etiqueta de un campo/subcampo/forma/grupo del catálogo fiscal
// usando los diccionarios portalFormulario.{campoLabel,subcampoLabel,
// formaLabel,grupoLabel} (en.json/es.json) — las claves ahí son las mismas
// claves técnicas que ya vienen del catálogo (ver
// App\Http\Controllers\PortalFormularioController), así que no hace falta
// mandar ningún texto desde el backend. Si una clave nueva todavía no tiene
// traducción, cae en `humanizarClave` (o el texto original, para grupo) en
// vez de mostrar la clave del diccionario sin resolver.
type Translate = (key: string, options?: Record<string, unknown>) => string;

export function campoLabel(t: Translate, campo: string): string {
    return t(`portalFormulario.campoLabel.${campo}`, {
        defaultValue: humanizarClave(campo),
    });
}

export function subcampoLabel(t: Translate, subcampo: string): string {
    return t(`portalFormulario.subcampoLabel.${subcampo}`, {
        defaultValue: humanizarClave(subcampo),
    });
}

export function formaCatalogoLabel(
    t: Translate,
    forma: string,
    fallback: string,
): string {
    return t(`portalFormulario.formaLabel.${forma}`, {
        defaultValue: fallback,
    });
}

export function grupoCatalogoLabel(t: Translate, etiqueta: string): string {
    return t(`portalFormulario.grupoLabel.${etiqueta}`, {
        defaultValue: etiqueta,
    });
}
