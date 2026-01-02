export interface User {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
}

export type PageProps<
    T extends Record<string, unknown> = Record<string, unknown>,
> = T & {
    auth: {
        user: User;
    };
    tenant?: {
        id: number;
        name: string;
        slug: string;
        brand_color: string;
        logo?: string;
        layout_template?: string;
    };
};
