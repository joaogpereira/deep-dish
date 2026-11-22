<?php

namespace App\Http\Controllers;

use App\Models\AssistenteConversa;
use App\Models\AssistenteMensagem;
use App\Models\AssistenteUso;
use App\Services\AssistenteService;
use App\Services\RespostaAssistente;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

/**
 * POST /api/restaurante/assistente/chat (#179).
 *
 * A inteligência está no AssistenteService (#178). Este controller cuida do que
 * fica em volta: de quem é a conversa, o que volta como contexto, e quanto o
 * restaurante ainda pode gastar.
 */
class AssistenteChatController extends Controller
{
    /** O "dia" do teto é o dia do restaurante, não o do servidor. */
    private const FUSO = 'America/Sao_Paulo';

    public function __construct(private readonly AssistenteService $assistente) {}

    public function chat(Request $request): JsonResponse
    {
        $validado = $request->validate([
            'mensagem' => ['required', 'string', 'max:2000'],
            'conversa_id' => ['nullable', 'uuid'],
        ]);

        $restauranteId = (string) auth('restaurante')->id();

        $conversa = null;
        if (isset($validado['conversa_id'])) {
            // 404 e não 403: para quem não é dono, a conversa não existe.
            $conversa = AssistenteConversa::where('id', $validado['conversa_id'])
                ->where('restaurante_id', $restauranteId)
                ->first();

            if ($conversa === null) {
                return response()->json([
                    'error' => 'conversa_nao_encontrada',
                    'message' => 'Conversa não encontrada.',
                ], 404);
            }
        }

        if ($this->consumoDoDia($restauranteId) >= config('assistente.teto_tokens_dia')) {
            return response()->json([
                'error' => 'assistente_teto_tokens',
                'message' => 'O limite diário do assistente foi atingido. Ele volta a funcionar amanhã.',
            ], 429);
        }

        $trava = Cache::lock("assistente:chat:{$restauranteId}", config('assistente.trava_segundos'));

        if (! $trava->get()) {
            return response()->json([
                'error' => 'assistente_ocupado',
                'message' => 'Ainda estou respondendo sua pergunta anterior. Aguarde a resposta.',
            ], 409);
        }

        try {
            $historico = $conversa ? $this->historico($conversa) : [];

            $resposta = $this->assistente->perguntar($restauranteId, $validado['mensagem'], $historico);

            if ($resposta->ok) {
                $conversa = $this->gravarTurno($restauranteId, $conversa, $resposta, count($historico));
            }

            $this->registrarUso($restauranteId, $conversa, $resposta);
        } finally {
            $trava->release();
        }

        if (! $resposta->ok) {
            return response()->json([
                'error' => 'assistente_indisponivel',
                'message' => $resposta->erro,
            ], 503);
        }

        return response()->json([
            'conversa_id' => $conversa->id,
            'resposta' => $resposta->texto,
            'ferramentas_usadas' => $resposta->ferramentasUsadas,
        ]);
    }

    /**
     * Os últimos turnos, só com o texto de usuário e assistente.
     *
     * As trocas de ferramenta ficam de fora por dois motivos. Custo: o resultado
     * de uma ferramenta é a parte mais pesada da conversa e a resposta final já
     * diz o que importava dele. Formato: cortar o histórico no meio de uma troca
     * deixaria uma mensagem 'tool' sem a 'tool_calls' que a originou, e o
     * provedor recusa a requisição inteira.
     *
     * Turno com falha não é gravado, então as mensagens sempre vêm em pares.
     *
     * @return list<array{role: string, content: string}>
     */
    private function historico(AssistenteConversa $conversa): array
    {
        $limite = max(0, (int) config('assistente.historico_turnos')) * 2;

        if ($limite === 0) {
            return [];
        }

        return $conversa->mensagens()
            ->reorder('posicao', 'desc')
            ->whereIn('role', ['user', 'assistant'])
            ->whereNotNull('content')
            ->limit($limite)
            ->get(['role', 'content'])
            ->reverse()
            ->map(fn (AssistenteMensagem $m) => ['role' => $m->role, 'content' => $m->content])
            ->values()
            ->all();
    }

    /**
     * Grava as mensagens novas do turno: a pergunta, as trocas de ferramenta e a
     * resposta. As mensagens devolvidas pelo serviço começam pelo prompt de
     * sistema e pelo histórico enviado, que já estão no banco (ou não são
     * gravados, no caso do sistema).
     */
    private function gravarTurno(
        string $restauranteId,
        ?AssistenteConversa $conversa,
        RespostaAssistente $resposta,
        int $tamanhoDoHistorico,
    ): AssistenteConversa {
        $novas = array_slice($resposta->mensagens, 1 + $tamanhoDoHistorico);

        return DB::transaction(function () use ($restauranteId, $conversa, $novas) {
            $conversa ??= AssistenteConversa::create(['restaurante_id' => $restauranteId]);
            $posicao = (int) $conversa->mensagens()->max('posicao');

            foreach ($novas as $mensagem) {
                $conversa->mensagens()->create([
                    'posicao' => ++$posicao,
                    'role' => $mensagem['role'],
                    'content' => $mensagem['content'] ?? null,
                    'tool_calls' => $mensagem['tool_calls'] ?? null,
                    'tool_call_id' => $mensagem['tool_call_id'] ?? null,
                    'name' => $mensagem['name'] ?? null,
                ]);
            }

            $conversa->touch();

            return $conversa;
        });
    }

    /** Pergunta que falhou também conta: o provedor cobra os tokens mesmo assim. */
    private function registrarUso(string $restauranteId, ?AssistenteConversa $conversa, RespostaAssistente $resposta): void
    {
        AssistenteUso::create([
            'restaurante_id' => $restauranteId,
            'conversa_id' => $conversa?->id,
            'tokens_prompt' => $resposta->uso['prompt'],
            'tokens_resposta' => $resposta->uso['resposta'],
            'tokens_total' => $resposta->uso['total'],
            'ok' => $resposta->ok,
        ]);
    }

    private function consumoDoDia(string $restauranteId): int
    {
        $inicioDoDia = CarbonImmutable::now(self::FUSO)->startOfDay()->utc();

        return (int) AssistenteUso::where('restaurante_id', $restauranteId)
            ->where('created_at', '>=', $inicioDoDia)
            ->sum('tokens_total');
    }
}
