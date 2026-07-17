export type TaxDocumentType =
    | 'identification'
    | 'dependent'
    | 'w2'
    | 'form_1099_nec'
    | 'bank_statement'
    | 'profit_and_loss'
    | 'balance_sheet'
    | 'deductible_expense'
    | 'asset_depreciation'
    | 'prior_year_return';

export type TaxDocumentTypeOption = {
    value: TaxDocumentType;
    label: string;
};

export type TaxDocumentClient = {
    id: number;
    name: string;
};

export type TaxDocument = {
    id: number;
    user_id: number;
    uploaded_by_id: number | null;
    type: TaxDocumentType;
    type_label: string;
    fiscal_year: number | null;
    title: string;
    description: string | null;
    ssn_itin_masked: string | null;
    dependent_name: string | null;
    dependent_date_of_birth: string | null;
    amount: string | null;
    file_original_name: string | null;
    file_mime_type: string | null;
    file_size: number | null;
    user?: TaxDocumentClient;
    created_at: string;
    updated_at: string;
};
