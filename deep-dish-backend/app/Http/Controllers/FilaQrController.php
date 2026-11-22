<?php

namespace App\Http\Controllers;

use App\Models\Restaurante;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;

/**
 * QR de entrada na fila (#21 do backlog / issue #174).
 *
 * O QR impresso na porta do restaurante carrega uma URL assinada, permanente
 * (sem expiração — vai para o papel) e sem nada sensível: o id do restaurante já
 * é público na listagem. A assinatura serve para recusar URL forjada ou editada,
 * não para esconder o id.
 *
 * ── Assinatura RELATIVA, base pública fixa ──
 * A assinatura cobre só caminho + query, não o host. Se cobrisse o host, o QR
 * dependeria de como a API foi alcançada na hora de gerar e de validar: atrás de
 * proxy/CDN (host interno, http em vez de https) a validação falharia em
 * produção, e o QR impresso valeria só enquanto o domínio fosse o mesmo. O host
 * do QR vem de APP_URL (a URL pública da API), que pode ser outro domínio que o
 * do frontend: o destino final vem de FRONTEND_URL.
 *
 * ── Rotação da APP_KEY ──
 * A assinatura é um HMAC com a APP_KEY. Trocar a chave INVALIDA todos os QR
 * Codes impressos (cairiam em /qr-erro?motivo=assinatura_invalida) e obrigaria a
 * reimprimir. Para rotacionar sem esse custo, mantenha a chave antiga em
 * APP_PREVIOUS_KEYS: o Laravel aceita assinaturas feitas com ela. Detalhes em
 * docs/qr-fila.md.
 */
class FilaQrController extends Controller
{
    /**
     * GET /restaurante/fila/qr (auth:restaurante)
     *
     * O restaurante é sempre o do token: não há parâmetro de id, então um painel
     * não tem como pedir o QR de outro estabelecimento.
     */
    public function url(): JsonResponse
    {
        /** @var Restaurante $restaurante */
        $restaurante = auth('restaurante')->user();

        return response()->json([
            'restaurante_id' => $restaurante->id,
            'nome' => $restaurante->name,
            'fila_ativa' => (bool) $restaurante->fila_ativa,
            'url' => self::urlAssinada((string) $restaurante->id),
        ]);
    }

    /**
     * GET /fila/qr/{id} (público, assinado)
     *
     * Quem chega aqui é o navegador do celular que leu o QR, não o app: a
     * resposta é sempre um redirecionamento para o frontend. A validação é feita
     * à mão, e não pelo middleware 'signed', porque o middleware devolveria um
     * 403 em JSON para uma pessoa parada na porta do restaurante.
     */
    public function entrar(Request $request, string $id): RedirectResponse
    {
        $frontend = rtrim((string) config('app.frontend_url'), '/');

        if (! $request->hasValidRelativeSignature()) {
            return redirect("{$frontend}/qr-erro?motivo=assinatura_invalida");
        }

        $restaurante = Restaurante::query()->find($id);

        if (! $restaurante) {
            return redirect("{$frontend}/qr-erro?motivo=restaurante_inexistente");
        }

        if (! $restaurante->fila_ativa) {
            return redirect("{$frontend}/qr-erro?motivo=fila_fechada&restaurante={$restaurante->id}");
        }

        // Login, retorno ao destino e "já está na fila" são do frontend: é ele
        // quem sabe se há sessão. Ver ProtectedRoute e lib/redirectAposLogin.
        return redirect("{$frontend}/app/restaurants/{$restaurante->id}/entrar-na-fila");
    }

    /**
     * URL permanente do QR: assinatura relativa, sem expiração, prefixada pela
     * URL pública da API (APP_URL) — ver o comentário da classe.
     */
    public static function urlAssinada(string $restauranteId): string
    {
        $relativa = URL::signedRoute('fila.qr', ['id' => $restauranteId], absolute: false);

        return rtrim((string) config('app.url'), '/').$relativa;
    }
}
