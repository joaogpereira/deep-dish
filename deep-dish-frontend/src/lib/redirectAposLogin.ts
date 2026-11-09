// Destino a retomar depois do login — usado pelo QR de entrada na fila.
//
// Quem lê o QR sem sessão cai no login; sem guardar o destino, perderia o
// restaurante que escaneou. Fica no localStorage (e não em sessionStorage ou no
// state do router) porque o cadastro passa pela verificação de e-mail, cujo link
// costuma abrir em outra aba.
//
// O destino é validado na ida E na volta: só caminho interno da área do cliente
// (/app/...) é aceito, então um valor forjado — por link, por query string ou por
// alguém com acesso ao storage — não vira redirecionamento aberto.

const CHAVE = 'redirect_apos_login';
const VALIDADE_MS = 60 * 60 * 1000; // 1h: dá tempo de cadastrar e confirmar o e-mail

// /app, /app/restaurants/<id>/entrar-na-fila, opcionalmente com query simples.
const CAMINHO_SEGURO = /^\/app(\/[A-Za-z0-9._~-]+)*(\?[A-Za-z0-9._~=&%-]*)?$/;

export function destinoSeguro(destino: string | null | undefined): string | null {
  if (!destino || destino.length > 300) return null;
  // Segmento só de pontos ('..') passaria no padrão acima.
  if (/\/\.+(\/|\?|$)/.test(destino)) return null;
  return CAMINHO_SEGURO.test(destino) ? destino : null;
}

export function salvarDestino(destino: string): void {
  const seguro = destinoSeguro(destino);
  if (!seguro) return;
  try {
    localStorage.setItem(CHAVE, JSON.stringify({ destino: seguro, salvoEm: Date.now() }));
  } catch {
    // storage indisponível (modo privado): o cliente cai em /app, sem quebrar o login
  }
}

export function lerDestino(): string | null {
  try {
    const bruto = localStorage.getItem(CHAVE);
    if (!bruto) return null;
    const { destino, salvoEm } = JSON.parse(bruto) as { destino?: string; salvoEm?: number };
    if (typeof salvoEm !== 'number' || Date.now() - salvoEm > VALIDADE_MS) {
      localStorage.removeItem(CHAVE);
      return null;
    }
    return destinoSeguro(destino);
  } catch {
    return null;
  }
}

export function limparDestino(): void {
  try {
    localStorage.removeItem(CHAVE);
  } catch {
    // ignorado: ver salvarDestino
  }
}

/** Lê e apaga: usar no momento em que a navegação de volta é feita. */
export function consumirDestino(): string | null {
  const destino = lerDestino();
  limparDestino();
  return destino;
}
