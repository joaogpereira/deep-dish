import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import StatusBadge from '@/components/StatusBadge';
import EmptyState from '@/components/EmptyState';
import { Skeleton } from '@/components/ui/skeleton';
import { queueService } from '@/services/queue.service';
import { ClienteFilaEntry } from '@/types';
import { Link } from 'react-router-dom';
import { ListOrdered, Users, Clock, QrCode, ShieldCheck } from 'lucide-react';
import { toast } from 'sonner';
import { formatBRT } from '@/lib/utils';
import { useAuth } from '@/contexts/AuthContext';
import { useRealtime } from '@/hooks/useRealtime';

const QueueManagement: React.FC = () => {
  const { user }                  = useAuth();
  const [entries, setEntries]     = useState<ClienteFilaEntry[]>([]);
  const [loading, setLoading]     = useState(true);
  const [removingId, setRemovingId] = useState<string | null>(null);

  const fetchQueue = useCallback(async (silent = false) => {
    if (!silent) setLoading(true);
    try {
      const data = await queueService.getRestaurantQueue();
      setEntries(data);
    } catch {
      if (!silent) toast.error('Erro ao carregar fila.');
    } finally {
      if (!silent) setLoading(false);
    }
  }, []);

  useEffect(() => { fetchQueue(); }, [fetchQueue]);

  // Tempo real: o backend avisa quando a fila muda, no lugar do polling de 30s.
  useRealtime(
    user?.id ? `restaurante.${user.id}` : undefined,
    { 'fila.atualizada': () => fetchQueue(true) },
    () => fetchQueue(true)
  );

  const handleRemover = async (id: string) => {
    setRemovingId(id);
    try {
      await queueService.removeFromQueue(id);
      setEntries(prev => prev.filter(e => e.id !== id));
      toast.success('Removido da fila.');
    } catch {
      toast.error('Erro ao remover da fila.');
    } finally {
      setRemovingId(null);
    }
  };

  if (loading) return (
    <div className="space-y-4">
      <Skeleton className="h-8 w-48" />
      {[1, 2, 3].map(i => <Skeleton key={i} className="h-20 rounded-2xl" />)}
    </div>
  );

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex flex-wrap items-center justify-between gap-3">
        <h1 className="font-display text-2xl font-bold text-foreground">Fila de espera</h1>
        <div className="flex items-center gap-3">
          <span className="text-sm text-muted-foreground">{entries.length} {entries.length === 1 ? 'pessoa' : 'pessoas'}</span>
          <Button asChild size="sm" variant="outline" className="min-h-[36px] gap-1.5">
            <Link to="/restaurant/queue/qr">
              <QrCode className="h-4 w-4" aria-hidden="true" />
              QR da fila
            </Link>
          </Button>
        </div>
      </div>

      {entries.length === 0 ? (
        <EmptyState
          icon={<ListOrdered className="h-7 w-7" />}
          title="Fila vazia"
          description="Nenhum cliente na fila no momento."
        />
      ) : (
        <div className="space-y-2.5 animate-stagger">
          {entries.map((e, idx) => (
            <div
              key={e.id}
              className="rounded-2xl bg-card p-4 shadow-card flex flex-col sm:flex-row sm:items-center gap-3 transition-all duration-200 hover:shadow-card-hover"
            >
              <div className="flex items-center justify-center h-9 w-9 rounded-full bg-primary/10 shrink-0">
                <span className="text-sm font-bold text-primary">#{e.posicao ?? idx + 1}</span>
              </div>

              <div className="flex-1 min-w-0">
                <div className="flex items-center gap-2 flex-wrap">
                  <span className="font-semibold text-foreground">
                    {e.cliente?.name ?? `Cliente #${e.id.slice(-4)}`}
                  </span>
                  <StatusBadge status="waiting" />
                  {e.protegida && (
                    <span
                      className="inline-flex items-center gap-1 rounded-full bg-primary/10 px-2 py-0.5 text-[11px] font-medium text-primary"
                      title="Esperou o tempo máximo: tem prioridade e não é ultrapassada por encaixe."
                    >
                      <ShieldCheck className="h-3 w-3" aria-hidden="true" />
                      Prioridade garantida
                    </span>
                  )}
                </div>
                <div className="flex items-center gap-3 mt-1 text-sm text-muted-foreground flex-wrap">
                  <span className="flex items-center gap-1">
                    <Users className="h-3.5 w-3.5" />
                    {e.qntd_pessoas} {e.qntd_pessoas === 1 ? 'pessoa' : 'pessoas'}
                  </span>
                  {e.fila?.horario_reserva && (
                    <span className="flex items-center gap-1">
                      <Clock className="h-3.5 w-3.5" />
                      {formatBRT(e.fila.horario_reserva, { hour: '2-digit', minute: '2-digit' })}
                    </span>
                  )}
                </div>
              </div>

              <div className="flex items-center gap-2 shrink-0">
                <Button
                  size="sm"
                  variant="outline"
                  className="min-h-[36px] text-destructive hover:text-destructive hover:bg-destructive/10 border-destructive/30"
                  onClick={() => handleRemover(e.id)}
                  disabled={removingId === e.id}
                >
                  {removingId === e.id ? 'Removendo...' : 'Remover'}
                </Button>
              </div>
            </div>
          ))}
        </div>
      )}
    </div>
  );
};

export default QueueManagement;
