import React, { useState, useEffect } from 'react';
import { Link, useSearchParams, useNavigate } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { httpClient } from '@/services/httpClient';
import { toast } from 'sonner';
import { MailCheck, MailX, Loader2 } from 'lucide-react';

const BASE = import.meta.env.VITE_API_URL as string;

const VerifyEmail: React.FC = () => {
  const [params] = useSearchParams();
  const navigate  = useNavigate();
  const verified  = params.get('verified') === '1';
  const error     = params.get('error') === 'invalid';
  const tipo      = params.get('tipo') ?? 'cliente';

  const [resending, setResending] = useState(false);
  const [resent, setResent]       = useState(false);
  const [checking, setChecking]   = useState(!verified && !error);

  const dashboardHref = tipo === 'restaurante' ? '/restaurant/dashboard' : '/app';
  const loginHref = tipo === 'restaurante' ? '/restaurant/login' : '/login';

  /**
   * Descarta a sessão não verificada e leva para o login.
   *
   * É local de propósito: o token pendente não passa pelas rotas autenticadas
   * (403 email_not_verified), então não há logout no servidor para chamar.
   */
  const usarOutraConta = () => {
    localStorage.removeItem('jwt');
    localStorage.removeItem('tipo_usuario');
    localStorage.removeItem('user');
    navigate(loginHref, { replace: true });
  };

  useEffect(() => {
    if (verified || error) return;

    const jwt = localStorage.getItem('jwt');
    if (!jwt) { setChecking(false); return; }

    const endpoint = tipo === 'restaurante' ? '/restaurante/me' : '/cliente/me';

    fetch(`${BASE}${endpoint}`, {
      headers: { Authorization: `Bearer ${jwt}`, 'Content-Type': 'application/json' },
    })
      .then(res => (res.ok ? res.json() : null))
      .then((data: { email_verified_at?: string | null } | null) => {
        if (data?.email_verified_at) navigate(dashboardHref, { replace: true });
      })
      .catch(() => {})
      .finally(() => setChecking(false));
  }, []);

  if (checking) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background">
        <Loader2 className="h-6 w-6 animate-spin text-muted-foreground" />
      </div>
    );
  }

  const handleResend = async () => {
    setResending(true);
    try {
      const endpoint = tipo === 'restaurante'
        ? '/restaurante/email/resend'
        : '/cliente/email/resend';
      await httpClient.post(endpoint, {});
      setResent(true);
      toast.success('E-mail reenviado! Verifique sua caixa de entrada.');
    } catch {
      toast.error('Erro ao reenviar e-mail. Tente novamente.');
    } finally {
      setResending(false);
    }
  };

  if (verified) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background px-4">
        <div className="w-full max-w-md space-y-6 rounded-2xl bg-card p-8 shadow-card animate-fade-in-up text-center">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-emerald-500/10">
            <MailCheck className="h-7 w-7 text-emerald-600 dark:text-emerald-400" />
          </div>
          <div className="space-y-1.5">
            <h1 className="font-display text-2xl font-bold text-foreground">E-mail verificado!</h1>
            <p className="text-sm text-muted-foreground">
              Sua conta está confirmada. Você já pode usar o Deep Dish normalmente.
            </p>
          </div>
          <Button asChild className="w-full min-h-[44px]">
            <Link to={dashboardHref}>Continuar</Link>
          </Button>
        </div>
      </div>
    );
  }

  if (error) {
    return (
      <div className="flex min-h-screen items-center justify-center bg-background px-4">
        <div className="w-full max-w-md space-y-6 rounded-2xl bg-card p-8 shadow-card animate-fade-in-up text-center">
          <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-destructive/10">
            <MailX className="h-7 w-7 text-destructive" />
          </div>
          <div className="space-y-1.5">
            <h1 className="font-display text-2xl font-bold text-foreground">Link inválido</h1>
            <p className="text-sm text-muted-foreground">
              O link de verificação é inválido ou expirou. Solicite um novo abaixo.
            </p>
          </div>
          <Button onClick={handleResend} disabled={resending || resent} className="w-full min-h-[44px]">
            {resending
              ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Reenviando...</>
              : resent ? 'E-mail reenviado!' : 'Reenviar e-mail de verificação'}
          </Button>
        </div>
      </div>
    );
  }

  return (
    <div className="flex min-h-screen items-center justify-center bg-background px-4">
      <div className="w-full max-w-md space-y-6 rounded-2xl bg-card p-8 shadow-card animate-fade-in-up text-center">
        <div className="mx-auto flex h-14 w-14 items-center justify-center rounded-full bg-primary/10">
          <MailCheck className="h-7 w-7 text-primary" />
        </div>
        <div className="space-y-1.5">
          <h1 className="font-display text-2xl font-bold text-foreground">Verifique seu e-mail</h1>
          <p className="text-sm text-muted-foreground">
            Enviamos um link de verificação para o seu e-mail. Clique no link para ativar sua conta.
          </p>
          <p className="text-xs text-muted-foreground">Não encontrou? Verifique a caixa de spam.</p>
        </div>

        <Button
          variant="outline"
          onClick={handleResend}
          disabled={resending || resent}
          className="w-full min-h-[44px]"
        >
          {resending
            ? <><Loader2 className="mr-2 h-4 w-4 animate-spin" />Reenviando...</>
            : resent ? 'E-mail reenviado!' : 'Reenviar e-mail'}
        </Button>

        {/* Saída da tela: sem isto, quem cadastrou e quer usar outra conta fica preso aqui. */}
        <button
          type="button"
          onClick={usarOutraConta}
          className="w-full text-sm text-muted-foreground underline-offset-4 hover:text-foreground hover:underline"
        >
          Entrar com outra conta
        </button>
      </div>
    </div>
  );
};

export default VerifyEmail;