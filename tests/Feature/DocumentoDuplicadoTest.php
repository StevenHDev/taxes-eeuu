<?php

namespace Tests\Feature;

use App\Enums\UserRole;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Inertia\Testing\AssertableInertia as Assert;
use Tests\TestCase;

class DocumentoDuplicadoTest extends TestCase
{
    use RefreshDatabase;

    private function subirDocumento(User $preparador, User $cliente, string $campo, UploadedFile $file): void
    {
        $this->actingAs($preparador)
            ->post(route('clientes.campos.update', ['cliente' => $cliente, 'campo' => $campo]).'?forma=form_1040&tax_year=2025', [
                '_method' => 'patch',
                'forma' => 'form_1040',
                'modo' => 'archivo',
                'file' => $file,
            ])
            ->assertRedirect();
    }

    public function test_mismo_archivo_subido_dos_veces_para_el_mismo_cliente_se_marca_como_duplicado(): void
    {
        Storage::fake('local');
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $contenido = 'contenido-identico-de-prueba';
        $this->subirDocumento($preparador, $cliente, 'w2', UploadedFile::fake()->createWithContent('w2.pdf', $contenido));
        $this->subirDocumento($preparador, $cliente, 'form_1099_nec', UploadedFile::fake()->createWithContent('1099.pdf', $contenido));

        $this->actingAs($preparador)
            ->get(route('clientes.show', $cliente).'?tax_year=2025')
            ->assertOk()
            ->assertInertia(function (Assert $page) {
                $page->has('campos', 2);

                foreach (['w2', 'form_1099_nec'] as $campo) {
                    $page->where('campos', function (Collection $campos) use ($campo) {
                        $fila = $campos->firstWhere('campo', $campo);

                        return $fila['documento']['duplicado']['posible_duplicado'] === true
                            && $fila['documento']['duplicado']['otro_cliente'] === false
                            && $fila['documento']['duplicado']['mismo_cliente'] !== null;
                    });
                }
            });
    }

    public function test_archivos_distintos_no_se_marcan_como_duplicados(): void
    {
        Storage::fake('local');
        $preparador = User::factory()->create(['role' => UserRole::Preparer]);
        $cliente = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador->id]);

        $this->subirDocumento($preparador, $cliente, 'w2', UploadedFile::fake()->createWithContent('w2.pdf', 'contenido-a'));
        $this->subirDocumento($preparador, $cliente, 'form_1099_nec', UploadedFile::fake()->createWithContent('1099.pdf', 'contenido-b'));

        $this->actingAs($preparador)
            ->get(route('clientes.show', $cliente).'?tax_year=2025')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('campos', function (Collection $campos) {
                return $campos->every(fn (array $c) => $c['documento']['duplicado']['posible_duplicado'] === false);
            }));
    }

    public function test_un_preparador_no_ve_la_identidad_de_un_cliente_que_no_tiene_asignado_al_coincidir_el_hash(): void
    {
        Storage::fake('local');
        $preparador1 = User::factory()->create(['role' => UserRole::Preparer]);
        $preparador2 = User::factory()->create(['role' => UserRole::Preparer]);
        $clienteA = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador1->id, 'name' => 'Cliente A']);
        $clienteB = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador2->id, 'name' => 'Cliente B']);

        $contenido = 'mismo-documento-reutilizado';
        $this->subirDocumento($preparador1, $clienteA, 'w2', UploadedFile::fake()->createWithContent('w2.pdf', $contenido));
        $this->subirDocumento($preparador2, $clienteB, 'w2', UploadedFile::fake()->createWithContent('w2.pdf', $contenido));

        $this->actingAs($preparador2)
            ->get(route('clientes.show', $clienteB).'?tax_year=2025')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page->where('campos.0.documento.duplicado.otro_cliente', true)
                ->where('campos.0.documento.duplicado.otro_cliente_detalle', null));
    }

    public function test_un_administrador_ve_el_detalle_completo_del_otro_cliente(): void
    {
        Storage::fake('local');
        $preparador1 = User::factory()->create(['role' => UserRole::Preparer]);
        $preparador2 = User::factory()->create(['role' => UserRole::Preparer]);
        $admin = User::factory()->create(['role' => UserRole::Administrator]);
        $clienteA = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador1->id, 'name' => 'Cliente A']);
        $clienteB = User::factory()->create(['role' => UserRole::Client, 'preparer_id' => $preparador2->id, 'name' => 'Cliente B']);

        $contenido = 'mismo-documento-reutilizado';
        $this->subirDocumento($preparador1, $clienteA, 'w2', UploadedFile::fake()->createWithContent('w2.pdf', $contenido));
        $this->subirDocumento($preparador2, $clienteB, 'w2', UploadedFile::fake()->createWithContent('w2.pdf', $contenido));

        $this->actingAs($admin)
            ->get(route('clientes.show', $clienteB).'?tax_year=2025')
            ->assertOk()
            ->assertInertia(fn (Assert $page) => $page
                ->where('campos.0.documento.duplicado.otro_cliente', true)
                ->where('campos.0.documento.duplicado.otro_cliente_detalle.cliente_nombre', 'Cliente A')
                ->where('campos.0.documento.duplicado.otro_cliente_detalle.campo', 'w2'));
    }
}
