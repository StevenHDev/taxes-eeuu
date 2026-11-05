import { useTranslation } from 'react-i18next';
import { Badge } from '@/components/ui/badge';
import { humanizarClave } from '@/lib/humanizar-clave';
import type { DocumentoDuplicado } from '@/types';

export function DuplicadoBadge({
    duplicado,
}: {
    duplicado?: DocumentoDuplicado;
}) {
    const { t } = useTranslation();

    if (!duplicado?.posible_duplicado) {
        return null;
    }

    const detalle = duplicado.otro_cliente_detalle
        ? t('clienteShow.duplicado.otroClienteConDetalle', {
              cliente: duplicado.otro_cliente_detalle.cliente_nombre,
              campo: humanizarClave(duplicado.otro_cliente_detalle.campo),
          })
        : duplicado.otro_cliente
          ? t('clienteShow.duplicado.otroClienteSinDetalle')
          : duplicado.mismo_cliente
            ? t('clienteShow.duplicado.mismoCliente', {
                  campos: duplicado.mismo_cliente
                      .map((c) => humanizarClave(c.campo))
                      .join(', '),
              })
            : undefined;

    return (
        <Badge variant="destructive" title={detalle}>
            {t('clienteShow.duplicado.badge')}
        </Badge>
    );
}
