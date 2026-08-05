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
  preenchido) valor total do patrimônio.
- **Equipamentos**: CRUD completo, pesquisa e filtros (tipo, status, rede),
  ficha individual com abas de Dados Gerais, Hardware, Licenciamento,
  Financeiro, Histórico e Observações.
- **Servidores**: tipo de equipamento cadastrado no mesmo módulo de
  Equipamentos, com campos adicionais (função do servidor, status
  Ativo/Inativo e observações) e uma aba própria de **Compartilhamentos**,
  onde é possível cadastrar pastas de rede (nome, caminho, descrição e
  permissões) e vincular cada uma a um ou mais computadores já cadastrados
  no sistema.
- **Histórico automático**: toda alteração de status, localização,
  responsável, rede, informações financeiras ou licenças gera um registro
  automático com data, hora, evento e descrição.
- **Observações**: registros cronológicos, nunca substituídos, com data e
  hora automáticas.
- **Estoque**: CRUD com controle de quantidade mínima, destaque visual para
  itens abaixo do mínimo e regra de quantidade nunca negativa (validada na
  aplicação e reforçada por `CHECK` no banco).
- **Itens Vinculados**: aba na ficha do equipamento para vincular itens já
  cadastrados no Estoque (periféricos, cabos, etc.) diretamente a ele, sem
  duplicar o cadastro. Cada vínculo consome 1 unidade da quantidade
  disponível do item (tabela `itens_vinculados`), permitindo que um mesmo
  item com várias unidades em estoque (ex.: "Adaptador de Vídeo",
  quantidade = 4) seja distribuído entre vários equipamentos ao mesmo
  tempo — vincular uma unidade não trava as demais. Ao desvincular (ou ao
  excluir o equipamento), a unidade volta automaticamente para a
  quantidade disponível do item.
- **Redes**: CRUD simples, com contagem de equipamentos vinculados.
- **Licenças**: CRUD com a regra `1 equipamento : N licenças` (uma licença
  pertence a no máximo um equipamento) e tela dedicada de **transferência**
  de licença entre equipamentos, que registra o evento no histórico de
  ambos os equipamentos envolvidos.
- **Relatórios**: todos os relatórios listados na seção 14 da documentação
  (equipamentos, estoque, licenças e financeiro), com opção de impressão em
  layout limpo (sem navbar/menu lateral), pronto para impressão ou
  exportação em PDF pelo próprio diálogo de impressão do navegador.
- **Interface responsiva** com Bootstrap 5, menu lateral recolhível em
  telas pequenas.

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
