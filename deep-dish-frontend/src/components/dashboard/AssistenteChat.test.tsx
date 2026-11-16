import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent } from '@testing-library/react';
import { AssistenteChat } from './AssistenteChat';
import { assistenteService } from '@/services/assistente.service';
import { ApiError } from '@/services/httpClient';

vi.mock('@/services/assistente.service', () => ({ assistenteService: { perguntar: vi.fn() } }));

const perguntar = vi.mocked(assistenteService.perguntar);

function perguntarNaTela(texto: string) {
  fireEvent.change(screen.getByLabelText('Pergunta para o assistente'), { target: { value: texto } });
  fireEvent.click(screen.getByRole('button', { name: 'Enviar pergunta' }));
}

describe('Assistente do painel', () => {
  beforeEach(() => {
    vi.clearAllMocks();
  });

  it('nasce com sugestões de pergunta, e clicar em uma já pergunta', async () => {
    perguntar.mockResolvedValue({ conversa_id: 'c1', resposta: 'Sábado às 13h.', ferramentas_usadas: ['horario_de_pico'] });
    render(<AssistenteChat />);

    fireEvent.click(screen.getByRole('button', { name: 'Qual é o meu horário de pico?' }));

    expect(perguntar).toHaveBeenCalledWith('Qual é o meu horário de pico?', undefined);
    expect(await screen.findByText('Sábado às 13h.')).toBeInTheDocument();
  });

  it('mostra o estado de carregando enquanto a resposta não chega', async () => {
    let responder!: (r: Awaited<ReturnType<typeof assistenteService.perguntar>>) => void;
    perguntar.mockReturnValue(new Promise(resolve => { responder = resolve; }));
    render(<AssistenteChat />);

    perguntarNaTela('Como está a fila?');

    expect(screen.getByRole('status')).toHaveTextContent('Consultando os dados do restaurante…');
    expect(screen.getByRole('button', { name: 'Enviar pergunta' })).toBeDisabled();

    responder({ conversa_id: 'c1', resposta: 'Tranquila.', ferramentas_usadas: [] });

    expect(await screen.findByText('Tranquila.')).toBeInTheDocument();
    expect(screen.queryByRole('status')).not.toBeInTheDocument();
  });

  it('a resposta mostra o indicador de origem e leva ao gráfico dele', async () => {
    perguntar.mockResolvedValue({
      conversa_id: 'c1',
      resposta: '25% desistiram nos últimos 90 dias.',
      // Repetida de propósito: o modelo pode chamar a mesma ferramenta duas vezes.
      ferramentas_usadas: ['taxa_de_abandono', 'taxa_de_abandono', 'giro_de_mesa'],
    });
    const onVerGrafico = vi.fn();
    render(<AssistenteChat onVerGrafico={onVerGrafico} />);

    perguntarNaTela('Quanta gente desiste?');

    const fonte = await screen.findByRole('button', { name: 'Ver o gráfico Abandono da fila' });
    expect(screen.getAllByText('Abandono da fila')).toHaveLength(1);
    // Indicador sem gráfico no painel aparece como origem, mas não é link.
    expect(screen.getByText('Giro de mesa')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: /Giro de mesa/ })).not.toBeInTheDocument();

    fireEvent.click(fonte);
    expect(onVerGrafico).toHaveBeenCalledWith('abandono');
  });

  it('avisa quando a resposta não consultou nenhum indicador', async () => {
    perguntar.mockResolvedValue({ conversa_id: 'c1', resposta: 'Não tenho esse dado.', ferramentas_usadas: [] });
    render(<AssistenteChat />);

    perguntarNaTela('Qual o faturamento?');

    expect(await screen.findByText('Esta resposta não consultou nenhum indicador.')).toBeInTheDocument();
  });

  it('negrito do modelo vira destaque, sem asterisco na tela', async () => {
    perguntar.mockResolvedValue({ conversa_id: 'c1', resposta: 'O pico é **sábado às 20h**, com 115 entradas.', ferramentas_usadas: ['horario_de_pico'] });
    render(<AssistenteChat />);

    perguntarNaTela('Qual o pico?');

    const destaque = await screen.findByText('sábado às 20h');
    expect(destaque.tagName).toBe('STRONG');
    expect(document.body.textContent).not.toContain('**');
  });

  it('mantém o histórico e continua a mesma conversa', async () => {
    perguntar
      .mockResolvedValueOnce({ conversa_id: 'c1', resposta: 'Primeira resposta.', ferramentas_usadas: [] })
      .mockResolvedValueOnce({ conversa_id: 'c1', resposta: 'Segunda resposta.', ferramentas_usadas: [] });
    render(<AssistenteChat />);

    perguntarNaTela('Primeira pergunta');
    await screen.findByText('Primeira resposta.');
    perguntarNaTela('Segunda pergunta');
    await screen.findByText('Segunda resposta.');

    expect(perguntar).toHaveBeenLastCalledWith('Segunda pergunta', 'c1');
    expect(screen.getByText('Primeira pergunta')).toBeInTheDocument();
    expect(screen.getByText('Primeira resposta.')).toBeInTheDocument();
  });

  it('erro aparece na conversa e "Tentar novamente" refaz a mesma pergunta', async () => {
    perguntar
      .mockRejectedValueOnce(new ApiError(503, 'O provedor não respondeu.', { error: 'assistente_indisponivel' }))
      .mockResolvedValueOnce({ conversa_id: 'c1', resposta: 'Agora foi.', ferramentas_usadas: [] });
    render(<AssistenteChat />);

    perguntarNaTela('Qual o pico?');

    const alerta = await screen.findByRole('alert');
    expect(alerta).toHaveTextContent('O assistente não conseguiu responder');
    expect(alerta).toHaveTextContent('O provedor não respondeu.');

    fireEvent.click(screen.getByRole('button', { name: 'Tentar novamente' }));

    expect(await screen.findByText('Agora foi.')).toBeInTheDocument();
    expect(perguntar).toHaveBeenLastCalledWith('Qual o pico?', undefined);
    expect(screen.queryByRole('alert')).not.toBeInTheDocument();
    // A pergunta não é duplicada na conversa ao tentar de novo.
    expect(screen.getAllByText('Qual o pico?')).toHaveLength(1);
  });

  it('falha de rede tem mensagem própria', async () => {
    perguntar.mockRejectedValue(new TypeError('Failed to fetch'));
    render(<AssistenteChat />);

    perguntarNaTela('Qual o pico?');

    expect(await screen.findByRole('alert')).toHaveTextContent('Sem conexão com o servidor');
  });

  it('limite diário bloqueia novas perguntas e não oferece nova tentativa', async () => {
    perguntar.mockRejectedValue(new ApiError(
      429,
      'O limite diário do assistente foi atingido. Ele volta a funcionar amanhã.',
      { error: 'assistente_teto_tokens' },
    ));
    render(<AssistenteChat />);

    perguntarNaTela('Qual o pico?');

    const alerta = await screen.findByRole('alert');
    expect(alerta).toHaveTextContent('Limite diário atingido');
    expect(alerta).toHaveTextContent('Ele volta a funcionar amanhã.');
    expect(screen.queryByRole('button', { name: 'Tentar novamente' })).not.toBeInTheDocument();
    expect(screen.getByLabelText('Pergunta para o assistente')).toBeDisabled();
  });

  it('limite por minuto pede para aguardar e deixa tentar de novo', async () => {
    perguntar.mockRejectedValue(new ApiError(429, 'Muitas perguntas em pouco tempo.', { error: 'assistente_rate_limit' }));
    render(<AssistenteChat />);

    perguntarNaTela('Qual o pico?');

    expect(await screen.findByRole('alert')).toHaveTextContent('Muitas perguntas em pouco tempo');
    expect(screen.getByRole('button', { name: 'Tentar novamente' })).toBeInTheDocument();
    expect(screen.getByLabelText('Pergunta para o assistente')).not.toBeDisabled();
  });
});
