import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { MemoryRouter, Routes, Route } from 'react-router-dom';
import RestaurantDetail from './RestaurantDetail';
import { restaurantsService } from '@/services/restaurants.service';
import { queueService } from '@/services/queue.service';
import type { Restaurante } from '@/types';

/**
 * O tamanho do grupo informado na tela é o que chega na API.
 *
 * Antes não havia campo nenhum no card de fila: o único input de quantidade
 * vivia no card de reservas, atrás de `reservations_enabled`, então toda entrada
 * ia com 2 pessoas — o valor inicial do estado. A alocação de mesa (#169) decide
 * pelo tamanho do grupo, e com todo mundo valendo 2 ela não tinha o que decidir.
 *
 * É um bug silencioso: nada quebrava, a fila só mentia o tempo todo. Por isso o
 * teste olha o argumento da chamada, e não a tela.
 */

vi.mock('@/services/restaurants.service', () => ({
  restaurantsService: { getRestaurantById: vi.fn(), getStaff: vi.fn() },
}));
vi.mock('@/services/queue.service', () => ({
  queueService: { joinQueue: vi.fn(), consultarEstimativa: vi.fn() },
}));
vi.mock('@/hooks/useFilaAtual', () => ({
  useFilaAtual: () => ({ fila: null, saida: null, salvar: vi.fn(), limpar: vi.fn(), atualizar: vi.fn() }),
}));
vi.mock('@/contexts/AuthContext', () => ({
  useAuth: () => ({ user: { id: 'cli-1', name: 'Cliente' } }),
}));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

const getRestaurant = vi.mocked(restaurantsService.getRestaurantById);
const getStaff = vi.mocked(restaurantsService.getStaff);
const joinQueue = vi.mocked(queueService.joinQueue);
const consultarEstimativa = vi.mocked(queueService.consultarEstimativa);

/** Restaurante só com fila: sem reservas o card de reservas nem é renderizado. */
function restauranteComFila(): Restaurante {
  return {
    id: 'r1',
    name: 'Cantina do Teste',
    description: 'Massas',
    tipo: 'italiana',
    logradouro: 'Rua A',
    numero: '10',
    bairro: 'Centro',
    cidade: 'Brasília',
    telefone: '61999999999',
    rating: 4.5,
    price_range: 2,
    imagem_url: null,
    horario_abertura: '11:00',
    horario_fechamento: '23:00',
    reservations_enabled: false,
    fila_ativa: true,
    tamanho_fila_atual: 3,
  } as unknown as Restaurante;
}

function renderizar() {
  return render(
    <MemoryRouter initialEntries={['/app/restaurant/r1']}>
      <Routes>
        <Route path="/app/restaurant/:id" element={<RestaurantDetail />} />
        <Route path="/app/queue" element={<div>tela da fila</div>} />
      </Routes>
    </MemoryRouter>
  );
}

beforeEach(() => {
  vi.clearAllMocks();
  getRestaurant.mockResolvedValue(restauranteComFila());
  getStaff.mockResolvedValue([]);
  consultarEstimativa.mockResolvedValue({ espera_estimada_minutos: 15, nivel: 'historico' } as never);
  joinQueue.mockResolvedValue({
    message: 'ok',
    data: { id: 'cf-1', fila: { horario_reserva: '2026-10-02T20:00:00Z' } },
  } as never);
});

describe('Entrar na fila — tamanho do grupo', () => {
  it('manda para a API o número digitado, e não o padrão de 2', async () => {
    renderizar();

    const campo = await screen.findByLabelText('Quantas pessoas?');
    fireEvent.change(campo, { target: { value: '6' } });

    fireEvent.click(screen.getByRole('button', { name: /entrar na fila/i }));

    await waitFor(() => expect(joinQueue).toHaveBeenCalledTimes(1));
    expect(joinQueue).toHaveBeenCalledWith({ restaurante_id: 'r1', qntd_pessoas: 6 });
  });

  it('a espera estimada é consultada para o tamanho do grupo informado', async () => {
    renderizar();

    const campo = await screen.findByLabelText('Quantas pessoas?');
    fireEvent.change(campo, { target: { value: '8' } });

    // Sem isto a tela prometeria a espera de um casal para uma mesa de 8.
    await waitFor(() =>
      expect(consultarEstimativa).toHaveBeenLastCalledWith({ restaurante_id: 'r1', qntd_pessoas: 8 })
    );
  });
});
