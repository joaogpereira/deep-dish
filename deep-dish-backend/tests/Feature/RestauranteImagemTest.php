<?php

namespace Tests\Feature;

use App\Models\Restaurante;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/**
 * Fotos dos restaurantes: o banco guarda só o caminho, a API devolve a URL do
 * disco configurado. Storage::fake() troca o disco padrão por um temporário —
 * o código testado é o mesmo que roda contra o Supabase em produção.
 *
 * Os uploads usam uma foto real do seed em vez de UploadedFile::fake()->image(),
 * que depende da extensão GD, ausente em parte das máquinas do time.
 */
class RestauranteImagemTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake();
    }

    public function test_upload_grava_o_caminho_no_banco_e_devolve_a_url(): void
    {
        $restaurante = Restaurante::factory()->create();

        $resposta = $this->comoRestaurante($restaurante)
            ->postJson('/api/restaurante/me/imagem', ['imagem' => $this->foto()]);

        $resposta->assertOk();
        $caminho = $restaurante->fresh()->getRawOriginal('imagem_url');
        $this->assertStringStartsWith('restaurantes/', $caminho);
        Storage::assertExists($caminho);
        $resposta->assertJsonPath('imagem_url', Storage::url($caminho));
    }

    public function test_trocar_a_imagem_apaga_a_antiga(): void
    {
        $restaurante = Restaurante::factory()->create();
        $this->comoRestaurante($restaurante)
            ->postJson('/api/restaurante/me/imagem', ['imagem' => $this->foto()]);
        $antiga = $restaurante->fresh()->getRawOriginal('imagem_url');

        $this->comoRestaurante($restaurante)
            ->postJson('/api/restaurante/me/imagem', ['imagem' => $this->foto()])
            ->assertOk();

        $nova = $restaurante->fresh()->getRawOriginal('imagem_url');
        $this->assertNotSame($antiga, $nova);
        Storage::assertMissing($antiga);
        Storage::assertExists($nova);
    }

    public function test_api_monta_a_url_pelo_disco_e_deixa_url_legada_passar(): void
    {
        $comCaminho = Restaurante::factory()->make(['imagem_url' => 'restaurantes/x.jpg']);
        $legado = Restaurante::factory()->make(['imagem_url' => 'http://localhost:8000/storage/y.jpg']);
        $semFoto = Restaurante::factory()->make(['imagem_url' => null]);

        $this->assertSame(Storage::url('restaurantes/x.jpg'), $comCaminho->imagem_url);
        $this->assertSame('http://localhost:8000/storage/y.jpg', $legado->imagem_url);
        $this->assertNull($semFoto->imagem_url);
    }

    public function test_seed_da_uma_foto_a_cada_restaurante(): void
    {
        $this->seed();

        foreach (Restaurante::all() as $restaurante) {
            $caminho = $restaurante->getRawOriginal('imagem_url');
            $this->assertNotNull($caminho, "{$restaurante->name} ficou sem foto");
            Storage::assertExists($caminho);
        }
    }

    private function comoRestaurante(Restaurante $restaurante): static
    {
        return $this->withHeader('Authorization', 'Bearer '.auth('restaurante')->login($restaurante));
    }

    private function foto(): UploadedFile
    {
        return new UploadedFile(
            database_path('seeders/imagens/trattoria-bella.jpg'), 'foto.jpg', 'image/jpeg', null, true
        );
    }
}
