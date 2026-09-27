import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { renderHook } from '@testing-library/react';
import { useRealtime, POLLING_FALLBACK_MS } from './useRealtime';
import { getEcho } from '@/lib/echo';

vi.mock('@/lib/echo', () => ({ getEcho: vi.fn() }));

const getEchoMock = vi.mocked(getEcho);

/** Echo falso: so o que o hook usa — private/listen, leave e o estado da conexao. */
function echoFalso(estadoInicial: string) {
  const connection = { state: estadoInicial, bind: vi.fn(), unbind: vi.fn() };
  const echo = {
    connector: { pusher: { connection } },
    private: vi.fn(() => ({ listen: vi.fn() })),
    leave: vi.fn(),
  };
  getEchoMock.mockReturnValue(echo as unknown as ReturnType<typeof getEcho>);
  return { echo, connection };
}

describe('useRealtime — fallback para polling', () => {
  beforeEach(() => {
    vi.useFakeTimers();
    getEchoMock.mockReset();
  });

  afterEach(() => {
    vi.useRealTimers();
  });

  it('com o socket conectado, nao faz polling', () => {
    echoFalso('connected');
    const recarregar = vi.fn();

    renderHook(() => useRealtime('restaurante.1', { 'fila.atualizada': vi.fn() }, recarregar));
    vi.advanceTimersByTime(POLLING_FALLBACK_MS * 3);

    expect(recarregar).not.toHaveBeenCalled();
  });

  it('com o Reverb fora do ar, recarrega a cada 30s', () => {
    echoFalso('unavailable');
    const recarregar = vi.fn();

    renderHook(() => useRealtime('restaurante.1', { 'fila.atualizada': vi.fn() }, recarregar));

    vi.advanceTimersByTime(POLLING_FALLBACK_MS - 1);
    expect(recarregar).not.toHaveBeenCalled();

    vi.advanceTimersByTime(1);
    expect(recarregar).toHaveBeenCalledTimes(1);

    vi.advanceTimersByTime(POLLING_FALLBACK_MS);
    expect(recarregar).toHaveBeenCalledTimes(2);
  });

  it('para o polling quando a conexao volta', () => {
    const { connection } = echoFalso('unavailable');
    const recarregar = vi.fn();

    renderHook(() => useRealtime('restaurante.1', { 'fila.atualizada': vi.fn() }, recarregar));
    vi.advanceTimersByTime(POLLING_FALLBACK_MS);
    expect(recarregar).toHaveBeenCalledTimes(1);

    connection.state = 'connected';
    vi.advanceTimersByTime(POLLING_FALLBACK_MS * 3);

    expect(recarregar).toHaveBeenCalledTimes(1);
  });

  it('se o Echo nem sobe, a tela nao quebra e cai no polling', () => {
    getEchoMock.mockImplementation(() => {
      throw new Error('You must pass your app key when you instantiate Pusher.');
    });
    const recarregar = vi.fn();

    expect(() =>
      renderHook(() => useRealtime('cliente.1', { 'reserva.atualizada': vi.fn() }, recarregar))
    ).not.toThrow();

    vi.advanceTimersByTime(POLLING_FALLBACK_MS);
    expect(recarregar).toHaveBeenCalledTimes(1);
  });

  it('ao desmontar, para o polling e sai do canal', () => {
    const { echo } = echoFalso('unavailable');
    const recarregar = vi.fn();

    const { unmount } = renderHook(() =>
      useRealtime('restaurante.1', { 'fila.atualizada': vi.fn() }, recarregar)
    );
    unmount();
    vi.advanceTimersByTime(POLLING_FALLBACK_MS * 2);

    expect(recarregar).not.toHaveBeenCalled();
    expect(echo.leave).toHaveBeenCalledWith('restaurante.1');
  });
});
