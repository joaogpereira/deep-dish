import { useRef, useEffect, useState } from 'react';
import { Link, useNavigate } from 'react-router-dom';
import { Capacitor } from '@capacitor/core';
import { useForm } from 'react-hook-form';
import { zodResolver } from '@hookform/resolvers/zod';
import { z } from 'zod';
import { Eye, EyeOff, Loader2 } from 'lucide-react';
import { motion } from 'framer-motion';
import { useAuth } from '@/contexts/AuthContext';
import { Button } from '@/components/ui/button';
import { Input } from '@/components/ui/input';
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form';

const onlyDigits = (value: string) => value.replace(/\D/g, '');

const formatCpf = (digits: string) => {
  const d = onlyDigits(digits).slice(0, 11);
  if (d.length <= 3) return d;
  if (d.length <= 6) return `${d.slice(0, 3)}.${d.slice(3)}`;
  if (d.length <= 9) return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6)}`;
  return `${d.slice(0, 3)}.${d.slice(3, 6)}.${d.slice(6, 9)}-${d.slice(9)}`;
};

const registerSchema = z
  .object({
    name: z.string().min(2, 'Informe seu nome completo'),
    email: z.string().email('Informe um e-mail válido'),
    cpf: z.string().refine((v) => onlyDigits(v).length === 11, 'CPF inválido'),
    password: z.string().min(8, 'A senha deve ter pelo menos 8 caracteres'),
    confirmPassword: z.string().min(1, 'Confirme sua senha'),
  })
  .refine((d) => d.password === d.confirmPassword, {
    message: 'As senhas não coincidem.',
    path: ['confirmPassword'],
  });

type RegisterFormValues = z.infer<typeof registerSchema>;

const Register: React.FC = () => {
  const [showPassword, setShowPassword] = useState(false);
  const [showConfirm, setShowConfirm] = useState(false);
  const [serverError, setServerError] = useState('');
  const errorRef = useRef<HTMLDivElement>(null);
  const { register } = useAuth();
  const navigate = useNavigate();

  const form = useForm<RegisterFormValues>({
    resolver: zodResolver(registerSchema),
    defaultValues: { name: '', email: '', cpf: '', password: '', confirmPassword: '' },
  });

  const { isSubmitting } = form.formState;

  useEffect(() => {
    if (serverError && errorRef.current) {
      errorRef.current.focus();
    }
  }, [serverError]);

  const onSubmit = async (values: RegisterFormValues) => {
    setServerError('');
    try {
      await register(values.name, values.email, onlyDigits(values.cpf), values.password);
      navigate('/verify-email?tipo=cliente');
    } catch (err) {
      setServerError(err instanceof Error ? err.message : 'Erro ao criar conta. Tente novamente.');
    }
  };

  return (
    <div className="flex min-h-screen">
      {/* Painel esquerdo — pitch de produto */}
      <motion.div
        className="hidden lg:flex lg:w-5/12 xl:w-1/2 flex-col justify-between bg-sidebar p-12 relative overflow-hidden"
        initial={{ opacity: 0, x: -24 }}
        animate={{ opacity: 1, x: 0 }}
        transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
      >
        {/* Textura de fundo */}
        <div
          className="absolute inset-0 opacity-[0.04]"
          style={{
            backgroundImage: `url("data:image/svg+xml,%3Csvg width='60' height='60' viewBox='0 0 60 60' xmlns='http://www.w3.org/2000/svg'%3E%3Cg fill='none' fill-rule='evenodd'%3E%3Cg fill='%23ffffff' fill-opacity='1'%3E%3Cpath d='M36 34v-4h-2v4h-4v2h4v4h2v-4h4v-2h-4zm0-30V0h-2v4h-4v2h4v4h2V6h4V4h-4zM6 34v-4H4v4H0v2h4v4h2v-4h4v-2H6zM6 4V0H4v4H0v2h4v4h2V6h4V4H6z'/%3E%3C/g%3E%3C/g%3E%3C/svg%3E")`,
          }}
          aria-hidden="true"
        />

        {/* Círculos decorativos */}
        <div className="absolute -bottom-32 -left-32 w-96 h-96 rounded-full bg-primary/25 blur-3xl" aria-hidden="true" />
        <div className="absolute top-1/4 -right-16 w-64 h-64 rounded-full bg-primary/10 blur-2xl" aria-hidden="true" />

        {/* Logo */}
        <div className="relative z-10">
          <span className="font-display text-4xl font-bold text-sidebar-foreground">Deep Dish</span>
        </div>

        {/* Conteúdo central */}
        <div className="relative z-10 space-y-6">
          <motion.div
            initial={{ opacity: 0, y: 20 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.2, duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
          >
            <p className="text-5xl font-display font-bold leading-tight text-sidebar-foreground text-balance">
              Sua próxima<br />
              <span className="text-primary">reserva começa</span><br />
              aqui.
            </p>
            <p className="mt-4 text-base text-sidebar-foreground/70 leading-relaxed max-w-xs">
              Crie sua conta e reserve mesas nos melhores restaurantes com poucos cliques.
            </p>
          </motion.div>

          {/* Destaques */}
          <motion.div
            className="space-y-3"
            initial={{ opacity: 0, y: 16 }}
            animate={{ opacity: 1, y: 0 }}
            transition={{ delay: 0.35, duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
          >
            {['Reserve em segundos', 'Acompanhe sua posição na fila', 'Histórico de visitas'].map((item) => (
              <div key={item} className="flex items-center gap-2.5 text-sm text-sidebar-foreground/80">
                <div className="h-1.5 w-1.5 rounded-full bg-primary flex-shrink-0" aria-hidden="true" />
                {item}
              </div>
            ))}
          </motion.div>
        </div>

        {/* Rodapé do painel */}
        <div className="relative z-10">
          <p className="text-xs text-sidebar-foreground/40">© 2025 Deep Dish · UniCEUB PI3</p>
        </div>
      </motion.div>

      {/* Painel direito — formulário */}
      <div className="flex flex-1 items-center justify-center bg-background px-8 py-10 overflow-y-auto">
        <motion.div
          className="w-full max-w-md"
          initial={{ opacity: 0, y: 20 }}
          animate={{ opacity: 1, y: 0 }}
          transition={{ duration: 0.5, ease: [0.16, 1, 0.3, 1] }}
        >
          {/* Logo mobile */}
          <div className="mb-6 lg:hidden">
            <span className="font-display text-3xl font-bold text-foreground">Deep Dish</span>
          </div>

          {/* Header */}
          <div className="mb-6">
            <h1 className="font-display text-4xl font-bold text-foreground text-balance leading-tight">
              Crie sua conta
            </h1>
            <p className="mt-2 text-sm text-muted-foreground">
              Preencha os dados abaixo para começar
            </p>
          </div>

          {/* Erro do servidor */}
          {serverError && (
            <motion.div
              ref={errorRef}
              className="mb-6 rounded-lg bg-destructive/10 px-3.5 py-3 text-sm text-destructive"
              initial={{ opacity: 0, y: -8 }}
              animate={{ opacity: 1, y: 0 }}
              transition={{ duration: 0.25 }}
              role="alert"
              tabIndex={-1}
            >
              {serverError}
            </motion.div>
          )}

          {/* Formulário */}
          <Form {...form}>
            <form onSubmit={form.handleSubmit(onSubmit)} className="space-y-4" noValidate>
              <FormField
                control={form.control}
                name="name"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Nome completo</FormLabel>
                    <FormControl>
                      <Input
                        type="text"
                        name="name"
                        placeholder="Seu nome"
                        autoComplete="name"
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="email"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>E-mail</FormLabel>
                    <FormControl>
                      <Input
                        type="email"
                        name="email"
                        placeholder="seu@email.com"
                        autoComplete="email"
                        spellCheck={false}
                        {...field}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="cpf"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>CPF</FormLabel>
                    <FormControl>
                      <Input
                        type="text"
                        name="cpf"
                        placeholder="000.000.000-00"
                        inputMode="numeric"
                        autoComplete="off"
                        spellCheck={false}
                        value={field.value}
                        onChange={(e) => {
                          field.onChange(formatCpf(e.target.value));
                        }}
                        onBlur={field.onBlur}
                      />
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="password"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Senha</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Input
                          type={showPassword ? 'text' : 'password'}
                          name="password"
                          placeholder="••••••••"
                          autoComplete="new-password"
                          className="pr-10"
                          {...field}
                        />
                        <button
                          type="button"
                          onClick={() => setShowPassword((v) => !v)}
                          className="absolute right-0 top-0 flex h-full w-10 items-center justify-center text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
                          aria-label={showPassword ? 'Ocultar senha' : 'Mostrar senha'}
                        >
                          {showPassword
                            ? <EyeOff className="h-4 w-4" aria-hidden="true" />
                            : <Eye className="h-4 w-4" aria-hidden="true" />
                          }
                        </button>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <FormField
                control={form.control}
                name="confirmPassword"
                render={({ field }) => (
                  <FormItem>
                    <FormLabel>Confirmar senha</FormLabel>
                    <FormControl>
                      <div className="relative">
                        <Input
                          type={showConfirm ? 'text' : 'password'}
                          name="confirmPassword"
                          placeholder="••••••••"
                          autoComplete="new-password"
                          className="pr-10"
                          {...field}
                        />
                        <button
                          type="button"
                          onClick={() => setShowConfirm((v) => !v)}
                          className="absolute right-0 top-0 flex h-full w-10 items-center justify-center text-muted-foreground hover:text-foreground transition-colors focus-visible:outline-none focus-visible:ring-2 focus-visible:ring-ring rounded"
                          aria-label={showConfirm ? 'Ocultar confirmação' : 'Mostrar confirmação'}
                        >
                          {showConfirm
                            ? <EyeOff className="h-4 w-4" aria-hidden="true" />
                            : <Eye className="h-4 w-4" aria-hidden="true" />
                          }
                        </button>
                      </div>
                    </FormControl>
                    <FormMessage />
                  </FormItem>
                )}
              />

              <Button
                type="submit"
                className="w-full min-h-[44px]"
                disabled={isSubmitting}
                aria-busy={isSubmitting}
              >
                {isSubmitting ? (
                  <>
                    <Loader2 className="mr-2 h-4 w-4 animate-spin" aria-hidden="true" />
                    Criando conta…
                  </>
                ) : (
                  'Criar conta'
                )}
              </Button>
            </form>
          </Form>

          {/* Divisor */}
          <div className="mt-5 flex items-center gap-3">
            <div className="h-px flex-1 bg-border" />
            <span className="text-xs text-muted-foreground">ou continue com</span>
            <div className="h-px flex-1 bg-border" />
          </div>

          {/* Botão Google */}
          <Button
            type="button"
            variant="outline"
            className="mt-3 w-full min-h-[44px] gap-2.5"
            onClick={() => {/* TODO: implementar OAuth Google */}}
          >
            <svg width="18" height="18" viewBox="0 0 18 18" aria-hidden="true">
              <path d="M17.64 9.205c0-.639-.057-1.252-.164-1.841H9v3.481h4.844a4.14 4.14 0 0 1-1.796 2.716v2.259h2.908c1.702-1.567 2.684-3.875 2.684-6.615Z" fill="#4285F4" />
              <path d="M9 18c2.43 0 4.467-.806 5.956-2.18l-2.908-2.259c-.806.54-1.837.86-3.048.86-2.344 0-4.328-1.584-5.036-3.711H.957v2.332A8.997 8.997 0 0 0 9 18Z" fill="#34A853" />
              <path d="M3.964 10.71A5.41 5.41 0 0 1 3.682 9c0-.593.102-1.17.282-1.71V4.958H.957A8.996 8.996 0 0 0 0 9c0 1.452.348 2.827.957 4.042l3.007-2.332Z" fill="#FBBC05" />
              <path d="M9 3.58c1.321 0 2.508.454 3.44 1.345l2.582-2.58C13.463.891 11.426 0 9 0A8.997 8.997 0 0 0 .957 4.958L3.964 7.29C4.672 5.163 6.656 3.58 9 3.58Z" fill="#EA4335" />
            </svg>
            Continuar com Google
          </Button>

          {/* Links de navegação */}
          <div className="mt-5 space-y-2 text-center text-sm text-muted-foreground">
            <p>
              Já tem conta?{' '}
              <Link to="/login" className="font-medium text-primary hover:text-primary/80 transition-colors">
                Entrar
              </Link>
            </p>
            {/* O app nativo é só do cliente; o restaurante usa o painel web. */}
            {!Capacitor.isNativePlatform() && (
              <p>
                É restaurante?{' '}
                <Link to="/restaurant/register" className="font-medium text-primary hover:text-primary/80 transition-colors">
                  Cadastrar restaurante
                </Link>
              </p>
            )}
          </div>
        </motion.div>
      </div>
    </div>
  );
};

export default Register;
