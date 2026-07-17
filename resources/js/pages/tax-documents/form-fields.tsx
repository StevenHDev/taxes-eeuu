import { useState } from 'react';
import InputError from '@/components/input-error';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import {
    Select,
    SelectContent,
    SelectItem,
    SelectTrigger,
    SelectValue,
} from '@/components/ui/select';
import { Textarea } from '@/components/ui/textarea';
import type {
    TaxDocument,
    TaxDocumentClient,
    TaxDocumentType,
    TaxDocumentTypeOption,
} from '@/types';

const REQUIRES_SSN: TaxDocumentType[] = ['identification', 'dependent'];
const REQUIRES_DEPENDENT_FIELDS: TaxDocumentType[] = ['dependent'];
const REQUIRES_AMOUNT: TaxDocumentType[] = [
    'deductible_expense',
    'asset_depreciation',
];
const REQUIRES_FILE: TaxDocumentType[] = [
    'w2',
    'form_1099_nec',
    'bank_statement',
    'profit_and_loss',
    'balance_sheet',
    'deductible_expense',
    'asset_depreciation',
    'prior_year_return',
];

type Props = {
    types: TaxDocumentTypeOption[];
    clients: TaxDocumentClient[];
    errors: Partial<Record<string, string>>;
    document?: TaxDocument;
};

export default function TaxDocumentFormFields({
    types,
    clients,
    errors,
    document,
}: Props) {
    const [type, setType] = useState<TaxDocumentType | ''>(
        document?.type ?? '',
    );
    const [clientId, setClientId] = useState<string>(
        document ? String(document.user_id) : '',
    );

    return (
        <>
            {clients.length > 0 && (
                <div className="grid gap-2">
                    <Label htmlFor="user_id">Cliente</Label>
                    <Select value={clientId} onValueChange={setClientId}>
                        <SelectTrigger id="user_id" className="w-full">
                            <SelectValue placeholder="Selecciona un cliente" />
                        </SelectTrigger>
                        <SelectContent>
                            {clients.map((client) => (
                                <SelectItem
                                    key={client.id}
                                    value={String(client.id)}
                                >
                                    {client.name}
                                </SelectItem>
                            ))}
                        </SelectContent>
                    </Select>
                    <input type="hidden" name="user_id" value={clientId} />
                    <InputError message={errors.user_id} />
                </div>
            )}

            <div className="grid gap-2">
                <Label htmlFor="type">Tipo de documento</Label>
                <Select
                    value={type}
                    onValueChange={(value) => setType(value as TaxDocumentType)}
                >
                    <SelectTrigger id="type" className="w-full">
                        <SelectValue placeholder="Selecciona un tipo" />
                    </SelectTrigger>
                    <SelectContent>
                        {types.map((option) => (
                            <SelectItem key={option.value} value={option.value}>
                                {option.label}
                            </SelectItem>
                        ))}
                    </SelectContent>
                </Select>
                <input type="hidden" name="type" value={type} />
                <InputError message={errors.type} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="fiscal_year">Año fiscal</Label>
                <Input
                    id="fiscal_year"
                    name="fiscal_year"
                    type="number"
                    placeholder="2025"
                    defaultValue={document?.fiscal_year ?? ''}
                />
                <InputError message={errors.fiscal_year} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="title">Título</Label>
                <Input
                    id="title"
                    name="title"
                    required
                    placeholder="Ej. W-2 — Acme Corp"
                    defaultValue={document?.title}
                />
                <InputError message={errors.title} />
            </div>

            <div className="grid gap-2">
                <Label htmlFor="description">Notas</Label>
                <Textarea
                    id="description"
                    name="description"
                    defaultValue={document?.description ?? ''}
                />
                <InputError message={errors.description} />
            </div>

            {type !== '' && REQUIRES_SSN.includes(type) && (
                <div className="grid gap-2">
                    <Label htmlFor="ssn_itin">SSN / ITIN</Label>
                    <Input
                        id="ssn_itin"
                        name="ssn_itin"
                        placeholder="123-45-6789"
                        autoComplete="off"
                    />
                    {document?.ssn_itin_masked && (
                        <p className="text-sm text-muted-foreground">
                            Valor actual: {document.ssn_itin_masked}. Deja este
                            campo vacío para no modificarlo.
                        </p>
                    )}
                    <InputError message={errors.ssn_itin} />
                </div>
            )}

            {type !== '' && REQUIRES_DEPENDENT_FIELDS.includes(type) && (
                <>
                    <div className="grid gap-2">
                        <Label htmlFor="dependent_name">Nombre completo</Label>
                        <Input
                            id="dependent_name"
                            name="dependent_name"
                            defaultValue={document?.dependent_name ?? ''}
                        />
                        <InputError message={errors.dependent_name} />
                    </div>

                    <div className="grid gap-2">
                        <Label htmlFor="dependent_date_of_birth">
                            Fecha de nacimiento
                        </Label>
                        <Input
                            id="dependent_date_of_birth"
                            name="dependent_date_of_birth"
                            type="date"
                            defaultValue={
                                document?.dependent_date_of_birth ?? ''
                            }
                        />
                        <InputError message={errors.dependent_date_of_birth} />
                    </div>
                </>
            )}

            {type !== '' && REQUIRES_AMOUNT.includes(type) && (
                <div className="grid gap-2">
                    <Label htmlFor="amount">Monto (USD)</Label>
                    <Input
                        id="amount"
                        name="amount"
                        type="number"
                        step="0.01"
                        min="0"
                        defaultValue={document?.amount ?? ''}
                    />
                    <InputError message={errors.amount} />
                </div>
            )}

            {type !== '' && REQUIRES_FILE.includes(type) && (
                <div className="grid gap-2">
                    <Label htmlFor="file">Archivo</Label>
                    <Input id="file" name="file" type="file" />
                    {document?.file_original_name && (
                        <p className="text-sm text-muted-foreground">
                            Archivo actual: {document.file_original_name}. Sube
                            uno nuevo para reemplazarlo.
                        </p>
                    )}
                    <InputError message={errors.file} />
                </div>
            )}
        </>
    );
}
