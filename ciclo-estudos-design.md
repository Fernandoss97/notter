# Desenho do módulo de Ciclo de Estudos

Desenho de implementação para `ciclo-estudos-spec.md`, aprovado antes da
implementação. Registra as decisões que a spec deixou em aberto e as
divergências deliberadas — o que aqui está resolvido não deve ser
redecidido no meio do código.

---

## 1. Nomenclatura: "ciclo" já significava duas coisas

| Uso | Onde | Significado |
|---|---|---|
| `Topic.cycle` | trilha, plano, `suggestNextStudy` | a leva da planilha de trilha (coluna `TRILHA`) |
| `completed_cycles`, `cycles_before_long_break` | pomodoro | uma volta de foco |
| **este módulo** | — | o rodízio ordenado de disciplinas |

São três objetos sem relação, e o conflito já era visível na UI (a trilha
mostrava "Ciclo 1, Ciclo 2"). A migration `2026_08_09_152607` já chamava as
levas da trilha de *ondas*; o vocabulário do próprio autor foi adotado:

- **Trilha** → "Onda N" na UI. Rótulo apenas: a coluna `topics.cycle`
  continua com o nome que tem. Renomear coluna seria escopo não pedido.
- **Pomodoro** → "ciclo pomodoro", sempre qualificado.
- **Este módulo** → "Ciclo de estudos" na UI (é o termo do concurseiro).
  No código: `CycleSlot` (a cadeira no rodízio), `CycleBlock` (a execução
  numa volta), `lap` (a volta).

---

## 2. A decisão central: bloco não é pomodoro

A spec quer blocos de 60–90 min (120 como exceção), retomáveis,
atravessando dias (§3, §6). O pomodoro é de 50 min e `StartFocusSession`
congela `planned_seconds` de propósito.

> **O bloco é um livro-razão que acumula segundos de foco ao longo de
> vários pomodoros e vários dias — não um pomodoro mais longo.**

Consequências, todas deliberadas:

1. **O mecanismo do pomodoro não muda.** `AdvanceFocusPhase`,
   `planned_seconds`, `extend`, as pausas — nada é tocado. O ciclo
   pomodoro roda *dentro* do bloco.
2. **A atribuição é explícita**, via `study_sessions.cycle_block_id`, nunca
   derivada de disciplina + data. Derivar quebra no instante em que o
   rodízio é reordenado.
3. **O ponteiro avança quando o bloco bate o alvo, não quando a sessão
   encerra.** É o que a continuidade do §3 exige, e é fácil implementar ao
   contrário.
4. **O bloco conta só segundos de foco** (`accumulated_seconds`),
   ignorando `exclude_breaks_from_study_time`. Divergência consciente da
   preferência de exibição: se o intervalo contasse, um bloco de 90 min
   fecharia com ~70 min de estudo real. A preferência continua valendo
   para dashboard e histórico.

---

## 3. O que a spec pede que o sistema já entrega

| Requisito | Status | Como |
|---|---|---|
| §2 Seleção de área/concurso | pronto | `StudyPlan` + `type: contest` + `name` |
| §3 Independência de calendário | **já satisfeito** | nada é atrelado a dia da semana. Requisito negativo atendido — não adicionar agendamento |
| §1 Prática espaçada | pronto | `Review` + `INTERVAL_DAYS` [1,3,7,14,30] e a fila na tela de foco |
| §5 Revisão via questões | pronto | `ActivityType::Exercises`, `exercises_total/correct`, acerto por disciplina no dashboard |
| §2 Sugestão de 4 / 5-6 disciplinas | dado existe | `weekly_goal_minutes` é a entrada; a sugestão é cálculo puro, sem storage |
| §6 Flexibilidade temporal | substrato existe | `StudySession` já acumula segundos e sobrevive a pausa/retomada |
| §3 Retomar de onde parou | substrato existe | `accumulated_seconds` já é fonte da verdade no servidor |

O resto é novo: o rodízio, os modos de disciplina, a reciclagem, as
páginas lidas e as heurísticas de montagem.

---

## 4. Modelo de dados

### Novo

```
cycle_slots                # a cadeira da disciplina no rodízio
  study_plan_id FK, subject_id FK
  position                 # a ordem do rodízio
  block_minutes            # 60..120; alerta acima de 90
  state                    # active | paused          (reciclagem, §4)
  boost_until (nullable)   # imersão inicial de matéria nova (§4)
  unique(study_plan_id, subject_id)

cycle_blocks               # uma execução do slot numa volta — o ledger
  cycle_slot_id FK
  lap                      # nº da volta
  target_seconds           # snapshot de block_minutes na abertura
  accumulated_seconds      # alimentado pelas sessões
  completed_at (nullable)
  unique(cycle_slot_id, lap)
```

### Alterações

```
subjects
  + area                   # direito | exatas | linguagens | humanas | outra
                           #   necessário para a intercalação do §3
  + mode                   # reading | review | done                  (§4)
  + pages_read (default 0)                                            (§5)

study_sessions
  + cycle_block_id (nullable FK)   # estudo fora do rodízio segue válido
  + pages_read (nullable)

topics
  + pages_read (default 0)         # addPages() espelhando addExercises(),
                                   #   incremento no banco pelo mesmo motivo
```

### `Subject.goal_minutes` fica como está

Existe, é editável na criação, e é meta **semanal**, só de exibição —
nada calcula contra ela. Não é o `block_minutes` (um é semanal, o outro é
por bloco), então não há duplicação. Mas deixa de ser exibida quando há
ciclo ativo: a carga semanal passa a ser derivável (`block_minutes` ×
voltas/semana), e duas metas concorrentes na mesma tela é ruído.

### A prioridade de disciplina não volta

O §4 pede "peso maior nas primeiras semanas". A tentação é ressuscitar
`subjects.priority`, removida em `2026_08_09_152607` com justificativa
explícita: *"seu único efeito era desempatar a sugestão de estudo — papel
que a ordem da trilha assume, medida em vez de declarada"*.

O princípio é respeitado. Peso vira duas grandezas concretas:
`block_minutes` maior e `position` mais cedo no rodízio, com `boost_until`
para o reforço expirar sozinho em vez de virar um enum esquecido em
`high`.

---

## 5. Lógica do rodízio

**O ponteiro é derivado, não armazenado** — mesmo princípio do número da
tarefa na trilha, derivado da posição para nunca ficar furado:

```
bloco corrente = primeiro cycle_block aberto da volta corrente,
                 na ordem de position dos slots ativos
```

- **Abertura da volta:** ao fechar o último bloco da volta N, materializa-se
  a volta N+1 com um bloco por slot `active`. Slot pausado não gera bloco.
  A escrita é `insertOrIgnore`, não um create por cadeira faltante: a
  resolução roda em toda visita ao foco e ao ciclo, e duas abas veriam
  ambas a volta vazia — com create, a segunda estouraria no índice único
  e derrubaria a tela principal com um 500.
- **Fechar bloco:** quando `accumulated_seconds >= target_seconds`, ou
  manualmente — "dar por encerrado", que é o §6 (*feito é melhor que
  perfeito*) virando botão.
- **Continuidade (§3):** parar no meio não fecha nada. Ao voltar, o bloco
  corrente é o mesmo, com o tempo restante. Cai de graça, porque o ledger
  vive no servidor.
- **Flexibilidade (§6):** 3h = 3 blocos, 1h = 1 bloco, e o ciclo segue
  amanhã. O rodízio não tem noção de "meta do dia"; a única meta é a
  semanal, que já existe no plano.
- **Pular bloco:** permitido, e registrado como fechado abaixo do alvo.
  Serve de sinal para "esta disciplina está sendo evitada" no Progresso.
- **Reordenar no meio da volta:** permitido; muda só qual é o próximo.
  Blocos já iniciados guardam o próprio tempo, então nada se perde.
- **O número da volta pode recuar**, e é intencional: uma cadeira pausada no
  meio de um bloco leva o bloco aberto consigo, o resto da volta fecha, e a
  seguinte começa. Ao devolvê-la ao rodízio, o bloco dela volta a ser o mais
  antigo em aberto. Preferir a volta mais nova esconderia esse bloco para
  sempre — e ele tem tempo estudado dentro.
- **Fechar um bloco convida para a próxima matéria**, pelos dois caminhos (o
  tempo da sessão bater o alvo e o "dar por encerrado" manual). É o único
  momento em que a intercalação fala com quem estuda; sem isso o rodízio
  anda calado. O aviso se soma ao da operação em vez de substituí-lo.

**Crescimento do ciclo (§4).** Mudar o modo da disciplina ajusta a cadeira
na mesma operação, e não numa segunda edição que o usuário teria de lembrar
de fazer: `review` encolhe o bloco ao mínimo e cancela o reforço, `done`
pausa a cadeira, `reading` a devolve ao rodízio. O caminho de volta não
restitui os minutos sozinho — quem reabre a teoria de uma matéria sabe
melhor que o sistema quanto tempo ela vai pedir agora.

O reforço de matéria nova é uma **data de validade** (`boost_until`, duas
semanas), não um sinalizador de prioridade: um peso declarado sem prazo
nunca é revisto, que foi exatamente o que condenou `subjects.priority`.

Enxugar mantém as primeiras cadeiras da fila e pausa o resto — a ordem do
rodízio já diz o que importa mais, e pedir a mesma informação de novo seria
fazer o usuário se repetir. O piso é duas disciplinas: abaixo disso não é
enxugar um rodízio, é desmontá-lo.

**Atribuição de tempo:** a sessão é carimbada com o bloco em
`StartFocusSession` — não no encerramento, senão trocar de disciplina no
meio reescreveria o passado. Estudar uma disciplina fora da vez é legítimo
e simplesmente não credita o rodízio: quem sai da fila não faz a fila
andar.

`SyncCycleBlockProgress` **recalcula** `accumulated_seconds` como a soma
das sessões do bloco, em vez de incrementar. O acumulador de foco da sessão
cresce em quatro lugares (pausar, avançar de fase, encerrar, fechar sessão
abandonada); com incremento, esquecer um gancho faria o bloco contar a
menos para sempre e em silêncio. A soma inteira é idempotente — o gancho
seguinte conserta o anterior. Os pontos de chamada são dois: a transição do
`FocusSessionController` (cobre os três primeiros) e
`CloseAbandonedFocusSession` (o único que fecha longe de uma requisição).

---

## 6. Ciclo e Trilha compõem, não competem

- **Ciclo** responde *qual disciplina agora* (a intercalação do §1).
- **Trilha** responde *qual tarefa dentro dela* (a fila importada).

`suggestNextStudy()` passa a ter duas etapas: bloco corrente → disciplina;
depois a próxima tarefa aberta **daquela disciplina** por
`(cycle, position)`. Sem ciclo ativo, o comportamento atual permanece
intacto — o módulo é opt-in.

**Precedência da pré-seleção na tela de foco:** clique do usuário na fila →
bloco do rodízio → revisão mais atrasada → próxima tarefa da trilha. O
bloco passa à frente da revisão porque ele decide a *disciplina*: começar
por outra faria a sessão não creditar o bloco, e o ciclo pararia de andar
sem ninguém entender por quê. Dentro da disciplina da vez, a trilha
continua escolhendo a tarefa.

---

## 7. Telas

### `/ciclo` (novo, `pages/cycle/index.tsx`)
- Volta atual com os blocos em ordem: concluídos, o corrente com barra de
  progresso, os pendentes.
- Montagem: adicionar disciplina ao rodízio, reordenar, definir
  `block_minutes`, pausar/retomar.
- Aviso suave de intercalação ao pôr duas disciplinas da mesma `area` em
  sequência — aviso, nunca bloqueio.
- Assistente de montagem (§2): a partir de `weekly_goal_minutes`, sugere o
  tamanho do núcleo básico e uma ordem intercalada; tudo editável.
- Modo enxuto (§4, reciclagem): pausa tudo menos as N mais importantes,
  sem apagar nada.

### `/foco` (alterada)
- Cabeçalho com o bloco corrente e sua barra, acima do relógio do pomodoro.
- A pré-seleção de disciplina passa a vir do bloco corrente.
- Ao fechar o bloco: o convite para a próxima matéria, que é o coração do §1.
- Campo de páginas lidas ao lado de questões no encerramento.

### `/dashboard` (alterada)
- Aderência ao ciclo: tempo real por disciplina contra o alvo do rodízio.
  É a métrica que revela a disciplina sendo evitada — hoje invisível.
- Voltas concluídas, tempo médio por volta, páginas lidas por disciplina.

### Sidebar
`Foco → Ciclo → Progresso → Trilha → Plano → Histórico`. O ciclo fica
colado no foco porque é o que responde "o que agora".

---

## 8. Decisões sobre o que a spec deixou em aberto

| Lacuna | Decisão |
|---|---|
| §2 diz `<20h → 4` e `>25h → 5-6`; **20–25h não é especificado**, e o default `weekly_goal_minutes` é 1200 = exatamente 20h, em cima da fronteira | `<20h → 4`; `20–25h → 5`; `>25h → 5`; `>=30h → 6`. Sempre sugestão editável, nunca limite |
| §2 "disciplinas basilares" por concurso | É conteúdo curado, não código. Adiado: sem catálogo, a montagem é manual ou importada, como já se faz na trilha |
| §5 "restrição a resumos", §6 "feito é melhor que perfeito" | São microcopy, não dados. Viram texto no estado vazio do ciclo e no encerramento de bloco abaixo do alvo. Implementar como regra seria over-building |
| §3 intercalação | Heurística na geração + aviso suave, nunca restrição rígida. Há motivo legítimo para dois direitos seguidos |
| Exceção de 120 min (§2) | `block_minutes` valida 60–120; acima de 90 mostra aviso inline, não erro |
| Um ciclo por plano ou vários? | **Um por plano.** O plano já tem semântica de "ativo"; vários exigiriam um modelo pai `StudyCycle` sem ganho no MVP |
| A disciplina pode aparecer duas vezes na mesma volta? | **Não.** O peso vem de `block_minutes`, não de repetição — daí o `unique(study_plan_id, subject_id)` |
| Sessão sem bloco continua permitida? | **Sim.** `cycle_block_id` é nullable: quem quer estudar fora do rodízio não é bloqueado |

---

## 9. Faseamento

| Fase | Entrega | Cobre |
|---|---|---|
| **F1** ✅ | `cycle_slots` + `cycle_blocks` + tela `/ciclo` com ordem manual + integração no foco | §1, §3, §6 — o módulo já é útil aqui |
| **F2** ✅ | `subjects.mode`, `boost_until`, pausar slot, modo enxuto | §4 |
| **F3** | `pages_read` nas três camadas + aderência no dashboard | §5 |
| **F4** | `subjects.area`, assistente de montagem, aviso de intercalação | §2, §3 |

Testes por fase, conforme o `CLAUDE.md`: Unit para as Actions, Feature
para rotas e props Inertia, Vitest para a tela de ciclo e o
`suggestNextStudy` reescrito, e um Browser test em F1 — o fluxo "iniciar →
pausar → recarregar → o bloco continua de onde parou", que é o requisito
que mais fácil regride.
