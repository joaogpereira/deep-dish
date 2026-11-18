import { httpClient } from './httpClient';

// Contrato de POST /restaurante/assistente/chat (AssistenteChatController, #179).

export interface RespostaDoAssistente {
  conversa_id: string;
  resposta: string;
  /** Nomes das ferramentas de analytics que o modelo consultou para responder. */
  ferramentas_usadas: string[];
}

export const assistenteService = {
  async perguntar(mensagem: string, conversaId?: string): Promise<RespostaDoAssistente> {
    return httpClient.post('/restaurante/assistente/chat', { mensagem, conversa_id: conversaId });
  },
};
