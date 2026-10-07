import React, { useState, useEffect, useCallback } from 'react';
import { Button } from '@/components/ui/button';
import { useAuth } from '@/contexts/AuthContext';
import { useRealtime } from '@/hooks/useRealtime';
import { Input } from '@/components/ui/input';
import { Label } from '@/components/ui/label';
import StatusBadge from '@/components/StatusBadge';
import { Skeleton } from '@/components/ui/skeleton';
import { Dialog, DialogContent, DialogHeader, DialogTitle, DialogFooter } from '@/components/ui/dialog';
import { Mesa, MesaStatus } from '@/types';
import { mesasService } from '@/services/mesas.service';
import { Plus, Pencil, Lock, Unlock, Trash2, Sparkles } from 'lucide-react';
import { toast } from 'sonner';

const Tables: React.FC = () => {
  const { user } = useAuth();
  const [tables, setTables] = useState<Mesa[]>([]);
  const [loading, setLoading] = useState(true);
  const [saving, setSaving] = useState(false);
  const [dialogOpen, setDialogOpen] = useState(false);
  const [editTable, setEditTable] = useState<Mesa | null>(null);
  const [formNumber, setFormNumber] = useState('');
  const [formCapacity, setFormCapacity] = useState('4');

  const fetchTables = useCallback(async (silent = false) => {
    try {
      const data = await mesasService.list();
      setTables(data);
    } catch {
      if (!silent) toast.error('Erro ao carregar mesas.');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { fetchTables(); }, [fetchTables]);

  // Mapa de mesas ao vivo: check-in ocupa, liberação devolve para livre.
  const recarregar = useCallback(() => { fetchTables(true); }, [fetchTables]);

  useRealtime(
    user?.id ? `restaurante.${user.id}` : undefined,
    { 'operacao.atualizada': recarregar },
    recarregar
  );

  const openNew = () => { setEditTable(null); setFormNumber(''); setFormCapacity('4'); setDialogOpen(true); };
  const openEdit = (t: Mesa) => { setEditTable(t); setFormNumber(String(t.numero)); setFormCapacity(String(t.capacidade)); setDialogOpen(true); };

  const handleSave = async () => {
    setSaving(true);
    try {
      if (editTable) {
        const updated = await mesasService.update(editTable.id, {
          numero: Number(formNumber),
          capacidade: Number(formCapacity),
        });
        setTables(prev => prev.map(t => t.id === editTable.id ? updated : t));
        toast.success('Mesa atualizada!');
      } else {
        const created = await mesasService.create({
          numero: Number(formNumber),
          capacidade: Number(formCapacity),
        });
        setTables(prev => [...prev, created]);
        toast.success('Mesa criada!');
      }
      setDialogOpen(false);
    } catch (err) {
      toast.error(err?.message || 'Erro ao salvar mesa.');
    } finally {
      setSaving(false);
    }
  };

  const toggleStatus = async (t: Mesa) => {
    const next = t.status === 'bloqueada' ? 'livre' : 'bloqueada';
    try {
      const updated = await mesasService.update(t.id, { status: next });
      setTables(prev => prev.map(tb => tb.id === t.id ? updated : tb));
      toast.success(`Mesa ${t.numero} ${next === 'bloqueada' ? 'bloqueada' : 'liberada'}.`);
    } catch (err) {
      toast.error(err?.message || 'Erro ao atualizar status.');
    }
  };

  /**
   * Transicoes do ciclo de limpeza (#171). Mesmo padrao do toggleStatus: aplica a
   * resposta do servidor, que e quem sabe o estado final — o backend valida a
   * transicao e pode recusar.
   */
  const avancarLimpeza = async (t: Mesa) => {
    const next: MesaStatus = t.status === 'aguardando_limpeza' ? 'em_limpeza' : 'livre';
    try {
      const updated = await mesasService.update(t.id, { status: next });
      setTables(prev => prev.map(tb => tb.id === t.id ? updated : tb));
      toast.success(
        next === 'em_limpeza'
          ? `Limpeza da mesa ${t.numero} iniciada.`
          : `Mesa ${t.numero} pronta.`
      );
    } catch (err) {
      toast.error(err?.message || 'Erro ao atualizar limpeza.');
    }
  };

  const handleDelete = async (t: Mesa) => {
    try {
      await mesasService.remove(t.id);
      setTables(prev => prev.filter(tb => tb.id !== t.id));
      toast.success(`Mesa ${t.numero} removida.`);
    } catch (err) {
      toast.error(err?.message || 'Erro ao remover mesa.');
    }
  };

  if (loading) return (
    <div className="space-y-4">
      <Skeleton className="h-8 w-48" />
      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {[1,2,3].map(i => <Skeleton key={i} className="h-24 rounded-2xl" />)}
      </div>
    </div>
  );

  return (
    <div className="space-y-6 animate-fade-in">
      <div className="flex items-center justify-between">
        <h1 className="font-display text-2xl font-bold text-foreground">Mesas</h1>
        <Button size="sm" onClick={openNew} className="min-h-[36px]">
          <Plus className="mr-1.5 h-4 w-4" />Nova mesa
        </Button>
      </div>

      {tables.length === 0 && (
        <p className="text-muted-foreground text-center py-10">
          Nenhuma mesa cadastrada. Clique em "Nova mesa" para começar.
        </p>
      )}

      <div className="grid gap-3 sm:grid-cols-2 lg:grid-cols-3 animate-stagger">
        {tables.map(t => (
          <div key={t.id} className="rounded-2xl bg-card p-4 shadow-card transition-all duration-200 hover:shadow-card-hover">
            <div className="flex items-start justify-between mb-3">
              <div>
                <p className="font-display font-semibold text-foreground">Mesa {t.numero}</p>
                <p className="text-sm text-muted-foreground">{t.capacidade} lugares</p>
              </div>
              <StatusBadge status={t.status} />
            </div>
            <div className="flex items-center gap-1.5 flex-wrap">
              <Button
                variant="ghost"
                size="sm"
                className="h-8 px-2.5 text-xs"
                onClick={() => openEdit(t)}
              >
                <Pencil className="h-3.5 w-3.5 mr-1" />
                Editar
              </Button>
              <Button
                variant="outline"
                size="sm"
                className="h-8 px-2.5 text-xs"
                onClick={() => toggleStatus(t)}
              >
                {t.status === 'bloqueada' ? (
                  <><Unlock className="h-3.5 w-3.5 mr-1" />Liberar</>
                ) : (
                  <><Lock className="h-3.5 w-3.5 mr-1" />Bloquear</>
                )}
              </Button>
              {(t.status === 'aguardando_limpeza' || t.status === 'em_limpeza') && (
                <Button
                  variant="default"
                  size="sm"
                  className="h-8 px-2.5 text-xs"
                  onClick={() => avancarLimpeza(t)}
                >
                  <Sparkles className="h-3.5 w-3.5 mr-1" />
                  {t.status === 'aguardando_limpeza' ? 'Iniciar limpeza' : 'Limpeza concluída'}
                </Button>
              )}
              <Button
                variant="destructive"
                size="sm"
                className="h-8 px-2.5 text-xs"
                onClick={() => handleDelete(t)}
              >
                <Trash2 className="h-3.5 w-3.5 mr-1" />
                Excluir
              </Button>
            </div>
          </div>
        ))}
      </div>

      <Dialog open={dialogOpen} onOpenChange={(open) => { if (!open) setDialogOpen(false); }}>
        <DialogContent
          className="bg-card rounded-2xl"
          onPointerDownOutside={e => e.preventDefault()}
          onEscapeKeyDown={e => e.preventDefault()}
        >
          <DialogHeader>
            <DialogTitle className="font-display">
              {editTable ? 'Editar mesa' : 'Nova mesa'}
            </DialogTitle>
          </DialogHeader>
          <div className="space-y-4 py-2">
            <div className="space-y-1.5">
              <Label>Número</Label>
              <Input type="number" value={formNumber} onChange={e => setFormNumber(e.target.value)} />
            </div>
            <div className="space-y-1.5">
              <Label>Capacidade</Label>
              <Input type="number" value={formCapacity} onChange={e => setFormCapacity(e.target.value)} />
            </div>
          </div>
          <DialogFooter className="gap-2">
            <Button variant="outline" onClick={() => setDialogOpen(false)}>Cancelar</Button>
            <Button onClick={handleSave} disabled={saving}>
              {saving ? 'Salvando...' : 'Salvar'}
            </Button>
          </DialogFooter>
        </DialogContent>
      </Dialog>
    </div>
  );
};

export default Tables;
