import React, { useState, useEffect, useCallback, useRef } from 'react';
import { useNavigate, useLocation, Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import StatusBadge from '@/components/StatusBadge';
import ConfirmModal from '@/components/ConfirmModal';
import EmptyState from '@/components/EmptyState';
import { Skeleton } from '@/components/ui/skeleton';
import { queueService } from '@/services/queue.service';
import { reservationsService } from '@/services/reservations.service';
import { ClienteFilaEntry, Reserva } from '@/types';
import { Users, Clock, Hash, ListOrdered, Home, PartyPopper, CalendarCheck, Info } from 'lucide-react';
import { toast } from 'sonner';
import { formatBRT, isEstimativaHistorica } from '@/lib/utils';
import { useRealtime } from '@/hooks/useRealtime';

const STORAGE_KEY = 'deepdish_fila';

interface QueueState {
  entry: ClienteFilaEntry;
  restaurantName: string;
  restaurantImage?: string;
  horarioReserva: string;
  clienteId?: string;
}

interface PromotedInfo {
  reserva: Reserva;
  restaurantName: string;
  restaurantImage?: string;
}

const Queue: React.FC = () => {
  const navigate = useNavigate();
  const location = useLocation();
  const navState = location.state as QueueState | null;

  const [state, setState]           = useState<QueueState | null>(null);
  const [loading, setLoading]       = useState(true);
  const [cancelOpen, setCancelOpen] = useState(false);
  const [cancelling, setCancelling] = useState(false);
  const [promoted, setPromoted]     = useState<PromotedInfo | null>(null);
  const [removido, setRemovido]     = useState(false);

  // Carrega do navigation state ou localStorage
  useEffect(() => {
    if (navState?.entry) {
      setState(navState);
      localStorage.setItem(STORAGE_KEY, JSON.stringify(navState));
      setLoading(false);
      return;
    }
    const saved = localStorage.getItem(STORAGE_KEY);
    if (saved) {
      try { setState(JSON.parse(saved)); } catch { /* ignore */ }
    }
    setLoading(false);
  }, []);

  // Verifica se o cliente foi promovido para reserva (chamado quando consultarPosicao retorna 404)
  const verificarPromocao = useCallback(async (currentState: QueueState): Promise<boolean> => {
    try {
      const pagina = await reservationsService.listUserReservations({ status_group: 'active', per_page: 5 });
      const confirmada = pagina.data.find(r => r.status === 'confirmada');
      if (confirmada) {
        setPromoted({
          reserva:         confirmada,
          restaurantName:  confirmada.mesa?.restaurante?.name  ?? currentState.restaurantName,
          restaurantImage: confirmada.mesa?.restaurante?.imagem_url ?? currentState.restaurantImage,
        });
        setState(null);
        localStorage.removeItem(STORAGE_KEY);
        return true;
      }
    } catch { /* ignore */ }
    return false;
  }, []);

  // Polling — atualiza posição a cada 30s
  const refreshPosicao = useCallback(async (current: QueueState) => {
    const filaId  = current.entry.fila?.restaurante_id;
    const horario = current.entry.fila?.horario_reserva;
    if (!filaId || !horario) return;
    try {
      const updated = await queueService.consultarPosicao({
        restaurante_id: filaId,
        horario_reserva: horario,
      });
      setState(prev => prev ? { ...prev, entry: updated } : prev);
      localStorage.setItem(STORAGE_KEY, JSON.stringify({ ...current, entry: updated }));
    } catch {
      // 404 = saiu da fila — verifica se foi promovido para reserva
      const promovido = await verificarPromocao(current);
      if (!promovido) {
        setState(null);
        localStorage.removeItem(STORAGE_KEY);
        setRemovido(true);
      }
    }
  }, [verificarPromocao]);

  // O state é reescrito a cada atualização de posição, então os handlers leem a
  // versão corrente por uma ref em vez de capturá-la no fechamento.
  const stateRef = useRef(state);
  useEffect(() => { stateRef.current = state; }, [state]);

  // Tempo real, no lugar do polling de 30s: o canal da fila avisa que a
  // composição mudou, e o canal pessoal avisa a promoção para mesa.
  const filaId    = state?.entry.fila?.id ?? state?.entry.fila_id;
  const clienteId = state?.entry.cliente_id;

  const aoMudarFila = useCallback(() => {
    const s = stateRef.current;
    if (s) refreshPosicao(s);
  }, [refreshPosicao]);

  useRealtime(
    filaId ? `fila.${filaId}` : undefined,
    { 'posicao.atualizada': aoMudarFila },
    aoMudarFila
  );

  useRealtime(
    clienteId ? `cliente.${clienteId}` : undefined,
    {
      'cliente.promovido': () => {
        const s = stateRef.current;
        if (s) verificarPromocao(s);
      },
    }
  );

  const handleCancel = async () => {
    if (!state) return;
    setCancelling(true);
    try {
      await queueService.cancelQueue(state.entry.id);
      toast.success('Você saiu da fila.');
      setState(null);
      localStorage.removeItem(STORAGE_KEY);
      setCancelOpen(false);
    } catch {
      toast.error('Erro ao sair da fila. Tente novamente.');
    } finally {
      setCancelling(false);
    }
  };

  if (loading) {
    return (
      <div className="space-y-4">
        <Skeleton className="h-48 rounded-2xl" />
      </div>
    );
  }

  // ── Tela: promovido da fila para reserva confirmada ──────────────────────
  if (promoted) {
    const { reserva, restaurantName, restaurantImage } = promoted;
    const mesaNumero = reserva.mesa?.numero;

    return (
      <div className="max-w-lg mx-auto space-y-6 animate-fade-in">
        <Link
          to="/app"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[36px]"
        >
          <Home className="h-4 w-4" />
          Início
        </Link>

        <div className="rounded-2xl bg-card p-6 shadow-card space-y-6 border border-emerald-500/30">
          {/* Restaurante */}
          <div className="flex items-center gap-3">
            {restaurantImage ? (
              <img src={restaurantImage} alt="" className="h-12 w-12 rounded-xl object-cover shrink-0" />
            ) : (
              <div className="h-12 w-12 rounded-xl bg-emerald-500/10 flex items-center justify-center shrink-0">
                <span className="text-lg font-bold text-emerald-600 font-display">
                  {restaurantName.charAt(0)}
                </span>
              </div>
            )}
            <div className="flex-1 min-w-0">
              <h2 className="font-display font-semibold text-foreground truncate">{restaurantName}</h2>
              <StatusBadge status="confirmada" />
            </div>
          </div>

          {/* Destaque */}
          <div className="rounded-xl bg-emerald-500/10 p-5 text-center space-y-2">
            <PartyPopper className="mx-auto h-10 w-10 text-emerald-600" />
            <h3 className="font-display text-xl font-bold text-foreground">Sua vez chegou!</h3>
            <p className="text-sm text-muted-foreground">
              {mesaNumero
                ? `A Mesa ${mesaNumero} foi reservada para você.`
                : 'Uma mesa foi reservada para você.'
              }
              {' '}Dirija-se ao restaurante e aguarde o check-in.
            </p>
          </div>

          {/* Horário */}
          {reserva.horario_reserva && (
            <div className="flex items-center gap-2 text-sm text-muted-foreground">
              <CalendarCheck className="h-4 w-4 shrink-0 text-primary" />
              {formatBRT(reserva.horario_reserva, {
                day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit',
              })}
            </div>
          )}

          <Button className="w-full min-h-[44px]" onClick={() => navigate('/app')}>
            Ver minhas reservas
          </Button>
        </div>
      </div>
    );
  }

  // ── Tela: removido da fila pelo restaurante ──────────────────────────────
  if (removido) {
    return (
      <div className="max-w-lg mx-auto space-y-6 animate-fade-in">
        <Link
          to="/app"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[36px]"
        >
          <Home className="h-4 w-4" />
          Início
        </Link>

        <div className="rounded-2xl bg-card p-6 shadow-card space-y-5 border border-border">
          <div className="flex items-center gap-3">
            <div className="h-10 w-10 rounded-full bg-secondary flex items-center justify-center shrink-0">
              <Info className="h-5 w-5 text-muted-foreground" />
            </div>
            <h2 className="font-display text-lg font-semibold text-foreground">
              Sua posição na fila foi encerrada
            </h2>
          </div>

          <p className="text-sm text-muted-foreground leading-relaxed">
            O restaurante precisou reorganizar os atendimentos e encerrou sua posição na fila.
            Isso pode acontecer por inatividade prolongada ou ajustes internos.
            Fique à vontade para entrar na fila novamente quando quiser.
          </p>

          <div className="flex flex-col sm:flex-row gap-3 pt-1">
            <Button className="flex-1 min-h-[44px]" onClick={() => navigate(-1)}>
              Voltar ao restaurante
            </Button>
            <Button variant="outline" className="flex-1 min-h-[44px]" onClick={() => navigate('/app')}>
              Ir para o início
            </Button>
          </div>
        </div>
      </div>
    );
  }

  // ── Tela: fora da fila ────────────────────────────────────────────────────
  if (!state) {
    return (
      <EmptyState
        icon={<ListOrdered className="h-7 w-7" />}
        title="Você não está em nenhuma fila"
        description="Entre na fila de um restaurante para acompanhar sua posição."
        action={<Button onClick={() => navigate('/app/search')}>Ver restaurantes</Button>}
      />
    );
  }

  // ── Tela: aguardando na fila ──────────────────────────────────────────────
  const { entry, restaurantName, restaurantImage, horarioReserva } = state;

  // Exige minutos + nivel válidos juntos — um nivel ausente/quebrado não pode
  // ser silenciosamente tratado como 'padrao' (ver isEstimativaHistorica).
  const nivelValido = entry.nivel === 'especifico' || entry.nivel === 'amplo' || entry.nivel === 'padrao';
  const temEstimativa = typeof entry.espera_estimada_minutos === 'number'
    && !Number.isNaN(entry.espera_estimada_minutos)
    && nivelValido;
  const esperaHistorica = isEstimativaHistorica(entry.nivel);
  const esperaValue = temEstimativa
    ? (esperaHistorica ? `~${entry.espera_estimada_minutos}` : `${entry.espera_estimada_minutos}`)
    : '—';
  const esperaCaption = temEstimativa && !esperaHistorica ? 'Estimativa aproximada' : undefined;

  return (
    <div className="max-w-lg mx-auto space-y-6 animate-fade-in">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-bold text-foreground">Acompanhar fila</h1>
        <Link
          to="/app"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[36px]"
        >
          <Home className="h-4 w-4" />
          Início
        </Link>
      </div>

      <div className="rounded-2xl bg-card p-6 shadow-card space-y-6">
        {/* Restaurante */}
        <div className="flex items-center gap-3">
          {restaurantImage ? (
            <img src={restaurantImage} alt="" className="h-12 w-12 rounded-xl object-cover shrink-0" />
          ) : (
            <div className="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
              <span className="text-lg font-bold text-primary font-display">
                {restaurantName?.charAt(0)}
              </span>
            </div>
          )}
          <div className="flex-1 min-w-0">
            <h2 className="font-display font-semibold text-foreground truncate">{restaurantName}</h2>
            {horarioReserva && (
              <p className="text-xs text-muted-foreground mt-0.5">
                {formatBRT(horarioReserva, { day: '2-digit', month: '2-digit', hour: '2-digit', minute: '2-digit' })}
              </p>
            )}
          </div>
          <StatusBadge status="waiting" />
        </div>

        {/* Stats */}
        <div className="grid grid-cols-3 gap-3">
          {[
            { icon: Hash,  value: entry.posicao,      label: 'Posição', caption: undefined as string | undefined },
            { icon: Clock, value: esperaValue,        label: 'min est.', caption: esperaCaption },
            { icon: Users, value: entry.qntd_pessoas, label: 'Pessoas', caption: undefined as string | undefined },
          ].map((stat, i) => (
            <div key={i} className="rounded-xl bg-secondary/60 p-4 text-center">
              <stat.icon className="mx-auto h-5 w-5 text-primary" />
              <p className="mt-2 text-2xl font-bold text-foreground font-display">
                {stat.value}
              </p>
              <p className="text-[11px] text-muted-foreground mt-0.5">{stat.label}</p>
              {stat.caption && (
                <p className="text-[10px] text-muted-foreground/70 italic mt-0.5">{stat.caption}</p>
              )}
            </div>
          ))}
        </div>

        <p className="text-xs text-center text-muted-foreground">
          Sua posição é atualizada automaticamente a cada 30 segundos.
        </p>

        <Button variant="outline" className="w-full min-h-[44px]" onClick={() => setCancelOpen(true)}>
          Sair da fila
        </Button>
      </div>

      <ConfirmModal
        open={cancelOpen}
        onClose={() => setCancelOpen(false)}
        onConfirm={handleCancel}
        title="Sair da fila?"
        description="Você perderá sua posição atual. Tem certeza?"
        confirmLabel="Sair da fila"
        isLoading={cancelling}
      />
    </div>
  );
};

export default Queue;
