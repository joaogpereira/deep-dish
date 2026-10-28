import { describe, it, expect, beforeEach, vi, afterEach } from 'vitest';
import {
  destinoSeguro,
  salvarDestino,
  lerDestino,
  consumirDestino,
} from './redirectAposLogin';

const DESTINO = '/app/restaurants/3f2c1d7e-8a4b-4c5d-9e6f-0a1b2c3d4e5f/entrar-na-fila';

describe('redirectAposLogin', () => {
  beforeEach(() => localStorage.clear());
  afterEach(() => vi.useRealTimers());

  it('aceita caminhos internos da área do cliente', () => {
    expect(destinoSeguro(DESTINO)).toBe(DESTINO);
    expect(destinoSeguro('/app')).toBe('/app');
    expect(destinoSeguro('/app/queue?x=1')).toBe('/app/queue?x=1');
  });

  it.each([
    'https://evil.example',
    '//evil.example',
    '/\\evil.example',
    'javascript:alert(1)',
    '/restaurant/dashboard',
    '/app/../login',
    '/app//evil.example',
    '/login',
    '',
  ])('recusa destino inseguro %s', destino => {
    expect(destinoSeguro(destino)).toBeNull();
  });

  it('guarda e devolve o destino, e consumir apaga', () => {
    salvarDestino(DESTINO);
    expect(lerDestino()).toBe(DESTINO);
    expect(consumirDestino()).toBe(DESTINO);
    expect(lerDestino()).toBeNull();
  });

  it('não guarda destino inseguro', () => {
    salvarDestino('https://evil.example');
    expect(lerDestino()).toBeNull();
  });

  it('descarta destino adulterado direto no storage', () => {
    localStorage.setItem(
      'redirect_apos_login',
      JSON.stringify({ destino: 'https://evil.example', salvoEm: Date.now() }),
    );
    expect(lerDestino()).toBeNull();
  });

  it('descarta destino vencido', () => {
    vi.useFakeTimers();
    salvarDestino(DESTINO);
    vi.advanceTimersByTime(61 * 60 * 1000);
    expect(lerDestino()).toBeNull();
  });
});
