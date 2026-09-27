import { useEffect, useRef } from 'react';
import { getEcho } from '@/lib/echo';

type Handlers = Record<string, () => void>;

/** Intervalo do polling de reserva, o mesmo que as telas usavam antes do Reverb. */
export const POLLING_FALLBACK_MS = 30_000;

interface PusherLike {
  connection: {
    state: string;
    bind: (evento: string, cb: () => void) => void;
    unbind: (evento: string, cb: () => void) => void;
  };
}

/**
 * Assina um canal privado do Reverb e liga um handler por evento.
 *
 * Dois cuidados que motivam o hook existir, em vez de repetir isto em cada tela:
 *
 * 1. Os handlers sao guardados numa ref. Se entrassem nas dependencias do efeito,
 *    cada re-render criaria funcoes novas e a inscricao seria derrubada e refeita
 *    a cada atualizacao de estado — justamente o estado que o evento acabou de
 *    provocar.
 * 2. Depois de uma queda de conexao, nada e reenviado. Por isso o `connected`
 *    dispara `aoReconectar`, para a tela buscar o que perdeu enquanto esteve fora.
 * 3. Com o Reverb fora do ar, a tela volta ao polling de antes: a cada 30s, se o
 *    socket nao estiver conectado, `aoReconectar` e chamado. Sem `aoReconectar`
 *    nao ha fallback — quem precisa dele deve passar a funcao de recarga.
 *

 * @param canal Nome do canal sem o prefixo 'private-' (ex.: `restaurante.${id}`).
 *              Passe undefined enquanto o id ainda nao existir.
 * @param handlers Mapa de evento -> callback. A chave e o nome do broadcastAs()
 *                 sem o ponto inicial (ex.: 'fila.atualizada').
 */
export function useRealtime(
  canal: string | undefined,
  handlers: Handlers,
  aoReconectar?: () => void
): void {
  const handlersRef = useRef(handlers);
  const reconectarRef = useRef(aoReconectar);

  useEffect(() => {
    handlersRef.current = handlers;
    reconectarRef.current = aoReconectar;
  });

  // Os nomes dos eventos sao estaveis; o objeto que os carrega nao e.
  const eventos = Object.keys(handlers).sort().join('|');

  useEffect(() => {
    if (!canal) return;

    const resync = () => reconectarRef.current?.();

    let echo: ReturnType<typeof getEcho>;
    try {
      echo = getEcho();
    } catch {
      // Echo nem sobe (ex.: VITE_REVERB_APP_KEY vazia): so polling, sem derrubar a tela.
      const polling = setInterval(resync, POLLING_FALLBACK_MS);
      return () => clearInterval(polling);
    }

    const pusher = (echo.connector as { pusher: PusherLike }).pusher;
    const assinatura = echo.private(canal);

    eventos.split('|').filter(Boolean).forEach(evento => {
      assinatura.listen(`.${evento}`, () => handlersRef.current[evento]?.());
    });

    pusher.connection.bind('connected', resync);

    const polling = setInterval(() => {
      if (pusher.connection.state !== 'connected') resync();
    }, POLLING_FALLBACK_MS);

    return () => {
      clearInterval(polling);
      pusher.connection.unbind('connected', resync);
      echo.leave(canal);
    };
  }, [canal, eventos]);
}
