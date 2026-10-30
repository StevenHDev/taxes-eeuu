<?php

namespace App\Services\Consentimiento;

use App\Models\Consentimiento\Consentimiento;
use App\Models\Consentimiento\ConsentimientoTexto;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Consentimiento §7216 del portal (F2.4): qué texto está vigente, si el
 * cliente ya lo firmó para el año y la firma. Lo usan el middleware
 * EnsureConsentimiento7216, la pantalla de firma y el freno que impide pasar
 * a un cliente real a la entrevista nueva con un texto provisional.
 */
class Consentimientos
{
    /**
     * La versión vigente: la más alta cuyo `vigente_desde` ya llegó.
     */
    public function vigente(string $codigo = ConsentimientoTexto::USO_7216): ?ConsentimientoTexto
    {
        return ConsentimientoTexto::query()
            ->where('codigo', $codigo)
            ->whereDate('vigente_desde', '<=', now())
            ->orderByDesc('version')
            ->first();
    }

    /**
     * Si el cliente firmó la versión vigente para el año. Un texto nuevo
     * vuelve a pedir la firma; si L1 pide renovar cada año, ya se compara
     * por año.
     */
    public function firmado(User $cliente, int $taxYear, string $codigo = ConsentimientoTexto::USO_7216): bool
    {
        $vigente = $this->vigente($codigo);

        return $vigente !== null && Consentimiento::query()
            ->where('user_id', $cliente->id)
            ->where('tax_year', $taxYear)
            ->where('consentimiento_texto_id', $vigente->id)
            ->exists();
    }

    /**
     * Si el texto vigente todavía es el provisional: entonces solo lo
     * firman usuarios de prueba.
     */
    public function soloPruebas(string $codigo = ConsentimientoTexto::USO_7216): bool
    {
        return $this->vigente($codigo)?->provisional ?? true;
    }

    /**
     * @throws InvalidArgumentException si la versión firmada no es la vigente
     */
    public function firmar(User $cliente, int $taxYear, int $version, string $nombreEscrito, ?string $ip, ?string $userAgent, string $codigo = ConsentimientoTexto::USO_7216): Consentimiento
    {
        return DB::transaction(function () use ($cliente, $taxYear, $version, $nombreEscrito, $ip, $userAgent, $codigo) {
            $vigente = $this->vigente($codigo);

            if ($vigente === null || $vigente->version !== $version) {
                throw new InvalidArgumentException(__('El texto del consentimiento cambió: léelo de nuevo antes de firmar.'));
            }

            return Consentimiento::query()->create([
                'user_id' => $cliente->id,
                'tax_year' => $taxYear,
                'consentimiento_texto_id' => $vigente->id,
                'nombre_escrito' => $nombreEscrito,
                'aceptado_en' => now(),
                'ip' => $ip,
                'user_agent' => $userAgent === null ? null : mb_substr($userAgent, 0, 500),
            ]);
        });
    }
}
