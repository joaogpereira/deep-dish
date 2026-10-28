/** '18 min', '1h05', '< 1 min'. Nunca devolve NaN: o backend manda 0 onde não há dado. */
export function formatarDuracao(segundos: number): string {
  const minutos = Math.round(segundos / 60);
  if (minutos === 0) return segundos > 0 ? '< 1 min' : '0 min';
  if (minutos < 60) return `${minutos} min`;

  const horas = Math.floor(minutos / 60);
  const resto = minutos % 60;
  return resto ? `${horas}h${String(resto).padStart(2, '0')}` : `${horas}h`;
}

/** '2.750' — separador de milhar pt-BR. */
export function formatarNumero(valor: number): string {
  return valor.toLocaleString('pt-BR');
}

export function formatarPercentual(valor: number): string {
  return `${valor.toLocaleString('pt-BR', { maximumFractionDigits: 1 })}%`;
}

/** 'YYYY-MM-DD' -> '14/09'. Recorta a string para não deslocar o dia pelo fuso do navegador. */
export function formatarDiaMes(data: string): string {
  const [, mes, dia] = data.split('-');
  return `${dia}/${mes}`;
}

/** 'YYYY-MM-DD' -> '14/09/2026'. */
export function formatarData(data: string): string {
  const [ano] = data.split('-');
  return `${formatarDiaMes(data)}/${ano}`;
}

/**
 * Primeira e última hora com movimento, ou null se não houve nenhum.
 *
 * O backend devolve as 24 horas; plotar a madrugada de um restaurante que abre
 * às 11h espreme o que importa num terço do gráfico. Horas vazias NO MEIO da
 * faixa continuam aparecendo — sumir com elas esconderia um buraco real.
 */
export function faixaComMovimento(itens: { hora: number; entradas: number }[]): [number, number] | null {
  const horas = itens.filter(i => i.entradas > 0).map(i => i.hora);
  return horas.length ? [Math.min(...horas), Math.max(...horas)] : null;
}
