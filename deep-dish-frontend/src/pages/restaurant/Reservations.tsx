import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import StatusBadge from '@/components/StatusBadge';
import { Skeleton } from '@/components/ui/skeleton';
import { Paginated, Reserva } from '@/types';
import { reservationsService } from '@/services/reservations.service';
import { ApiError } from '@/services/httpClient';
import { Clock, Users, CalendarDays, ChevronLeft, ChevronRight, Trash2 } from 'lucide-react';
import { toast } from 'sonner';
import { formatBRT } from '@/lib/utils';
import { useAuth } from '@/contexts/AuthContext';
import { useRealtime } from '@/hooks/useRealtime';

const formatDate = (iso: string) => formatBRT(iso, { day: '2-digit', month: '2-digit' });
const formatTime = (iso: string) => formatBRT(iso, { hour: '2-digit', minute: '2-digit' });

type StatusGroup = 'all' | 'active' | 'finished';

const EMPTY_PAGE: Paginated<Reserva> = { data: [], current_page: 1, last_page: 1, per_page: 10, total: 0 };

const Reservations: React.FC = () => {
  const { user }                        = useAuth();
  const [pageData, setPageData]         = useState<Paginated<Reserva>>(EMPTY_PAGE);
  const [page, setPage]                 = useState(1);
  const [statusGroup, setStatusGroup]   = useState<StatusGroup>('all');
  const [loading, setLoading]           = useState(true);
  const [checkinId, setCheckinId]       = useState<string | null>(null);
  const [liberandoId, setLiberandoId]   = useState<string | null>(null);
  const [deletingId, setDeletingId]     = useState<string | null>(null);

  const fetchPage = useCallback(async (p: number, group: StatusGroup, silent = false) => {
    if (!silent) setLoading(true);
    try {
      const params = {
        page: p,
        per_page: 10,
        ...(group !== 'all' ? { status_group: group as 'active' | 'finished' } : {}),
      };
      const data = await reservationsService.listRestaurantReservations(params);
      setPageData(data);
    } catch (err) {
      if (!silent) {
        const msg = err instanceof ApiError ? err.message : 'Erro ao carregar reservas';
        toast.error(msg);
      }
    } finally {
      if (!silent) setLoading(false);
    }
  }, []);

  useEffect(() => { fetchPage(page, statusGroup); }, [fetchPage, page, statusGroup]);

  // Atualização ao vivo: check-in e liberação feitos noutro dispositivo do salão
  // aparecem aqui sem F5. Silencioso, para não piscar o skeleton a cada evento.
  const recarregar = useCallback(
    () => { fetchPage(page, statusGroup, true); },
    [fetchPage, page, statusGroup]
  );

  useRealtime(
    user?.id ? `restaurante.${user.id}` : undefined,
    { 'operacao.atualizada': recarregar },
    recarregar
  );

  const handleGroupChange = (group: StatusGroup) => {
    setStatusGroup(group);
    setPage(1);
  };

  const handleCheckin = async (id: string) => {
    setCheckinId(id);
    try {
      const updated = await reservationsService.checkin(id);
      setPageData(prev => ({ ...prev, data: prev.data.map(r => (String(r.id) === id ? updated : r)) }));
      toast.success('Check-in realizado!');
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Erro ao fazer check-in';
      toast.error(msg);
    } finally {
      setCheckinId(null);
    }
  };

  const handleLiberar = async (id: string) => {
    setLiberandoId(id);
    try {
      const updated = await reservationsService.liberarMesa(id);
      setPageData(prev => ({ ...prev, data: prev.data.map(r => (String(r.id) === id ? updated : r)) }));
      toast.success('Mesa liberada.');
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Erro ao liberar mesa';
      toast.error(msg);
    } finally {
      setLiberandoId(null);
    }
  };

  const handleDelete = async (id: string) => {
    if (!window.confirm('Excluir este registro permanentemente?')) return;
    setDeletingId(id);
    try {
      await reservationsService.deleteRestaurantReservation(id);
      toast.success('Reserva excluída.');
      const nextPage = pageData.data.length === 1 && page > 1 ? page - 1 : page;
      setPage(nextPage);
      await fetchPage(nextPage, statusGroup);
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Erro ao excluir reserva';
      toast.error(msg);
    } finally {
      setDeletingId(null);
    }
  };

  const filterBtns: { label: string; value: StatusGroup }[] = [
    { label: 'Todas',      value: 'all'      },
    { label: 'Ativas',     value: 'active'   },
    { label: 'Finalizadas', value: 'finished' },
  ];

  return (
    <div className="space-y-6 animate-fade-in">
      <h1 className="font-display text-2xl font-bold text-foreground">Reservas</h1>

      {/* Filtro por grupo */}
      <div className="flex gap-2">
        {filterBtns.map(btn => (
          <Button
            key={btn.value}
            size="sm"
            variant={statusGroup === btn.value ? 'default' : 'outline'}
            onClick={() => handleGroupChange(btn.value)}
          >
            {btn.label}
          </Button>
        ))}
      </div>

      {loading ? (
        <div className="space-y-4">
          {[1, 2, 3].map(i => <Skeleton key={i} className="h-20 rounded-2xl" />)}
        </div>
      ) : pageData.data.length === 0 ? (
        <p className="text-center text-muted-foreground py-14">
          Nenhuma reserva encontrada.
        </p>
      ) : (
        <>
          <div className="space-y-2.5 animate-stagger">
            {pageData.data.map(r => {
              const id          = String(r.id);
              const isCheckin   = checkinId === id;
              const isLiberando = liberandoId === id;
              const isDeletando = deletingId === id;
              const podeCheckin = r.status === 'confirmada';
              const podeLiberar = r.status === 'em_andamento';
              const podeExcluir = ['liberada', 'expirada', 'cancelada'].includes(r.status);

              return (
                <div
                  key={id}
                  className={`rounded-2xl bg-card p-4 shadow-card flex flex-col sm:flex-row sm:items-center gap-3 transition-all duration-200 hover:shadow-card-hover ${podeExcluir ? 'opacity-70' : ''}`}
                >
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2 flex-wrap">
                      <span className="font-semibold text-foreground">
                        {r.cliente?.name ?? `Reserva #${id.slice(-4)}`}
                      </span>
                      <StatusBadge status={r.status} />
                    </div>
                    <div className="flex items-center gap-3 mt-1 text-sm text-muted-foreground flex-wrap">
                      <span className="flex items-center gap-1">
                        <CalendarDays className="h-3.5 w-3.5 shrink-0" />
                        {formatDate(r.horario_reserva)}
                      </span>
                      <span className="flex items-center gap-1">
                        <Clock className="h-3.5 w-3.5 shrink-0" />
                        {formatTime(r.horario_reserva)}
                      </span>
                      {r.mesa && (
                        <span className="flex items-center gap-1">
                          <Users className="h-3.5 w-3.5 shrink-0" />
                          Mesa {r.mesa.numero} · {r.party_size ?? r.mesa.capacidade}/{r.mesa.capacidade} pessoas
                        </span>
                      )}
                    </div>
                  </div>
                  <div className="flex items-center gap-2 shrink-0">
                    {podeCheckin && (
                      <Button size="sm" className="min-h-[36px]" onClick={() => handleCheckin(id)} disabled={isCheckin}>
                        {isCheckin ? 'Registrando...' : 'Check-in'}
                      </Button>
                    )}
                    {podeLiberar && (
                      <Button size="sm" variant="outline" className="min-h-[36px]" onClick={() => handleLiberar(id)} disabled={isLiberando}>
                        {isLiberando ? 'Liberando...' : 'Liberar mesa'}
                      </Button>
                    )}
                    {podeExcluir && (
                      <Button
                        size="sm"
                        variant="ghost"
                        className="text-destructive hover:text-destructive hover:bg-destructive/10 h-9 w-9 p-0"
                        onClick={() => handleDelete(id)}
                        disabled={isDeletando}
                        title="Excluir registro"
                      >
                        <Trash2 className="h-4 w-4" />
                      </Button>
                    )}
                  </div>
                </div>
              );
            })}
          </div>

          {/* Paginação */}
          {pageData.last_page > 1 && (
            <div className="flex items-center justify-center gap-3 pt-2">
              <Button size="sm" variant="outline" onClick={() => setPage(p => p - 1)} disabled={page <= 1} className="h-8 w-8 p-0">
                <ChevronLeft className="h-4 w-4" />
              </Button>
              <span className="text-sm text-muted-foreground">{page} / {pageData.last_page}</span>
              <Button size="sm" variant="outline" onClick={() => setPage(p => p + 1)} disabled={page >= pageData.last_page} className="h-8 w-8 p-0">
                <ChevronRight className="h-4 w-4" />
              </Button>
            </div>
          )}
        </>
      )}
    </div>
  );
};

export default Reservations;
