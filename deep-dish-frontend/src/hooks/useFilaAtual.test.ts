import { renderHook, waitFor, act } from '@testing-library/react';
import { vi, describe, it, expect, beforeEach, afterEach } from 'vitest';
import { ApiError } from '@/services/httpClient';

const consultarPosicao = vi.fn();
vi.mock('@/services/queue.service', () => ({ queueService: { consultarPosicao: (...a: unknown[]) => consultarPosicao(...a) } }));
vi.mock('@/contexts/AuthContext', () => ({ useAuth: () => ({ user: { id: 'cli-1' } }) }));
vi.mock('@/hooks/useRealtime', () => ({ useRealtime: () => {} }));

// Conexão falsa do pusher: o teste decide o estado do socket.
const conexao = { state: 'connected', bind: vi.fn(), unbind: vi.fn() };
vi.mock('@/lib/echo', () => ({ getEcho: () => ({ connector: { pusher: { connection: conexao } } }) }));

import { useFilaAtual } from './useFilaAtual';

const entry = {
  id: 'cf-1', fila_id: 'fila-1', cliente_id: 'cli-1', qntd_pessoas: 2, posicao: 3,
  created_at: '', updated_at: '',
  fila: { id: 'fila-1', restaurante_id: 'rest-1', horario_reserva: '2099-01-01T20:00:00Z', status: 'aberta' },
};
const salva = { entry, restaurantName: 'Cantina', horarioReserva: '2099-01-01T20:00:00Z', clienteId: 'cli-1' };

describe('useFilaAtual', () => {
  beforeEach(() => {
    localStorage.setItem('deepdish_fila', JSON.stringify(salva));
    consultarPosicao.mockReset();
    conexao.state = 'connected';
  });
  afterEach(() => {
    localStorage.clear();
    vi.useRealTimers();
  });

  it('atualiza a posição ao montar', async () => {
    consultarPosicao.mockResolvedValue({ ...entry, posicao: 1 });
    const { result } = renderHook(() => useFilaAtual());
    await waitFor(() => expect(result.current.fila?.entry.posicao).toBe(1));
  });

  it('404 com status_saida vira saida com o motivo, e limpa a fila salva', async () => {
    consultarPosicao.mockRejectedValue(new ApiError(404, 'x', { status_saida: 'atendido' }));
    const { result } = renderHook(() => useFilaAtual());

    await waitFor(() => expect(result.current.saida?.status).toBe('atendido'));
    expect(result.current.fila).toBeNull();
    expect(result.current.saida?.fila.restaurantName).toBe('Cantina');
    expect(localStorage.getItem('deepdish_fila')).toBeNull();
  });

  it('erro que não é 404 não tira o cliente da fila', async () => {
    consultarPosicao.mockRejectedValue(new TypeError('Failed to fetch'));
    const { result } = renderHook(() => useFilaAtual());

    await waitFor(() => expect(consultarPosicao).toHaveBeenCalled());
    expect(result.current.fila).not.toBeNull();
    expect(result.current.saida).toBeNull();
  });

  it('com o socket fora, faz polling; conectado, não', async () => {
    vi.useFakeTimers();
    consultarPosicao.mockResolvedValue(entry);

    conexao.state = 'unavailable';
    const fora = renderHook(() => useFilaAtual());
    await act(async () => { await vi.advanceTimersByTimeAsync(0); });
    const aoMontar = consultarPosicao.mock.calls.length;
    await act(async () => { await vi.advanceTimersByTimeAsync(90_000); });
    expect(consultarPosicao.mock.calls.length).toBe(aoMontar + 1);
    fora.unmount();

    consultarPosicao.mockClear();
    conexao.state = 'connected';
    renderHook(() => useFilaAtual());
    await act(async () => { await vi.advanceTimersByTimeAsync(0); });
    const aoMontarConectado = consultarPosicao.mock.calls.length;
    await act(async () => { await vi.advanceTimersByTimeAsync(180_000); });
    expect(consultarPosicao.mock.calls.length).toBe(aoMontarConectado);
  });
});
