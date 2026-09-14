import React from 'react';
import { Bar, BarChart, Cell, LabelList, XAxis, YAxis } from 'recharts';
import { ChartContainer, type ChartConfig } from '@/components/ui/chart';
import type { Abandono } from '@/services/analytics.service';
import { ChartCard } from './ChartCard';
import { formatarNumero, formatarPercentual } from './format';

const COR_ABANDONO = 'hsl(var(--primary))';
const COR_DEMAIS = 'hsl(var(--muted-foreground) / 0.45)';

const config = { valor: { label: 'Grupos' } } satisfies ChartConfig;

const TITULO = 'Abandono da fila';
const DESCRICAO = 'Desistências e chamados que não vieram, sobre todas as entradas do período.';

export const AbandonoChart: React.FC<{ abandono: Abandono }> = ({ abandono: a }) => {
  if (a.total_entradas === 0) {
    return <ChartCard titulo={TITULO} descricao={DESCRICAO} vazio="Nenhuma entrada na fila neste período." />;
  }

  const barras = [
    { rotulo: 'Atendidos', valor: a.atendido, abandono: false },
    { rotulo: 'Desistiram', valor: a.desistiu, abandono: true },
    { rotulo: 'Não vieram', valor: a.expirado, abandono: true },
    // 'removido' é ação do restaurante e não conta como abandono (AnalyticsService::taxaDeAbandono).
    { rotulo: 'Removidos', valor: a.removido, abandono: false },
    ...(a.ativas > 0 ? [{ rotulo: 'Ainda na fila', valor: a.ativas, abandono: false }] : []),
  ];

  return (
    <ChartCard titulo={TITULO} descricao={DESCRICAO}>
      <div className="flex items-baseline gap-2">
        <p className="text-4xl font-bold text-foreground">{formatarPercentual(a.taxa_percentual)}</p>
        <p className="text-sm text-muted-foreground">
          {formatarNumero(a.abandonos)} de {formatarNumero(a.total_entradas)} {a.total_entradas === 1 ? 'grupo' : 'grupos'}
        </p>
      </div>

      <ChartContainer config={config} className="mt-4 aspect-auto w-full" style={{ height: barras.length * 40 }}>
        <BarChart data={barras} layout="vertical" margin={{ top: 0, right: 40, left: 0, bottom: 0 }} barCategoryGap={8}>
          <XAxis type="number" hide allowDecimals={false} />
          <YAxis type="category" dataKey="rotulo" tickLine={false} axisLine={false} width={96} />
          <Bar dataKey="valor" radius={[0, 4, 4, 0]} isAnimationActive={false}>
            {barras.map(b => <Cell key={b.rotulo} fill={b.abandono ? COR_ABANDONO : COR_DEMAIS} />)}
            <LabelList dataKey="valor" position="right" className="fill-foreground" fontSize={12} formatter={formatarNumero} />
          </Bar>
        </BarChart>
      </ChartContainer>

      <p className="mt-3 flex items-center gap-1.5 text-xs text-muted-foreground">
        <span className="h-2.5 w-2.5 rounded-[2px]" style={{ backgroundColor: COR_ABANDONO }} aria-hidden />
        Conta como abandono
      </p>
    </ChartCard>
  );
};
