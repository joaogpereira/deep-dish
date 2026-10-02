import React, { useState, useEffect, useCallback } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { useAuth } from '@/contexts/AuthContext';
import { useRealtime } from '@/hooks/useRealtime';
import { Button } from '@/components/ui/button';
import StatusBadge from '@/components/StatusBadge';
import ConfirmModal from '@/components/ConfirmModal';
import { Skeleton } from '@/components/ui/skeleton';
import { Reserva } from '@/types';
import { reservationsService } from '@/services/reservations.service';
import { ApiError } from '@/services/httpClient';
import { CalendarDays, Clock, Users, MapPin, ArrowLeft } from 'lucide-react';
import { toast } from 'sonner';
import { formatBRT } from '@/lib/utils';
import { storageUrl } from '@/lib/storage';

const formatDate = (iso: string) => formatBRT(iso, { day: '2-digit', month: 'long', year: 'numeric' });
const formatTime = (iso: string) => formatBRT(iso, { hour: '2-digit', minute: '2-digit' });

const ReservationDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [reservation, setReservation] = useState<Reserva | null>(null);
  const [loading, setLoading] = useState(true);
  const [cancelOpen, setCancelOpen] = useState(false);
  const [cancelling, setCancelling] = useState(false);

  useEffect(() => {
    if (!id) return;
    let cancelled = false;

    const load = async (silent = false) => {
      if (!silent) setLoading(true);
      try {
        const data = await reservationsService.getReservationById(id);
        if (!cancelled) setReservation(data);
      } catch {
        if (!cancelled && !silent) setReservation(null);
      } finally {
        if (!cancelled && !silent) setLoading(false);
      }
    };
    load();
    return () => { cancelled = true; };
  }, [id]);

  // Tempo real, no lugar do polling silencioso: o backend avisa quando a reserva
  // muda de estado (check-in, liberação, cancelamento, expiração).
  const recarregar = useCallback(async () => {
    if (!id) return;
    try {
      const data = await reservationsService.getReservationById(id);
      if (data) setReservation(data);
    } catch { /* silencioso */ }
  }, [id]);

  useRealtime(
    user?.id ? `cliente.${user.id}` : undefined,
    { 'reserva.atualizada': recarregar },
    recarregar
  );

  const handleCancel = async () => {
    if (!id) return;
    setCancelling(true);
    try {
      const updated = await reservationsService.cancelReservation(id);
      setReservation(updated);
      toast.success('Reserva cancelada.');
      setCancelOpen(false);
    } catch (err) {
      const msg = err instanceof ApiError ? err.message : 'Erro ao cancelar reserva';
      toast.error(msg);
    } finally {
      setCancelling(false);
    }
  };

  if (loading) {
    return (
      <div className="max-w-lg mx-auto space-y-4">
        <Skeleton className="h-48 rounded-2xl" />
        <Skeleton className="h-32 rounded-2xl" />
      </div>
    );
  }

  if (!reservation) {
    return <p className="text-center text-muted-foreground py-20">Reserva não encontrada.</p>;
  }

  const restaurante = reservation.mesa?.restaurante;
  const canCancel = reservation.status === 'confirmada' || reservation.status === 'em_andamento';

  return (
    <div className="max-w-lg mx-auto space-y-6 animate-fade-in">
      <button
        onClick={() => navigate(-1)}
        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[44px]"
      >
        <ArrowLeft className="h-4 w-4" />
        Voltar
      </button>

      <h1 className="font-display text-2xl font-bold text-foreground">Detalhes da reserva</h1>

      <div className="rounded-2xl bg-card shadow-card overflow-hidden">
        {restaurante?.imagem_url && (
          <img src={storageUrl(restaurante.imagem_url)} alt="" className="h-40 w-full object-cover" />
        )}
        <div className="p-6 space-y-5">
          <div className="flex items-center justify-between">
            <h2 className="font-display text-lg font-semibold text-foreground">
              {restaurante?.name ?? 'Restaurante'}
            </h2>
            <StatusBadge status={reservation.status} />
          </div>

          <div className="space-y-2.5 text-sm text-muted-foreground">
            <p className="flex items-center gap-2.5">
              <CalendarDays className="h-4 w-4 shrink-0" />
              {formatDate(reservation.horario_reserva)}
            </p>
            <p className="flex items-center gap-2.5">
              <Clock className="h-4 w-4 shrink-0" />
              {formatTime(reservation.horario_reserva)}
            </p>
            {reservation.mesa && (
              <p className="flex items-center gap-2.5">
                <Users className="h-4 w-4 shrink-0" />
                Mesa {reservation.mesa.numero} · {reservation.party_size ?? reservation.mesa.capacidade}/{reservation.mesa.capacidade} pessoas
              </p>
            )}
            {restaurante?.endereco_completo && (
              <p className="flex items-center gap-2.5">
                <MapPin className="h-4 w-4 shrink-0" />
                {restaurante.endereco_completo}
              </p>
            )}
          </div>

          {reservation.status === 'confirmada' && (
            <div className="rounded-xl bg-secondary/50 p-4 text-xs text-muted-foreground leading-relaxed">
              Confirme sua chegada com o restaurante para liberar sua mesa.
            </div>
          )}
          {reservation.status === 'em_andamento' && (
            <div className="rounded-xl bg-emerald-500/10 p-4 text-xs text-emerald-700 dark:text-emerald-400 leading-relaxed">
              Check-in realizado! Aproveite sua refeição.
            </div>
          )}
          {reservation.status === 'expirada' && (
            <div className="rounded-xl bg-muted p-4 text-xs text-muted-foreground leading-relaxed">
              Esta reserva expirou porque o check-in não foi realizado dentro de 1 hora após o horário marcado. A mesa foi liberada.
            </div>
          )}

          <div className="flex gap-3 pt-1">
            <Button variant="outline" className="flex-1 min-h-[44px]" onClick={() => navigate('/app')}>
              Voltar
            </Button>
            {canCancel && (
              <Button variant="destructive" className="flex-1 min-h-[44px]" onClick={() => setCancelOpen(true)}>
                Cancelar
              </Button>
            )}
          </div>
        </div>
      </div>

      <ConfirmModal
        open={cancelOpen}
        onClose={() => setCancelOpen(false)}
        onConfirm={handleCancel}
        title="Cancelar reserva?"
        description="Esta ação não pode ser desfeita."
        confirmLabel="Cancelar reserva"
        isLoading={cancelling}
      />
    </div>
  );
};

export default ReservationDetail;