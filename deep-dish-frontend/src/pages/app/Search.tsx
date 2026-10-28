import React, { useState } from 'react';
import { useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import { Search as SearchIcon, ArrowLeft } from 'lucide-react';
import { TIPOS_RESTAURANTE } from '@/constants/tipos';

const ESTADOS = [
  'AC','AL','AP','AM','BA','CE','DF','ES','GO','MA',
  'MT','MS','MG','PA','PB','PR','PE','PI','RJ','RN',
  'RS','RO','RR','SC','SP','SE','TO',
];

const Search: React.FC = () => {
  const [q, setQ]           = useState('');
  const [cidade, setCidade] = useState('');
  const [estado, setEstado] = useState('');
  const [bairro, setBairro] = useState('');
  const [cep, setCep]       = useState('');
  const [tipo, setTipo]     = useState('');
  const navigate = useNavigate();

  const handleSearch = (e: React.FormEvent) => {
    e.preventDefault();
    const params = new URLSearchParams();
    if (q)      params.set('q',      q);
    if (cidade) params.set('cidade', cidade);
    if (estado) params.set('estado', estado);
    if (bairro) params.set('bairro', bairro);
    if (cep)    params.set('cep',    cep);
    if (tipo)   params.set('tipo',   tipo);
    navigate(`/app/restaurants?${params.toString()}`);
  };

  const handleCep = (value: string) => {
    const v = value.replace(/\D/g, '').slice(0, 8);
    setCep(v.length > 5 ? `${v.slice(0, 5)}-${v.slice(5)}` : v);
  };

  return (
    <div className="space-y-6 animate-fade-in">
      <button
        onClick={() => navigate('/app')}
        className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[44px]"
      >
        <ArrowLeft className="h-4 w-4" />
        Voltar
      </button>
      <h1 className="font-display text-2xl font-bold text-foreground">Buscar restaurantes</h1>
      <form onSubmit={handleSearch} className="space-y-5 rounded-2xl bg-card p-6 shadow-card">

        <div className="space-y-1.5">
          <Label>Busca livre</Label>
          <Input
            placeholder="Nome, bairro, cidade…"
            value={q}
            onChange={e => setQ(e.target.value)}
          />
        </div>

        <div className="grid gap-4 sm:grid-cols-2">
          <div className="space-y-1.5">
            <Label>Cidade</Label>
            <Input
              placeholder="Ex: São Paulo"
              value={cidade}
              onChange={e => setCidade(e.target.value)}
            />
          </div>

          <div className="space-y-1.5">
            <Label>Estado</Label>
            <select
              value={estado}
              onChange={e => setEstado(e.target.value)}
              className="flex h-10 w-full rounded-md border border-input bg-background px-3 py-2 text-sm ring-offset-background focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring focus-visible:ring-offset-2 transition-colors"
            >
              <option value="">Selecione</option>
              {ESTADOS.map(uf => (
                <option key={uf} value={uf}>{uf}</option>
              ))}
            </select>
          </div>

          <div className="space-y-1.5">
            <Label>Bairro</Label>
            <Input
              placeholder="Ex: Vila Madalena"
              value={bairro}
              onChange={e => setBairro(e.target.value)}
            />
          </div>

          <div className="space-y-1.5">
            <Label>CEP</Label>
            <Input
              placeholder="00000-000"
              inputMode="numeric"
              value={cep}
              onChange={e => handleCep(e.target.value)}
            />
          </div>
        </div>

        <div className="space-y-2">
          <Label>Tipo do Restaurante</Label>
          <div className="flex flex-wrap gap-2">
            {TIPOS_RESTAURANTE.map(t => (
              <button
                key={t.value}
                type="button"
                onClick={() => setTipo(tipo === t.value ? '' : t.value)}
                className={`rounded-full px-3.5 py-1.5 text-xs font-medium transition-all duration-200 min-h-[40px] ${
                  tipo === t.value
                    ? 'bg-primary text-primary-foreground shadow-sm'
                    : 'bg-secondary text-secondary-foreground hover:bg-secondary/80'
                }`}
              >
                {t.label}
              </button>
            ))}
          </div>
        </div>

        <Button type="submit" className="w-full sm:w-auto min-h-[44px]">
          <SearchIcon className="mr-2 h-4 w-4" />
          Buscar
        </Button>
      </form>
    </div>
  );
};

export default Search;
