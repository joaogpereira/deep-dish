# Origem dos dados usados nos gráficos — Deep Dish (PI4)

**Projeto Integrador 4 — Ciência da Computação**  
**Responsável pela documentação:** Eduardo Gondim Marinho  
**Revisão do código:** 22/11/2026, branch `develop`.

## 1. Por que precisamos explicar isso

Os gráficos do Deep Dish mostram tempo de espera, abandono da fila, ocupação de mesas e horários de maior movimento. Como o projeto ainda não tinha um histórico de uso suficiente para demonstrar todos esses indicadores, foi criado um gerador de dados de exemplo. Esses números ajudam a mostrar como os gráficos funcionam, mas **não são resultados comprovados de um restaurante real**.

## 2. De onde vieram os registros de demonstração

O arquivo [`HistoricoSinteticoSeeder.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/database/seeders/HistoricoSinteticoSeeder.php) cria histórico artificial de filas e reservas. O comando [`historico:sintetico`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Console/Commands/SeedHistoricoSinteticoCommand.php) usa **12 semanas por padrão**, mas o número pode mudar com o parâmetro `--semanas`.

A simulação considera dias e horários mais movimentados, tamanho dos grupos, tempo de permanência e motivos de saída. Isso produz um cenário útil para testar o painel, sem afirmar que a movimentação aconteceu de verdade. O projeto também tem o `DatabaseSeeder`, que cria restaurantes e clientes de exemplo para desenvolvimento; esse cenário inicial não deve ser confundido com operação real.

## 3. Como a aplicação separa os dados

Existe uma coluna chamada `origem` em `fila`, `clientefila` e `clientemesa`, criada pela [migration de procedência](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/database/migrations/2026_08_23_220000_add_origem_to_historico_tables.php). O gerador grava `origem = 'sintetico'` nos registros que produz. A API `GET /api/restaurante/analytics` retorna a seção `procedencia`, com contagens por categoria e o campo `contem_sintetico`.

No código, **`origem = NULL` é contado pela API como `real`**. Isso significa apenas que o registro não recebeu a marcação sintética; **não prova, sozinho, que houve atendimento real em um restaurante**. Para afirmar que um dado é de operação real, o grupo precisa confirmar de qual ambiente e atendimento veio.

Outro cuidado: o [`AnalyticsService.php`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-backend/app/Services/AnalyticsService.php) calcula os indicadores com os registros do período, sem separar automaticamente os dados sintéticos dos não marcados. A API informa a contagem, mas a tela do [`Dashboard.tsx`](https://github.com/joaogpereira/deep-dish/blob/develop/deep-dish-frontend/src/pages/restaurant/Dashboard.tsx) consultada não mostra um aviso visual de “dados sintéticos”. Por isso, é importante colocar o aviso no material da apresentação.

## 4. Texto para usar junto dos gráficos

> Os gráficos apresentados foram montados com dados de demonstração gerados pelo sistema para simular o movimento de um restaurante. O gerador trabalha, por padrão, com 12 semanas de histórico, mas o período efetivo depende da base carregada. Esses números mostram como o Deep Dish calcula e apresenta os indicadores e não devem ser tratados como resultados reais de um estabelecimento. Caso o ambiente também tenha registros de uso, a origem de cada grupo de dados será informada separadamente.

## 5. Dados a confirmar no ambiente da banca

| O que conferir | Informação |
|---|---|
| Qual restaurante e banco serão usados | A equipe precisa informar |
| Intervalo selecionado no gráfico | Conferir na tela / resposta da API |
| `procedencia.fila.real` | Consultar a resposta da API |
| `procedencia.fila.sintetico` | Consultar a resposta da API |
| `procedencia.reservas.real` | Consultar a resposta da API |
| `procedencia.reservas.sintetico` | Consultar a resposta da API |
| `procedencia.contem_sintetico` | Consultar a resposta da API |
| Há atendimento real confirmado, além da simulação? | Perguntar à equipe e identificar a fonte |

Não preenchi os números porque não foi possível consultar o banco que será usado na apresentação. Colocar valores estimados aqui seria apresentar um resultado que não foi medido.

## 6. Conclusão

Usar dados de exemplo foi uma forma de desenvolver e demonstrar os gráficos sem depender de meses de funcionamento em um restaurante parceiro. O importante é falar isso claramente para a banca e manter separado o que é simulação do que foi registrado em uso real.
