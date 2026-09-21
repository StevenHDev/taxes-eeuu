// Convierte una clave técnica (snake_case) en una etiqueta legible:
// "nombre_completo" -> "Nombre completo", "ssn" -> "SSN", "w2" -> "W2".
// Compartido entre clientes/show.tsx y derivation-logs-panel.tsx — antes
// vivía solo en show.tsx, sin forma de reusarlo desde el panel de
// derivación sin duplicarlo.
const ACRONIMOS = new Set(['ssn', 'itin', 'rfc', 'ein', 'w2', 'id', 'irs', 'usa']);

export function humanizarClave(clave: string): string {
    return clave
        .split(/[_\s]+/)
        .filter(Boolean)
        .map((palabra, i) => {
            if (ACRONIMOS.has(palabra.toLowerCase())) {
                return palabra.toUpperCase();
            }

            return i === 0
                ? palabra.charAt(0).toUpperCase() + palabra.slice(1)
                : palabra;
        })
        .join(' ');
}
