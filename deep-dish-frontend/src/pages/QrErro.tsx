import React from 'react';
import { Link, useSearchParams } from 'react-router-dom';
import { Button } from '@/components/ui/button';
import EmptyState from '@/components/EmptyState';
import { QrCode } from 'lucide-react';

// Para onde o backend manda quem leu um QR que não pode ser usado
// (FilaQrController::entrar). Pública: o erro vem antes de qualquer login.
const MOTIVOS: Record<string, { titulo: string; descricao: string }> = {
  assinatura_invalida: {
    titulo: 'QR Code inválido',
    descricao: 'Este QR Code não é válido ou foi alterado. Peça outro ao restaurante ou procure-o no app.',
  },
  restaurante_inexistente: {
    titulo: 'Restaurante não encontrado',
    descricao: 'O restaurante deste QR Code não existe mais.',
  },
  fila_fechada: {
    titulo: 'Fila fechada',
    descricao: 'Este restaurante não está recebendo fila no momento.',
  },
};

const PADRAO = {
  titulo: 'Não foi possível entrar na fila',
  descricao: 'Não conseguimos processar este QR Code. Tente novamente ou procure o restaurante no app.',
};

const QrErro: React.FC = () => {
  const [params] = useSearchParams();
  const motivo = params.get('motivo') ?? '';
  const { titulo, descricao } = MOTIVOS[motivo] ?? PADRAO;

  return (
    <div className="mx-auto max-w-md px-4 py-10">
      <EmptyState
        icon={<QrCode className="h-7 w-7" />}
        title={titulo}
        description={descricao}
        action={
          <Button asChild>
            <Link to="/app/restaurants">Ver restaurantes</Link>
          </Button>
        }
      />
    </div>
  );
};

export default QrErro;
