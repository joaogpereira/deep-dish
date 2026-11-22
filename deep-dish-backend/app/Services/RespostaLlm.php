<?php

namespace App\Services;

/**
 * O que uma chamada ao LLM devolve (#177).
 *
 * Existe para o chamador nunca precisar de try/catch: falha de rede, chave
 * errada, timeout e resposta malformada todas chegam aqui como `ok = false` com
 * um motivo legível. O dashboard não pode cair porque o assistente caiu.
 *
 * 'toolCalls' já faz parte do contrato, mesmo sem uso na #177: é a #178 que vai
 * ler isso, e deixar o campo de fora agora obrigaria a mexer em todo o caminho
 * depois.
 */
readonly class RespostaLlm
{
    /**
     * @param  list<array{id: string, nome: string, argumentos: array<string, mixed>}>  $toolCalls
     * @param  array{prompt: int, resposta: int, total: int}  $uso
     */
    private function __construct(
        public bool $ok,
        public string $texto,
        public array $toolCalls,
        public array $uso,
        public ?string $erro,
        public ?string $motivoParada,
    ) {}

    /**
     * @param  list<array{id: string, nome: string, argumentos: array<string, mixed>}>  $toolCalls
     * @param  array{prompt: int, resposta: int, total: int}  $uso
     */
    public static function sucesso(
        string $texto,
        array $toolCalls = [],
        array $uso = ['prompt' => 0, 'resposta' => 0, 'total' => 0],
        ?string $motivoParada = null,
    ): self {
        return new self(true, $texto, $toolCalls, $uso, null, $motivoParada);
    }

    public static function falha(string $erro): self
    {
        return new self(false, '', [], ['prompt' => 0, 'resposta' => 0, 'total' => 0], $erro, null);
    }

    /** O modelo pediu ferramenta em vez de responder — a #178 trata isso. */
    public function pediuFerramenta(): bool
    {
        return $this->toolCalls !== [];
    }
}
