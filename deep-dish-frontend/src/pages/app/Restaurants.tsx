import React, { useState, useEffect } from 'react';
import { useSearchParams, useNavigate } from 'react-router-dom';
import RestaurantCard from '@/components/RestaurantCard';
import EmptyState from '@/components/EmptyState';
import { Skeleton } from '@/components/ui/skeleton';
import { Restaurante } from '@/types';
import { Utensils, ChevronLeft } from 'lucide-react';
import { restaurantsService } from '@/services/restaurants.service';

const Restaurants: React.FC = () => {
  const [searchParams] = useSearchParams();
  const [restaurants, setRestaurants] = useState<Restaurante[]>([]);
  const [loading, setLoading] = useState(true);
  const navigate = useNavigate();

  useEffect(() => {
    let cancelled = false;
    const load = async () => {
      setLoading(true);
      try {
        const results = await restaurantsService.listRestaurants({
          q:      searchParams.get('q')      || undefined,
          cidade: searchParams.get('cidade') || undefined,
          estado: searchParams.get('estado') || undefined,
          bairro: searchParams.get('bairro') || undefined,
          cep:    searchParams.get('cep')    || undefined,
          tipo:   searchParams.get('tipo')   || undefined,
        });
        if (!cancelled) setRestaurants(results);
      } finally {
        if (!cancelled) setLoading(false);
      }
    };
    load();
    return () => { cancelled = true; };
  }, [searchParams]);

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex items-center gap-3">
        <button
          onClick={() => navigate(-1)}
          className="flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[44px]"
        >
          <ChevronLeft className="h-4 w-4" />
          Voltar
        </button>
        <h1 className="font-display text-2xl font-bold text-foreground">Restaurantes</h1>
      </div>

      {loading ? (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {[1, 2, 3].map(i => (
            <Skeleton key={i} className="h-72 rounded-2xl" />
          ))}
        </div>
      ) : restaurants.length === 0 ? (
        <EmptyState
          icon={<Utensils className="h-7 w-7" />}
          title="Nenhum restaurante encontrado"
          description="Tente outros filtros de busca."
        />
      ) : (
        <div className="grid gap-5 sm:grid-cols-2 lg:grid-cols-3">
          {restaurants.map(r => (
            <RestaurantCard key={r.id} restaurant={r} />
          ))}
        </div>
      )}
    </div>
  );
};

export default Restaurants;
