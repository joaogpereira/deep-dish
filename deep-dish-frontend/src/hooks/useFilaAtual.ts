import { useCallback, useEffect, useRef, useState } from 'react';
import { useAuth } from '@/contexts/AuthContext';
import { useRealtime } from '@/hooks/useRealtime';
import { getEcho } from '@/lib/echo';
import { queueService } from '@/services/queue.service';
import { ApiError } from '@/services/httpClient';
import { ClienteFilaEntry } from '@/types';

const STORAGE_KEY = 'deepdish_fila';

// Rede de segurança para quando o socket cai e não volta: sem ela a posição
// congela. Com o socket conectado, quem atualiza é o evento, não o relógio.
const POLLING_MS = 90_000;

export interface FilaAtual {
  entry: ClienteFilaEntry;
  restaurantName: string;
  restaurantImage?: string;
  horarioReserva: string;
  clienteId?: string;
}

/** Vem do 404 de /fila/posicao. 'atendido' = promovido para mesa; null = nunca esteve na fila. */
export type StatusSaida = 'atendido' | 'desistiu' | 'removido' | 'expirado' | null;

export interface SaidaDaFila {
  status: StatusSaida;
  /** A fila de onde saiu, para a tela ainda poder mostrar o restaurante. */
  fila: FilaAtual;
}

function ler(clienteId: string | undefined): FilaAtual | null {
  const salvo = localStorage.getItem(STORAGE_KEY);
  if (!salvo) return null;

  let fila: FilaAtual;
  try { fila = JSON.parse(salvo); } catch { localStorage.removeItem(STORAGE_KEY); return null; }

  // Outro cliente logou neste navegador, ou o horário já passou há mais de 2h.
  const limite = new Date(fila.horarioReserva);
  limite.setHours(limite.getHours() + 2);
  if ((clienteId && fila.clienteId && fila.clienteId !== clienteId) || limite < new Date()) {
    localStorage.removeItem(STORAGE_KEY);
    return null;
  }

  return fila;
}

/** Segue o estado da conexão com o Reverb. Só abre o socket quando `ativo`. */
function useSocketConectado(ativo: boolean): boolean {
  const [conectado, setConectado] = useState(false);

  useEffect(() => {
    if (!ativo) return;

    const connection = (getEcho().connector as {
      pusher: {
        connection: {
          state: string;
          bind: (evento: string, cb: (e: { current: string }) => void) => void;
          unbind: (evento: string, cb: (e: { current: string }) => void) => void;
        };
      };
    }).pusher.connection;

    const aoMudar = ({ current }: { current: string }) => setConectado(current === 'connected');
    connection.bind('state_change', aoMudar);
    setConectado(connection.state === 'connected');

    return () => connection.unbind('state_change', aoMudar);
  }, [ativo]);

  return conectado;
}

/**
 * A fila em que o cliente está agora — única leitura da chave 'deepdish_fila'.
 *
 * Mantém a posição em dia pelo canal fila.{id} e, se o socket não estiver
 * conectado, por polling. Quando a consulta volta 404, o cliente saiu: `saida`
 * diz por quê, direto do backend.
 */
export function useFilaAtual() {
  const { user } = useAuth();
  const [fila, setFila]   = useState<FilaAtual | null>(() => ler(user?.id));
  const [saida, setSaida] = useState<SaidaDaFila | null>(null);

  // atualizar() é chamado por evento e por timer; lê a fila corrente por ref.
  const filaRef = useRef(fila);
  useEffect(() => { filaRef.current = fila; }, [fila]);

  useEffect(() => { setFila(ler(user?.id)); }, [user?.id]);

  const salvar = useCallback((nova: FilaAtual) => {
    localStorage.setItem(STORAGE_KEY, JSON.stringify(nova));
    setFila(nova);
    setSaida(null);
  }, []);

  const limpar = useCallback(() => {
    localStorage.removeItem(STORAGE_KEY);
    setFila(null);
  }, []);

  const atualizar = useCallback(async () => {
    const atual = filaRef.current;
    const restauranteId = atual?.entry.fila?.restaurante_id;
    const horario       = atual?.entry.fila?.horario_reserva;
    if (!atual || !restauranteId || !horario) return;

    // A resposta pode chegar depois de um salvar() de outra fila; aí ela é velha.
    const aindaEaMesma = () => filaRef.current?.entry.id === atual.entry.id;

    try {
      const entry = await queueService.consultarPosicao({ restaurante_id: restauranteId, horario_reserva: horario });
      if (!aindaEaMesma()) return;
      const nova = { ...atual, entry };
      localStorage.setItem(STORAGE_KEY, JSON.stringify(nova));
      setFila(nova);
    } catch (err) {
      // Só o 404 significa "saiu". Erro de rede não tira ninguém da fila.
      if (!(err instanceof ApiError) || err.status !== 404 || !aindaEaMesma()) return;
      const status = (err.data as { status_saida?: StatusSaida } | undefined)?.status_saida ?? null;
      localStorage.removeItem(STORAGE_KEY);
      setFila(null);
      setSaida({ status, fila: atual });
    }
  }, []);

  // O que está salvo pode estar velho: confere uma vez ao montar.
  useEffect(() => { atualizar(); }, [atualizar]);

  const filaId = fila?.entry.fila?.id ?? fila?.entry.fila_id;
  useRealtime(
    filaId ? `fila.${filaId}` : undefined,
    { 'posicao.atualizada': atualizar },
    atualizar
  );

  const temFila   = fila !== null;
  const conectado = useSocketConectado(temFila);
  useEffect(() => {
    if (!temFila || conectado) return;
    const timer = setInterval(atualizar, POLLING_MS);
    return () => clearInterval(timer);
  }, [temFila, conectado, atualizar]);

  return { fila, saida, salvar, limpar, atualizar };
}
