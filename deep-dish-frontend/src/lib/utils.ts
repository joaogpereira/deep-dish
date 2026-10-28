import { clsx, type ClassValue } from "clsx";
import { twMerge } from "tailwind-merge";
import { NivelEstimativa } from "@/types";

export function cn(...inputs: ClassValue[]) {
  return twMerge(clsx(inputs));
}

/**
 * 'especifico'/'amplo' vêm de regressão sobre histórico real;
 * 'padrao' é o fallback do EstimativaEsperaService sem amostra suficiente.
 */
export function isEstimativaHistorica(nivel?: NivelEstimativa): boolean {
  return nivel === 'especifico' || nivel === 'amplo';
}

// Brasil não adota horário de verão desde 2019 — UTC-3 fixo.
const BRT_TIMEZONE = 'America/Sao_Paulo';

/**
 * Formata uma string ISO do backend sempre em horário de Brasília (BRT),
 * independente do timezone configurado no navegador.
 */
export function formatBRT(
  iso: string,
  opts: Omit<Intl.DateTimeFormatOptions, 'timeZone'>,
): string {
  const normalized = iso.replace(' ', 'T');
  return new Intl.DateTimeFormat('pt-BR', { ...opts, timeZone: BRT_TIMEZONE }).format(
    new Date(normalized),
  );
}

/**
 * Retorna a data atual no fuso de Brasília no formato YYYY-MM-DD.
 */
export function hojeEmBRT(): string {
  return new Intl.DateTimeFormat('en-CA', { timeZone: BRT_TIMEZONE }).format(new Date());
}

/**
 * Monta um ISO datetime com offset BRT fixo (-03:00),
 * garantindo que o backend interprete o horário corretamente.
 */
export function toISOBRT(date: string, time: string): string {
  return `${date}T${time}:00-03:00`;
}
