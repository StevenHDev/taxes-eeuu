import { Deferred, Head } from '@inertiajs/react';
import { TriangleAlert } from 'lucide-react';
import { useState } from 'react';
import { useTranslation } from 'react-i18next';
import { FichaSkeleton } from '@/components/ficha/ficha-skeleton';
import { AccionesExpediente } from '@/components/revision/acciones-expediente';
import { ChecklistRevision } from '@/components/revision/checklist-revision';
import { EstadoCivilForm } from '@/components/revision/estado-civil-form';
import { PersonaForm } from '@/components/revision/persona-form';
import { TransicionDialog } from '@/components/revision/transicion-dialog';
import { Alert, AlertDescription, AlertTitle } from '@/components/ui/alert';
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card';
import { dashboard } from '@/routes';
import { index as clientesIndex } from '@/routes/clientes';
import type { EstadoExpediente, FichaEncabezado } from '@/types/expediente';
import type { AccionRevision, Revision } from '@/types/revision';

type Props = {
    ficha: FichaEncabezado;
    revision?: Revision;
};

// Antes de "Listo para preparar" todavía no se puede revisar.
const ANTES_DE_REVISAR = new Set<EstadoExpediente>([
    'invitado',
    'en_entrevista',
    'en_consulta',
    'en_recoleccion',
    'por_validar',
]);

function RevisionError() {
    const { t } = useTranslation();

    return (
        <Alert variant="destructive">
            <TriangleAlert aria-hidden="true" />
            <AlertTitle>{t('revision.error.titulo')}</AlertTitle>
            <AlertDescription>{t('revision.error.detalle')}</AlertDescription>
        </Alert>
    );
}

function Contenido({
    clienteId,
    taxYear,
    revision,
    onElegir,
}: {
    clienteId: number;
    taxYear: number;
    revision: Revision;
    onElegir: (accion: AccionRevision) => void;
}) {
    const { t } = useTranslation();
    const abierta = revision.decisiones_abiertas;
    const antes = ANTES_DE_REVISAR.has(revision.estado);
    const decision = revision.estado_civil;

    return (
        <div className="space-y-6">
            {antes && (
                <Alert>
                    <TriangleAlert aria-hidden="true" />
                    <AlertTitle>{t('revision.antes.titulo')}</AlertTitle>
                    <AlertDescription>
                        <p>
                            {t('revision.antes.detalle', {
                                motivos:
                                    revision.diagnostico.motivos.join('; '),
                            })}
                        </p>
                        <p>{t('revision.antes.decisiones')}</p>
                    </AlertDescription>
                </Alert>
            )}

            <Card>
                <CardContent>
                    <EstadoCivilForm
                        key={`${decision.valor ?? ''}-${decision.en ?? ''}`}
                        clienteId={clienteId}
                        taxYear={taxYear}
                        decision={decision}
                        abierta={abierta}
                    />
                </CardContent>
            </Card>

            <section aria-labelledby="revision-personas" className="space-y-3">
                <h2 id="revision-personas" className="text-base font-semibold">
                    {t('revision.personas.titulo')}
                </h2>
                {revision.personas.length === 0 ? (
                    <p className="rounded-lg border border-dashed p-4 text-sm text-muted-foreground">
                        {t('revision.personas.vacio')}
                    </p>
                ) : (
                    <div className="grid gap-4 lg:grid-cols-2">
                        {revision.personas.map((p) => (
                            <PersonaForm
                                key={`${p.id}-${p.en ?? ''}`}
                                clienteId={clienteId}
                                taxYear={taxYear}
                                persona={p}
                                clasificaciones={revision.clasificaciones}
                                abierta={abierta}
                                enlaceEntrevista={revision.enlaces.entrevista}
                            />
                        ))}
                    </div>
                )}
            </section>

            <Card>
                <CardHeader>
                    <CardTitle className="text-base">
                        {t('revision.checklist.titulo')}
                    </CardTitle>
                </CardHeader>
                <CardContent>
                    <ChecklistRevision
                        clienteId={clienteId}
                        taxYear={taxYear}
                        revision={revision}
                    />
                </CardContent>
            </Card>

            <section
                aria-labelledby="revision-acciones"
                className="space-y-3 border-t pt-6"
            >
                <h2 id="revision-acciones" className="text-base font-semibold">
                    {t('revision.acciones.titulo')}
                </h2>
                <AccionesExpediente
                    acciones={revision.acciones}
                    onElegir={onElegir}
                />
            </section>
        </div>
    );
}

export default function FichaRevision({ ficha, revision }: Props) {
    const { t } = useTranslation();
    // Fuera de <Deferred>: el diálogo no se cierra cuando la revisión se recarga.
    const [pendiente, setPendiente] = useState<AccionRevision | null>(null);

    return (
        <>
            <Head title={t('revision.titulo')} />
            <Deferred
                data="revision"
                fallback={<FichaSkeleton />}
                rescue={<RevisionError />}
            >
                {revision && (
                    <Contenido
                        clienteId={ficha.cliente.id}
                        taxYear={ficha.tax_year}
                        revision={revision}
                        onElegir={setPendiente}
                    />
                )}
            </Deferred>
            <TransicionDialog
                key={pendiente?.accion ?? 'ninguna'}
                clienteId={ficha.cliente.id}
                taxYear={ficha.tax_year}
                accion={pendiente}
                onClose={() => setPendiente(null)}
            />
        </>
    );
}

FichaRevision.layout = {
    breadcrumbs: [
        { title: 'nav.dashboard', href: dashboard() },
        { title: 'nav.clients', href: clientesIndex() },
    ],
};
