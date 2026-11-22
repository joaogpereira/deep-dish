import React from 'react';
import { Bar, BarChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { ChartContainer, ChartTooltip, type ChartConfig } from '@/components/ui/chart';
import type { EsperaPorHora } from '@/services/analytics.service';
import { ChartCard, TooltipCaixa } from './ChartCard';
import { faixaComMovimento, formatarDuracao, formatarNumero } from './format';

const config = {
  minutos: { label: 'Espera média', color: 'hsl(var(--primary))' },
} satisfies ChartConfig;

type Ponto = EsperaPorHora & { rotulo: string; minutos: number | null };

const TITULO = 'Espera média por horário';
const DESCRICAO = 'Tempo na fila pela hora de chegada, incluindo quem desistiu.';

export const EsperaPorHoraChart: React.FC<{ dados: EsperaPorHora[] }> = ({ dados }) => {
  const faixa = faixaComMovimento(dados);

  if (!faixa) {
    return <ChartCard titulo={TITULO} descricao={DESCRICAO} vazio="Ninguém saiu da fila neste período." />;
  }

  const pontos: Ponto[] = dados
    .filter(d => d.hora >= faixa[0] && d.hora <= faixa[1])
    .map(d => ({
      ...d,
      rotulo: `${d.hora}h`,
      // null (e não 0) numa hora sem movimento: a barra some em vez de sugerir
      // "espera zero".
      minutos: d.entradas > 0 ? Math.round(d.tempo_medio_segundos / 6) / 10 : null,
    }));

  return (
    <ChartCard titulo={TITULO} descricao={DESCRICAO}>
      <ChartContainer config={config} className="aspect-auto h-[280px] w-full">
        <BarChart data={pontos} margin={{ top: 8, right: 8, left: 0, bottom: 0 }}>
          <CartesianGrid vertical={false} />
          <XAxis dataKey="rotulo" tickLine={false} axisLine={false} tickMargin={8} />
          <YAxis tickLine={false} axisLine={false} width={52} allowDecimals={false} tickFormatter={v => `${v} min`} />
          <ChartTooltip
            cursor={{ fill: 'hsl(var(--muted))' }}
            content={({ active, payload }) => {
              const p = payload?.[0]?.payload as Ponto | undefined;
              if (!active || !p) return null;
              return (
                <TooltipCaixa
                  titulo={`${p.hora}h às ${p.hora + 1}h`}
                  linhas={p.entradas > 0 ? [
                    ['Espera média', formatarDuracao(p.tempo_medio_segundos)],
                    ['Só atendidos', formatarDuracao(p.tempo_medio_atendidos_segundos)],
                    ['Grupos', formatarNumero(p.entradas)],
                  ] : [['Grupos', '0']]}
                />
              );
            }}
          />
          <Bar dataKey="minutos" fill="var(--color-minutos)" radius={[4, 4, 0, 0]} maxBarSize={36} />
        </BarChart>
      </ChartContainer>
    </ChartCard>
  );
};
