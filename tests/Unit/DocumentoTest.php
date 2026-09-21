<?php

namespace Tests\Unit;

use App\Models\Documento;
use Tests\TestCase;

/**
 * `Documento::disco()` decide en tiempo de ejecución entre 's3' y 'local'
 * según si AWS_BUCKET está configurado — ver el docblock del método. Esto
 * reemplazó una constante fija porque, en producción, el bucket S3 real
 * todavía no existe (2026-09-21): mientras tanto se usa 'local' + un volumen
 * Docker compartido entre frontend y worker como solución puente.
 */
class DocumentoTest extends TestCase
{
    public function test_usa_s3_cuando_hay_un_bucket_configurado(): void
    {
        config(['filesystems.disks.s3.bucket' => 'un-bucket-real']);

        $this->assertSame('s3', Documento::disco());
    }

    public function test_cae_a_local_cuando_no_hay_bucket_configurado(): void
    {
        config(['filesystems.disks.s3.bucket' => null]);

        $this->assertSame('local', Documento::disco());

        config(['filesystems.disks.s3.bucket' => '']);

        $this->assertSame('local', Documento::disco());
    }
}
