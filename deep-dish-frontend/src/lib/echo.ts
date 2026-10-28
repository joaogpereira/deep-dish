import Echo from 'laravel-echo';
import Pusher from 'pusher-js';

/**
 * Conexão WebSocket com o Laravel Reverb.
 *
 * O ponto sensível aqui é o token. O httpClient rotaciona o JWT no 401 e regrava
 * o localStorage, então um Authorization capturado na criação do Echo fica velho
 * na primeira renovação e as inscrições em canal passam a ser negadas. Por isso a
 * autorização usa um `authorizer` próprio, que lê o token no momento de cada
 * inscrição em vez de guardá-lo.
 */

const API_URL = import.meta.env.VITE_API_URL ?? 'http://localhost:8000/api';

let echo: Echo<'reverb'> | null = null;

export function getEcho(): Echo<'reverb'> {
  if (echo) return echo;

  // laravel-echo espera o Pusher no escopo global.
  (window as unknown as { Pusher: typeof Pusher }).Pusher = Pusher;

  echo = new Echo({
    broadcaster: 'reverb',
    key: import.meta.env.VITE_REVERB_APP_KEY,
    wsHost: import.meta.env.VITE_REVERB_HOST,
    wsPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8081),
    wssPort: Number(import.meta.env.VITE_REVERB_PORT ?? 8081),
    forceTLS: import.meta.env.VITE_REVERB_SCHEME === 'https',
    enabledTransports: ['ws', 'wss'],

    authorizer: (channel: { name: string }) => ({
      authorize: (
        socketId: string,
        callback: (error: boolean, data: unknown) => void
      ) => {
        fetch(`${API_URL}/broadcasting/auth`, {
          method: 'POST',
          headers: {
            'Content-Type': 'application/json',
            Accept: 'application/json',
            Authorization: `Bearer ${localStorage.getItem('jwt') ?? ''}`,
          },
          body: JSON.stringify({
            socket_id: socketId,
            channel_name: channel.name,
          }),
        })
          .then(res => (res.ok ? res.json() : Promise.reject(res)))
          .then(data => callback(false, data))
          .catch(err => callback(true, err));
      },
    }),
  });

  return echo;
}

/** Encerra a conexão — usar no logout, para não deixar socket aberto. */
export function disconnectEcho(): void {
  echo?.disconnect();
  echo = null;
}
