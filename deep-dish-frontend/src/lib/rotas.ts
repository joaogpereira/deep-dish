/**
 * Rotas de autenticação: as telas onde alguém entra, cadastra ou recupera conta.
 *
 * Vale para duas decisões diferentes, e por isso mora fora das duas:
 * - o PublicLayout esconde a navbar nelas;
 * - o httpClient NÃO sequestra a navegação nelas. Um token de e-mail não
 *   verificado faz toda chamada autenticada voltar 403, e redirecionar daqui
 *   prendia a pessoa na tela de verificação: sem conseguir entrar com outra
 *   conta nem criar uma nova.
 */
export const ROTAS_DE_AUTENTICACAO = [
  '/login',
  '/register',
  '/restaurant/login',
  '/restaurant/register',
  '/forgot-password',
  '/restaurant/forgot-password',
  '/reset-password',
  '/verify-email',
] as const;

export function isRotaDeAutenticacao(pathname: string): boolean {
  return (ROTAS_DE_AUTENTICACAO as readonly string[]).includes(pathname);
}
