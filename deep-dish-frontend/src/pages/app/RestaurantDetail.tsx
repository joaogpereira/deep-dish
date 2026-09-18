import React, { useState, useEffect } from 'react';
import { useParams, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Skeleton } from '@/components/ui/skeleton';
import StatusBadge from '@/components/StatusBadge';
import { restaurantsService } from '@/services/restaurants.service';
import { queueService } from '@/services/queue.service';
import { ApiError } from '@/services/httpClient';
import { Restaurante, Mesa } from '@/types';
import { ArrowLeft, MapPin, Clock, Phone, Star, Users, ListOrdered } from 'lucide-react';
import { hojeEmBRT, toISOBRT } from '@/lib/utils';
import { storageUrl } from '@/lib/storage';
import { getTipoLabel } from '@/constants/tipos';
import { toast } from 'sonner';
import { useAuth } from '@/contexts/AuthContext';
import { useFilaAtual } from '@/hooks/useFilaAtual';

interface PublicStaff { name: string; cargo: string; horario?: string | null; }

const PRICE_LABELS: Record<number, string> = { 1: 'R$', 2: 'R$$', 3: 'R$$$', 4: 'R$$$$' };

const RestaurantDetail: React.FC = () => {
  const { id } = useParams<{ id: string }>();
  const navigate = useNavigate();
  const { user } = useAuth();
  const [restaurant, setRestaurant] = useState<Restaurante | null>(null);
  const [staffList, setStaffList]   = useState<PublicStaff[]>([]);
  const [mesas, setMesas] = useState<Mesa[]>([]);
  const [mesasError, setMesasError] = useState<string | null>(null);
  const [loading, setLoading] = useState(true);
  const [loadingMesas, setLoadingMesas] = useState(false);
  const [partySize, setPartySize] = useState('2');
  const [selectedMesa, setSelectedMesa] = useState<Mesa | null>(null);
  const [selectedDate, setSelectedDate] = useState<string>(hojeEmBRT());
  const [selectedTime, setSelectedTime] = useState<string>('');
  const [joiningQueue, setJoiningQueue] = useState(false);

  // O cliente logado já está na fila deste restaurante?
  const { fila } = useFilaAtual();
  const jaEmFilaNesteRestaurante = fila?.entry.fila?.restaurante_id === id;

  // Carrega dados do restaurante
  useEffect(() => {
    if (!id) return;
    let cancelled = false;
    setLoading(true);

    Promise.all([
      restaurantsService.getRestaurantById(id),
      restaurantsService.getStaff(id),
    ]).then(([rest, staff]) => {
      if (cancelled) return;
      setRestaurant(rest ?? null);
      setStaffList(staff);
    }).catch(() => {
      if (!cancelled) setRestaurant(null);
    }).finally(() => { if (!cancelled) setLoading(false); });

    return () => { cancelled = true; };
  }, [id]);

  // Verifica se o horário informado está dentro do funcionamento do restaurante
  const horarioForaDoFuncionamento = (() => {
    if (!selectedTime || !restaurant?.horario_abertura || !restaurant?.horario_fechamento) return false;
    const toMin = (hhmm: string) => {
      const [h, m] = hhmm.slice(0, 5).split(':').map(Number);
      return h * 60 + m;
    };
    const sel   = toMin(selectedTime);
    const abre  = toMin(restaurant.horario_abertura);
    const fecha = toMin(restaurant.horario_fechamento);
    return sel < abre || sel >= fecha;
  })();

  // Verifica se o horário selecionado já passou
  const horarioNoPassado = (() => {
    if (!selectedDate || !selectedTime) return false;
    return new Date(toISOBRT(selectedDate, selectedTime)) <= new Date();
  })();

  // Recarrega mesas quando data ou hora muda
  useEffect(() => {
    if (!id || !restaurant?.reservations_enabled || !selectedDate || !selectedTime) {
      setMesas([]);
      return;
    }
    if (horarioForaDoFuncionamento || horarioNoPassado) {
      setMesas([]);
      return;
    }
    let cancelled = false;
    setLoadingMesas(true);
    setMesasError(null);
    setSelectedMesa(null);

    const horario = toISOBRT(selectedDate, selectedTime);

    restaurantsService.getMesasDisponiveis(id, undefined, horario)
      .then(data => { if (!cancelled) setMesas(data); })
      .catch(err => {
        if (!cancelled) {
          setMesasError(err instanceof ApiError ? err.message : 'Erro ao carregar mesas.');
        }
      })
      .finally(() => { if (!cancelled) setLoadingMesas(false); });

    return () => { cancelled = true; };
  }, [id, restaurant?.reservations_enabled, selectedDate, selectedTime]);

  const partySizeNum = Math.max(1, Number(partySize) || 1);
  const mesasFiltradas = mesas.filter(m => m.capacidade >= partySizeNum);

  const horarioReserva = selectedDate && selectedTime ? toISOBRT(selectedDate, selectedTime) : '';

  const handleQueue = async () => {
    if (!id || !horarioReserva) return;
    setJoiningQueue(true);
    try {
      const result = await queueService.joinQueue({
        restaurante_id: id,
        horario_reserva: horarioReserva,
        qntd_pessoas: partySizeNum,
      });
      navigate('/app/queue', {
        state: {
          entry:           result.data,
          restaurantName:  restaurant?.name,
          restaurantImage: restaurant?.imagem_url,
          horarioReserva,
          clienteId:       user?.id,
        },
      });
    } catch (err) {
      toast.error(err instanceof ApiError ? err.message : 'Erro ao entrar na fila.');
    } finally {
      setJoiningQueue(false);
    }
  };

  const handleReserve = () => {
    if (!selectedMesa || !horarioReserva) return;
    navigate('/app/confirm', {
      state: {
        restaurantId:    id,
        restaurantName:  restaurant?.name,
        restaurantImage: restaurant?.imagem_url,
        mesaId:          String(selectedMesa.id),
        mesaNumero:      selectedMesa.numero,
        mesaCapacidade:  selectedMesa.capacidade,
        partySize:       partySizeNum,
        horarioReserva,
      },
    });
  };

  const endereco = restaurant?.endereco_completo ||
    (restaurant
      ? [restaurant.logradouro, restaurant.numero, restaurant.bairro, restaurant.cidade]
          .filter(Boolean).join(', ')
      : '');

  if (loading) return (
    <div className="space-y-4 animate-fade-in">
      <Skeleton className="h-56 rounded-2xl" />
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-20 rounded-xl" />
    </div>
  );

  if (!restaurant) return (
    <p className="text-center text-muted-foreground py-20">Restaurante não encontrado.</p>
  );

  return (
    <div className="space-y-6 animate-fade-in">
      <button
        onClick={() => navigate(-1)}
        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[36px]"
      >
        <ArrowLeft className="h-4 w-4" />
        Voltar
      </button>

      {/* Banner */}
      <div className="relative h-52 sm:h-60 md:h-72 overflow-hidden rounded-2xl">
        {restaurant.imagem_url ? (
          <img
            src={storageUrl(restaurant.imagem_url)}
            alt={restaurant.name}
            className="h-full w-full object-cover"
          />
        ) : (
          <div className="h-full w-full bg-muted flex items-center justify-center">
            <span className="text-6xl font-bold text-muted-foreground/15 font-display">
              {restaurant.name.charAt(0)}
            </span>
          </div>
        )}
        <div className="absolute inset-0 bg-gradient-to-t from-black/70 via-black/20 to-transparent" />
        <div className="absolute bottom-4 left-4 right-4 sm:bottom-5 sm:left-5">
          <h1 className="font-display text-2xl md:text-3xl font-bold text-white">
            {restaurant.name}
          </h1>
          <div className="flex items-center gap-3 mt-1.5 text-sm text-white/75">
            {restaurant.rating && (
              <span className="flex items-center gap-1">
                <Star className="h-4 w-4 fill-gold-accent text-gold-accent" />
                {Number(restaurant.rating).toFixed(1)}
              </span>
            )}
            <span>{getTipoLabel(restaurant.tipo)}</span>
            {restaurant.price_range && (
              <span className="font-medium">{PRICE_LABELS[restaurant.price_range]}</span>
            )}
          </div>
        </div>
      </div>

      <div className={`grid gap-6 ${restaurant.fila_ativa ? 'md:grid-cols-3' : ''}`}>
        <div className={`space-y-5 ${restaurant.fila_ativa ? 'md:col-span-2' : ''}`}>

          {/* Info */}
          <div className="rounded-2xl bg-card p-5 shadow-card">
            <p className="text-foreground leading-relaxed">
              {restaurant.description || `Restaurante especializado em ${getTipoLabel(restaurant.tipo)}.`}
            </p>
            <div className="mt-4 space-y-2 text-sm text-muted-foreground">
              {endereco && (
                <span className="flex items-center gap-2">
                  <MapPin className="h-4 w-4 shrink-0" />
                  {endereco}
                </span>
              )}
              <div className="flex flex-wrap gap-x-6 gap-y-2">
                {restaurant.horario_abertura && restaurant.horario_fechamento && (
                  <span className="flex items-center gap-2">
                    <Clock className="h-4 w-4 shrink-0" />
                    {restaurant.horario_abertura.slice(0, 5)} – {restaurant.horario_fechamento.slice(0, 5)}
                  </span>
                )}
                {restaurant.telefone && (
                  <span className="flex items-center gap-2">
                    <Phone className="h-4 w-4 shrink-0" />
                    {restaurant.telefone}
                  </span>
                )}
              </div>
            </div>
          </div>

          {/* Equipe */}
          {staffList.length > 0 && (
            <div className="rounded-2xl bg-card p-5 shadow-card space-y-3">
              <h2 className="font-display text-lg font-semibold text-foreground">Nossa equipe</h2>
              <div className="space-y-2.5">
                {staffList.map((s, i) => (
                  <div key={i} className="flex items-center gap-3">
                    <div className="h-9 w-9 shrink-0 rounded-full bg-primary/10 flex items-center justify-center">
                      <span className="text-sm font-bold text-primary">
                        {s.name.charAt(0).toUpperCase()}
                      </span>
                    </div>
                    <div className="min-w-0">
                      <p className="font-medium text-foreground text-sm leading-tight">{s.name}</p>
                      <p className="text-xs text-muted-foreground">
                        {s.cargo}
                        {s.horario && <span className="ml-1.5">· {s.horario}</span>}
                      </p>
                    </div>
                  </div>
                ))}
              </div>
            </div>
          )}

          {/* Reservas */}
          {!!restaurant.reservations_enabled && (
            <div className="rounded-2xl bg-card p-5 shadow-card space-y-4">
              <h2 className="font-display text-lg font-semibold text-foreground">
                Fazer uma reserva
              </h2>

              {/* Seletor de data e horário */}
              <div className="grid gap-4 sm:grid-cols-2">
                <div>
                  <Label className="text-xs text-muted-foreground">Data</Label>
                  <Input
                    type="date"
                    min={hojeEmBRT()}
                    value={selectedDate}
                    onChange={e => { setSelectedDate(e.target.value); setSelectedTime(''); }}
                    className="mt-1"
                  />
                </div>
                <div>
                  <Label className="text-xs text-muted-foreground">Horário</Label>
                  <Input
                    type="time"
                    value={selectedTime}
                    onChange={e => setSelectedTime(e.target.value)}
                    className={`mt-1 ${horarioForaDoFuncionamento || horarioNoPassado ? 'border-destructive focus-visible:ring-destructive' : ''}`}
                  />
                  {horarioNoPassado && (
                    <p className="mt-1 text-xs text-destructive">
                      Este horário já passou. Escolha um horário futuro.
                    </p>
                  )}
                  {horarioForaDoFuncionamento && !horarioNoPassado && (
                    <p className="mt-1 text-xs text-destructive">
                      Fora do horário de funcionamento ({restaurant.horario_abertura?.slice(0, 5)} – {restaurant.horario_fechamento?.slice(0, 5)}).
                    </p>
                  )}
                </div>
              </div>

              {/* Filtro de pessoas — só aparece após selecionar horário */}
              {selectedTime && (
                <div>
                  <Label className="text-xs text-muted-foreground">Quantas pessoas?</Label>
                  <Input
                    type="number"
                    min="1"
                    max="20"
                    value={partySize}
                    onChange={e => { setPartySize(e.target.value); setSelectedMesa(null); }}
                    className="w-24"
                  />
                </div>
              )}

              {/* Lista de mesas */}
              {selectedTime && (
                loadingMesas ? (
                  <p className="text-sm text-muted-foreground">Buscando mesas disponíveis...</p>
                ) : mesasError ? (
                  <p className="text-sm text-destructive">{mesasError}</p>
                ) : mesas.length === 0 && !horarioNoPassado && !horarioForaDoFuncionamento ? (
                  <div className="space-y-1">
                    <p className="text-sm text-muted-foreground">
                      Nenhuma mesa disponível neste horário.
                    </p>
                    {!!restaurant.fila_ativa && (
                      <p className="text-xs text-muted-foreground">
                        Use o card de fila de espera ao lado para garantir seu lugar.
                      </p>
                    )}
                  </div>
                ) : mesasFiltradas.length === 0 ? (
                  <p className="text-sm text-muted-foreground">
                    Nenhuma mesa disponível para {partySizeNum} pessoas. Tente um número menor.
                  </p>
                ) : (
                  <>
                    <p className="text-xs text-muted-foreground">Selecione uma mesa para reservar:</p>
                    <div className="grid gap-2 sm:grid-cols-2">
                      {mesasFiltradas.map(mesa => (
                        <button
                          key={mesa.id}
                          onClick={() => setSelectedMesa(mesa)}
                          className={`rounded-xl border p-3 text-left transition-all duration-200 min-h-[56px] ${
                            selectedMesa?.id === mesa.id
                              ? 'bg-primary/10 border-primary ring-2 ring-primary/20'
                              : 'bg-card border-border hover:border-primary/40 hover:bg-primary/[0.03]'
                          }`}
                        >
                          <div className="flex items-center justify-between">
                            <span className="font-semibold text-foreground">Mesa {mesa.numero}</span>
                            <StatusBadge status={mesa.status} />
                          </div>
                          <span className="flex items-center gap-1 mt-1 text-sm text-muted-foreground">
                            <Users className="h-3.5 w-3.5" />
                            {mesa.capacidade} lugares
                          </span>
                        </button>
                      ))}
                    </div>
                  </>
                )
              )}

              <div className="pt-3 border-t border-border/60">
                <Button
                  onClick={handleReserve}
                  disabled={!selectedMesa || !horarioReserva || horarioForaDoFuncionamento || horarioNoPassado}
                  size="lg"
                  className="w-full min-h-[48px]"
                >
                  {selectedMesa
                    ? `Reservar Mesa ${selectedMesa.numero} • ${new Date(`${selectedDate}T${selectedTime}:00`).toLocaleDateString('pt-BR', { day: '2-digit', month: '2-digit' })} às ${selectedTime}`
                    : 'Selecione data, horário e mesa'}
                </Button>
              </div>
            </div>
          )}
        </div>

        {/* Sidebar — só exibe quando a fila está ativa */}
        {!!restaurant.fila_ativa && (
          <div className="space-y-4">
            <div className="rounded-2xl bg-card p-5 shadow-card space-y-4">
              <div className="flex items-center gap-2">
                <ListOrdered className="h-4 w-4 text-primary" />
                <span className="text-sm font-semibold text-foreground">Fila de espera</span>
                <StatusBadge status="waiting" />
              </div>
              <p className="text-xs text-muted-foreground">
                {restaurant.tamanho_fila_atual ?? 0} {restaurant.tamanho_fila_atual === 1 ? 'pessoa' : 'pessoas'} aguardando
                {restaurant.averageWaitTime ? ` · ~${restaurant.averageWaitTime} min` : ''}
              </p>
              {jaEmFilaNesteRestaurante ? (
                <p className="text-xs text-primary font-medium">
                  Você já está na fila.{' '}
                  <button className="underline" onClick={() => navigate('/app/queue')}>
                    Ver posição
                  </button>
                </p>
              ) : !horarioReserva ? (
                <p className="text-xs text-muted-foreground italic">
                  Selecione data e horário para entrar na fila.
                </p>
              ) : horarioNoPassado || horarioForaDoFuncionamento ? (
                <p className="text-xs text-muted-foreground italic">
                  Selecione um horário válido para entrar na fila.
                </p>
              ) : mesas.length > 0 && !loadingMesas ? (
                <p className="text-xs text-muted-foreground italic">
                  Há mesas disponíveis — faça uma reserva acima.
                </p>
              ) : (
                <Button
                  onClick={handleQueue}
                  disabled={joiningQueue || loadingMesas}
                  className="w-full min-h-[40px]"
                  size="sm"
                >
                  {joiningQueue ? 'Entrando...' : 'Entrar na fila'}
                </Button>
              )}
            </div>
          </div>
        )}
      </div>
    </div>
  );
};

export default RestaurantDetail;