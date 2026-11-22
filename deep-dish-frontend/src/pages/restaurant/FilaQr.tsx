import React, { useEffect, useState } from 'react';
import { Link } from 'react-router-dom';
import { QRCodeSVG } from 'qrcode.react';
import { Button } from '@/components/ui/button';
import { Skeleton } from '@/components/ui/skeleton';
import { queueService } from '@/services/queue.service';
import { FilaQrInfo } from '@/types';
import { ArrowLeft, Printer, AlertTriangle } from 'lucide-react';

// QR fixo de entrada na fila. A URL é assinada e permanente no backend
// (FilaQrController): o mesmo papel impresso serve para sempre.
const FilaQr: React.FC = () => {
  const [info, setInfo] = useState<FilaQrInfo | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');

  useEffect(() => {
    let cancelled = false;
    queueService.getFilaQr()
      .then(data => { if (!cancelled) setInfo(data); })
      .catch(() => { if (!cancelled) setError('Não foi possível gerar o QR Code. Tente novamente.'); })
      .finally(() => { if (!cancelled) setLoading(false); });
    return () => { cancelled = true; };
  }, []);

  if (loading) return (
    <div className="mx-auto max-w-xl space-y-4">
      <Skeleton className="h-8 w-48" />
      <Skeleton className="h-96 rounded-2xl" />
    </div>
  );

  if (error || !info) return (
    <div className="mx-auto max-w-xl space-y-4">
      <p role="alert" className="rounded-lg bg-destructive/10 px-3.5 py-3 text-sm text-destructive">
        {error || 'QR Code indisponível.'}
      </p>
    </div>
  );

  return (
    <div className="mx-auto max-w-xl space-y-6 animate-fade-in">
      {/* Controles: ficam de fora da folha impressa */}
      <div className="flex flex-wrap items-center justify-between gap-3 print:hidden">
        <Link
          to="/restaurant/queue"
          className="inline-flex items-center gap-1.5 text-sm text-muted-foreground hover:text-foreground transition-colors min-h-[44px]"
        >
          <ArrowLeft className="h-4 w-4" />
          Voltar à fila
        </Link>
        <Button onClick={() => window.print()} className="min-h-[44px] gap-2">
          <Printer className="h-4 w-4" aria-hidden="true" />
          Imprimir
        </Button>
      </div>

      {!info.fila_ativa && (
        <p className="flex items-start gap-2 rounded-lg bg-amber-500/10 px-3.5 py-3 text-sm text-amber-700 dark:text-amber-400 print:hidden">
          <AlertTriangle className="mt-0.5 h-4 w-4 shrink-0" aria-hidden="true" />
          A fila está desativada: quem ler o QR Code verá um aviso de fila fechada.
        </p>
      )}

      {/* Folha: sempre preto sobre branco, mesmo no tema escuro — a câmera lê contraste, não tema */}
      <section
        aria-label="Folha para impressão"
        className="rounded-2xl bg-white p-6 text-center text-black shadow-card sm:p-10 print:rounded-none print:p-0 print:shadow-none"
      >
        <h1 className="font-display text-2xl font-bold sm:text-3xl print:text-4xl">{info.nome}</h1>

        <div className="mx-auto my-6 w-full max-w-[320px] print:my-10 print:max-w-[12cm]">
          <QRCodeSVG
            value={info.url}
            level="M"
            marginSize={4}
            bgColor="#ffffff"
            fgColor="#000000"
            className="h-auto w-full"
            title={`QR Code para entrar na fila de ${info.nome}`}
          />
        </div>

        <p className="font-display text-xl font-semibold sm:text-2xl print:text-3xl">
          Escaneie para entrar na fila
        </p>
        <p className="mt-2 text-sm text-neutral-600 print:text-base">
          Aponte a câmera do celular para o código e acompanhe sua posição pelo Deep Dish.
        </p>
      </section>
    </div>
  );
};

export default FilaQr;
