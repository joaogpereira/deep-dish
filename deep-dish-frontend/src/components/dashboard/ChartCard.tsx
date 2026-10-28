import React from 'react';
import { BarChart3 } from 'lucide-react';
import { cn } from '@/lib/utils';

interface ChartCardProps {
  titulo: string;
  descricao?: string;
  /** Quando preenchido, o card mostra esta mensagem no lugar do gráfico. */
  vazio?: string;
  className?: string;
  children?: React.ReactNode;
}

export const ChartCard: React.FC<ChartCardProps> = ({ titulo, descricao, vazio, className, children }) => (
  <section className={cn('rounded-2xl bg-card p-5 shadow-card', className)} aria-label={titulo}>
    <header className="mb-4">
      <h3 className="font-semibold text-foreground">{titulo}</h3>
      {descricao && <p className="mt-0.5 text-sm text-muted-foreground">{descricao}</p>}
    </header>
    {vazio ? (
      <div className="flex h-[220px] flex-col items-center justify-center gap-2 rounded-xl border border-dashed text-center">
        <BarChart3 className="h-6 w-6 text-muted-foreground/60" />
        <p className="max-w-xs text-sm text-muted-foreground">{vazio}</p>
      </div>
    ) : (
      children
    )}
  </section>
);

/** Conteúdo de tooltip dos gráficos do dashboard: um título e pares rótulo/valor. */
export const TooltipCaixa: React.FC<{ titulo: string; linhas: [string, string][] }> = ({ titulo, linhas }) => (
  <div className="grid min-w-[9rem] gap-1.5 rounded-lg border border-border/50 bg-background px-2.5 py-1.5 text-xs shadow-xl">
    <p className="font-medium text-foreground">{titulo}</p>
    {linhas.map(([rotulo, valor]) => (
      <div key={rotulo} className="flex justify-between gap-4">
        <span className="text-muted-foreground">{rotulo}</span>
        <span className="font-mono font-medium tabular-nums text-foreground">{valor}</span>
      </div>
    ))}
  </div>
);
