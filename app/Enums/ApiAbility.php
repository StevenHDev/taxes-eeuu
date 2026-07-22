<?php

namespace App\Enums;

enum ApiAbility: string
{
    case EventosWrite = 'eventos:write';
    case ClientesRead = 'clientes:read';
    case ClientesWrite = 'clientes:write';
    case RevealSensitive = 'clientes:reveal-sensitive';

    public function label(): string
    {
        return match ($this) {
            self::EventosWrite => 'Emitir eventos de recolección de datos (agente conversacional)',
            self::ClientesRead => 'Leer clientes, formas y campos',
            self::ClientesWrite => 'Corregir campos y marcar formas como revisadas',
            self::RevealSensitive => 'Revelar campos sensibles en texto plano',
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $ability) => ['value' => $ability->value, 'label' => $ability->label()],
            self::cases(),
        );
    }

    /**
     * @return array<int, string>
     */
    public static function values(): array
    {
        return array_map(fn (self $ability) => $ability->value, self::cases());
    }
}
