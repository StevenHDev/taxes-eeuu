import { usePage } from '@inertiajs/react';
import type { PropsWithChildren } from 'react';
import { FichaEncabezadoCliente } from '@/components/ficha/ficha-encabezado';
import { FichaPestanas } from '@/components/ficha/ficha-pestanas';
import type { ClavePestana, FichaEncabezado } from '@/types/expediente';

/**
 * Ficha del cliente (F5.2): encabezado y pestañas persistentes; cada
 * pestaña es su propia ruta y página Inertia (`ficha/*`). Al imprimir solo
 * sale el contenido y una línea con el cliente y el año.
 */
const COMPONENTE_A_PESTANA: Record<string, ClavePestana> = {
    'hoja-taxwise': 'hoja',
    'debida-diligencia': 'debida_diligencia',
};

export function pestanaDe(component: string): ClavePestana {
    const nombre = component.replace(/^ficha\//, '');

    return COMPONENTE_A_PESTANA[nombre] ?? (nombre as ClavePestana);
}

export default function FichaLayout({ children }: PropsWithChildren) {
    const page = usePage<{ ficha: FichaEncabezado }>();
    const { ficha } = page.props;
    const activa = pestanaDe(page.component);

    return (
        <div className="space-y-6 px-4 py-6 print:space-y-3 print:p-0">
            <FichaEncabezadoCliente ficha={ficha} activa={activa} />
            <FichaPestanas pestanas={ficha.pestanas} activa={activa} />
            <section>{children}</section>
        </div>
    );
}
