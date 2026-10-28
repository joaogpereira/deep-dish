import React from 'react';
import { describe, it, expect, vi, beforeEach, beforeAll } from 'vitest';
import { render, screen, fireEvent, waitFor } from '@testing-library/react';
import { QueryClient, QueryClientProvider } from '@tanstack/react-query';
import Dashboard from './Dashboard';
import { analyticsService, type Analytics } from '@/services/analytics.service';
import { dashboardService, type DashboardStats } from '@/services/dashboard.service';

vi.mock('@/services/analytics.service', () => ({ analyticsService: { get: vi.fn() } }));
vi.mock('@/services/dashboard.service', () => ({ dashboardService: { stats: vi.fn() } }));

const getAnalytics = vi.mocked(analyticsService.get);
const getStats = vi.mocked(dashboardService.stats);

beforeAll(() => {
  // O ResponsiveContainer do recharts observa o tamanho do pai; o jsdom não tem ResizeObserver.
  globalThis.ResizeObserver ??= class {
    observe() {}
    unobserve() {}
    disconnect() {}
  } as unknown as typeof ResizeObserver;
});

const STATS: DashboardStats = {
  queue_size: 3,
  reservations_today: 5,
  tables_available: 5,
  total_tables: 8,
  occupancy_percent: 38,
};

/** Payload como o backend manda para um restaurante sem movimento: estrutura completa, tudo zero. */
function analyticsVazio(): Analytics {
  const NOMES = ['domingo', 'segunda-feira', 'terça-feira', 'quarta-feira', 'quinta-feira', 'sexta-feira', 'sábado'];
  return {
    periodo: { data_inicio: '2026-06-17', data_fim: '2026-09-14', dias: 90, fuso: 'America/Sao_Paulo' },
    procedencia: { fila: { real: 0, sintetico: 0 }, reservas: { real: 0, sintetico: 0 }, contem_sintetico: false },
    espera_por_dia_da_semana: NOMES.map((nome, dia) => ({
      dia_da_semana: dia, nome, entradas: 0, tempo_medio_segundos: 0, tempo_medio_atendidos_segundos: 0,
    })),
    espera_por_faixa_horaria: Array.from({ length: 24 }, (_, hora) => ({
      hora, entradas: 0, tempo_medio_segundos: 0, tempo_medio_atendidos_segundos: 0,
    })),
    abandono: {
      total_entradas: 0, abandonos: 0, desistiu: 0, expirado: 0, atendido: 0, removido: 0, ativas: 0, taxa: 0, taxa_percentual: 0,
    },
    ocupacao: [{
      data: '2026-09-14', mesas: 8, segundos_disponiveis: 345600, segundos_ocupados: 0, atendimentos: 0, taxa: 0, taxa_percentual: 0,
    }],
    giro_de_mesa: { mesas: [], total_atendimentos: 0, duracao_media_geral_segundos: 0, giro_medio_por_mesa: 0 },
    mapa_de_calor: {
      celulas: NOMES.flatMap((nome, dia) => Array.from({ length: 24 }, (_, hora) => ({
        dia_da_semana: dia, nome, hora, entradas: 0, pessoas: 0,
      }))),
      pico: null,
    },
  };
}

function analyticsComMovimento(): Analytics {
  const a = analyticsVazio();
  a.espera_por_faixa_horaria[13] = { hora: 13, entradas: 4, tempo_medio_segundos: 1080, tempo_medio_atendidos_segundos: 900 };
  a.abandono = { ...a.abandono, total_entradas: 4, abandonos: 1, desistiu: 1, atendido: 3, taxa: 0.25, taxa_percentual: 25 };
  const pico = { dia_da_semana: 6, nome: 'sábado', hora: 13, entradas: 4, pessoas: 10 };
  a.mapa_de_calor = {
    celulas: a.mapa_de_calor.celulas.map(c => (c.dia_da_semana === 6 && c.hora === 13 ? pico : c)),
    pico,
  };
  a.ocupacao = [{ ...a.ocupacao[0], segundos_ocupados: 86400, atendimentos: 3, taxa: 0.25, taxa_percentual: 25 }];
  return a;
}

function renderizar() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } });
  return render(
    <QueryClientProvider client={client}>
      <Dashboard />
    </QueryClientProvider>,
  );
}

const diasEntre = (inicio: string, fim: string) =>
  (Date.parse(`${fim}T00:00:00Z`) - Date.parse(`${inicio}T00:00:00Z`)) / 86_400_000;

describe('Dashboard do restaurante', () => {
  beforeEach(() => {
    vi.clearAllMocks();
    getStats.mockResolvedValue(STATS);
  });

  it('mostra a taxa de ocupação calculada pelo backend', async () => {
    getAnalytics.mockResolvedValue(analyticsVazio());
    renderizar();

    expect(await screen.findByText('38%')).toBeInTheDocument();
  });

  it('mostra — quando o restaurante não tem mesa', async () => {
    getStats.mockResolvedValue({ ...STATS, tables_available: 0, total_tables: 0, occupancy_percent: null });
    getAnalytics.mockResolvedValue(analyticsVazio());
    renderizar();

    expect(await screen.findByText('—')).toBeInTheDocument();
    expect(screen.queryByText(/NaN/)).not.toBeInTheDocument();
  });

  it('restaurante sem histórico vê o estado vazio, sem gráfico nem NaN', async () => {
    getAnalytics.mockResolvedValue(analyticsVazio());
    renderizar();

    expect(await screen.findByText('Sem histórico neste período')).toBeInTheDocument();
    expect(screen.queryByRole('region', { name: 'Abandono da fila' })).not.toBeInTheDocument();
    expect(document.body.textContent).not.toMatch(/NaN|undefined/);
  });

  it('com movimento, renderiza os quatro gráficos', async () => {
    getAnalytics.mockResolvedValue(analyticsComMovimento());
    renderizar();

    expect(await screen.findByRole('region', { name: 'Espera média por horário' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Abandono da fila' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Movimento por dia e horário' })).toBeInTheDocument();
    expect(screen.getByRole('region', { name: 'Ocupação ao longo do período' })).toBeInTheDocument();
    expect(screen.getByText('25%')).toBeInTheDocument();
    expect(screen.getByText('sábado, 13h')).toBeInTheDocument();
    expect(document.body.textContent).not.toMatch(/NaN|undefined/);
  });

  it('erro de rede tenta mais 2 vezes, mostra estado de erro e "Tentar novamente" refaz a consulta', async () => {
    const falhaDeRede = new TypeError('Failed to fetch');
    getAnalytics
      .mockRejectedValueOnce(falhaDeRede)
      .mockRejectedValueOnce(falhaDeRede)
      .mockRejectedValueOnce(falhaDeRede)
      .mockResolvedValue(analyticsComMovimento());
    renderizar();

    // Primeira chamada + 2 retentativas, com backoff real de 1s + 2s.
    expect(await screen.findByText('Não foi possível carregar o histórico.', {}, { timeout: 5000 })).toBeInTheDocument();
    expect(screen.getByText('Verifique sua conexão e tente novamente.')).toBeInTheDocument();
    expect(getAnalytics).toHaveBeenCalledTimes(3);

    fireEvent.click(screen.getByRole('button', { name: /tentar novamente/i }));

    expect(await screen.findByRole('region', { name: 'Abandono da fila' })).toBeInTheDocument();
    expect(getAnalytics).toHaveBeenCalledTimes(4);
  }, 10_000);

  it('abre em 90 dias e trocar o período refaz a consulta com as novas datas', async () => {
    getAnalytics.mockResolvedValue(analyticsComMovimento());
    renderizar();

    await screen.findByRole('region', { name: 'Abandono da fila' });
    const primeira = getAnalytics.mock.calls[0][0];
    expect(diasEntre(primeira.data_inicio, primeira.data_fim)).toBe(89);

    fireEvent.click(screen.getByRole('radio', { name: '7 dias' }));

    await waitFor(() => expect(getAnalytics).toHaveBeenCalledTimes(2));
    const segunda = getAnalytics.mock.calls[1][0];
    expect(segunda.data_fim).toBe(primeira.data_fim);
    expect(diasEntre(segunda.data_inicio, segunda.data_fim)).toBe(6);
  });

  it('clicar no período já ativo não deixa a página sem período', async () => {
    getAnalytics.mockResolvedValue(analyticsComMovimento());
    renderizar();

    await screen.findByRole('region', { name: 'Abandono da fila' });
    fireEvent.click(screen.getByRole('radio', { name: '90 dias' }));

    expect(screen.getByRole('radio', { name: '90 dias' })).toHaveAttribute('aria-checked', 'true');
    expect(getAnalytics).toHaveBeenCalledTimes(1);
  });
});
