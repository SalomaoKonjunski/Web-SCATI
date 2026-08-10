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

4. Acesse o sistema pelo navegador e comece cadastrando as **Redes** e
   **Categorias de Estoque** (já vêm pré-cadastradas) antes dos equipamentos,
   para poder vinculá-los.

## Estrutura do projeto

```
web-scati/
├── config/
│   └── database.php        # Conexão PDO e constantes de configuração
├── includes/
│   ├── functions.php       # Funções auxiliares (formatação, histórico, etc.)
│   ├── header.php          # Cabeçalho HTML + navbar (compartilhado)
│   ├── sidebar.php         # Menu lateral (compartilhado)
│   └── footer.php          # Rodapé HTML + scripts (compartilhado)
├── database/
│   └── scati.sql           # Script de criação do banco de dados
├── modules/
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
  dias, itens de estoque abaixo do mínimo e impressoras sem toner
  vinculado — cada alerta linka direto para a tela correspondente. Logo
  abaixo, o card **"Itens de Estoque por Categoria"** mostra em um gráfico
  de pizza (donut) como a quantidade em estoque está distribuída entre as
  categorias cadastradas, com legenda (quantidade e percentual), tooltip
  ao passar o mouse/focar em cada fatia e um botão para alternar para
  visualização em tabela. As 5 categorias com mais itens aparecem com cor
  própria; o restante é agrupado em "Outras categorias".
- **Equipamentos**: CRUD completo, pesquisa e filtros (tipo, status, rede),
  ficha individual com abas de Dados Gerais, Hardware, Licenciamento,
  Financeiro, Histórico e Observações.
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
  item com o mesmo nome (sem diferenciar maiúsculas/minúsculas ou espaços);
  se existir, não cria um cadastro duplicado — soma a quantidade informada
  à quantidade já existente, mantendo um único registro por nome de item.
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
  "Asus VP32AQ — 2" e "Acer V227Q — 1"). "Desvincular" sempre remove 1
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
  para registrar e vincular um toner sem sair da página.
- **Cadastro e exclusão de itens de estoque com histórico**: toda vez que
  um item é cadastrado no Estoque, um evento "Cadastro" é gravado
  automaticamente na tabela `historico_estoque`. A exclusão de qualquer
  item passa por uma tela de confirmação que exige um motivo por escrito
  antes de concluir — a exclusão só acontece depois do motivo preenchido,
  e fica registrada no histórico (inclusive no histórico do equipamento,
  se o item estava vinculado a algum).
- **Redes**: CRUD simples, com contagem de equipamentos vinculados.
- **Licenças**: CRUD com a regra `1 equipamento : N licenças` (uma licença
  pertence a no máximo um equipamento) e tela dedicada de **transferência**
  de licença entre equipamentos, que registra o evento no histórico de
  ambos os equipamentos envolvidos.
- **Relatórios**: todos os relatórios listados na seção 14 da documentação
  (equipamentos, estoque, licenças e financeiro), com opção de impressão em
  layout limpo (sem navbar/menu lateral), pronto para impressão ou
  exportação em PDF pelo próprio diálogo de impressão do navegador. O
  relatório "Histórico de alterações" reúne o histórico de equipamentos e
  de itens de estoque numa lista só, com filtros por tipo de ação,
  categoria, período e patrimônio/item.
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

## Notas de projeto

- O sistema foi propositalmente mantido **sem autenticação/login** e sem
  módulo de usuários, pois a documentação define "Controle de usuários do
  sistema" como fora do escopo desta versão.
- O código usa **PDO com prepared statements** em todas as consultas para
  evitar SQL Injection, e `htmlspecialchars()` em todas as saídas para
  evitar XSS.
- A estrutura modular (uma pasta por módulo, funções isoladas em
  `includes/functions.php`) foi escolhida para permitir expansões futuras
  (ex.: autenticação, Help Desk, integração com AD) sem exigir reescrita do
  que já existe.
