import React from 'react';
import { Area, AreaChart, CartesianGrid, XAxis, YAxis } from 'recharts';
import { ChartContainer, ChartTooltip, type ChartConfig } from '@/components/ui/chart';
import type { OcupacaoDiaria } from '@/services/analytics.service';
import { ChartCard, TooltipCaixa } from './ChartCard';
import { formatarData, formatarDiaMes, formatarNumero, formatarPercentual } from './format';

const config = {
  taxa_percentual: { label: 'Ocupação', color: 'hsl(var(--primary))' },
} satisfies ChartConfig;

const TITULO = 'Ocupação ao longo do período';
const DESCRICAO = 'Horas-mesa ocupadas sobre as horas-mesa disponíveis no horário de funcionamento.';

// Marcações em passos iguais até o primeiro múltiplo do passo acima do máximo,
// com piso de 10%: um salão a 3% não pode virar uma montanha só porque o eixo
// encolheu até o dado. Sem ticks explícitos o recharts gerava 0/8/16/30.
function marcacoesDoEixo(maximo: number): number[] {
  const passo = maximo > 50 ? 20 : 10;
  const teto = Math.max(passo, Math.ceil(maximo / passo) * passo);
  return Array.from({ length: teto / passo + 1 }, (_, i) => i * passo);
}

export const OcupacaoChart: React.FC<{ ocupacao: OcupacaoDiaria[] }> = ({ ocupacao }) => {
  if (ocupacao[0]?.mesas === 0) {
    return <ChartCard titulo={TITULO} descricao={DESCRICAO} vazio="Cadastre as mesas do salão para acompanhar a ocupação." />;
  }

  if (ocupacao.every(d => d.segundos_ocupados === 0)) {
    return <ChartCard titulo={TITULO} descricao={DESCRICAO} vazio="Nenhuma mesa com check-in neste período." />;
  }

  const marcacoes = marcacoesDoEixo(Math.max(...ocupacao.map(d => d.taxa_percentual)));

  return (
    <ChartCard titulo={TITULO} descricao={DESCRICAO}>
      <ChartContainer config={config} className="aspect-auto h-[280px] w-full">
        <AreaChart data={ocupacao} margin={{ top: 8, right: 12, left: 0, bottom: 0 }}>
          <CartesianGrid vertical={false} />
          <XAxis dataKey="data" tickLine={false} axisLine={false} tickMargin={8} minTickGap={24} tickFormatter={formatarDiaMes} />
          <YAxis
            tickLine={false}
            axisLine={false}
            width={44}
            domain={[0, marcacoes[marcacoes.length - 1]]}
            ticks={marcacoes}
            tickFormatter={v => `${v}%`}
          />
          <ChartTooltip
            cursor={{ stroke: 'hsl(var(--border))' }}
            content={({ active, payload }) => {
              const d = payload?.[0]?.payload as OcupacaoDiaria | undefined;
              if (!active || !d) return null;
              return (
                <TooltipCaixa
                  titulo={formatarData(d.data)}
                  linhas={[
                    ['Ocupação', formatarPercentual(d.taxa_percentual)],
                    ['Atendimentos', formatarNumero(d.atendimentos)],
                  ]}
                />
              );
            }}
          />
          <Area
            type="monotone"
            dataKey="taxa_percentual"
            stroke="var(--color-taxa_percentual)"
            strokeWidth={2}
            fill="var(--color-taxa_percentual)"
            fillOpacity={0.12}
            dot={false}
            activeDot={{ r: 4 }}
          />
        </AreaChart>
      </ChartContainer>
    </ChartCard>
  );
};
