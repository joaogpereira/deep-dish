import { useEffect, useRef } from 'react';
import { getEcho } from '@/lib/echo';

type Handlers = Record<string, (dados: unknown) => void>;

interface PusherLike {
  connection: {
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
 *
 * @param canal Nome do canal sem o prefixo 'private-' (ex.: `restaurante.${id}`).
 *              Passe undefined enquanto o id ainda nao existir.
 * @param handlers Mapa de evento -> callback. A chave e o nome do broadcastAs()
 *                 sem o ponto inicial (ex.: 'fila.atualizada'); o callback recebe
 *                 o payload do evento (as propriedades publicas dele no backend).
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

    const echo = getEcho();
    const pusher = (echo.connector as { pusher: PusherLike }).pusher;
    const assinatura = echo.private(canal);

    eventos.split('|').filter(Boolean).forEach(evento => {
      assinatura.listen(`.${evento}`, (dados: unknown) => handlersRef.current[evento]?.(dados));
    });

    const resync = () => reconectarRef.current?.();
    pusher.connection.bind('connected', resync);

    return () => {
      pusher.connection.unbind('connected', resync);
      echo.leave(canal);
    };
  }, [canal, eventos]);
}
