<?php

namespace App\Enums;

enum TaxDocumentType: string
{
    case Identification = 'identification';
    case Dependent = 'dependent';
    case W2 = 'w2';
    case Form1099Nec = 'form_1099_nec';
    case BankStatement = 'bank_statement';
    case ProfitAndLoss = 'profit_and_loss';
    case BalanceSheet = 'balance_sheet';
    case DeductibleExpense = 'deductible_expense';
    case AssetDepreciation = 'asset_depreciation';
    case PriorYearReturn = 'prior_year_return';

    public function label(): string
    {
        return match ($this) {
            self::Identification => 'Identificación (SSN/ITIN)',
            self::Dependent => 'Cónyuge o dependiente',
            self::W2 => 'Formulario W-2',
            self::Form1099Nec => 'Formulario 1099-NEC',
            self::BankStatement => 'Estado de cuenta bancario',
            self::ProfitAndLoss => 'Estado de resultados (P&L)',
            self::BalanceSheet => 'Balance general',
            self::DeductibleExpense => 'Gasto deducible',
            self::AssetDepreciation => 'Activo / depreciación',
            self::PriorYearReturn => 'Declaración del año anterior',
        };
    }

    public function requiresFile(): bool
    {
        return match ($this) {
            self::W2,
            self::Form1099Nec,
            self::BankStatement,
            self::ProfitAndLoss,
            self::BalanceSheet,
            self::DeductibleExpense,
            self::AssetDepreciation,
            self::PriorYearReturn => true,
            default => false,
        };
    }

    public function requiresSsn(): bool
    {
        return match ($this) {
            self::Identification, self::Dependent => true,
            default => false,
        };
    }

    public function requiresDependentFields(): bool
    {
        return $this === self::Dependent;
    }

    public function requiresAmount(): bool
    {
        return match ($this) {
            self::DeductibleExpense, self::AssetDepreciation => true,
            default => false,
        };
    }

    /**
     * @return array<int, array{value: string, label: string}>
     */
    public static function options(): array
    {
        return array_map(
            fn (self $type) => ['value' => $type->value, 'label' => $type->label()],
            self::cases(),
        );
    }
}
