import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen } from '@testing-library/react';
import { MemoryRouter } from 'react-router-dom';
import Queue from './Queue';

/**
 * #170: a posição por chegada não pode parecer promessa de ordem de chamada.
 * A tela diz se a vez é garantida e, se não, em quanto tempo passa a ser.
 */

const fila = vi.hoisted(() => ({ value: null as unknown }));

vi.mock('@/hooks/useFilaAtual', () => ({
  useFilaAtual: () => ({ fila: fila.value, saida: null, salvar: vi.fn(), limpar: vi.fn(), atualizar: vi.fn() }),
}));
vi.mock('@/hooks/useRealtime', () => ({ useRealtime: vi.fn() }));
vi.mock('@/contexts/AuthContext', () => ({ useAuth: () => ({ user: { id: 'c1' } }) }));
vi.mock('@/services/queue.service', () => ({ queueService: { cancelQueue: vi.fn() } }));
vi.mock('@/services/reservations.service', () => ({ reservationsService: {} }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

function com(entry: Record<string, unknown>) {
  fila.value = {
    entry: { id: 'e1', qntd_pessoas: 2, posicao: 2, ...entry },
    restaurantName: 'Cantina',
    horarioReserva: '2026-01-01 12:00:00',
  };
  return render(<MemoryRouter><Queue /></MemoryRouter>);
}

describe('Queue — transparência da prioridade', () => {
  beforeEach(() => { fila.value = null; });

  it('não protegida: posição por chegada e tempo até a vez garantida', () => {
    com({ protegida: false, protecao_em_minutos: 12 });

    expect(screen.getByText('por chegada')).toBeInTheDocument();
    expect(screen.getByTestId('aviso-prioridade')).toHaveTextContent('pode ser chamado antes de você');
    expect(screen.getByTestId('aviso-prioridade')).toHaveTextContent('12 min');
  });

  it('protegida: posição garantida, sem aviso de ultrapassagem', () => {
    com({ protegida: true, protecao_em_minutos: 0 });

    expect(screen.getByText('garantida')).toBeInTheDocument();
    expect(screen.getByTestId('aviso-prioridade')).toHaveTextContent('ninguém passa na sua frente');
    expect(screen.getByTestId('aviso-prioridade')).not.toHaveTextContent('pode ser chamado antes');
  });
});
