import { httpClient } from './httpClient';

// Contrato de GET /restaurante/analytics (AnalyticsController). O backend
// devolve as séries sempre completas — 24 horas, 168 células, todos os dias do
// período — preenchidas com zero onde não houve movimento.

export interface EsperaPorHora {
  hora: number;
  entradas: number;
  tempo_medio_segundos: number;
  tempo_medio_atendidos_segundos: number;
}

export interface EsperaPorDiaDaSemana {
  dia_da_semana: number;
  nome: string;
  entradas: number;
  tempo_medio_segundos: number;
  tempo_medio_atendidos_segundos: number;
}

export interface Abandono {
  total_entradas: number;
  abandonos: number;
  desistiu: number;
  expirado: number;
  atendido: number;
  removido: number;
  ativas: number;
  taxa: number;
  taxa_percentual: number;
}

export interface OcupacaoDiaria {
  data: string;
  mesas: number;
  segundos_disponiveis: number;
  segundos_ocupados: number;
  atendimentos: number;
  taxa: number;
  taxa_percentual: number;
}

export interface CelulaMapaDeCalor {
  /** 0 = domingo, como EXTRACT(DOW) do Postgres. */
  dia_da_semana: number;
  nome: string;
  hora: number;
  entradas: number;
  pessoas: number;
}

export interface Analytics {
  periodo: { data_inicio: string; data_fim: string; dias: number; fuso: string };
  procedencia: {
    fila: { real: number; sintetico: number };
    reservas: { real: number; sintetico: number };
    contem_sintetico: boolean;
  };
  espera_por_dia_da_semana: EsperaPorDiaDaSemana[];
  espera_por_faixa_horaria: EsperaPorHora[];
  abandono: Abandono;
  ocupacao: OcupacaoDiaria[];
  giro_de_mesa: {
    mesas: { mesa_id: string; numero: number | null; capacidade: number | null; atendimentos: number; duracao_media_segundos: number }[];
    total_atendimentos: number;
    duracao_media_geral_segundos: number;
    giro_medio_por_mesa: number;
  };
  mapa_de_calor: { celulas: CelulaMapaDeCalor[]; pico: CelulaMapaDeCalor | null };
}

export interface PeriodoAnalytics {
  /** 'YYYY-MM-DD', dia de calendário no fuso do restaurante. */
  data_inicio: string;
  data_fim: string;
}

export const analyticsService = {
  async get({ data_inicio, data_fim }: PeriodoAnalytics): Promise<Analytics> {
    const query = new URLSearchParams({ data_inicio, data_fim });
    return httpClient.get(`/restaurante/analytics?${query}`);
  },
};
