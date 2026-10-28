import React, { useMemo, useState } from 'react';
import { keepPreviousData, useQuery } from '@tanstack/react-query';
import { Skeleton } from '@/components/ui/skeleton';
import { Button } from '@/components/ui/button';
import { ToggleGroup, ToggleGroupItem } from '@/components/ui/toggle-group';
import { Users, CalendarDays, Grid3X3, TrendingUp, AlertTriangle, History, RotateCw } from 'lucide-react';
import { dashboardService } from '@/services/dashboard.service';
import { analyticsService, type Analytics, type PeriodoAnalytics } from '@/services/analytics.service';
import { ApiError } from '@/services/httpClient';
import { cn, hojeEmBRT } from '@/lib/utils';
import { EsperaPorHoraChart } from '@/components/dashboard/EsperaPorHoraChart';
import { MapaDeCalor } from '@/components/dashboard/MapaDeCalor';
import { AbandonoChart } from '@/components/dashboard/AbandonoChart';
import { OcupacaoChart } from '@/components/dashboard/OcupacaoChart';
import { formatarData } from '@/components/dashboard/format';

const PERIODOS = [
  { dias: 7, rotulo: '7 dias' },
  { dias: 30, rotulo: '30 dias' },
  { dias: 90, rotulo: '90 dias' },
  { dias: 365, rotulo: '12 meses' },
];

// Mesmo padrão do backend (AnalyticsController::DIAS_PADRAO).
const DIAS_PADRAO = 90;

// Novas tentativas após a primeira falha (o padrão do react-query é 3). Com o
// backoff de 1s + 2s, o estado de erro aparece em ~3s em vez de ~7s.
const RETENTATIVAS = 2;

/** Últimos N dias até hoje, contados como dia de calendário em Brasília. */
function periodoDosUltimos(dias: number): PeriodoAnalytics {
  const hoje = hojeEmBRT();
  const inicio = new Date(`${hoje}T00:00:00Z`);
  inicio.setUTCDate(inicio.getUTCDate() - (dias - 1));
  return { data_inicio: inicio.toISOString().slice(0, 10), data_fim: hoje };
}

function semHistorico(a: Analytics): boolean {
  return a.abandono.total_entradas === 0 && a.ocupacao.every(d => d.segundos_ocupados === 0);
}

function mensagemDeErro(error: unknown): string {
  return error instanceof ApiError ? error.message : 'Verifique sua conexão e tente novamente.';
}

const ErroAoCarregar: React.FC<{ titulo: string; error: unknown; tentando: boolean; onTentarDeNovo: () => void }> = ({
  titulo, error, tentando, onTentarDeNovo,
}) => (
  <div role="alert" className="flex flex-col items-center gap-3 rounded-2xl bg-card px-6 py-10 text-center shadow-card">
    <AlertTriangle className="h-7 w-7 text-destructive" />
    <div>
      <p className="font-semibold text-foreground">{titulo}</p>
      <p className="mt-1 text-sm text-muted-foreground">{mensagemDeErro(error)}</p>
    </div>
    <Button variant="outline" size="sm" onClick={onTentarDeNovo} disabled={tentando}>
      <RotateCw className={cn('mr-2 h-4 w-4', tentando && 'animate-spin')} />
      {tentando ? 'Tentando…' : 'Tentar novamente'}
    </Button>
  </div>
);

const Dashboard: React.FC = () => {
  const [dias, setDias] = useState(DIAS_PADRAO);
  const periodo = useMemo(() => periodoDosUltimos(dias), [dias]);

  const stats = useQuery({
    queryKey: ['restaurante', 'dashboard'],
    queryFn: dashboardService.stats,
    retry: RETENTATIVAS,
  });

  const analytics = useQuery({
    queryKey: ['restaurante', 'analytics', periodo.data_inicio, periodo.data_fim],
    queryFn: () => analyticsService.get(periodo),
    // Trocar o período mantém os gráficos atuais esmaecidos até o novo dado
    // chegar, em vez de piscar o skeleton e pular o layout.
    placeholderData: keepPreviousData,
    retry: RETENTATIVAS,
  });

  const cards = stats.data ? [
    { label: 'Fila agora', value: stats.data.queue_size, icon: Users, color: 'text-primary', bg: 'bg-primary/8' },
    { label: 'Reservas hoje', value: stats.data.reservations_today, icon: CalendarDays, color: 'text-gold-accent', bg: 'bg-gold-accent/10' },
    { label: 'Mesas livres', value: `${stats.data.tables_available}/${stats.data.total_tables}`, icon: Grid3X3, color: 'text-emerald-600 dark:text-emerald-400', bg: 'bg-emerald-500/8' },
    {
      label: 'Taxa de ocupação',
      value: stats.data.occupancy_percent === null ? '—' : `${stats.data.occupancy_percent}%`,
      icon: TrendingUp,
      color: 'text-primary',
      bg: 'bg-primary/8',
    },
  ] : [];

  return (
    <div className="space-y-8 animate-fade-in">
      <h1 className="font-display text-2xl font-bold text-foreground">Dashboard</h1>

      {stats.isPending ? (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
          {[1, 2, 3, 4].map(i => <Skeleton key={i} className="h-28 rounded-2xl" />)}
        </div>
      ) : stats.isError ? (
        <ErroAoCarregar
          titulo="Não foi possível carregar os números de agora."
          error={stats.error}
          tentando={stats.isFetching}
          onTentarDeNovo={() => stats.refetch()}
        />
      ) : (
        <div className="grid gap-4 sm:grid-cols-2 lg:grid-cols-4 animate-stagger">
          {cards.map((c, i) => (
            <div key={i} className="rounded-2xl bg-card p-5 shadow-card transition-all duration-200 hover:shadow-card-hover">
              <div className="flex items-center justify-between">
                <span className="text-sm font-medium text-muted-foreground">{c.label}</span>
                <div className={`h-9 w-9 rounded-lg ${c.bg} flex items-center justify-center`}>
                  <c.icon className={`h-[18px] w-[18px] ${c.color}`} />
                </div>
              </div>
              <p className="mt-3 text-3xl font-bold text-foreground font-display animate-count-up">
                {c.value}
              </p>
            </div>
          ))}
        </div>
      )}

      <section className="space-y-4" aria-labelledby="titulo-historico">
        <div className="flex flex-wrap items-end justify-between gap-3">
          <div>
            <h2 id="titulo-historico" className="font-display text-xl font-bold text-foreground">Histórico</h2>
            <p className="text-sm text-muted-foreground">
              {formatarData(periodo.data_inicio)} a {formatarData(periodo.data_fim)}
            </p>
          </div>
          <ToggleGroup
            type="single"
            variant="outline"
            size="sm"
            value={String(dias)}
            // O Radix devolve '' ao clicar no item já ativo; ignorar mantém sempre um período.
            onValueChange={v => v && setDias(Number(v))}
            aria-label="Período do histórico"
          >
            {PERIODOS.map(p => (
              <ToggleGroupItem key={p.dias} value={String(p.dias)}>{p.rotulo}</ToggleGroupItem>
            ))}
          </ToggleGroup>
        </div>

        {analytics.isPending ? (
          <div className="grid gap-4 xl:grid-cols-2">
            {[1, 2, 3, 4].map(i => <Skeleton key={i} className={cn('h-[360px] rounded-2xl', i > 2 && 'xl:col-span-2')} />)}
          </div>
        ) : analytics.isError ? (
          <ErroAoCarregar
            titulo="Não foi possível carregar o histórico."
            error={analytics.error}
            tentando={analytics.isFetching}
            onTentarDeNovo={() => analytics.refetch()}
          />
        ) : semHistorico(analytics.data) ? (
          <div className="flex flex-col items-center gap-3 rounded-2xl bg-card px-6 py-14 text-center shadow-card">
            <History className="h-8 w-8 text-muted-foreground/60" />
            <div>
              <p className="font-semibold text-foreground">Sem histórico neste período</p>
              <p className="mt-1 max-w-md text-sm text-muted-foreground">
                Os gráficos aparecem conforme a fila e as reservas forem sendo usadas.
                {dias < 365 && ' Experimente um período maior.'}
              </p>
            </div>
          </div>
        ) : (
          <div
            aria-busy={analytics.isPlaceholderData}
            className={cn('grid gap-4 transition-opacity xl:grid-cols-2', analytics.isPlaceholderData && 'opacity-60')}
          >
            <EsperaPorHoraChart dados={analytics.data.espera_por_faixa_horaria} />
            <AbandonoChart abandono={analytics.data.abandono} />
            {/* Os dois de largura total: a grade de horas e a série de até 365 dias precisam de espaço. */}
            <div className="xl:col-span-2">
              <MapaDeCalor
                celulas={analytics.data.mapa_de_calor.celulas}
                pico={analytics.data.mapa_de_calor.pico}
              />
            </div>
            <div className="xl:col-span-2">
              <OcupacaoChart ocupacao={analytics.data.ocupacao} />
            </div>
          </div>
        )}
      </section>
    </div>
  );
};

export default Dashboard;
