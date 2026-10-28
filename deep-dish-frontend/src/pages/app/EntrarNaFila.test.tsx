import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import EntrarNaFila from './EntrarNaFila';
import ProtectedRoute from '@/components/ProtectedRoute';
import { restaurantsService } from '@/services/restaurants.service';
import { queueService } from '@/services/queue.service';
import { lerDestino } from '@/lib/redirectAposLogin';
import type { Restaurante } from '@/types';

/**
 * Fluxo do QR da fila no frontend (issue #174): a rota de entrada é protegida,
 * guarda o destino quando não há sessão e, com sessão, deixa o cliente informar
 * o grupo e entrar.
 */

const auth = vi.hoisted(() => ({
  value: { user: null as unknown, isAuthenticated: false, isLoading: false },
}));
const filaAtual = vi.hoisted(() => ({ value: null as unknown }));

vi.mock('@/contexts/AuthContext', () => ({
  useAuth: () => auth.value,
}));
vi.mock('@/hooks/useFilaAtual', () => ({
  useFilaAtual: () => ({ fila: filaAtual.value, saida: null, salvar: vi.fn(), limpar: vi.fn(), atualizar: vi.fn() }),
}));
vi.mock('@/services/restaurants.service', () => ({
  restaurantsService: { getRestaurantById: vi.fn() },
}));
vi.mock('@/services/queue.service', () => ({
  queueService: { joinQueue: vi.fn() },
}));

const getRestaurant = vi.mocked(restaurantsService.getRestaurantById);
const joinQueue = vi.mocked(queueService.joinQueue);

const ID = '3f2c1d7e-8a4b-4c5d-9e6f-0a1b2c3d4e5f';
const ROTA = `/app/restaurants/${ID}/entrar-na-fila`;

const cliente = { id: 'cli-1', name: 'Cliente', cpf: '12345678901' };

function restaurante(parcial: Partial<Restaurante> = {}): Restaurante {
  return {
    id: ID,
    name: 'Cantina do Teste',
    fila_ativa: true,
    tamanho_fila_atual: 3,
    imagem_url: null,
    ...parcial,
  } as Restaurante;
}

function renderizar() {
  return render(
    <MemoryRouter initialEntries={[ROTA]}>
      <Routes>
        <Route path="/login" element={<p>tela de login</p>} />
        <Route path="/app/queue" element={<p>tela da fila</p>} />
        <Route element={<ProtectedRoute allow={['cliente']} />}>
          <Route path="/app/restaurants/:id/entrar-na-fila" element={<EntrarNaFila />} />
        </Route>
      </Routes>
    </MemoryRouter>,
  );
}

describe('EntrarNaFila (QR)', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    localStorage.clear();
    filaAtual.value = null;
    auth.value = { user: cliente, isAuthenticated: true, isLoading: false };
    getRestaurant.mockResolvedValue(restaurante());
  });

  it('sem sessão, vai para o login e guarda o destino do QR', () => {
    auth.value = { user: null, isAuthenticated: false, isLoading: false };

    renderizar();

    expect(screen.getByText('tela de login')).toBeInTheDocument();
    expect(lerDestino()).toBe(ROTA);
  });

  it('com sessão, entra na fila com o tamanho do grupo informado', async () => {
    joinQueue.mockResolvedValue({
      message: 'ok',
      data: { id: 'e1', qntd_pessoas: 5, posicao: 1, fila: { horario_reserva: '2026-01-01 12:00:00' } },
    } as never);

    renderizar();

    const campo = await screen.findByLabelText('Quantas pessoas?');
    fireEvent.change(campo, { target: { value: '5' } });
    fireEvent.click(screen.getByRole('button', { name: 'Entrar na fila' }));

    await waitFor(() => expect(joinQueue).toHaveBeenCalledWith({ restaurante_id: ID, qntd_pessoas: 5 }));
    expect(await screen.findByText('tela da fila')).toBeInTheDocument();
  });

  it('mostra o erro do servidor sem sair da tela', async () => {
    const { ApiError } = await import('@/services/httpClient');
    joinQueue.mockRejectedValue(new ApiError(422, 'A maior mesa deste restaurante comporta 4 pessoas.'));

    renderizar();

    fireEvent.click(await screen.findByRole('button', { name: 'Entrar na fila' }));

    expect(await screen.findByRole('alert')).toHaveTextContent('A maior mesa deste restaurante comporta 4 pessoas.');
  });

  it('fila fechada não oferece o formulário', async () => {
    getRestaurant.mockResolvedValue(restaurante({ fila_ativa: false }));

    renderizar();

    expect(await screen.findByText('Fila fechada')).toBeInTheDocument();
    expect(screen.queryByLabelText('Quantas pessoas?')).not.toBeInTheDocument();
  });

  it('quem já está na fila deste restaurante é levado a ver a posição', async () => {
    filaAtual.value = { entry: { id: 'e1', fila: { restaurante_id: ID } } };

    renderizar();

    expect(await screen.findByText('Você já está na fila')).toBeInTheDocument();
    expect(screen.queryByRole('button', { name: 'Entrar na fila' })).not.toBeInTheDocument();
  });

  it('restaurante inexistente', async () => {
    getRestaurant.mockResolvedValue(undefined as never);

    renderizar();

    expect(await screen.findByText('Restaurante não encontrado')).toBeInTheDocument();
  });
});
