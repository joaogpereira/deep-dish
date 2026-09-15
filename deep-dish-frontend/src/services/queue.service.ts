import { ClienteFilaEntry, EstimativaEspera } from '@/types';
import { httpClient } from './httpClient';

export const queueService = {
  // ─── Cliente ────────────────────────────────────────────
  async joinQueue(payload: {
    restaurante_id: string;
    horario_reserva: string;
    qntd_pessoas: number;
  }): Promise<{ message: string; data: ClienteFilaEntry }> {
    return httpClient.post('/fila', payload);
  },

  async cancelQueue(entryId: string): Promise<void> {
    return httpClient.delete(`/fila/${entryId}`);
  },

  async consultarPosicao(params: {
    restaurante_id: string;
    horario_reserva: string;
  }): Promise<ClienteFilaEntry> {
    const qs = new URLSearchParams(params).toString();
    return httpClient.get(`/fila/posicao?${qs}`);
  },

  // Estimativa de espera "se eu entrasse agora" — usada antes de entrar na fila.
  async consultarEstimativa(params: {
    restaurante_id: string;
    horario_reserva: string;
    qntd_pessoas: number;
  }): Promise<EstimativaEspera> {
    const qs = new URLSearchParams({
      restaurante_id: params.restaurante_id,
      horario_reserva: params.horario_reserva,
      qntd_pessoas: String(params.qntd_pessoas),
    }).toString();
    return httpClient.get(`/fila/estimativa?${qs}`);
  },

  // ─── Restaurante ────────────────────────────────────────
  async getRestaurantQueue(): Promise<ClienteFilaEntry[]> {
    return httpClient.get('/restaurante/fila');
  },

  async removeFromQueue(entryId: string): Promise<void> {
    return httpClient.delete(`/restaurante/fila/${entryId}`);
  },
};
