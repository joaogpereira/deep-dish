<?php

namespace App\Services;

/**
 * O que o assistente devolve para uma pergunta (#178).
 *
 * Como o OpenRouterClient, não existe caminho de exceção: falha vira `ok = false`
 * com motivo legível.
 *
 * 'ferramentasUsadas' e 'uso' existem para a #179, que precisa registrar consumo
 * por restaurante e impor teto de tokens — sem eles ela teria que reabrir este
 * contrato. 'mensagens' devolve a conversa completa (incluindo as trocas de
 * ferramenta) para a #179 persistir contexto entre mensagens.
 */
readonly class RespostaAssistente
{
    /**
     * @param  list<string>  $ferramentasUsadas
     * @param  array{prompt: int, resposta: int, total: int}  $uso
     * @param  list<array<string, mixed>>  $mensagens
     */
    private function __construct(
        public bool $ok,
        public string $texto,
        public array $ferramentasUsadas,
        public array $uso,
        public array $mensagens,
        public ?string $erro,
    ) {}

    /**
     * @param  list<string>  $ferramentasUsadas
     * @param  array{prompt: int, resposta: int, total: int}  $uso
     * @param  list<array<string, mixed>>  $mensagens
     */
    public static function sucesso(
        string $texto,
        array $ferramentasUsadas,
        array $uso,
        array $mensagens,
    ): self {
        return new self(true, $texto, $ferramentasUsadas, $uso, $mensagens, null);
    }

    /**
     * @param  list<string>  $ferramentasUsadas
     * @param  array{prompt: int, resposta: int, total: int}  $uso
     */
    public static function falha(
        string $erro,
        array $ferramentasUsadas = [],
        array $uso = ['prompt' => 0, 'resposta' => 0, 'total' => 0],
    ): self {
        return new self(false, '', $ferramentasUsadas, $uso, [], $erro);
    }

    /** O modelo chegou à resposta sem consultar nada — sinal de número inventado. */
    public function respondeuSemConsultar(): bool
    {
        return $this->ok && $this->ferramentasUsadas === [];
    }
}
