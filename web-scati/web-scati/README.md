# Web SCATI — Sistema de Controle de Ativos de TI

Sistema web para inventário interno de ativos de TI, desenvolvido em PHP 8+,
MySQL, HTML5, CSS3, JavaScript e Bootstrap 5, conforme a Documentação
Funcional (v1.0).

## Requisitos

- PHP 8.0 ou superior, com extensão **PDO MySQL** habilitada
- MySQL 5.7+ ou MariaDB 10.3+
- Servidor web (Apache, Nginx ou o servidor embutido do PHP)

## Instalação

1. **Banco de dados**
   Importe o script `database/scati.sql`, que cria o banco `scati` e todas
   as tabelas necessárias:
   ```bash
   mysql -u root -p < database/scati.sql
   ```

   Se o banco `scati` **já existia** antes do suporte a equipamentos do tipo
   Servidor (antes das colunas `funcao_servidor`, `servidor_status`,
   `servidor_observacoes` e das tabelas `compartilhamentos_servidor` /
   `compartilhamento_computadores`), não reimporte o `scati.sql` do zero —
   isso falharia por conflito com as tabelas já existentes. Em vez disso,
   rode a migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_servidor.sql
   ```

   Da mesma forma, se o banco já existia antes do suporte a **Itens
   Vinculados** (antes da tabela `itens_vinculados`), rode também esta
   migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_itens_vinculados.sql
   ```

   > Se você já rodou uma versão **anterior** dessa migração (a que
   > adicionava as colunas `status` e `equipamento_id` diretamente na
   > tabela `estoque` — modelo que tinha um bug com itens de quantidade > 1),
   > use `database/migration_itens_vinculados_fix.sql` no lugar do arquivo
   > acima para corrigir a estrutura sem perder os vínculos já cadastrados.

   Se o banco já existia antes do suporte a **histórico de cadastro/exclusão
   de itens de estoque** (antes da tabela `historico_estoque`), rode também
   esta migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_historico_estoque.sql
   ```

   Se o banco já existia antes do campo **IP Fixo** de computadores (antes
   da coluna `ip_fixo` na tabela `equipamentos`), rode também esta migração
   incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_ip_fixo.sql
   ```

   Se o banco já existia antes do suporte a **Anexos** na ficha do
   equipamento (antes da tabela `anexos_equipamentos`), rode também esta
   migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_anexos.sql
   ```

   Se o banco já existia antes do campo **Placa Mãe** de computadores
   (antes da coluna `placa_mae` na tabela `equipamentos`), rode também esta
   migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_placa_mae.sql
   ```

   Se o banco já existia antes do campo **Placa de Vídeo** de computadores
   (antes da coluna `placa_video` na tabela `equipamentos`), rode também esta
   migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_placa_video.sql
   ```

   Se o banco já existia antes do campo **Acesso a Dispositivos USB**
   (antes da coluna `acesso_usb` na tabela `equipamentos`), rode também
   esta migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_acesso_usb.sql
   ```

   Se o banco já existia antes da tela de **Configurações** (antes das
   tabelas `tipos_manutencao` e `configuracoes`), rode também esta
   migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_configuracoes.sql
   ```

   Se o banco já existia antes do **alerta de troca de toner por tempo de
   uso** (antes das colunas `toner_duracao_dias` e `toner_ultima_troca` na
   tabela `equipamentos`), rode também esta migração incremental **uma
   única vez**:
   ```bash
   mysql -u root -p scati < database/migration_toner_troca.sql
   ```

   Se o banco já existia antes do **patrimônio opcional** (antes da coluna
   `patrimonio` da tabela `equipamentos` aceitar valores em branco), rode
   também esta migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_patrimonio_indefinido.sql
   ```

   Se o banco já existia antes do **sistema de login** (antes da tabela
   `usuarios`), rode também esta migração incremental **uma única vez**
   — ela já cria o usuário administrador padrão (`Salomao`):
   ```bash
   mysql -u root -p scati < database/migration_usuarios.sql
   ```

   Se o banco já existia antes do **registro de usuário no histórico**
   (antes da coluna `usuario_nome` nas tabelas `historico_equipamentos` e
   `historico_estoque`), rode também esta migração incremental **uma
   única vez**:
   ```bash
   mysql -u root -p scati < database/migration_historico_usuario.sql
   ```

   Se o banco já existia antes do **Nome do Equipamento** (antes da
   coluna `nome` na tabela `equipamentos`), rode também esta migração
   incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_nome_equipamento.sql
   ```

   Se o banco já existia antes da aba de **Chamados** (antes da tabela
   `chamados`), rode também esta migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_chamados.sql
   ```

   Se o banco já existia antes do **perfil de usuário restrito a
   Chamados** (a tabela `usuarios` ainda tem a antiga coluna `admin` em
   vez de `perfil`), rode também esta migração incremental **uma única
   vez**:
   ```bash
   mysql -u root -p scati < database/migration_perfil_usuario.sql
   ```

   Se o banco já existia antes do **autor do chamado** (a tabela
   `chamados` ainda não tem a coluna `criado_por_id`), rode também esta
   migração incremental **uma única vez** (depois da migração acima):
   ```bash
   mysql -u root -p scati < database/migration_chamados_solicitante.sql
   ```

   Se o banco já tem a coluna `perfil` com o nome antigo do perfil
   restrito, **"Solicitante"** (em vez do nome atual, **"Usuário"**),
   rode também esta migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_renomeia_perfil_usuario.sql
   ```

   Se o banco já existia antes do **ramal e telefone do usuário**
   (a tabela `usuarios` ainda não tem as colunas `ramal`/`telefone`),
   rode também esta migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_ramal_telefone_usuario.sql
   ```

   Se o banco já existia antes das **respostas do chamado** (ainda não
   tem as tabelas `chamado_respostas` e `chamado_visualizacoes`), rode
   também esta migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_chamado_respostas.sql
   ```

   Se o banco já existia antes do **histórico automático do chamado**
   (ainda não tem a tabela `historico_chamados`), rode também esta
   migração incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_historico_chamados.sql
   ```

   Se o banco já existia antes das **observações do chamado** (ainda
   não tem a tabela `chamado_observacoes`), rode também esta migração
   incremental **uma única vez**:
   ```bash
   mysql -u root -p scati < database/migration_chamado_observacoes.sql
   ```

   Se você chegou a rodar uma versão anterior deste projeto em que o
   autor de uma observação escolhia individualmente quem mais podia
   vê-la (e por isso seu banco ainda tem a tabela
   `chamado_observacao_visualizadores`), esse recurso foi substituído
   por uma regra fixa — toda observação é visível para qualquer
   Administrador — então rode esta migração para remover a tabela que
   não é mais usada:
   ```bash
   mysql -u root -p scati < database/migration_remove_chamado_observacao_visualizadores.sql
   ```

   > Importante: ao importar qualquer um dos arquivos `.sql` deste projeto,
   > garanta que o cliente MySQL use UTF-8 (ex.: `mysql --default-character-set=utf8mb4 -u root -p < arquivo.sql`),
   > caso contrário os valores acentuados dos campos `ENUM` (como "Disponível")
   > podem ser salvos corrompidos.

2. **Configuração**
   Edite `config/database.php` e ajuste:
   - `DB_HOST`, `DB_NAME`, `DB_USER`, `DB_PASS` — credenciais do MySQL
   - `BASE_URL` — caminho onde o sistema será acessado
     (ex.: `/web-scati` se acessado em `http://localhost/web-scati`,
     ou `''` se estiver na raiz do domínio)

3. **Publicação**
   Copie a pasta `web-scati` inteira para o diretório público do seu
   servidor web (ex.: `htdocs`, `www`, `public_html`).

   Para testar rapidamente com o servidor embutido do PHP:
   ```bash
   cd web-scati
   php -S localhost:8000
   ```
   Neste caso, defina `BASE_URL` como `''` (string vazia) em `config/database.php`.

4. Acesse o sistema pelo navegador — a primeira tela será o **login**.
   Entre com o usuário administrador padrão (**usuário:** `Salomao`,
   **senha:** `scati2026`) e cadastre os demais usuários da equipe em
   **Usuários** (menu lateral). Depois, comece cadastrando as **Redes** e
   **Categorias de Estoque** (já vêm pré-cadastradas) antes dos equipamentos,
   para poder vinculá-los.

## Estrutura do projeto

```
web-scati/
├── config/
│   └── database.php        # Conexão PDO e constantes de configuração
├── includes/
│   ├── functions.php       # Funções auxiliares (formatação, histórico, etc.)
│   ├── auth.php            # Autenticação/sessão (exigirLogin, exigirAdmin)
│   ├── header.php          # Cabeçalho HTML + navbar (compartilhado)
│   ├── sidebar.php         # Menu lateral (compartilhado)
│   └── footer.php          # Rodapé HTML + scripts (compartilhado)
├── database/
│   └── scati.sql           # Script de criação do banco de dados
├── modules/
│   ├── auth/                # Login e logout
│   ├── usuarios/             # CRUD de usuários (somente administradores)
│   ├── equipamentos/       # CRUD + ficha detalhada (histórico e observações)
│   ├── compartilhamentos/  # CRUD de pastas de rede compartilhadas por servidores
│   ├── estoque/            # CRUD de itens de estoque
│   ├── redes/               # CRUD de redes
│   ├── licencas/            # CRUD de licenças + transferência entre equipamentos
│   └── relatorios/          # Relatórios básicos (seção 14 da documentação)
├── assets/
│   ├── css/style.css
│   └── js/scripts.js
└── index.php                # Dashboard
```

## Funcionalidades implementadas

- **Dashboard** com indicadores de equipamentos, estoque, licenças e (se
  preenchido) valor total do patrimônio, além de uma **Central de Alertas**
  no topo da página reunindo licenças vencidas ou vencendo nos próximos 30
  dias, itens de estoque abaixo do mínimo, impressoras sem toner vinculado
  e impressoras com a troca de toner por tempo de uso próxima ou vencida —
  cada alerta linka direto para a tela correspondente. Logo
  abaixo, o card **"Itens de Estoque por Categoria"** mostra em um gráfico
  de pizza (donut) como a quantidade em estoque está distribuída entre as
  categorias cadastradas, com legenda (quantidade e percentual), tooltip
  ao passar o mouse/focar em cada fatia e um botão para alternar para
  visualização em tabela. As 5 categorias com mais itens aparecem com cor
  própria; o restante é agrupado em "Outras categorias".
- **Equipamentos**: CRUD completo, pesquisa e filtros (tipo, status, rede),
  ficha individual com abas de Dados Gerais, Hardware, Licenciamento,
  Financeiro, Histórico e Observações. Todo equipamento tem um campo
  **Nome do Equipamento** (obrigatório, ex.: "Notebook do Financeiro"),
  usado como identificador principal (em negrito) nas listagens de
  Equipamentos e Impressoras, nos últimos equipamentos do Dashboard, na
  ficha, na ficha para impressão e nos relatórios — o **Patrimônio**
  continua aparecendo ao lado, em sua própria coluna, sem ser removido.
  Equipamentos cadastrados antes deste campo existir mostram o patrimônio
  (ou "Indefinido") no lugar do nome até serem editados. O campo
  **Patrimônio** em si é opcional — se deixado em branco, o equipamento é
  salvo e exibido como "Indefinido" em todas as listagens, fichas,
  relatórios e seletores, permitindo cadastrar vários equipamentos sem
  patrimônio definido ao mesmo tempo (a verificação de duplicidade
  continua ativa apenas entre patrimônios preenchidos). Os cabeçalhos
  **Tipo**, **Marca/Modelo**, **Responsável**, **Rede** e **Status** na
  listagem são clicáveis para ordenar a tabela por aquela coluna — clicar
  de novo inverte a direção (crescente/decrescente) — e os filtros de
  pesquisa continuam aplicados ao trocar a ordenação.
- **Impressoras**: aba própria no menu lateral com uma listagem separada,
  mostrando só os equipamentos do tipo Impressora (reaproveita a mesma
  tabela de Equipamentos, sem cadastro duplicado). Além de Patrimônio,
  Marca/Modelo, IP, Status e Localização, mostra também o **Toner**
  instalado — ou um alerta "Sem toner" quando a impressora não tem
  nenhum vinculado no momento. Tem pesquisa e filtro por status
  próprios. O botão "Nova Impressora" abre o cadastro de equipamentos
  já com o tipo Impressora selecionado; cadastro, edição, ficha e
  exclusão continuam nas mesmas telas do módulo de Equipamentos. Por não
  se aplicarem a impressoras, as abas/seções **Hardware** e
  **Licenciamento** não aparecem para esse tipo de equipamento (nem na
  ficha, nem no cadastro, nem na ficha para impressão).
- **Servidores**: tipo de equipamento cadastrado no mesmo módulo de
  Equipamentos, com campos adicionais (função do servidor, status
  Ativo/Inativo e observações) e uma aba própria de **Compartilhamentos**,
  onde é possível cadastrar pastas de rede (nome, caminho, descrição e
  permissões) e vincular cada uma a um ou mais computadores já cadastrados
  no sistema.
- **Computadores**: campos adicionais de **IP Fixo**, **Placa Mãe** e
  **Placa de Vídeo**, exibidos apenas quando o tipo do equipamento é
  Computador.
- **Acesso a Dispositivos USB**: checkbox "Permitir acesso a dispositivos
  USB" disponível no cadastro de **qualquer tipo de equipamento**, exibido
  na ficha como um badge (Permitido/Bloqueado) na seção Localização e Uso.
  Alterações ficam registradas no histórico do equipamento.
- **Anexos**: aba na ficha de qualquer equipamento para anexar arquivos
  (nota fiscal, foto, manual em PDF, etc.), com descrição opcional,
  download e exclusão. O upload valida extensão (imagens, PDF, Office,
  txt/csv, zip), tamanho máximo (10 MB) e MIME real do arquivo; os
  arquivos ficam em `uploads/anexos/` com nome gerado aleatoriamente e a
  pasta tem um `.htaccess` que bloqueia a execução de scripts. No
  celular, um botão "Tirar foto" (visível só em telas pequenas) abre a
  câmera diretamente em vez do seletor de arquivos genérico.
- **Ficha individual para impressão**: botão "Ficha para Impressão" na
  ficha do equipamento abre um cartão com os dados principais (inclusive
  os campos específicos de Servidor/Impressora/Computador), pronto para
  imprimir ou salvar em PDF e arquivar junto ao equipamento físico.
- **Histórico automático**: toda alteração de status, localização,
  responsável, rede, informações financeiras ou licenças gera um registro
  automático com data, hora, evento e descrição.
- **Observações**: registros cronológicos, nunca substituídos, com data e
  hora automáticas.
- **Estoque**: CRUD com controle de quantidade mínima, destaque visual para
  itens abaixo do mínimo e regra de quantidade nunca negativa (validada na
  aplicação e reforçada por `CHECK` no banco). Na listagem, cada item tem
  botões "+"/"-" (com um campo para escolher quanto ajustar de uma vez) que
  aumentam ou diminuem a quantidade diretamente, sem precisar abrir o
  formulário de edição; a redução nunca deixa a quantidade negativa e cada
  ajuste fica registrado no histórico do item. Ao cadastrar um item novo
  (tanto pela tela "Novo Item" do Estoque quanto pelo atalho "Cadastrar e
  Vincular" na ficha do equipamento), o sistema verifica se já existe um
  item com o mesmo **nome, marca e modelo** (sem diferenciar maiúsculas/
  minúsculas ou espaços); se existir, não cria um cadastro duplicado —
  soma a quantidade informada à quantidade já existente. Marca ou modelo
  diferentes (ex.: "Monitor" Asus e "Monitor" Acer) geram registros
  separados, mantendo cada modelo com sua própria quantidade e
  rastreável individualmente — inclusive a qual equipamento cada um
  está vinculado, visível na coluna "Vinculado a" da listagem de
  Estoque. A coluna **Observações** na mesma listagem mostra as anotações
  cadastradas em cada item (com um ícone, quando preenchida) — o texto
  completo aparece ao passar o mouse. A linha inteira do item também é
  clicável (como em Equipamentos) para abrir seu cadastro completo, onde
  as observações ficam visíveis no campo correspondente.
- **Itens Vinculados**: aba na ficha do equipamento para vincular itens já
  cadastrados no Estoque (periféricos, cabos, etc.) diretamente a ele, sem
  duplicar o cadastro. Cada vínculo consome 1 unidade da quantidade
  disponível do item (tabela `itens_vinculados`), permitindo que um mesmo
  item com várias unidades em estoque (ex.: "Adaptador de Vídeo",
  quantidade = 4) seja distribuído entre vários equipamentos ao mesmo
  tempo — vincular uma unidade não trava as demais. A tabela de itens
  vinculados (e a de Toner) agrupa por **nome do item**: quando o
  equipamento tem mais de um registro vinculado com o mesmo nome —
  seja a mesma marca/modelo (ex.: 2 cabos de rede) ou marcas/modelos
  diferentes cadastrados separadamente no Estoque (ex.: um monitor
  Asus e outro Acer), aparece uma única linha com o total somado na
  coluna "Qtd. Itens", e a coluna Marca/Modelo lista cada marca/modelo
  em sua própria linha com a respectiva quantidade vinculada (ex.:
  "Asus VP32AQ — 2" e "Acer V227Q — 1"). Cada linha de marca/modelo tem um
  ícone de observação (com o texto completo ao passar o mouse, quando o
  item tem alguma anotação) e um link para abrir o cadastro completo
  daquele item no Estoque — sem precisar sair da ficha do equipamento
  para conferir os detalhes. "Desvincular" sempre remove 1
  unidade por vez. Ao desvincular (ou ao
  excluir o equipamento), a unidade volta automaticamente para a
  quantidade disponível do item. Se o item ainda não existir no Estoque,
  não é preciso sair da ficha do equipamento: o botão "Cadastrar e
  Vincular" abre o formulário de cadastro completo (com todos os campos
  do cadastro padrão de Estoque, inclusive Observações) direto na aba, e
  ao confirmar o item já é criado e vinculado a este equipamento em uma
  única ação. Se já existir um item com o mesmo nome (mesmo que
  categoria, marca ou modelo sejam diferentes), nenhum cadastro
  duplicado é criado — a quantidade informada é somada ao item já
  existente, mantendo um único registro consolidado no Estoque, e 1
  unidade desse registro é vinculada ao equipamento.
- **Toner de impressoras**: aba própria "Toner" na ficha de equipamentos do
  tipo Impressora (reaproveita o mecanismo de Itens Vinculados acima,
  restrito à categoria de estoque "Toner"). Permite vincular, desvincular e
  também excluir o toner diretamente da tela da impressora, além do mesmo
  atalho "Cadastrar e Vincular" (com a mesma lógica de evitar duplicidade)
  para registrar e vincular um toner sem sair da página. A mesma seção
  "Toner" também aparece direto na tela de **edição** da impressora
  (Equipamentos > Editar), então dá pra vincular, desvincular ou cadastrar
  um toner sem precisar abrir a ficha separadamente. Ao editar um
  equipamento ainda não salvo, aparece um aviso pedindo para salvar o
  cadastro primeiro. Impressoras só podem ter itens de estoque da
  categoria "Toner" vinculados — a aba genérica "Itens Vinculados" (usada
  pelos demais tipos de equipamento) não aparece para impressoras, e o
  sistema recusa no servidor qualquer tentativa de vincular ou
  cadastrar-e-vincular um item de outra categoria a uma impressora.
- **Alerta de troca de toner por tempo de uso**: independente da
  vinculação de itens de Estoque acima, cada impressora pode ter uma
  **duração estimada do toner** (em dias, ex.: 90 ≈ 3 meses) definida no
  campo "Duração estimada do toner (dias)" em Equipamentos > Editar. Um
  botão **"Registrar Troca de Toner"** (na aba Toner da ficha e na tela de
  edição) grava a data de hoje como a última troca e reinicia a contagem —
  o evento fica registrado no Histórico do equipamento. Com base nessa data
  e na duração configurada, um painel mostra se a impressora está "Em dia",
  com a "Troca se aproximando" ou com a "Troca atrasada". Quando a troca
  está próxima ou vencida, a impressora aparece automaticamente na Central
  de Alertas do Dashboard. Como cada impressora tem sua própria duração
  configurada, locais com uso mais intenso podem ter um prazo menor que
  locais com uso mais leve.
- **Cadastro e exclusão de itens de estoque com histórico**: toda vez que
  um item é cadastrado no Estoque, um evento "Cadastro" é gravado
  automaticamente na tabela `historico_estoque`. A exclusão de qualquer
  item passa por uma tela de confirmação que exige um motivo por escrito
  antes de concluir — a exclusão só acontece depois do motivo preenchido,
  e fica registrada no histórico (inclusive no histórico do equipamento,
  se o item estava vinculado a algum). Todo evento do histórico (de
  equipamentos e de estoque) também grava **qual usuário** o realizou —
  a coluna "Usuário" aparece na aba Histórico da ficha do equipamento e
  no relatório "Histórico de alterações".
- **Redes**: CRUD simples, com contagem de equipamentos vinculados.
- **Licenças**: CRUD com a regra `1 equipamento : N licenças` (uma licença
  pertence a no máximo um equipamento) e tela dedicada de **transferência**
  de licença entre equipamentos, que registra o evento no histórico de
  ambos os equipamentos envolvidos.
- **Relatórios**: todos os relatórios listados na seção 14 da documentação
  (equipamentos, estoque, licenças e financeiro), com opção de impressão em
  layout limpo (sem navbar/menu lateral), pronto para impressão ou
  exportação em PDF pelo próprio diálogo de impressão do navegador. O
  relatório "Histórico de alterações" reúne o histórico de equipamentos,
  de itens de estoque e de chamados numa lista só, com filtros por tipo de
  ação, categoria, período e patrimônio/item/chamado.
- **Interface responsiva** com Bootstrap 5, menu lateral recolhível em
  telas pequenas.
- **Configurações**: tela central (menu lateral) com três ajustes do
  sistema:
  - **Categorias de Estoque**: CRUD completo das categorias usadas para
    classificar itens do Estoque — antes só podiam ser criadas via SQL
    direto. A categoria "Toner" é protegida contra renomeação e exclusão
    (é usada por nome em outras partes do sistema), e uma categoria com
    itens de estoque vinculados não pode ser excluída.
  - **Tipos de Manutenção**: CRUD completo dos tipos disponíveis ao
    registrar uma manutenção no histórico de um equipamento — antes era
    uma lista fixa no código. Excluir um tipo não afeta os registros já
    existentes no histórico.
  - **Alerta de Licenças**: define com quantos dias de antecedência uma
    licença a vencer aparece na Central de Alertas do Dashboard (antes
    fixo em 30 dias).
  - **Alerta de Troca de Toner**: define com quantos dias de antecedência
    uma impressora com a troca de toner se aproximando (com base na
    duração estimada configurada em cada impressora) aparece na Central de
    Alertas do Dashboard (padrão: 7 dias).
- **Login e usuários**: todas as páginas do sistema exigem autenticação
  (usuário e senha, com a senha armazenada com hash bcrypt via
  `password_hash()`). Cada usuário tem um **perfil de acesso**, definido
  na tela **Usuários** (menu lateral, restrita a administradores):
  - **Administrador**: acesso completo, inclusive à tela Usuários (criar,
    editar, redefinir senha e excluir outras contas).
  - **Padrão**: acesso completo ao sistema (cadastros, edições e
    exclusões em qualquer módulo), exceto gerenciar usuários.
  - **Usuário**: só acessa a aba **Chamados** — pode registrar
    chamados e acompanhar os que ele mesmo abriu, mas não enxerga o
    restante do sistema (Equipamentos, Estoque, Dashboard etc.) nem pode
    editar/excluir chamados, alterar prioridade/andamento ou se atribuir
    a um chamado. Pensado para quem só precisa abrir solicitações para a
    TI (ex.: recepção, outros setores), sem acesso operacional ao
    sistema.

  Cada usuário pode ter também **ramal** e **telefone** cadastrados
  (ambos opcionais), exibidos na coluna "Contato" da listagem — útil
  para saber rapidamente como entrar em contato com quem abriu ou está
  responsável por um chamado.

  O sistema sempre mantém pelo menos um administrador: não é possível
  excluir a própria conta logada, nem excluir ou remover o perfil de
  administrador do único administrador restante. A instalação já vem com
  um usuário administrador padrão (usuário `Salomao`) — recomenda-se
  trocar a senha após o primeiro acesso, ou cadastrar um novo
  administrador e excluir o padrão.
- **Chamados**: aba própria no menu lateral funcionando como uma tabela
  de pendências de TI, dividida em duas sub-abas:
  - **Chamados**: lista só os chamados em aberto (Aberto/Em
    andamento/Aguardando) — a pendência ativa do dia a dia. Chamados
    novos (que o usuário logado nunca abriu) sempre aparecem primeiro
    na lista, ordenados por prioridade entre eles; os demais mantêm a
    ordem de sempre. Mostra cartões de resumo no topo (Abertos, Em
    Andamento, Aguardando, Urgentes em Aberto), clicáveis para filtrar
    a lista pelo status correspondente (o cartão ativo fica destacado,
    com um atalho para limpar o filtro), e um filtro **"Parado há mais
    de (dias)"** para achar solicitações esquecidas.
  - **Resolvidos**: lista os chamados Concluídos/Cancelados, com as
    colunas **Solicitado em** e **Concluído em** (para os cancelados,
    mostra a data da última alteração com a marcação "cancelado", já
    que não têm data de conclusão). É também onde dá para efetivamente
    excluir um chamado antigo, já que a exclusão só é permitida depois
    de encerrado.

  Cada chamado tem título, **descrição** (a solicitação em si — campo
  obrigatório), o campo **Usuário** (quem pediu) e **prioridade**
  (Baixa/Média/Alta/Urgente — campo obrigatório). O campo Usuário já
  vem preenchido com o próprio usuário logado e fica bloqueado (só
  leitura); apenas o perfil **Administrador** pode digitar livremente
  quem é o usuário (por exemplo, ao registrar um chamado em nome de
  alguém que ligou ou pediu pessoalmente) — os demais perfis sempre
  abrem o chamado em seu próprio nome. O sistema também registra
  automaticamente qual usuário abriu cada chamado (usado para o perfil
  Usuário enxergar só os próprios).

  **Depois de criado, título, descrição e usuário não podem mais ser
  alterados por ninguém** — nem pelo Administrador — para preservar o
  pedido original; qualquer atualização a partir daí é feita pelas
  respostas. Prioridade e andamento podem
  ser alterados direto na listagem, através de um seletor colorido em
  cada linha, sem precisar abrir o cadastro — a mudança é salva assim
  que a opção é escolhida. Um botão **"Atribuir para mim"** (na
  listagem e no formulário), disponível apenas para o perfil
  Administrador, atribui o chamado a ele com um clique; o atalho "Meus
  Chamados" filtra só os chamados já atribuídos ao usuário logado. Ao
  marcar um chamado como "Concluído", a data de conclusão é registrada
  automaticamente; reabrir o chamado (mudar para qualquer outro
  andamento) limpa essa data. Por segurança, **nenhum chamado em
  aberto pode ser excluído** (nem por administrador) — só depois de
  marcado como Concluído ou Cancelado; o botão de excluir aparece
  desabilitado enquanto o chamado estiver aberto. E chamados de
  prioridade Alta ou Urgente ainda em aberto aparecem na Central de
  Alertas do Dashboard. A listagem também mostra um trecho da
  descrição e o usuário em cada linha, o tempo em aberto (ex.: "há 3
  dias"), e destaca com um fundo suave as linhas de chamados urgentes
  ou de alta prioridade ainda em aberto.

  Ao abrir a ficha de um chamado existente, aparece uma seção
  **Respostas** em formato de conversa: quem estiver envolvido no
  chamado (quem abriu, o responsável, ou qualquer Administrador/
  Padrão) pode escrever uma atualização, que fica registrada com nome
  e data/hora. O perfil **Usuário** também ganha acesso à ficha dos
  próprios chamados para acompanhar e responder — só não pode alterar
  título, descrição, prioridade, andamento ou responsável.

  O sistema de notificação é unificado: tanto a **contagem em
  vermelho ao lado de "Chamados" no menu lateral** quanto o **sininho**
  no topo da tela só aparecem quando existe uma novidade que o usuário
  logado ainda não viu — uma solicitação nova (chamado que ele nunca
  abriu) ou uma resposta nova em algum chamado — e tocam um bipe curto
  quando a novidade chega, enquanto o sistema estiver aberto em alguma
  aba do navegador (a verificação é feita a cada 25 segundos). As duas
  situações são destacadas de um jeito diferente, tanto na listagem de
  chamados quanto no menu do sininho: uma **solicitação nova** pinta a
  linha inteira (ou a caixa inteira do item, no sininho) de azul vivo;
  uma **mensagem nova** mostra um contador com a quantidade de
  respostas ainda não lidas naquele chamado — e quando as duas coisas
  acontecem ao mesmo tempo (uma solicitação nova que já recebeu
  resposta antes de ser aberta), aparecem as duas juntas: a linha
  pintada de azul **e** o contador. O sininho também mostra quem
  escreveu e uma prévia da última mensagem. Abrir a ficha do chamado
  marca tudo como lido — some a cor, o contador, a contagem do menu
  lateral e do sininho. Administrador e Padrão são avisados sobre
  qualquer chamado do sistema (já que
  enxergam a listagem inteira); o perfil Usuário só é avisado sobre os
  próprios chamados (como solicitante ou responsável) — e nunca sobre
  a própria solicitação que acabou de abrir, já que ele sabe que ela
  existe.

  > Nota técnica: a notificação sonora funciona enquanto o navegador
  > estiver aberto com alguma página do sistema carregada (mesmo em
  > segundo plano) — ela não "acorda" o computador/celular se o
  > navegador estiver fechado. Notificações push de verdade (que
  > funcionam mesmo com o navegador fechado) exigem HTTPS, que uma
  > instalação via XAMPP em rede local normalmente não tem.

  Cada chamado tem um histórico automático, registrando quem fez e
  quando: abertura (com a descrição da solicitação), mudanças de
  andamento (destacando quando o chamado é marcado como concluído), de
  prioridade e de responsável — capturado tanto ao editar a ficha
  quanto pelos atalhos rápidos da listagem (seletores inline e
  "Atribuir para mim"). Esse histórico não aparece mais na própria
  ficha do chamado; ele fica disponível apenas no relatório
  "Histórico de alterações" (aba **Relatórios**), junto com o
  histórico de equipamentos e estoque.

  Também tem uma seção **Observações**, visível só para o perfil
  Administrador (nem o solicitante, nem Padrão/Usuário veem essa
  seção): qualquer Administrador pode escrever uma anotação interna, e
  todos os outros Administradores conseguem ver — cada nota mostra
  quem escreveu e quando. Útil para lembretes ou combinados internos
  sobre o andamento de um chamado que não fazem sentido no histórico
  público de respostas.

## Notas de projeto

- O código usa **PDO com prepared statements** em todas as consultas para
  evitar SQL Injection, e `htmlspecialchars()` em todas as saídas para
  evitar XSS.
- A estrutura modular (uma pasta por módulo, funções isoladas em
  `includes/functions.php`) foi escolhida para permitir expansões futuras
  (ex.: autenticação, Help Desk, integração com AD) sem exigir reescrita do
  que já existe.
