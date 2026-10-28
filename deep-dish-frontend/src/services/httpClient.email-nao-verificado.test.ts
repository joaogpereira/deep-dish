import { describe, it, expect, vi, beforeEach, afterEach } from 'vitest';
import { httpClient, ApiError } from './httpClient';

/**
 * Quem cadastra e ainda não verificou o e-mail fica com um token guardado que
 * leva 403 em toda chamada autenticada. O httpClient manda essa pessoa para a
 * tela de verificação — menos quando ela já está numa tela de autenticação,
 * senão não conseguiria entrar com outra conta nem criar uma nova.
 */
describe('httpClient com token de e-mail não verificado', () => {
  const local = { pathname: '/app/queue', href: 'http://localhost:8080/app/queue' };

  beforeEach(() => {
    Object.defineProperty(window, 'location', { value: local, writable: true });
    localStorage.setItem('jwt', 'token-de-teste');
    localStorage.setItem('tipo_usuario', 'cliente');

    vi.stubGlobal('fetch', vi.fn().mockResolvedValue(
      new Response(JSON.stringify({ error: 'email_not_verified' }), { status: 403 })
    ));
  });

  afterEach(() => {
    vi.unstubAllGlobals();
    localStorage.clear();
  });

  it('numa tela do app, manda para a verificação', async () => {
    local.pathname = '/app/queue';

    await expect(httpClient.get('/cliente/me')).rejects.toBeInstanceOf(ApiError);

    expect(window.location.href).toBe('/verify-email?tipo=cliente');
  });

  it('na tela de login, não sequestra a navegação', async () => {
    local.pathname = '/login';
    local.href = 'http://localhost:8080/login';

    await expect(httpClient.get('/cliente/me')).rejects.toBeInstanceOf(ApiError);

    // Continua onde estava: é aqui que a pessoa troca de conta.
    expect(window.location.href).toBe('http://localhost:8080/login');
  });

  it('na própria tela de verificação, também não redireciona', async () => {
    local.pathname = '/verify-email';
    local.href = 'http://localhost:8080/verify-email?tipo=cliente';

    await expect(httpClient.get('/cliente/me')).rejects.toBeInstanceOf(ApiError);

    expect(window.location.href).toBe('http://localhost:8080/verify-email?tipo=cliente');
  });
});
