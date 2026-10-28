import React from 'react';
import type { CelulaMapaDeCalor } from '@/services/analytics.service';
import { ChartCard } from './ChartCard';
import { faixaComMovimento, formatarNumero } from './format';

// Segunda a domingo: deixa sábado e domingo juntos no fim, onde o restaurante
// costuma lotar. O backend numera como o Postgres (0 = domingo).
const ORDEM_DIAS = [1, 2, 3, 4, 5, 6, 0];
const ABREVIACAO = ['Dom', 'Seg', 'Ter', 'Qua', 'Qui', 'Sex', 'Sáb'];

// Um só matiz, do claro ao escuro. Quatro faixas mais o zero — acima de ~5
// classes as vizinhas deixam de ser distinguíveis a olho.
const OPACIDADES = [0.16, 0.36, 0.6, 0.88];

function corDaCelula(entradas: number, maximo: number): string {
  if (entradas === 0) return 'hsl(var(--muted))';
  const faixa = Math.min(OPACIDADES.length, Math.ceil((entradas / maximo) * OPACIDADES.length)) - 1;
  return `hsl(var(--primary) / ${OPACIDADES[faixa]})`;
}

const TITULO = 'Movimento por dia e horário';
const DESCRICAO = 'Grupos que entraram na fila, por dia da semana e hora de chegada.';

export const MapaDeCalor: React.FC<{ celulas: CelulaMapaDeCalor[]; pico: CelulaMapaDeCalor | null }> = ({ celulas, pico }) => {
  const faixa = faixaComMovimento(celulas);

  if (!faixa || !pico) {
    return <ChartCard titulo={TITULO} descricao={DESCRICAO} vazio="Nenhuma entrada na fila neste período." />;
  }

  const horas = Array.from({ length: faixa[1] - faixa[0] + 1 }, (_, i) => faixa[0] + i);
  const porChave = new Map(celulas.map(c => [`${c.dia_da_semana}:${c.hora}`, c]));

  return (
    <ChartCard titulo={TITULO} descricao={DESCRICAO}>
      {/* Tabela de verdade: o mapa já é a própria visão tabular, legível por leitor de tela. */}
      <div className="overflow-x-auto">
        <table className="w-full border-separate border-spacing-[3px] text-xs">
          <thead>
            <tr>
              <th scope="col"><span className="sr-only">Dia</span></th>
              {horas.map(h => (
                <th key={h} scope="col" className="min-w-[1.75rem] pb-1 font-normal text-muted-foreground tabular-nums">
                  {h}h
                </th>
              ))}
            </tr>
          </thead>
          <tbody>
            {ORDEM_DIAS.map(dia => (
              <tr key={dia}>
                <th scope="row" className="pr-2 text-left font-normal text-muted-foreground">{ABREVIACAO[dia]}</th>
                {horas.map(hora => {
                  const celula = porChave.get(`${dia}:${hora}`);
                  const entradas = celula?.entradas ?? 0;
                  const descricao = `${celula?.nome ?? ABREVIACAO[dia]}, ${hora}h: ${formatarNumero(entradas)} ${entradas === 1 ? 'grupo' : 'grupos'}`
                    + (celula?.pessoas ? ` (${formatarNumero(celula.pessoas)} pessoas)` : '');
                  return (
                    <td
                      key={hora}
                      title={descricao}
                      className="h-8 rounded-[4px]"
                      style={{ backgroundColor: corDaCelula(entradas, pico.entradas) }}
                    >
                      <span className="sr-only">{descricao}</span>
                    </td>
                  );
                })}
              </tr>
            ))}
          </tbody>
        </table>
      </div>

      <div className="mt-4 flex flex-wrap items-center justify-between gap-3 text-xs text-muted-foreground">
        <p>
          Pico: <span className="font-medium text-foreground">{pico.nome}, {pico.hora}h</span>{' '}
          ({formatarNumero(pico.entradas)} {pico.entradas === 1 ? 'grupo' : 'grupos'})
        </p>
        <div className="flex items-center gap-1.5" aria-hidden>
          <span>Menos</span>
          <span className="h-3 w-3 rounded-[3px]" style={{ backgroundColor: 'hsl(var(--muted))' }} />
          {OPACIDADES.map(o => (
            <span key={o} className="h-3 w-3 rounded-[3px]" style={{ backgroundColor: `hsl(var(--primary) / ${o})` }} />
          ))}
          <span>Mais</span>
        </div>
      </div>
    </ChartCard>
  );
};
