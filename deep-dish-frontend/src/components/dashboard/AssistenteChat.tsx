import React, { useEffect, useRef, useState } from 'react';
import { AlertTriangle, ArrowDown, BarChart3, Clock, Gauge, RotateCw, Send, Sparkles } from 'lucide-react';
import { Button } from '@/components/ui/button';
import { assistenteService } from '@/services/assistente.service';
import { ApiError } from '@/services/httpClient';
import { cn } from '@/lib/utils';

export type GraficoDoPainel = 'espera' | 'abandono' | 'mapa' | 'ocupacao';

// Cada ferramenta do backend (FerramentasDeAnalytics) é um indicador. As que têm
// gráfico no dashboard apontam para ele, para o gerente conferir o número.
const INDICADORES: Record<string, { rotulo: string; grafico?: GraficoDoPainel }> = {
  horario_de_pico: { rotulo: 'Movimento por dia e horário', grafico: 'mapa' },
  pior_horario_de_espera: { rotulo: 'Espera média por horário', grafico: 'espera' },
  taxa_de_abandono: { rotulo: 'Abandono da fila', grafico: 'abandono' },
  ocupacao: { rotulo: 'Ocupação ao longo do período', grafico: 'ocupacao' },
  espera_por_dia_da_semana: { rotulo: 'Espera por dia da semana' },
  giro_de_mesa: { rotulo: 'Giro de mesa' },
  desperdicio_de_capacidade: { rotulo: 'Lugares ociosos nas mesas' },
  tempos_de_limpeza: { rotulo: 'Tempos de limpeza' },
  procedencia_dos_dados: { rotulo: 'Procedência dos dados' },
};

const SUGESTOES = [
  'Qual é o meu horário de pico?',
  'Quanta gente desiste da fila?',
  'Em que horário a espera é pior?',
  'Como está a ocupação das mesas?',
];

// Mesmo teto da validação do backend.
const MAX_CARACTERES = 2000;

interface Mensagem {
  papel: 'usuario' | 'assistente';
  texto: string;
  fontes?: string[];
}

interface Falha {
  tipo: 'limite_diario' | 'aguarde' | 'erro';
  titulo: string;
  detalhe: string;
}

function classificar(error: unknown): Falha {
  if (!(error instanceof ApiError)) {
    return { tipo: 'erro', titulo: 'Sem conexão com o servidor', detalhe: 'Verifique sua conexão e tente novamente.' };
  }

  const codigo = (error.data as { error?: string } | undefined)?.error;

  if (codigo === 'assistente_teto_tokens') {
    return { tipo: 'limite_diario', titulo: 'Limite diário atingido', detalhe: error.message };
  }
  if (error.status === 429) {
    return { tipo: 'aguarde', titulo: 'Muitas perguntas em pouco tempo', detalhe: 'Aguarde um minuto e tente de novo.' };
  }
  if (codigo === 'assistente_ocupado') {
    return { tipo: 'aguarde', titulo: 'Ainda respondendo a pergunta anterior', detalhe: 'Aguarde um instante e tente de novo.' };
  }
  return { tipo: 'erro', titulo: 'O assistente não conseguiu responder', detalhe: error.message };
}

/** O modelo marca os números com **negrito**; o resto do texto sai como veio. */
function comNegrito(texto: string): React.ReactNode[] {
  return texto.split(/\*\*(.+?)\*\*/g).map((parte, i) => (i % 2 === 1 ? <strong key={i}>{parte}</strong> : parte));
}

interface AssistenteChatProps {
  /** Rola até o gráfico e o destaca. Sem ele, a origem aparece só como texto. */
  onVerGrafico?: (grafico: GraficoDoPainel) => void;
}

export const AssistenteChat: React.FC<AssistenteChatProps> = ({ onVerGrafico }) => {
  const [mensagens, setMensagens] = useState<Mensagem[]>([]);
  const [texto, setTexto] = useState('');
  const [carregando, setCarregando] = useState(false);
  const [falha, setFalha] = useState<Falha | null>(null);
  const conversaId = useRef<string>();
  const fim = useRef<HTMLDivElement>(null);

  useEffect(() => {
    fim.current?.scrollIntoView?.({ block: 'nearest' });
  }, [mensagens, carregando, falha]);

  const limiteAtingido = falha?.tipo === 'limite_diario';
  const ultimaPergunta = [...mensagens].reverse().find(m => m.papel === 'usuario')?.texto;

  async function perguntar(pergunta: string) {
    setCarregando(true);
    setFalha(null);
    try {
      const r = await assistenteService.perguntar(pergunta, conversaId.current);
      conversaId.current = r.conversa_id;
      setMensagens(m => [...m, { papel: 'assistente', texto: r.resposta, fontes: [...new Set(r.ferramentas_usadas)] }]);
    } catch (error) {
      // A conversa sumiu no servidor: a nova tentativa começa outra.
      if (error instanceof ApiError && error.status === 404) conversaId.current = undefined;
      setFalha(classificar(error));
    } finally {
      setCarregando(false);
    }
  }

  function enviar(pergunta: string) {
    const limpa = pergunta.trim();
    if (!limpa || carregando || limiteAtingido) return;
    setTexto('');
    setMensagens(m => [...m, { papel: 'usuario', texto: limpa }]);
    perguntar(limpa);
  }

  return (
    <section className="rounded-2xl bg-card shadow-card" aria-labelledby="titulo-assistente">
      <header className="flex items-center gap-3 border-b px-5 py-4">
        <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-primary/8">
          <Sparkles className="h-[18px] w-[18px] text-primary" />
        </div>
        <div>
          <h2 id="titulo-assistente" className="font-semibold text-foreground">Assistente</h2>
          <p className="text-sm text-muted-foreground">Pergunte sobre a operação. Toda resposta mostra de qual indicador veio.</p>
        </div>
      </header>

      <div
        role="log"
        aria-label="Conversa com o assistente"
        className="max-h-[26rem] space-y-4 overflow-y-auto px-5 py-4"
      >
        {mensagens.length === 0 && (
          <div>
            <p className="text-sm text-muted-foreground">Comece por uma destas perguntas:</p>
            <div className="mt-3 flex flex-wrap gap-2">
              {SUGESTOES.map(s => (
                <button
                  key={s}
                  type="button"
                  onClick={() => enviar(s)}
                  disabled={carregando || limiteAtingido}
                  className="min-h-[44px] rounded-full border bg-background px-4 text-left text-sm text-foreground transition-colors hover:border-primary hover:text-primary disabled:opacity-50"
                >
                  {s}
                </button>
              ))}
            </div>
          </div>
        )}

        {mensagens.map((m, i) => m.papel === 'usuario' ? (
          <div key={i} className="flex justify-end">
            <p className="max-w-[85%] whitespace-pre-wrap break-words rounded-2xl rounded-br-md bg-primary px-4 py-2.5 text-sm text-primary-foreground">
              {m.texto}
            </p>
          </div>
        ) : (
          <div key={i} className="max-w-[85%] space-y-2">
            <p className="whitespace-pre-wrap break-words rounded-2xl rounded-bl-md bg-muted px-4 py-2.5 text-sm text-foreground">
              {comNegrito(m.texto)}
            </p>
            <Fontes fontes={m.fontes ?? []} onVerGrafico={onVerGrafico} />
          </div>
        ))}

        {carregando && (
          <div role="status" className="flex max-w-[85%] items-center gap-2 rounded-2xl rounded-bl-md bg-muted px-4 py-2.5 text-sm text-muted-foreground">
            <RotateCw className="h-4 w-4 animate-spin" />
            Consultando os dados do restaurante…
          </div>
        )}

        {falha && (
          <div
            role="alert"
            className={cn(
              'flex items-start gap-3 rounded-xl border px-4 py-3',
              falha.tipo === 'erro'
                ? 'border-destructive/30 bg-destructive/5'
                : 'border-amber-500/40 bg-amber-500/10',
            )}
          >
            {falha.tipo === 'erro' && <AlertTriangle className="mt-0.5 h-5 w-5 shrink-0 text-destructive" />}
            {falha.tipo === 'aguarde' && <Clock className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />}
            {falha.tipo === 'limite_diario' && <Gauge className="mt-0.5 h-5 w-5 shrink-0 text-amber-600 dark:text-amber-400" />}
            <div className="min-w-0 flex-1">
              <p className="text-sm font-semibold text-foreground">{falha.titulo}</p>
              <p className="mt-0.5 text-sm text-muted-foreground">{falha.detalhe}</p>
            </div>
            {!limiteAtingido && ultimaPergunta && (
              <Button variant="outline" size="sm" className="min-h-[44px] shrink-0" onClick={() => perguntar(ultimaPergunta)}>
                <RotateCw className="mr-2 h-4 w-4" />
                Tentar novamente
              </Button>
            )}
          </div>
        )}

        <div ref={fim} />
      </div>

      <form
        className="flex items-end gap-2 border-t px-5 py-4"
        onSubmit={e => { e.preventDefault(); enviar(texto); }}
      >
        <textarea
          value={texto}
          onChange={e => setTexto(e.target.value)}
          onKeyDown={e => {
            if (e.key === 'Enter' && !e.shiftKey) {
              e.preventDefault();
              enviar(texto);
            }
          }}
          rows={1}
          maxLength={MAX_CARACTERES}
          disabled={limiteAtingido}
          aria-label="Pergunta para o assistente"
          placeholder={limiteAtingido ? 'O assistente volta a funcionar amanhã.' : 'Pergunte sobre fila, espera, ocupação…'}
          // text-base (16px) evita o zoom automático do Safari do iPad ao focar.
          className="min-h-[44px] flex-1 resize-none rounded-xl border border-input bg-background px-4 py-2.5 text-base placeholder:text-muted-foreground focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring disabled:cursor-not-allowed disabled:opacity-50"
        />
        <Button
          type="submit"
          size="icon"
          className="h-11 w-11 shrink-0 rounded-xl"
          disabled={!texto.trim() || carregando || limiteAtingido}
          aria-label="Enviar pergunta"
        >
          <Send className="h-[18px] w-[18px]" />
        </Button>
      </form>
    </section>
  );
};

const Fontes: React.FC<{ fontes: string[]; onVerGrafico?: (grafico: GraficoDoPainel) => void }> = ({ fontes, onVerGrafico }) => {
  if (fontes.length === 0) {
    return <p className="px-1 text-xs text-muted-foreground">Esta resposta não consultou nenhum indicador.</p>;
  }

  return (
    <div className="flex flex-wrap items-center gap-2 px-1">
      <span className="text-xs text-muted-foreground">Baseado em:</span>
      {fontes.map(nome => {
        const indicador = INDICADORES[nome] ?? { rotulo: nome };
        const grafico = indicador.grafico;

        return grafico && onVerGrafico ? (
          <button
            key={nome}
            type="button"
            onClick={() => onVerGrafico(grafico)}
            aria-label={`Ver o gráfico ${indicador.rotulo}`}
            className="inline-flex min-h-[36px] items-center gap-1.5 rounded-full border border-primary/30 bg-primary/5 px-3 text-xs font-medium text-primary transition-colors hover:bg-primary/10"
          >
            <BarChart3 className="h-3.5 w-3.5" />
            {indicador.rotulo}
            <ArrowDown className="h-3.5 w-3.5" />
          </button>
        ) : (
          <span key={nome} className="inline-flex min-h-[36px] items-center gap-1.5 rounded-full border px-3 text-xs font-medium text-foreground">
            <BarChart3 className="h-3.5 w-3.5 text-muted-foreground" />
            {indicador.rotulo}
          </span>
        );
      })}
    </div>
  );
};
