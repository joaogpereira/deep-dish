<?php

namespace Database\Seeders;

use App\Models\Cliente;
use App\Models\ClienteFila;
use App\Models\ClienteMesa;
use App\Models\Fila;
use App\Models\Mesa;
use App\Models\Restaurante;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Histórico sintético de fila e reservas (~12 semanas) para o dashboard do
 * Sprint 2 e o assistente do Sprint 4 terem volume na apresentação.
 *
 * ── Por que o relógio é congelado ──
 * ClienteFila::registrarSaida() e ClienteMesa::registrarSaida() derivam
 * 'saiu_em'/'horario_saida' de now(), e as durações de created_at/horario_checkin
 * até now(). Criar uma linha com created_at de 8 semanas atrás e chamar essas
 * portas produziria saída "hoje" e espera de 8 semanas.
 *
 * A alternativa seria escrever status_saida/tempo_espera_segundos direto no
 * banco — exatamente o que as factories evitam de propósito, para não burlar o
 * hook `deleting` do ClienteFila. Então em vez de contornar a regra, movemos o
 * relógio: Carbon::setTestNow() no instante histórico faz as mesmas portas de
 * produção gravarem o passado.
 *
 * O congelamento precisa envolver a criação E o registrarSaida(): se a saída
 * cair fora da janela congelada, saiu_em volta a ser "agora".
 *
 * ── Fuso ──
 * Os picos (11h30-14h e 19h-22h) são horário de Brasília, mas as colunas guardam
 * UTC, como ReservaController::store faz. O sorteio acontece em
 * America/Sao_Paulo e a gravação converte. Gerar direto em UTC colocaria os
 * picos às 8h30 e 16h no gráfico.
 *
 * ── Soft delete ──
 * Fila encerrada nasce soft-deleted porque registrarSaida() faz isso em
 * produção; reserva encerrada não, porque o fluxo real não apaga. O seeder
 * espelha os dois. Consultas de fila histórica precisam de withTrashed().
 */
class HistoricoSinteticoSeeder extends Seeder
{
    /** Valor gravado na coluna `origem`. NULL lá significa dado real. */
    public const ORIGEM = 'sintetico';

    /** Domínio reservado dos clientes fictícios — permite reuso entre execuções. */
    public const DOMINIO_CLIENTE = 'sintetico.deepdish.test';

    private const FUSO = 'America/Sao_Paulo';

    private const PASSO_MINUTOS = 30;

    /** Janela usada quando o restaurante não tem horário configurado (BRT). */
    private const ABERTURA_PADRAO = '11:00';

    private const FECHAMENTO_PADRAO = '23:00';

    /** Chegadas esperadas por slot quando o peso da curva é 1.0. */
    private const BASE_FILA = 2.2;

    private const BASE_RESERVA = 1.8;

    private const TAMANHO_POOL_CLIENTES = 120;

    // ─────────────────────── Curva (puro, sem banco) ───────────────────────

    /**
     * Peso da demanda num instante do dia, em minutos desde a meia-noite (BRT).
     *
     * Duas gaussianas — almoço centrado em 12h30, jantar em 20h30, este mais
     * intenso — sobre um piso baixo que representa o movimento fora de pico.
     * É esta função que o critério "reproduz os dois picos ao ser plotada"
     * exercita; ela é estática e pura justamente para ser testável sem banco.
     *
     * Os centros caem exatamente sobre um slot (múltiplos de PASSO_MINUTOS) de
     * propósito. Centrado em 12h45, o pico ficaria empatado entre os slots de
     * 12h30 e 13h, e nenhum dos dois seria máximo local — a curva teria o
     * formato certo, mas o pico não seria detectável ao ser plotada.
     */
    public static function pesoDoSlot(int $minutosDoDia): float
    {
        $gaussiana = static fn (float $centro, float $sigma): float => exp(
            -(($minutosDoDia - $centro) ** 2) / (2 * $sigma ** 2)
        );

        return 0.12                                // piso fora de pico
            + 1.00 * $gaussiana(12 * 60 + 30, 45)  // almoço  11h30-14h
            + 1.25 * $gaussiana(20 * 60 + 30, 60); // jantar  19h-22h
    }

    /**
     * Multiplicador por dia da semana (0 = domingo, como Carbon::dayOfWeek).
     * Fim de semana mais cheio que dia útil, com sexta já puxando.
     */
    public static function multiplicadorDoDia(int $diaDaSemana): float
    {
        return [
            0 => 1.30, // domingo
            1 => 0.85, // segunda
            2 => 0.90,
            3 => 0.95,
            4 => 1.05,
            5 => 1.35, // sexta
            6 => 1.60, // sábado
        ][$diaDaSemana] ?? 1.0;
    }

    // ─────────────────────────── Operações ───────────────────────────

    /**
     * Remove todo o dado sintético do restaurante. É o que garante o critério de
     * "rodar duas vezes não duplica": a geração sempre limpa antes.
     *
     * forceDelete() é obrigatório no ClienteFila — as linhas já estão
     * soft-deleted, e um delete() comum não as tiraria da tabela.
     *
     * @return array{filas:int, entradas:int, reservas:int}
     */
    public function limpar(Restaurante $restaurante): array
    {
        $filaIds = Fila::query()
            ->where('restaurante_id', $restaurante->id)
            ->where('origem', self::ORIGEM)
            ->pluck('id');

        $entradas = ClienteFila::withTrashed()
            ->whereIn('fila_id', $filaIds)
            ->where('origem', self::ORIGEM)
            ->forceDelete();

        // Só apaga a fila que ficou de fato vazia. Se sobrou entrada real dentro
        // de uma fila sintética, a fila fica — apagá-la levaria junto o dado real.
        $vazias = $filaIds->filter(
            fn (string $id) => ! ClienteFila::withTrashed()->where('fila_id', $id)->exists()
        );

        $filas = $vazias->isEmpty() ? 0 : Fila::whereIn('id', $vazias)->delete();

        $mesaIds = Mesa::where('restaurante_id', $restaurante->id)->pluck('id');

        $reservas = ClienteMesa::withTrashed()
            ->whereIn('mesa_id', $mesaIds)
            ->where('origem', self::ORIGEM)
            ->forceDelete();

        return ['filas' => $filas, 'entradas' => $entradas, 'reservas' => $reservas];
    }

    /**
     * Gera `$semanas` de histórico encerrado para o restaurante.
     *
     * @param  callable(int, int):void|null  $progresso  recebe (dia atual, total de dias)
     * @return array{dias:int, filas:int, entradas:int, reservas:int}
     */
    public function gerar(Restaurante $restaurante, int $semanas, ?callable $progresso = null): array
    {
        $mesas = Mesa::where('restaurante_id', $restaurante->id)
            ->orderBy('capacidade')
            ->get();

        if ($mesas->isEmpty()) {
            throw new \RuntimeException(
                "O restaurante '{$restaurante->name}' não tem mesas. "
                .'Cadastre ao menos uma antes de gerar histórico de reservas.'
            );
        }

        $clientes = $this->poolDeClientes();
        [$aberturaMin, $fechamentoMin] = $this->janelaDeFuncionamento($restaurante);

        // Semente fixa: regerar com os mesmos parâmetros reproduz o mesmo
        // resultado, o que o torna conferível entre máquinas.
        //
        // Precisa vir DEPOIS de poolDeClientes(): quando o pool ainda não
        // existe, o Faker consome o mesmo gerador ao criar os 120 clientes, e a
        // primeira execução sairia diferente de todas as seguintes.
        mt_srand(crc32($restaurante->id.'|'.$semanas));

        $totalDias = $semanas * 7;
        $hojeBrt = Carbon::now(self::FUSO)->startOfDay();

        $contagem = ['dias' => $totalDias, 'filas' => 0, 'entradas' => 0, 'reservas' => 0];

        for ($d = $totalDias; $d >= 1; $d--) {
            $dia = $hojeBrt->copy()->subDays($d);
            $multiplicador = self::multiplicadorDoDia($dia->dayOfWeek);

            $idsFila = [];
            $idsEntrada = [];
            $idsReserva = [];

            DB::transaction(function () use (
                $restaurante, $dia, $multiplicador, $aberturaMin, $fechamentoMin,
                $clientes, $mesas, &$idsFila, &$idsEntrada, &$idsReserva
            ) {
                for ($m = $aberturaMin; $m < $fechamentoMin; $m += self::PASSO_MINUTOS) {
                    $peso = self::pesoDoSlot($m) * $multiplicador;
                    $slotBrt = $dia->copy()->addMinutes($m);

                    $this->gerarFilaDoSlot($restaurante, $slotBrt, $peso, $clientes, $idsFila, $idsEntrada);
                    $this->gerarReservasDoSlot($slotBrt, $peso, $clientes, $mesas, $idsReserva);
                }
            });

            $this->marcarOrigem('fila', $idsFila);
            $this->marcarOrigem('clientefila', $idsEntrada);
            $this->marcarOrigem('clientemesa', $idsReserva);

            $contagem['filas'] += count($idsFila);
            $contagem['entradas'] += count($idsEntrada);
            $contagem['reservas'] += count($idsReserva);

            if ($progresso) {
                $progresso($totalDias - $d + 1, $totalDias);
            }
        }

        return $contagem;
    }

    // ─────────────────────────────── Fila ───────────────────────────────

    /**
     * @param  list<string>  $idsFila
     * @param  list<string>  $idsEntrada
     */
    private function gerarFilaDoSlot(
        Restaurante $restaurante,
        Carbon $slotBrt,
        float $peso,
        Collection $clientes,
        array &$idsFila,
        array &$idsEntrada
    ): void {
        $quantidade = $this->poisson(self::BASE_FILA * $peso);

        if ($quantidade === 0) {
            return;
        }

        $slotUtc = $slotBrt->copy()->utc();

        // A fila abre um pouco antes do slot e já nasce encerrada: é histórico.
        $fila = Fila::factory()->create([
            'restaurante_id' => $restaurante->id,
            'horario_reserva' => $slotUtc,
            'status' => Fila::STATUS_ENCERRADA,
            'created_at' => $slotUtc->copy()->subMinutes(self::PASSO_MINUTOS),
            'updated_at' => $slotUtc,
        ]);

        $idsFila[] = $fila->id;

        // As chegadas são sorteadas dentro do slot e ORDENADAS antes de usar.
        // Sem o sort, cada pessoa receberia um horário independente e a ordem
        // real de chegada viraria uma permutação aleatória do índice do laço —
        // que é justamente o que define a probabilidade de desistir. O efeito
        // "quem chega depois pega uma fila maior" se perderia, e a taxa de
        // abandono deixaria de acompanhar a posição na fila.
        $chegadas = [];

        for ($i = 0; $i < $quantidade; $i++) {
            $chegadas[] = $this->inteiro(0, self::PASSO_MINUTOS - 1);
        }

        sort($chegadas);

        for ($posicao = 0; $posicao < $quantidade; $posicao++) {
            $chegada = $slotUtc->copy()->addMinutes($chegadas[$posicao]);
            $esperaBase = 5 + 3.2 * $posicao;

            [$status, $fator] = $this->desfechoDaFila($posicao);
            $espera = max(2, (int) round($esperaBase * $fator));
            $saida = $chegada->copy()->addMinutes($espera);

            $cliente = $clientes->random();
            $pessoas = $this->tamanhoDoGrupo();

            // Criação e saída no MESMO congelamento: registrarSaida() lê now().
            $entrada = $this->comRelogioEm($saida, function () use ($fila, $cliente, $chegada, $pessoas, $status) {
                $registro = ClienteFila::factory()
                    ->for($fila)
                    ->for($cliente, 'cliente')
                    ->state([
                        'created_at' => $chegada,
                        'qntd_pessoas' => $pessoas,
                    ])
                    ->create();

                $registro->registrarSaida($status);

                return $registro;
            });

            $idsEntrada[] = $entrada->id;
        }
    }

    /**
     * Sorteia o desfecho de quem entrou na posição `$posicao`.
     *
     * A probabilidade de desistir cresce com a fila à frente — de ~6% para quem
     * chega primeiro até o teto de 55%. Os demais desfechos dividem o resto.
     *
     * @return array{0:string, 1:float} status e fator aplicado à espera base
     */
    private function desfechoDaFila(int $posicao): array
    {
        $pDesistiu = min(0.55, 0.06 + 0.05 * $posicao);
        $sorteio = $this->real(0, 1);

        if ($sorteio < $pDesistiu) {
            return [ClienteFila::STATUS_SAIDA_DESISTIU, $this->real(0.7, 1.4)];
        }

        $restante = ($sorteio - $pDesistiu) / max(1e-9, 1 - $pDesistiu);

        if ($restante < 0.88) {
            return [ClienteFila::STATUS_SAIDA_ATENDIDO, $this->real(0.8, 1.25)];
        }

        if ($restante < 0.95) {
            return [ClienteFila::STATUS_SAIDA_REMOVIDO, $this->real(0.4, 0.9)];
        }

        // Foi chamado e não apareceu.
        return [ClienteFila::STATUS_SAIDA_EXPIRADO, $this->real(0.9, 1.3)];
    }

    // ───────────────────────────── Reservas ─────────────────────────────

    /**
     * @param  list<string>  $idsReserva
     */
    private function gerarReservasDoSlot(
        Carbon $slotBrt,
        float $peso,
        Collection $clientes,
        Collection $mesas,
        array &$idsReserva
    ): void {
        $quantidade = $this->poisson(self::BASE_RESERVA * $peso);
        $slotUtc = $slotBrt->copy()->utc();

        for ($i = 0; $i < $quantidade; $i++) {
            $pessoas = $this->tamanhoDoGrupo();
            $mesa = $this->mesaPara($mesas, $pessoas);
            $horario = $slotUtc->copy()->addMinutes($this->inteiro(0, self::PASSO_MINUTOS - 1));
            $cliente = $clientes->random();

            $sorteio = $this->real(0, 1);

            $reserva = match (true) {
                $sorteio < 0.78 => $this->reservaLiberada($cliente, $mesa, $horario, $pessoas),
                $sorteio < 0.91 => $this->reservaSemComparecimento($cliente, $mesa, $horario, $pessoas, 'expirada'),
                default => $this->reservaSemComparecimento($cliente, $mesa, $horario, $pessoas, 'cancelada'),
            };

            $idsReserva[] = $reserva->id;
        }
    }

    /**
     * Cliente chegou, sentou e saiu. A permanência cresce com o tamanho do grupo:
     * ~42 min para uma dupla, ~+11 min por pessoa adicional, com ruído.
     */
    private function reservaLiberada(Cliente $cliente, Mesa $mesa, Carbon $horario, int $pessoas): ClienteMesa
    {
        $checkin = $horario->copy()->addMinutes($this->inteiro(0, 14));
        $permanencia = (int) round((42 + 11 * max(0, $pessoas - 2)) * $this->real(0.8, 1.3));
        $permanencia = max(25, min(190, $permanencia));
        $saida = $checkin->copy()->addMinutes($permanencia);

        return $this->comRelogioEm($saida, function () use ($cliente, $mesa, $horario, $checkin, $pessoas) {
            $reserva = ClienteMesa::factory()
                ->for($cliente, 'cliente')
                ->for($mesa, 'mesa')
                ->state([
                    'horario_reserva' => $horario,
                    'horario_checkin' => $checkin,
                    'party_size' => $pessoas,
                    'status' => 'liberada',
                    'created_at' => $horario,
                ])
                ->create();

            $reserva->registrarSaida();

            return $reserva;
        });
    }

    /**
     * No-show ou cancelamento: nunca houve check-in, então registrarSaida()
     * deixa duracao_segundos NULL — nunca 0, que rebaixaria a média.
     */
    private function reservaSemComparecimento(
        Cliente $cliente,
        Mesa $mesa,
        Carbon $horario,
        int $pessoas,
        string $status
    ): ClienteMesa {
        $encerramento = $horario->copy()->addMinutes($this->inteiro(30, 90));

        return $this->comRelogioEm($encerramento, function () use ($cliente, $mesa, $horario, $pessoas, $status) {
            $reserva = ClienteMesa::factory()
                ->for($cliente, 'cliente')
                ->for($mesa, 'mesa')
                ->state([
                    'horario_reserva' => $horario,
                    'horario_checkin' => null,
                    'party_size' => $pessoas,
                    'status' => $status,
                    'created_at' => $horario,
                ])
                ->create();

            $reserva->registrarSaida();

            return $reserva;
        });
    }

    private function mesaPara(Collection $mesas, int $pessoas): Mesa
    {
        $cabem = $mesas->filter(fn (Mesa $m) => $m->capacidade >= $pessoas);

        return $cabem->isNotEmpty() ? $cabem->random() : $mesas->last();
    }

    // ─────────────────────────────── Apoio ───────────────────────────────

    /**
     * Executa $acao com now() fixado em $instante e restaura o relógio depois.
     * O finally é obrigatório: um relógio congelado que vaza contamina todo o
     * resto do processo.
     *
     * @template T
     *
     * @param  callable():T  $acao
     * @return T
     */
    private function comRelogioEm(Carbon $instante, callable $acao): mixed
    {
        Carbon::setTestNow($instante);

        try {
            return $acao();
        } finally {
            Carbon::setTestNow();
        }
    }

    /**
     * Carimba a origem sem passar pelos models: 'origem' fica fora do $fillable
     * de propósito, e o update em massa evita uma escrita por linha.
     *
     * @param  list<string>  $ids
     */
    private function marcarOrigem(string $tabela, array $ids): void
    {
        foreach (array_chunk($ids, 500) as $lote) {
            DB::table($tabela)->whereIn('id', $lote)->update(['origem' => self::ORIGEM]);
        }
    }

    /**
     * Pool estável de clientes fictícios, reaproveitado entre execuções.
     * O domínio reservado no e-mail é o que os distingue de clientes reais.
     */
    private function poolDeClientes(): Collection
    {
        $existentes = Cliente::where('email', 'like', '%@'.self::DOMINIO_CLIENTE)->get();

        for ($i = $existentes->count(); $i < self::TAMANHO_POOL_CLIENTES; $i++) {
            $existentes->push(Cliente::factory()->create([
                'email' => sprintf('cliente%03d@%s', $i + 1, self::DOMINIO_CLIENTE),
            ]));
        }

        return $existentes;
    }

    /**
     * @return array{0:int, 1:int} abertura e fechamento em minutos desde a meia-noite
     */
    private function janelaDeFuncionamento(Restaurante $restaurante): array
    {
        $paraMinutos = static function (?string $hora, string $padrao): int {
            [$h, $m] = array_map('intval', explode(':', substr($hora ?: $padrao, 0, 5)));

            return $h * 60 + $m;
        };

        $abertura = $paraMinutos($restaurante->horario_abertura, self::ABERTURA_PADRAO);
        $fechamento = $paraMinutos($restaurante->horario_fechamento, self::FECHAMENTO_PADRAO);

        // Fecha depois da meia-noite: trunca no fim do dia para não gerar slot
        // que caia no dia seguinte e desalinhe a curva por hora.
        if ($fechamento <= $abertura) {
            $fechamento = 24 * 60;
        }

        return [$abertura, $fechamento];
    }

    /** Tamanhos de grupo com a cauda curta típica de restaurante. */
    private function tamanhoDoGrupo(): int
    {
        $sorteio = $this->real(0, 1);

        return match (true) {
            $sorteio < 0.05 => 1,
            $sorteio < 0.40 => 2,
            $sorteio < 0.55 => 3,
            $sorteio < 0.80 => 4,
            $sorteio < 0.88 => 5,
            $sorteio < 0.96 => 6,
            default => 8,
        };
    }

    /** Knuth: número de chegadas num intervalo, dada a média esperada. */
    private function poisson(float $lambda): int
    {
        if ($lambda <= 0) {
            return 0;
        }

        $limite = exp(-$lambda);
        $k = 0;
        $p = 1.0;

        do {
            $k++;
            $p *= $this->real(0, 1);
        } while ($p > $limite);

        return $k - 1;
    }

    private function real(float $min, float $max): float
    {
        return $min + (mt_rand() / mt_getrandmax()) * ($max - $min);
    }

    private function inteiro(int $min, int $max): int
    {
        return mt_rand($min, $max);
    }
}
