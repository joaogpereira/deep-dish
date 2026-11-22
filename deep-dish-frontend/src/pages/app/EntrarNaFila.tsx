import React, { useEffect, useState } from 'react';
import { useNavigate, useParams, Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import EmptyState from '@/components/EmptyState';
import { restaurantsService } from '@/services/restaurants.service';
import { queueService } from '@/services/queue.service';
import { ApiError } from '@/services/httpClient';
import { Restaurante } from '@/types';
import { ListOrdered, QrCode, Store } from 'lucide-react';
import { useAuth } from '@/contexts/AuthContext';
import { useFilaAtual } from '@/hooks/useFilaAtual';

// Destino do QR da porta do restaurante (backend: FilaQrController::entrar).
// A rota é protegida: quem chega sem sessão passa pelo login e volta aqui
// (ver lib/redirectAposLogin).
const EntrarNaFila: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const { fila } = useFilaAtual();

  const [restaurant, setRestaurant] = useState<Restaurante | null>(null);
  const [loading, setLoading] = useState(true);
  const [partySize, setPartySize] = useState('2');
  const [joining, setJoining] = useState(false);
  const [error, setError] = useState('');

  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    setLoading(true);
    restaurantsService.getRestaurantById(id)
      .then(r => { if (!cancelled) setRestaurant(r ?? null); })
      .catch(() => { if (!cancelled) setRestaurant(null); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, [id]);

  const partySizeNum = Math.max(1, Number(partySize) || 1);
  const jaEmFila = fila?.entry.fila?.restaurante_id === id;

  const handleJoin = async () => {
    if (!id) return;
    setJoining(true);
    setError('');
    try {
      const result = await queueService.joinQueue({ restaurante_id: id, qntd_pessoas: partySizeNum });
      navigate('/app/queue', {
        replace: true,
        state: {
          entry:           result.data,
          restaurantName:  restaurant?.name,
          restaurantImage: restaurant?.imagem_url,
          horarioReserva:  result.data.fila?.horario_reserva ?? '',
          clienteId:       user?.id,
        },
      });
    } catch (err) {
      setError(err instanceof ApiError ? err.message : 'Erro ao entrar na fila. Tente novamente.');
    } finally {
      setJoining(false);
    }
  };

  if (loading) return (
    <div className="mx-auto max-w-md space-y-4">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-48 rounded-2xl" />
    </div>
  );

  if (!restaurant) return (
    <EmptyState
      icon={<Store className="h-7 w-7" />}
      title="Restaurante não encontrado"
      description="Não encontramos o restaurante deste QR Code."
      action={<Button onClick={() => navigate('/app/restaurants')}>Ver restaurantes</Button>}
    />
  );

  if (!restaurant.fila_ativa) return (
    <EmptyState
      icon={<ListOrdered className="h-7 w-7" />}
      title="Fila fechada"
      description={`${restaurant.name} não está recebendo fila no momento.`}
      action={<Button onClick={() => navigate(`/app/restaurants/${restaurant.id}`)}>Ver restaurante</Button>}
    />
  );

  if (jaEmFila) return (
    <EmptyState
      icon={<ListOrdered className="h-7 w-7" />}
      title="Você já está na fila"
      description={`Sua entrada na fila de ${restaurant.name} já está ativa.`}
      action={<Button onClick={() => navigate('/app/queue')}>Ver minha posição</Button>}
    />
  );

  return (
    <div className="mx-auto max-w-md space-y-6 animate-fade-in">
      <div className="rounded-2xl bg-card p-5 shadow-card space-y-5">
        <div className="flex items-center gap-3">
          <div className="flex h-12 w-12 shrink-0 items-center justify-center rounded-xl bg-primary/10">
            <QrCode className="h-6 w-6 text-primary" aria-hidden="true" />
          </div>
          <div className="min-w-0">
            <p className="text-xs text-muted-foreground">Entrar na fila de</p>
            <h1 className="font-display text-xl font-bold text-foreground truncate">{restaurant.name}</h1>
          </div>
        </div>

        <p className="text-sm text-muted-foreground">
          {restaurant.tamanho_fila_atual ?? 0}{' '}
          {restaurant.tamanho_fila_atual === 1 ? 'pessoa aguardando' : 'pessoas aguardando'}
        </p>

        <div>
          <Label htmlFor="qr-pessoas" className="text-xs text-muted-foreground">Quantas pessoas?</Label>
          <Input
            id="qr-pessoas"
            type="number"
            inputMode="numeric"
            min="1"
            max="20"
            value={partySize}
            onChange={e => setPartySize(e.target.value)}
            className="mt-1 w-full sm:w-24"
          />
        </div>

        {error && (
          <p role="alert" className="rounded-lg bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
            {error}
          </p>
        )}

        <Button onClick={handleJoin} disabled={joining} className="w-full min-h-[48px]" size="lg">
          {joining ? 'Entrando...' : 'Entrar na fila'}
        </Button>
      </div>

      <Link
        to={`/app/restaurants/${restaurant.id}`}
        className="block text-center text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[44px] leading-[44px]"
      >
        Ver detalhes do restaurante
      </Link>
    </div>
  );
};

export default EntrarNaFila;
