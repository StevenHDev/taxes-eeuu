<?php

namespace App\Enums;

enum TaxDocumentAbility: string
{
    case Read = 'tax-documents:read';
    case Write = 'tax-documents:write';
    case RevealSsn = 'tax-documents:reveal-ssn';

    public function label(): string
    {
        return match ($this) {
            self::Read => 'Leer documentos fiscales',
            self::Write => 'Crear, editar y eliminar documentos fiscales',
            self::RevealSsn => 'Revelar SSN/ITIN en texto plano',
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
