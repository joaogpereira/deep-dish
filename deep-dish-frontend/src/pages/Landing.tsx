import React, { useRef } from 'react';
import { Link } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import { ContainerScroll } from '@/components/ui/container-scroll-animation';
import { GlowCard } from '@/components/ui/spotlight-card';
import { SparklesCore } from '@/components/ui/sparkles';
import {
  Clock, Users, CalendarCheck, ChevronRight,
  Utensils, Store, Smartphone, ArrowRight, MapPin, Star,
} from 'lucide-react';
import { motion, useScroll, useTransform } from 'framer-motion';
import heroBg from '@/assets/hero-bg.jpg';
import logo from '@/assets/logo/logo-sem-fundo.png';

const fadeUp = {
  hidden: { opacity: 0, y: 20 },
  visible: { opacity: 1, y: 0 },
};

// Marquee items — repeated twice for seamless loop
const MARQUEE_ITEMS = [
  'Reserve já', 'Deep Dish', 'Sem espera', 'Mesa garantida',
  'Reserve já', 'Deep Dish', 'Sem espera', 'Mesa garantida',
];

const Landing: React.FC = () => {
  // Hero parallax
  const heroRef = useRef<HTMLElement>(null);
  const { scrollYProgress: heroProgress } = useScroll({
    target: heroRef,
    offset: ['start start', 'end start'],
  });
  const heroImageY = useTransform(heroProgress, [0, 1], ['0px', '120px']);

  // Page-level scroll progress bar
  const { scrollYProgress } = useScroll();

  return (
    <div className="bg-background">

      {/* ── Scroll progress bar ───────────────────────── */}
      <motion.div
        className="fixed top-0 left-0 right-0 h-[2px] bg-primary z-[60] origin-left"
        style={{ scaleX: scrollYProgress }}
      />

      {/* ── Hero ──────────────────────────────────────── */}
      <section ref={heroRef} className="relative min-h-screen flex items-end overflow-hidden">
        {/* Parallax image — slightly taller so it has room to move */}
        <motion.img
          src={heroBg}
          alt=""
          className="absolute left-0 w-full object-cover pointer-events-none"
          style={{ top: '-60px', height: 'calc(100% + 120px)', y: heroImageY }}
        />
        <div className="absolute inset-0 bg-gradient-to-r from-black/88 via-black/55 to-black/15" />
        <div className="absolute inset-0 bg-gradient-to-t from-black/60 via-transparent to-transparent" />

        <div className="relative z-10 container mx-auto px-4 pb-20 pt-32">
          <motion.div
            initial="hidden"
            animate="visible"
            variants={{ hidden: {}, visible: { transition: { staggerChildren: 0.13 } } }}
          >
            <motion.p
              className="text-white/55 text-xs font-semibold tracking-widest uppercase mb-6"
              variants={fadeUp}
              transition={{ duration: 0.55, ease: [0.16, 1, 0.3, 1] }}
            >
              Fila digital &amp; reservas em tempo real
            </motion.p>

            <motion.h1
              className="font-display font-extrabold text-white leading-[0.92] tracking-tight"
              style={{ fontSize: 'clamp(3.25rem, 10vw, 7.5rem)' }}
              variants={fadeUp}
              transition={{ duration: 0.7, ease: [0.16, 1, 0.3, 1] }}
            >
              Sua mesa,<br />
              <span className="text-primary">sem espera.</span>
            </motion.h1>

            <motion.p
              className="mt-8 text-white/65 max-w-xs text-base leading-relaxed"
              variants={fadeUp}
              transition={{ duration: 0.65, ease: [0.16, 1, 0.3, 1] }}
            >
              Nos melhores restaurantes. Sem pegar fila na porta,
              sem surpresas de horário.
            </motion.p>

            <motion.div
              className="mt-10 flex flex-wrap items-center gap-3"
              variants={fadeUp}
              transition={{ duration: 0.65, ease: [0.16, 1, 0.3, 1] }}
            >
              <Link to="/register">
                <Button size="lg" className="text-base px-8 min-h-[48px] shadow-lg shadow-primary/25">
                  Criar conta
                </Button>
              </Link>
              <Link to="/login">
                <Button
                  size="lg"
                  variant="ghost"
                  className="text-base px-6 min-h-[48px] text-white hover:text-white hover:bg-white/10 focus-visible:ring-white/40"
                >
                  Entrar
                  <ArrowRight className="ml-2 h-4 w-4" />
                </Button>
              </Link>
            </motion.div>
          </motion.div>
        </div>
      </section>

      {/* ── Stats band ────────────────────────────────── */}
      <div className="bg-foreground">
        <div className="container mx-auto px-4">
          <div className="grid grid-cols-3 divide-x divide-background/10">
            {[
              { value: '150+', label: 'restaurantes parceiros' },
              { value: '12k+', label: 'reservas realizadas' },
              { value: '4.9★', label: 'avaliação média' },
            ].map((stat) => (
              <div key={stat.label} className="px-4 py-6 text-center">
                <div className="font-display text-xl md:text-3xl font-extrabold text-primary">
                  {stat.value}
                </div>
                <div className="mt-1 text-[10px] md:text-xs text-background/45 uppercase tracking-wide leading-tight">
                  {stat.label}
                </div>
              </div>
            ))}
          </div>
        </div>
      </div>

      {/* ── Marquee strip ─────────────────────────────── */}
      <div className="py-5 bg-muted/30 overflow-hidden border-y border-border/40 select-none">
        {/* Row 1 — left */}
        <div className="flex whitespace-nowrap animate-marquee mb-3">
          {MARQUEE_ITEMS.concat(MARQUEE_ITEMS).map((item, i) => (
            <span key={i} className="inline-flex items-center gap-4 px-4">
              <span className="font-display font-semibold text-sm text-foreground/40 uppercase tracking-widest">
                {item}
              </span>
              <span className="h-1 w-1 rounded-full bg-primary shrink-0" />
            </span>
          ))}
        </div>
        {/* Row 2 — right (opposite direction) */}
        <div className="flex whitespace-nowrap animate-marquee-reverse">
          {MARQUEE_ITEMS.concat(MARQUEE_ITEMS).map((item, i) => (
            <span key={i} className="inline-flex items-center gap-4 px-4">
              <span className="font-display font-semibold text-sm text-foreground/25 uppercase tracking-widest">
                {item}
              </span>
              <span className="h-1 w-1 rounded-full bg-primary/40 shrink-0" />
            </span>
          ))}
        </div>
      </div>

      {/* ── Sobre ─────────────────────────────────────── */}
      <section className="py-24 bg-background">
        <div className="container mx-auto px-4">
          <div className="max-w-2xl">
            <motion.p
              className="text-xs font-semibold uppercase tracking-widest text-primary mb-4"
              initial={{ opacity: 0, y: 8 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4 }}
            >
              Sobre o projeto
            </motion.p>
            <motion.h2
              className="font-display text-3xl md:text-4xl font-bold text-foreground leading-tight"
              initial={{ opacity: 0, y: 16 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5 }}
            >
              Tecnologia e experiência<br className="hidden sm:block" />
              a serviço do restaurante.
            </motion.h2>
            <motion.p
              className="mt-6 text-muted-foreground leading-relaxed"
              style={{ maxWidth: '65ch' }}
              initial={{ opacity: 0, y: 12 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: 0.1 }}
            >
              O <strong className="text-foreground font-semibold">Deep Dish</strong> é um projeto integrador
              desenvolvido no <strong className="text-foreground font-semibold">UniCEUB</strong> como parte da
              disciplina <strong className="text-foreground font-semibold">PI3</strong>. A proposta: unir tecnologia
              e experiência do usuário para digitalizar filas e reservas em restaurantes de forma prática e moderna.
            </motion.p>
          </div>
        </div>
      </section>

      {/* ── Como funciona ─────────────────────────────── */}
      <section className="py-24 bg-muted/40">
        <div className="container mx-auto px-4">
          <motion.p
            className="text-xs font-semibold uppercase tracking-widest text-primary mb-3"
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.4 }}
          >
            Como funciona
          </motion.p>
          <motion.h2
            className="font-display text-3xl md:text-4xl font-bold text-foreground mb-14"
            initial={{ opacity: 0, y: 12 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            Três passos. Nenhuma fila.
          </motion.h2>

          <div className="divide-y divide-border/50">
            {[
              {
                num: '01',
                icon: <Smartphone className="h-5 w-5" />,
                title: 'Busque',
                desc: 'Encontre restaurantes próximos e veja filas e horários disponíveis em tempo real.',
              },
              {
                num: '02',
                icon: <CalendarCheck className="h-5 w-5" />,
                title: 'Reserve ou entre na fila',
                desc: 'Escolha o melhor horário ou entre na fila digital com um toque.',
              },
              {
                num: '03',
                icon: <Utensils className="h-5 w-5" />,
                title: 'Aproveite',
                desc: 'Acompanhe sua posição, receba alertas e chegue direto na mesa.',
              },
            ].map((step, i) => (
              <motion.div
                key={step.num}
                className="flex items-center gap-6 md:gap-14 py-7"
                initial={{ opacity: 0, x: -12 }}
                whileInView={{ opacity: 1, x: 0 }}
                viewport={{ once: true }}
                transition={{ duration: 0.45, delay: i * 0.08 }}
              >
                <span
                  className="font-display font-extrabold text-foreground/15 dark:text-border/60 shrink-0 tabular-nums select-none"
                  style={{ fontSize: 'clamp(2.5rem, 5vw, 4.5rem)', lineHeight: 1, width: '4rem', textAlign: 'right' }}
                >
                  {step.num}
                </span>
                <div className="flex items-center gap-4 flex-1">
                  <div className="flex h-10 w-10 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
                    {step.icon}
                  </div>
                  <div>
                    <h3 className="font-display font-semibold text-foreground text-lg">{step.title}</h3>
                    <p className="mt-1 text-sm text-muted-foreground leading-relaxed" style={{ maxWidth: '48ch' }}>
                      {step.desc}
                    </p>
                  </div>
                </div>
              </motion.div>
            ))}
          </div>
        </div>
      </section>

      {/* ── App showcase (ContainerScroll) ───────────── */}
      <ContainerScroll
        titleComponent={
          <div className="mb-4">
            <p className="text-xs font-semibold uppercase tracking-widest text-primary mb-4">
              O app
            </p>
            <h2
              className="font-display font-extrabold text-foreground leading-tight"
              style={{ fontSize: 'clamp(2rem, 5vw, 3.75rem)' }}
            >
              Simples de usar.<br />
              <span className="text-muted-foreground font-normal">Poderoso por dentro.</span>
            </h2>
          </div>
        }
      >
        {/* App mockup — reservation list screen */}
        <div className="flex h-full w-full bg-background">
          {/* Sidebar */}
          <div className="hidden md:flex w-56 shrink-0 flex-col border-r border-border/60 bg-card px-4 py-6 gap-1">
            <div className="flex items-center gap-2 px-2 mb-6">
              <div className="h-7 w-7 rounded-md bg-primary flex items-center justify-center">
                <Utensils className="h-3.5 w-3.5 text-white" />
              </div>
              <span className="font-display font-bold text-sm text-foreground">Deep Dish</span>
            </div>
            {['Início', 'Buscar', 'Minhas reservas', 'Fila'].map((item, i) => (
              <div
                key={item}
                className={`px-3 py-2 rounded-lg text-sm font-medium cursor-default ${
                  i === 0
                    ? 'bg-primary/10 text-primary'
                    : 'text-muted-foreground hover:text-foreground'
                }`}
              >
                {item}
              </div>
            ))}
          </div>

          {/* Main content */}
          <div className="flex-1 flex flex-col overflow-hidden">
            {/* Top bar */}
            <div className="flex items-center justify-between px-6 py-4 border-b border-border/60 bg-card/60 shrink-0">
              <div>
                <p className="text-xs text-muted-foreground">Boa tarde,</p>
                <p className="font-display font-semibold text-foreground text-sm">João Pedro</p>
              </div>
              <div className="h-8 w-8 rounded-full bg-primary/15 flex items-center justify-center text-primary text-xs font-bold">
                JP
              </div>
            </div>

            {/* Restaurant cards */}
            <div className="flex-1 overflow-y-auto px-6 py-5 space-y-3 bg-background">
              <p className="text-xs font-semibold uppercase tracking-widest text-muted-foreground mb-4">
                Próximos de você
              </p>
              {[
                { name: 'Fogo de Chão', type: 'Churrascaria', city: 'Brasília', rating: 4.8, open: true, queue: 7 },
                { name: 'Coco Bambu', type: 'Frutos do mar', city: 'Brasília', rating: 4.7, open: true, queue: 12 },
                { name: 'Outback Steakhouse', type: 'Americana', city: 'Brasília', rating: 4.5, open: false, queue: 0 },
              ].map((r) => (
                <div
                  key={r.name}
                  className="flex items-center gap-4 rounded-xl border border-border/60 bg-card p-4 shadow-sm"
                >
                  <div className="h-12 w-12 rounded-xl bg-primary/10 flex items-center justify-center shrink-0">
                    <span className="font-display font-extrabold text-primary text-lg">
                      {r.name.charAt(0)}
                    </span>
                  </div>
                  <div className="flex-1 min-w-0">
                    <div className="flex items-center gap-2">
                      <p className="font-display font-semibold text-foreground text-sm truncate">{r.name}</p>
                      <span className={`shrink-0 text-[10px] font-semibold px-1.5 py-0.5 rounded-full ${r.open ? 'bg-emerald-500/15 text-emerald-600' : 'bg-red-500/10 text-red-500'}`}>
                        {r.open ? 'Aberto' : 'Fechado'}
                      </span>
                    </div>
                    <div className="flex items-center gap-3 mt-0.5 text-xs text-muted-foreground">
                      <span className="flex items-center gap-1">
                        <MapPin className="h-3 w-3" />{r.city}
                      </span>
                      <span className="flex items-center gap-1">
                        <Star className="h-3 w-3 fill-amber-400 text-amber-400" />{r.rating}
                      </span>
                      {r.queue > 0 && (
                        <span className="flex items-center gap-1">
                          <Users className="h-3 w-3" />{r.queue} na fila
                        </span>
                      )}
                    </div>
                  </div>
                  <button className="shrink-0 text-xs font-semibold text-primary px-3 py-1.5 rounded-lg bg-primary/10 hover:bg-primary/20 transition-colors">
                    Reservar
                  </button>
                </div>
              ))}
            </div>
          </div>
        </div>
      </ContainerScroll>

      {/* ── Por que Deep Dish ─────────────────────────── */}
      <section className="py-24 bg-background">
        <div className="container mx-auto px-4">
          <motion.p
            className="text-xs font-semibold uppercase tracking-widest text-primary mb-3"
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.4 }}
          >
            Benefícios
          </motion.p>
          <motion.h2
            className="font-display text-3xl md:text-4xl font-bold text-foreground mb-14"
            initial={{ opacity: 0, y: 12 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            Por que Deep Dish?
          </motion.h2>

          <div className="grid md:grid-cols-2 gap-6 md:gap-8 items-start">
            {/* Featured benefit — wrapped in GlowCard */}
            <motion.div
              className="md:sticky md:top-24"
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.55 }}
            >
              <GlowCard
                customSize
                glowColor="red"
                className="w-full bg-primary p-8 md:p-10"
              >
                <div>
                  <div className="flex h-12 w-12 items-center justify-center rounded-xl bg-foreground/10 text-foreground dark:bg-white/15 dark:text-white">
                    <Clock className="h-6 w-6" />
                  </div>
                  <h3 className="mt-6 font-display text-2xl font-bold text-foreground dark:text-white leading-snug">
                    Fila em tempo real
                  </h3>
                  <p className="mt-3 text-muted-foreground dark:text-white/70 leading-relaxed" style={{ maxWidth: '40ch' }}>
                    Acompanhe sua posição ao vivo. Nada de ligar pro restaurante —
                    você espera de onde quiser e chega na hora certa.
                  </p>
                </div>
              </GlowCard>
            </motion.div>

            {/* Secondary benefits — each wrapped in GlowCard */}
            <div className="flex flex-col gap-4">
              {[
                {
                  icon: <Users className="h-5 w-5" />,
                  title: 'Sem aglomeração',
                  desc: 'Espere de onde quiser, chegue na hora certa.',
                  glow: 'red' as const,
                },
                {
                  icon: <CalendarCheck className="h-5 w-5" />,
                  title: 'Reservas fáceis',
                  desc: 'Escolha mesa, horário e número de pessoas em segundos.',
                  glow: 'red' as const,
                },
                {
                  icon: <Store className="h-5 w-5" />,
                  title: 'Para restaurantes',
                  desc: 'Gerencie filas, mesas e equipe em um único painel.',
                  glow: 'red' as const,
                },
              ].map((b, i) => (
                <motion.div
                  key={b.title}
                  initial={{ opacity: 0, x: 14 }}
                  whileInView={{ opacity: 1, x: 0 }}
                  viewport={{ once: true }}
                  transition={{ duration: 0.4, delay: i * 0.07 }}
                >
                  <GlowCard
                    customSize
                    glowColor={b.glow}
                    className="w-full p-5"
                  >
                    <div className="flex items-start gap-4">
                      <div className="flex h-9 w-9 shrink-0 items-center justify-center rounded-lg bg-accent text-accent-foreground mt-0.5">
                        {b.icon}
                      </div>
                      <div>
                        <h3 className="font-display font-semibold text-foreground">{b.title}</h3>
                        <p className="mt-1 text-sm text-muted-foreground leading-relaxed">{b.desc}</p>
                      </div>
                    </div>
                  </GlowCard>
                </motion.div>
              ))}
            </div>
          </div>
        </div>
      </section>

      {/* ── Para restaurantes ─────────────────────────── */}
      <section className="relative py-24 bg-background overflow-hidden border-t border-border/40">
        {/* Sparkles background */}
        <div className="absolute inset-0 w-full h-full dark:hidden">
          <SparklesCore
            id="cta-sparkles-light"
            background="transparent"
            minSize={0.4}
            maxSize={1.4}
            particleDensity={80}
            className="w-full h-full"
            particleColor="#000000"
            speed={1}
          />
        </div>
        <div className="absolute inset-0 w-full h-full hidden dark:block">
          <SparklesCore
            id="cta-sparkles-dark"
            background="transparent"
            minSize={0.4}
            maxSize={1.4}
            particleDensity={80}
            className="w-full h-full"
            particleColor="#FFFFFF"
            speed={1}
          />
        </div>
        {/* Radial mask for soft edges */}
        <div className="absolute inset-0 w-full h-full bg-background [mask-image:radial-gradient(ellipse_80%_60%_at_50%_50%,transparent_30%,white)]" />

        <div className="relative z-10 container mx-auto px-4">
          <div className="max-w-2xl">
            <motion.p
              className="text-xs font-semibold uppercase tracking-widest text-primary mb-4"
              initial={{ opacity: 0 }}
              whileInView={{ opacity: 1 }}
              viewport={{ once: true }}
              transition={{ duration: 0.4 }}
            >
              Para restaurantes
            </motion.p>
            <motion.h2
              className="font-display font-extrabold text-foreground leading-[0.92] tracking-tight"
              style={{ fontSize: 'clamp(2.25rem, 6vw, 4.5rem)' }}
              initial={{ opacity: 0, y: 20 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.6, ease: [0.16, 1, 0.3, 1] }}
            >
              Chega de lista<br />de espera no papel.
            </motion.h2>
            <motion.p
              className="mt-6 text-muted-foreground leading-relaxed"
              style={{ maxWidth: '55ch' }}
              initial={{ opacity: 0, y: 12 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: 0.1 }}
            >
              Digitalize a gestão de filas e reservas. Reduza no-shows,
              otimize a ocupação e encante seus clientes.
            </motion.p>
            <motion.div
              className="mt-10 flex flex-wrap items-center gap-3"
              initial={{ opacity: 0, y: 12 }}
              whileInView={{ opacity: 1, y: 0 }}
              viewport={{ once: true }}
              transition={{ duration: 0.5, delay: 0.18 }}
            >
              <Link to="/restaurant/register">
                <Button size="lg" className="text-base px-8 min-h-[48px] shadow-lg shadow-primary/25">
                  Cadastrar restaurante
                </Button>
              </Link>
              <Link to="/restaurant/login">
                <Button
                  size="lg"
                  variant="ghost"
                  className="text-base px-6 min-h-[48px] text-foreground hover:bg-accent/50 focus-visible:ring-foreground/40"
                >
                  Já tenho conta
                  <ChevronRight className="ml-1 h-4 w-4" />
                </Button>
              </Link>
            </motion.div>
          </div>
        </div>
      </section>

      {/* ── FAQ ───────────────────────────────────────── */}
      <section className="py-24 bg-background">
        <div className="container mx-auto px-4 max-w-2xl">
          <motion.p
            className="text-xs font-semibold uppercase tracking-widest text-primary mb-3"
            initial={{ opacity: 0 }}
            whileInView={{ opacity: 1 }}
            viewport={{ once: true }}
            transition={{ duration: 0.4 }}
          >
            Dúvidas
          </motion.p>
          <motion.h2
            className="font-display text-3xl md:text-4xl font-bold text-foreground mb-12"
            initial={{ opacity: 0, y: 12 }}
            whileInView={{ opacity: 1, y: 0 }}
            viewport={{ once: true }}
            transition={{ duration: 0.5 }}
          >
            Perguntas frequentes
          </motion.h2>

          <div className="divide-y divide-border/50">
            {[
              { q: 'O Deep Dish é gratuito para clientes?', a: 'Sim. Buscar restaurantes, entrar na fila e reservar são 100% gratuitos para clientes.' },
              { q: 'Preciso baixar um app?', a: 'Não. O Deep Dish funciona direto no navegador, sem instalar nada.' },
              { q: 'Como o restaurante recebe meu pedido?', a: 'O restaurante vê sua reserva ou entrada na fila em tempo real no painel de gestão.' },
              { q: 'Posso cancelar uma reserva?', a: 'Sim, a qualquer momento antes do horário, respeitando a política de cada restaurante.' },
            ].map((faq, i) => (
              <motion.details
                key={i}
                className="group py-5"
                initial={{ opacity: 0, y: 8 }}
                whileInView={{ opacity: 1, y: 0 }}
                viewport={{ once: true }}
                transition={{ delay: i * 0.06 }}
              >
                <summary className="cursor-pointer font-semibold text-foreground flex items-center justify-between gap-4 select-none list-none">
                  {faq.q}
                  <ChevronRight className="h-4 w-4 shrink-0 text-muted-foreground transition-transform duration-300 ease-out group-open:rotate-90" />
                </summary>
                <p className="mt-3 text-sm text-muted-foreground leading-relaxed pr-8">{faq.a}</p>
              </motion.details>
            ))}
          </div>
        </div>
      </section>

      {/* ── Footer ────────────────────────────────────── */}
      <footer className="border-t border-border/60 bg-card py-10">
        <div className="container mx-auto px-4 flex flex-col md:flex-row items-center justify-between gap-4 text-sm text-muted-foreground">
          <div className="flex items-center gap-2.5">
            <div className="h-9 w-9 rounded-lg bg-white p-1.5 flex items-center justify-center">
              <img src={logo} alt="" className="max-h-full max-w-full object-contain" />
            </div>
            <span className="font-display font-semibold text-foreground">Deep Dish</span>
          </div>
          <p>&copy; {new Date().getFullYear()} Deep Dish. Todos os direitos reservados.</p>
        </div>
      </footer>

    </div>
  );
};

export default Landing;
