import React, { useState, useEffect, useCallback, useRef } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { CalendarDays, Plus, Clock, ChevronLeft, ChevronRight, ListOrdered, Hash, Users, Bell, PartyPopper, UtensilsCrossed } from 'lucide-react';
import StatusBadge from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { Skeleton } from '@/components/ui/skeleton';
import { reservationsService } from '@/services/reservations.service';
import { ApiError } from '@/services/httpClient';
import { storageUrl } from '@/lib/storage';
import { Paginated, Reserva } from '@/types';
import { toast } from 'sonner';
import { formatBRT } from '@/lib/utils';
import { useAuth } from '@/contexts/AuthContext';
import { useRealtime } from '@/hooks/useRealtime';
import { useFilaAtual, FilaAtual } from '@/hooks/useFilaAtual';

const formatDate = (iso: string) => formatBRT(iso, { day: '2-digit', month: '2-digit', year: 'numeric' });
const formatTime = (iso: string) => formatBRT(iso, { hour: '2-digit', minute: '2-digit' });

const EMPTY_PAGE: Paginated<Reserva> = { data: [], current_page: 1, last_page: 1, per_page: 10, total: 0 };
// ─── Card de fila ativa ──────────────────────────────────────────────────────

const FilaCard = ({ state, onClick }: { state: FilaAtual; onClick: () => void }) => (
  <button onClick={onClick} className="w-full text-left group">
    <div className="flex items-center gap-4 rounded-2xl bg-card p-4 shadow-card transition-all duration-200 ease-out-expo group-hover:shadow-card-hover group-hover:-translate-y-0.5 border border-primary/20">
      <div className="h-14 w-14 rounded-xl bg-primary/10 flex items-center justify-center shrink-0 overflow-hidden">
        {state.restaurantImage
          ? <img src={state.restaurantImage} alt="" className="h-full w-full object-cover" />
          : <ListOrdered className="h-6 w-6 text-primary" />
        }
      </div>
      <div className="flex-1 min-w-0">
        <p className="font-semibold text-foreground truncate">{state.restaurantName}</p>
        <div className="flex items-center gap-3 mt-1 text-sm text-muted-foreground flex-wrap">
          <span className="flex items-center gap-1">
            <Hash className="h-3.5 w-3.5" />
            Posição {state.entry.posicao}
          </span>
          <span className="flex items-center gap-1">
            <Users className="h-3.5 w-3.5" />
            {state.entry.qntd_pessoas} {state.entry.qntd_pessoas === 1 ? 'pessoa' : 'pessoas'}
          </span>
          <span className="flex items-center gap-1">
            <Clock className="h-3.5 w-3.5" />
            {formatTime(state.horarioReserva)}
          </span>
        </div>
      </div>
      <StatusBadge status="waiting" />
    </div>
  </button>
);

// ─── Banner de reserva aguardando check-in ───────────────────────────────────

const ConfirmadaBanner = ({ reservas }: { reservas: Reserva[] }) => (
  <section>
    <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
      Aguardando check-in
    </h2>
    <div className="space-y-2.5">
      {reservas.map(r => {
        const nome   = r.mesa?.restaurante?.name ?? 'Restaurante';
        const imagem = storageUrl(r.mesa?.restaurante?.imagem_url);
        const mesa   = r.mesa?.numero;
        return (
          <Link key={r.id} to={`/app/reservations/${String(r.id)}`} className="block group">
            <div className="flex items-center gap-4 rounded-2xl bg-amber-500/10 border border-amber-500/30 p-4 transition-all duration-200 ease-out-expo group-hover:-translate-y-0.5 group-hover:bg-amber-500/15">
              <div className="h-14 w-14 rounded-xl bg-amber-500/20 flex items-center justify-center shrink-0 overflow-hidden">
                {imagem
                  ? <img src={imagem} alt="" className="h-full w-full object-cover" />
                  : <PartyPopper className="h-6 w-6 text-amber-600" />
                }
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-foreground truncate">{nome}</p>
                <p className="text-sm text-amber-700 dark:text-amber-400 font-medium mt-0.5">
                  {mesa ? `Mesa ${mesa} reservada` : 'Mesa reservada'} — aguardando o restaurante confirmar
                </p>
                <p className="text-xs text-muted-foreground mt-0.5">
                  {formatDate(r.horario_reserva)} às {formatTime(r.horario_reserva)}
                </p>
              </div>
              <Bell className="h-5 w-5 text-amber-500 animate-pulse-soft shrink-0" />
            </div>
          </Link>
        );
      })}
    </div>
  </section>
);

// ─── Banner de reserva em andamento ─────────────────────────────────────────

const EmAndamentoBanner = ({ reservas }: { reservas: Reserva[] }) => (
  <section>
    <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
      Em andamento
    </h2>
    <div className="space-y-2.5">
      {reservas.map(r => {
        const nome   = r.mesa?.restaurante?.name ?? 'Restaurante';
        const imagem = storageUrl(r.mesa?.restaurante?.imagem_url);
        const mesa   = r.mesa?.numero;
        return (
          <Link key={r.id} to={`/app/reservations/${String(r.id)}`} className="block group">
            <div className="flex items-center gap-4 rounded-2xl bg-emerald-500/10 border border-emerald-500/30 p-4 transition-all duration-200 ease-out-expo group-hover:-translate-y-0.5 group-hover:bg-emerald-500/15">
              <div className="h-14 w-14 rounded-xl bg-emerald-500/20 flex items-center justify-center shrink-0 overflow-hidden">
                {imagem
                  ? <img src={imagem} alt="" className="h-full w-full object-cover" />
                  : <UtensilsCrossed className="h-6 w-6 text-emerald-600" />
                }
              </div>
              <div className="flex-1 min-w-0">
                <p className="font-semibold text-foreground truncate">{nome}</p>
                <p className="text-sm text-emerald-700 dark:text-emerald-400 font-medium mt-0.5">
                  {mesa ? `Mesa ${mesa} em andamento` : 'Reserva em andamento'} — aproveite sua visita!
                </p>
                <p className="text-xs text-muted-foreground mt-0.5">
                  {formatDate(r.horario_reserva)} às {formatTime(r.horario_reserva)}
                </p>
              </div>
              <UtensilsCrossed className="h-5 w-5 text-emerald-500 animate-pulse-soft shrink-0" />
            </div>
          </Link>
        );
      })}
    </div>
  </section>
);

// ─── Card de reserva genérico ────────────────────────────────────────────────

const ReservaCard = ({ r, faded }: { r: Reserva; faded?: boolean }) => {
  const imagem  = storageUrl(r.mesa?.restaurante?.imagem_url);
  const name    = r.mesa?.restaurante?.name ?? 'Restaurante';
  const initial = name.charAt(0).toUpperCase();

  return (
    <Link to={`/app/reservations/${String(r.id)}`} className="block group">
      <div className={`flex items-center gap-4 rounded-2xl bg-card p-4 shadow-card transition-all duration-200 ease-out-expo group-hover:shadow-card-hover group-hover:-translate-y-0.5 ${faded ? 'opacity-60' : ''}`}>
        <div className="h-14 w-14 rounded-xl bg-primary/10 flex items-center justify-center shrink-0 overflow-hidden">
          {imagem
            ? <img src={imagem} alt="" className="h-full w-full object-cover" />
            : <span className="text-lg font-bold text-primary font-display">{initial}</span>
          }
        </div>
        <div className="flex-1 min-w-0">
          <p className="font-semibold text-foreground truncate">{name}</p>
          <div className="flex items-center gap-2 mt-1 text-sm text-muted-foreground">
            <CalendarDays className="h-3.5 w-3.5 shrink-0" />
            {formatDate(r.horario_reserva)}
            <Clock className="h-3.5 w-3.5 ml-1 shrink-0" />
            {formatTime(r.horario_reserva)}
            {r.mesa && (
              <span className="hidden sm:inline">· {r.party_size ?? r.mesa.capacidade}/{r.mesa.capacidade} pessoas</span>
            )}
          </div>
        </div>
        <StatusBadge status={r.status} />
      </div>
    </Link>
  );
};

// ─── Paginação ────────────────────────────────────────────────────────────────

const Pagination = ({
  page, lastPage, onPrev, onNext,
}: { page: number; lastPage: number; onPrev: () => void; onNext: () => void }) => {
  if (lastPage <= 1) return null;
  return (
    <div className="flex items-center justify-center gap-3 mt-3">
      <Button size="sm" variant="outline" onClick={onPrev} disabled={page <= 1} className="h-8 w-8 p-0">
        <ChevronLeft className="h-4 w-4" />
      </Button>
      <span className="text-sm text-muted-foreground">{page} / {lastPage}</span>
      <Button size="sm" variant="outline" onClick={onNext} disabled={page >= lastPage} className="h-8 w-8 p-0">
        <ChevronRight className="h-4 w-4" />
      </Button>
    </div>
  );
};

// ─── Componente principal ────────────────────────────────────────────────────

const AppHome: React.FC = () => {
  const navigate = useNavigate();
  const { user } = useAuth();

  const { fila: filaState, saida, atualizar: atualizarFila } = useFilaAtual();
  const [activePage,     setActivePage]     = useState(1);
  const [finishedPage,   setFinishedPage]   = useState(1);
  const [activeData,     setActiveData]     = useState<Paginated<Reserva>>(EMPTY_PAGE);
  const [finishedData,   setFinishedData]   = useState<Paginated<Reserva>>(EMPTY_PAGE);
  const [loadingActive,   setLoadingActive]   = useState(true);
  const [loadingFinished, setLoadingFinished] = useState(true);

  // Rastreia statuses anteriores para detectar mudanças e disparar toasts
  const prevStatusesRef  = useRef<Record<string, string>>({});
  const isFirstFetchRef  = useRef(true);

  const fetchActive = useCallback(async (page: number, silent = false) => {
    if (!silent) setLoadingActive(true);
    try {
      const data = await reservationsService.listUserReservations({ status_group: 'active', page, per_page: 10 });

      // Detecta mudanças de status para disparar notificações
      if (!isFirstFetchRef.current) {
        data.data.forEach(r => {
          const id         = String(r.id);
          const prevStatus = prevStatusesRef.current[id];

          // Nova reserva confirmada apareceu (promoção da fila)
          if (!prevStatus && r.status === 'confirmada') {
            const nome = r.mesa?.restaurante?.name ?? 'Restaurante';
            toast.success('Sua vez chegou!', {
              description: `Mesa reservada no ${nome}. Dirija-se ao restaurante e aguarde o check-in.`,
              duration: 12000,
            });
          }

          // confirmada → em_andamento (check-in aceito pelo restaurante)
          if (prevStatus === 'confirmada' && r.status === 'em_andamento') {
            const nome = r.mesa?.restaurante?.name ?? 'Restaurante';
            const mesa = r.mesa?.numero;
            toast.success('Check-in realizado!', {
              description: mesa
                ? `Mesa ${mesa} no ${nome} — boa visita!`
                : `Boa visita ao ${nome}!`,
              duration: 8000,
            });
          }
        });
      }

      isFirstFetchRef.current = false;
      prevStatusesRef.current = Object.fromEntries(
        data.data.map(r => [String(r.id), r.status])
      );

      setActiveData(data);
    } catch (err) {
      if (!silent) toast.error(err instanceof ApiError ? err.message : 'Erro ao carregar reservas');
    } finally {
      if (!silent) setLoadingActive(false);
    }
  }, []);

  const fetchFinished = useCallback(async (page: number, silent = false) => {
    if (!silent) setLoadingFinished(true);
    try {
      const data = await reservationsService.listUserReservations({ status_group: 'finished', page, per_page: 10 });
      setFinishedData(data);
    } catch (err) {
      if (!silent) toast.error(err instanceof ApiError ? err.message : 'Erro ao carregar histórico');
    } finally {
      if (!silent) setLoadingFinished(false);
    }
  }, []);

  // Saída da fila que não foi promoção nem desistência do próprio cliente.
  useEffect(() => {
    if (!saida || saida.status === 'atendido' || saida.status === 'desistiu') return;
    toast.info('Sua posição na fila foi encerrada', {
      description: 'O restaurante reorganizou os atendimentos. Fique à vontade para entrar novamente.',
      duration: 10000,
    });
  }, [saida]);

  useEffect(() => { fetchActive(activePage); },    [fetchActive, activePage]);
  useEffect(() => { fetchFinished(finishedPage); }, [fetchFinished, finishedPage]);

  // Tempo real, no lugar do polling de 30s. 'cliente.promovido' entra aqui também
  // porque a promoção cria uma reserva — a lista de ativas muda junto. A posição
  // na fila quem mantém é o useFilaAtual.
  const sincronizar = useCallback(() => {
    fetchActive(activePage, true);
  }, [fetchActive, activePage]);

  useRealtime(
    user?.id ? `cliente.${user.id}` : undefined,
    {
      'reserva.atualizada': sincronizar,
      'cliente.promovido': sincronizar,
    },
    sincronizar
  );

  // Refetch ao voltar para a aba
  useEffect(() => {
    const onFocus = () => {
      atualizarFila();
      fetchActive(activePage, true);
      fetchFinished(finishedPage, true);
    };
    window.addEventListener('focus', onFocus);
    return () => window.removeEventListener('focus', onFocus);
  }, [atualizarFila, fetchActive, fetchFinished, activePage, finishedPage]);

  const loading = loadingActive && loadingFinished;
  const hasAny  = activeData.total > 0 || finishedData.total > 0;

  // Separa reservas por tipo para exibição
  const confirmadas  = activeData.data.filter(r => r.status === 'confirmada');
  const emAndamento  = activeData.data.filter(r => r.status === 'em_andamento');
  const proximas     = activeData.data.filter(r => r.status !== 'confirmada' && r.status !== 'em_andamento');

  return (
    <div className="space-y-8 animate-fade-in">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-bold text-foreground">Suas reservas</h1>
        <Link to="/app/search">
          <Button size="sm" className="min-h-[36px]">
            <Plus className="mr-1.5 h-4 w-4" /> Nova reserva
          </Button>
        </Link>
      </div>

      {/* Banner de aguardando check-in (reservas confirmadas via fila ou reserva direta) */}
      {!loadingActive && confirmadas.length > 0 && (
        <ConfirmadaBanner reservas={confirmadas} />
      )}

      {/* Card de fila ativa */}
      {filaState && (
        <section>
          <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
            Na fila
          </h2>
          <FilaCard
            state={filaState}
            onClick={() => navigate('/app/queue', { state: filaState })}
          />
        </section>
      )}

      {loading ? (
        <div className="space-y-3">
          {[1, 2, 3].map(i => <Skeleton key={i} className="h-[72px] rounded-2xl" />)}
        </div>
      ) : !hasAny && confirmadas.length === 0 ? (
        <EmptyState
          icon={<CalendarDays className="h-7 w-7" />}
          title="Nenhuma reserva"
          description="Busque um restaurante e faça sua primeira reserva."
          action={<Link to="/app/search"><Button>Buscar restaurantes</Button></Link>}
        />
      ) : (
        <>
          {emAndamento.length > 0 && (
            <EmAndamentoBanner reservas={emAndamento} />
          )}

          {proximas.length > 0 && (
            <section>
              <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Próximas
              </h2>
              <div className="space-y-2.5 animate-stagger">
                {proximas.map(r => <ReservaCard key={r.id} r={r} />)}
              </div>
              <Pagination
                page={activePage}
                lastPage={activeData.last_page}
                onPrev={() => setActivePage(p => p - 1)}
                onNext={() => setActivePage(p => p + 1)}
              />
            </section>
          )}

          {finishedData.total > 0 && (
            <section>
              <h2 className="text-xs font-semibold uppercase tracking-wider text-muted-foreground mb-3">
                Finalizadas
              </h2>
              {loadingFinished ? (
                <div className="space-y-3">
                  {[1, 2].map(i => <Skeleton key={i} className="h-[72px] rounded-2xl" />)}
                </div>
              ) : (
                <>
                  <div className="space-y-2.5">
                    {finishedData.data.map(r => <ReservaCard key={r.id} r={r} faded />)}
                  </div>
                  <Pagination
                    page={finishedPage}
                    lastPage={finishedData.last_page}
                    onPrev={() => setFinishedPage(p => p - 1)}
                    onNext={() => setFinishedPage(p => p + 1)}
                  />
                </>
              )}
            </section>
          )}
        </>
      )}
    </div>
  );
};

export default AppHome;