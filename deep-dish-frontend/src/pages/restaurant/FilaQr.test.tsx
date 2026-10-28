import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import FilaQr from './FilaQr';
import { queueService } from '@/services/queue.service';

vi.mock('@/services/queue.service', () => ({
  queueService: { getFilaQr: vi.fn() },
}));

const getFilaQr = vi.mocked(queueService.getFilaQr);

const URL_QR = 'http://localhost:8000/api/fila/qr/3f2c1d7e-8a4b-4c5d-9e6f-0a1b2c3d4e5f?signature=abc';

function renderizar() {
  return render(<MemoryRouter><FilaQr /></MemoryRouter>);
}

describe('Tela de impressão do QR da fila', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getFilaQr.mockResolvedValue({ restaurante_id: 'r1', nome: 'Cantina do Teste', fila_ativa: true, url: URL_QR });
  });

  it('mostra o nome, o texto orientativo e o QR da URL assinada', async () => {
    const { container } = renderizar();

    expect(await screen.findByRole('heading', { name: 'Cantina do Teste' })).toBeInTheDocument();
    expect(screen.getByText('Escaneie para entrar na fila')).toBeInTheDocument();
    expect(container.querySelector('svg[role="img"], svg')).not.toBeNull();
  });

  it('o botão imprimir chama window.print e some na impressão', async () => {
    const print = vi.spyOn(window, 'print').mockImplementation(() => {});

    renderizar();

    const botao = await screen.findByRole('button', { name: /Imprimir/ });
    fireEvent.click(botao);

    expect(print).toHaveBeenCalledTimes(1);
    expect(botao.parentElement?.className).toContain('print:hidden');
  });

  it('avisa quando a fila está desativada', async () => {
    getFilaQr.mockResolvedValue({ restaurante_id: 'r1', nome: 'Cantina', fila_ativa: false, url: URL_QR });

    renderizar();

    expect(await screen.findByText(/fila está desativada/i)).toBeInTheDocument();
  });

  it('mostra erro quando o QR não pode ser gerado', async () => {
    getFilaQr.mockRejectedValue(new Error('falhou'));

    renderizar();

    await waitFor(() => expect(screen.getByRole('alert')).toBeInTheDocument());
  });
});
