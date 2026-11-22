import { cn } from '@/lib/utils';

type StatusType =
  | 'waiting' | 'called' | 'seated' | 'cancelled' | 'no_show'
  | 'pending' | 'confirmed' | 'completed'
  | 'available' | 'occupied' | 'reserved' | 'inactive'
  | 'livre' | 'reservada' | 'ocupada' | 'bloqueada' | 'aguardando_limpeza' | 'em_limpeza'
  | 'confirmada' | 'em_andamento' | 'cancelada' | 'liberada' | 'expirada';

const statusConfig: Record<StatusType, { label: string; className: string; dot?: string }> = {
  waiting:   { label: 'Aguardando', className: 'bg-secondary text-secondary-foreground', dot: 'bg-amber-500' },
  called:    { label: 'Chamado', className: 'bg-primary/10 text-primary', dot: 'bg-primary animate-pulse-soft' },
  seated:    { label: 'Sentado', className: 'bg-accent/10 text-accent', dot: 'bg-accent' },
  cancelled: { label: 'Cancelado', className: 'bg-muted text-muted-foreground line-through' },
  no_show:   { label: 'Não compareceu', className: 'bg-muted text-muted-foreground' },
  pending:   { label: 'Pendente', className: 'bg-secondary text-secondary-foreground', dot: 'bg-amber-500' },
  confirmed: { label: 'Confirmado', className: 'bg-accent/10 text-accent', dot: 'bg-accent' },
  completed: { label: 'Concluído', className: 'bg-muted text-muted-foreground' },
  available: { label: 'Disponível', className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', dot: 'bg-emerald-500' },
  occupied:  { label: 'Ocupada', className: 'bg-primary/10 text-primary', dot: 'bg-primary' },
  reserved:  { label: 'Reservada', className: 'bg-amber-500/10 text-amber-700 dark:text-amber-400', dot: 'bg-amber-500' },
  inactive:  { label: 'Inativa', className: 'bg-muted text-muted-foreground' },
  livre:     { label: 'Livre', className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', dot: 'bg-emerald-500' },
  reservada: { label: 'Reservada', className: 'bg-amber-500/10 text-amber-700 dark:text-amber-400', dot: 'bg-amber-500' },
  ocupada:   { label: 'Ocupada', className: 'bg-primary/10 text-primary', dot: 'bg-primary' },
  bloqueada: { label: 'Bloqueada', className: 'bg-muted text-muted-foreground' },
  // Aguardando e espera parada; em limpeza esta acontecendo agora, por isso o pulse,
  // como em 'called' e 'em_andamento'.
  aguardando_limpeza: { label: 'Aguardando limpeza', className: 'bg-orange-500/10 text-orange-700 dark:text-orange-400', dot: 'bg-orange-500' },
  em_limpeza: { label: 'Em limpeza', className: 'bg-sky-500/10 text-sky-700 dark:text-sky-400', dot: 'bg-sky-500 animate-pulse-soft' },
  confirmada:    { label: 'Aguardando check-in', className: 'bg-amber-500/10 text-amber-700 dark:text-amber-400', dot: 'bg-amber-500' },
  em_andamento:  { label: 'Em andamento', className: 'bg-emerald-500/10 text-emerald-700 dark:text-emerald-400', dot: 'bg-emerald-500 animate-pulse-soft' },
  cancelada:     { label: 'Cancelada', className: 'bg-muted text-muted-foreground line-through' },
  liberada:      { label: 'Liberada', className: 'bg-muted text-muted-foreground' },
  expirada:      { label: 'Expirada', className: 'bg-muted text-muted-foreground line-through' },
};

const StatusBadge = ({ status }: { status: StatusType }) => {
  const config = statusConfig[status] || { label: status, className: '' };
  return (
    <span className={cn(
      'inline-flex items-center gap-1.5 rounded-full px-2.5 py-0.5 text-xs font-medium',
      config.className
    )}>
      {config.dot && (
        <span className={cn('h-1.5 w-1.5 rounded-full shrink-0', config.dot)} />
      )}
      {config.label}
    </span>
  );
};

export default StatusBadge;
