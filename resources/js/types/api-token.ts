export type ApiTokenAbilityOption = {
    value: string;
    label: string;
};

export type ApiToken = {
    id: number;
    name: string;
    abilities: string[];
    last_used_at: string | null;
    created_at: string;
};
