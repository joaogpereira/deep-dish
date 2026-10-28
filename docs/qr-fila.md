# QR de entrada na fila e priorização por encaixe

Notas operacionais das issues #174 (QR) e #170 (encaixe). O código é a fonte de
verdade; aqui ficam as decisões que não aparecem nele.

## QR de entrada na fila (#174)

### Como funciona

1. O painel (`GET /api/restaurante/fila/qr`) devolve uma URL como
   `https://api.exemplo.com/api/fila/qr/{restaurante_id}?signature=…`.
2. O QR é essa URL. Quem lê abre o navegador, e a API valida a assinatura e
   redireciona para `FRONTEND_URL/app/restaurants/{id}/entrar-na-fila`.
3. A rota do frontend é protegida: sem sessão, o cliente vai ao login (ou ao
   cadastro) e volta ao destino guardado (`lib/redirectAposLogin`).
4. Erros (assinatura inválida, restaurante inexistente, fila fechada) caem em
   `FRONTEND_URL/qr-erro?motivo=…`.

### Configuração em produção

| Variável | Papel |
|---|---|
| `APP_URL` | URL **pública** da API, com `https`. É o endereço gravado no QR. |
| `FRONTEND_URL` | URL pública do frontend; pode ser **outro domínio**. |
| `APP_KEY` | Chave do HMAC da assinatura. |

A assinatura é **relativa**: cobre caminho + query, não host nem esquema. Assim a
validação não depende de como a requisição chegou (proxy, CDN, `http` interno), e
o QR continua válido se o frontend mudar de domínio. O que **não pode mudar** é o
host do `APP_URL` depois de imprimir — ele é o endereço que está no papel.

A URL não expira (vai para o papel) e só contém o id do restaurante, que já é
público na listagem. Alterar o id ou qualquer parâmetro invalida a assinatura.

### Rotação da `APP_KEY`

A assinatura é um HMAC calculado com a `APP_KEY`. **Trocar a chave invalida todos
os QR Codes já impressos**: eles passam a cair em `/qr-erro?motivo=assinatura_invalida`
e cada restaurante precisa reimprimir. Trocar a chave também invalida sessões e
valores cifrados.

Para rotacionar sem esse custo, mantenha a chave antiga em `APP_PREVIOUS_KEYS`
(separadas por vírgula). O Laravel aceita assinaturas feitas com qualquer uma delas,
e os QRs antigos seguem válidos. Os QRs novos (gerados no painel) já usam a chave
nova. Retire a chave antiga de `APP_PREVIOUS_KEYS` só quando todos os restaurantes
tiverem reimpresso — ou aceite a invalidação.

Se a chave vazou e a ideia é justamente invalidar os QRs: troque-a **sem** colocar
a antiga em `APP_PREVIOUS_KEYS`.

## Priorização por encaixe (#170)

Parâmetros em `config/fila.php` (`.env`: `FILA_DESPERDICIO_MAXIMO_LUGARES`,
`FILA_ESPERA_PROTEGIDA_MINUTOS`).

### O que a tela mostra

A ordem de chamada depende de quais mesas vagam, então **não existe posição
absoluta garantida** para quem ainda não é protegido. A API devolve três campos
juntos e a tela os trata como um só:

- `posicao`: lugar na fila de espera, por chegada, somando todas as filas abertas
  do restaurante (a mesma ordem que a chamada percorre).
- `protegida`: o tempo máximo já passou. A ordem deste cliente é **garantida** —
  ninguém o ultrapassa quando houver mesa para o grupo.
- `protecao_em_minutos`: quanto falta para a proteção. Até lá, um grupo que
  combine melhor com uma mesa que vagou pode ser chamado antes.

### Estimativa de espera

`EstimativaEsperaService` ajusta `espera ≈ a + b · posição` sobre o histórico. A
posição de cada amostra é a mesma noção de `ClienteFila::posicao` (quantos
estavam à frente, ainda esperando, em qualquer fila do restaurante). Quem foi
ultrapassado por encaixe esperou mais do que a posição sugeria, e isso já está nos
dados reais. A estimativa continua sendo uma média, não uma garantia.
