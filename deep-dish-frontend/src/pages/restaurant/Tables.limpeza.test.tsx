import React from 'react';
import { describe, it, expect, vi, beforeEach } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import Tables from './Tables';
import { mesasService } from '@/services/mesas.service';
import type { Mesa, MesaStatus } from '@/types';

/**
 * O botão de limpeza aparece conforme o estado da mesa (#171).
 *
 * O ciclo é `aguardando_limpeza -> em_limpeza -> livre`, e cada passo é uma ação
 * do painel. O teste olha o argumento mandado ao serviço, não o texto na tela: o
 * que importa é qual transição foi pedida, e um rótulo errado quebraria menos que
 * uma transição errada.
 */

vi.mock('@/services/mesas.service', () => ({
  mesasService: { list: vi.fn(), update: vi.fn(), create: vi.fn(), remove: vi.fn() },
}));
vi.mock('@/contexts/AuthContext', () => ({
  useAuth: () => ({ user: { id: 'rest-1', name: 'Restaurante' } }),
}));
vi.mock('@/hooks/useRealtime', () => ({ useRealtime: () => undefined }));
vi.mock('sonner', () => ({ toast: { error: vi.fn(), success: vi.fn() } }));

const listar = vi.mocked(mesasService.list);
const atualizar = vi.mocked(mesasService.update);

function mesa(status: MesaStatus): Mesa {
  return {
    id: 'mesa-1',
    restaurante_id: 'rest-1',
    numero: 7,
    capacidade: 4,
    status,
  } as unknown as Mesa;
}

beforeEach(() => {
  vi.clearAllMocks();
  atualizar.mockImplementation(async (_id, dados) => mesa(dados.status as MesaStatus));
});

describe('Tela de mesas — ciclo de limpeza', () => {
  it('mesa aguardando limpeza oferece iniciar, e pede em_limpeza', async () => {
    listar.mockResolvedValue([mesa('aguardando_limpeza')]);
    render(<Tables />);

    fireEvent.click(await screen.findByRole('button', { name: /iniciar limpeza/i }));

    await waitFor(() => expect(atualizar).toHaveBeenCalledWith('mesa-1', { status: 'em_limpeza' }));
  });

  it('mesa em limpeza oferece concluir, e pede livre', async () => {
    listar.mockResolvedValue([mesa('em_limpeza')]);
    render(<Tables />);

    fireEvent.click(await screen.findByRole('button', { name: /limpeza concluída/i }));

    await waitFor(() => expect(atualizar).toHaveBeenCalledWith('mesa-1', { status: 'livre' }));
  });

  it('mesa livre não mostra botão de limpeza', async () => {
    listar.mockResolvedValue([mesa('livre')]);
    render(<Tables />);

    // Espera a lista aparecer antes de afirmar a ausência.
    await screen.findByRole('button', { name: /bloquear/i });

    expect(screen.queryByRole('button', { name: /limpeza/i })).toBeNull();
  });
});
