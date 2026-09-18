//TODO CONSERTAR AS INTERFACES PARA ENTRAREM NO PADRÃO DO SITE

export interface Cliente {
  id: string;
  name: string;
  email: string;
  cpf: string;
}

export interface Restaurante {
  id: string;
  name: string;
  tipo: string;
  email: string;
  cnpj: string;
  logradouro: string;
  numero: string;
  complemento?: string | null;
  bairro: string;
  cidade: string;
  estado: string;
  cep: string;
  endereco_completo?: string;
  telefone: string;
  imagem_url: string;
  horario_abertura?: string | null;
  horario_fechamento?: string | null;
  fila_ativa: boolean;
  tamanho_fila_atual: number;
  // Campos do backend (cadastráveis pelo restaurante)
  rating?: number | null;
  price_range?: number | null;
  reservations_enabled?: boolean;
  description?: string | null;
}

// Nível de confiança da estimativa de espera (EstimativaEsperaService).
// 'especifico'/'amplo' = baseado em histórico real; 'padrao' = fallback sem dado suficiente.
export type NivelEstimativa = 'especifico' | 'amplo' | 'padrao';

// Retorno de EstimativaEsperaService::estimar(), devolvido por
// POST /fila, GET /fila/posicao e GET /fila/estimativa.
export interface EstimativaEspera {
  espera_estimada_minutos: number;
  espera_estimada_segundos: number;
  nivel: NivelEstimativa;
  amostra: number;
  posicao: number;
}

export type User = Cliente | Restaurante;

export interface AuthSession {
  token: string;          
  restaurant: Restaurante; 
}

// Mesa conforme retornada pelo backend (máquina de estados: livre → reservada → ocupada → livre)
export type MesaStatus = 'livre' | 'reservada' | 'ocupada' | 'bloqueada';

export interface Mesa {
  id: number | string;
  restaurante_id: string;
  numero: number;
  capacidade: number;
  status: MesaStatus;
  confirmacao?: string;
  created_at?: string;
  updated_at?: string;
}

// Resposta paginada do Laravel (paginate())
export interface Paginated<T> {
  data: T[];
  current_page: number;
  last_page: number;
  per_page: number;
  total: number;
}

// Status real vindo do backend (clientemesa.status)
export type ReservaStatus = 'confirmada' | 'em_andamento' | 'cancelada' | 'liberada' | 'expirada';

// Reserva conforme retornada pelo backend (Eloquent ClienteMesa)
export interface Reserva {
  id: number | string;
  cliente_id: number | string;
  mesa_id: number | string;
  horario_reserva: string; // ISO datetime
  horario_checkin?: string | null; // ISO datetime — preenchido no check-in
  party_size?: number;
  status: ReservaStatus;
  created_at?: string;
  updated_at?: string;
  mesa?: {
    id: number | string;
    restaurante_id: string;
    numero: number;
    capacidade: number;
    status: string;
    restaurante?: Restaurante;
  };
  cliente?: Cliente;
}

// Entrada na fila conforme retornada pelo backend (ClienteFila + relações).
// POST /fila e GET /fila/posicao acrescentam os campos de EstimativaEspera —
// opcionais aqui porque a tela não deve inventar número quando ainda não chegaram.
export interface ClienteFilaEntry extends Partial<EstimativaEspera> {
  id: string;
  fila_id: string;
  cliente_id: string;
  qntd_pessoas: number;
  posicao: number;
  created_at: string;
  updated_at: string;
  fila?: {
    id: string;
    restaurante_id: string;
    horario_reserva: string; // UTC datetime
    status: string;
  };
  cliente?: {
    id: string;
    name: string;
    email: string;
    telefone?: string;
  };
}

export interface QueueEntry {
  id: string;
  userId: string;
  userName: string;
  userPhone: string;
  restaurantId: string;
  restaurantName: string;
  partySize: number;
  position: number;
  estimatedWaitMinutes: number;
  status: 'waiting' | 'called' | 'seated' | 'cancelled' | 'no_show';
  joinedAt: string;
}

export interface Notification {
  id: string;
  userId: string;
  title: string;
  message: string;
  type: 'queue' | 'reservation' | 'system';
  read: boolean;
  createdAt: string;
}

export interface RestaurantSettings {
  id: string;
  restaurantId: string;
  name: string;
  address: string;
  city: string;
  phone: string;
  capacity: number;
  toleranceMinutes: number;
  cancellationPolicyMinutes: number;
  autoConfirmReservations: boolean;
  maxPartySize: number;
  openingTime: string;
  closingTime: string;
}

export interface StaffMember {
  id: string;
  restaurante_id: string;
  name: string;
  cargo: string;
  cpf?: string | null;
  telefone?: string | null;
  email?: string | null;
  data_nascimento?: string | null;
  horario?: string | null;
  observacoes?: string | null;
  ativo: boolean;
  motivo_afastamento?: string | null;
  created_at?: string;
  updated_at?: string;
}
