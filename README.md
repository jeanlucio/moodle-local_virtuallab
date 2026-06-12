# Moodle Local Lab Virtual

[![Moodle Plugin CI](https://github.com/jeanlucio/moodle-local_labvirtual/actions/workflows/ci.yml/badge.svg)](https://github.com/jeanlucio/moodle-local_labvirtual/actions/workflows/ci.yml)
[![MDL Shield](https://img.shields.io/endpoint?url=https%3A%2F%2Fmdlshield.com%2Fapi%2Fbadge%2Flocal_labvirtual)](https://mdlshield.com/plugins/local_labvirtual)
![Moodle](https://img.shields.io/badge/Moodle-4.5%2B-orange?style=flat-square&logo=moodle&logoColor=white)
![License](https://img.shields.io/badge/License-GPLv3-blue?style=flat-square)
![Status](https://img.shields.io/badge/Status-Alpha-yellow?style=flat-square)

[English](#english) | [Português](#português)

---

## English

**Lab Virtual** (`local_labvirtual`) is a Moodle local plugin for **batch creation and lifecycle management of sandbox lab courses**, organised into **batches** (one per class or discipline), with a **self-service student panel** so students can pick and join their workspace without any admin intervention each semester.

Each batch lives in its own course subcategory, and its **responsible teachers manage only their own batch** — creating, editing, resetting and deleting their labs, and entering the courses without being enrolled — while the administrator oversees everything. All write operations are scoped to courses registered in the plugin's own tables, so it never touches courses that belong to other teachers or disciplines.

---

### ✨ Features

* 🗂 **Batch creation:** Create N lab courses in one step, each with a unique short name, inside the batch's own subcategory.
* 🔓 **Key-free enrolment:** Students join straight from the panel — one click picks the role (editor or visitor) and enrols them through the course manual enrolment instance. No enrolment keys, and the standard "enrolment options" page no longer shows confusing key blocks.
* 👥 **Multiple responsible teachers:** A batch can have several responsible teachers; all of them manage the batch and receive its notifications.
* 🎓 **Delegated management:** Responsible teachers get a scoped role on their batch subcategory, giving them full control of *their own* lab courses (edit content, grades and enrolments, enter without enrolling) — but no access to the rest of the platform.
* 🖥 **Self-service student panel:** Authenticated students see every lab in the batch, its current status, and join with a single click.
* 🚦 **Lab status logic:** Labs are automatically classified as *Available*, *In use*, or *Full* based on the number of enrolled editors.
* 🔒 **One-editor-anywhere rule:** A student who is already an editor in one lab cannot take the editor slot in another lab in the same batch — they can still join as visitor.
* ✏️ **Edit batch:** Rename a batch, add or remove co-responsible teachers, change the lab name prefix, and override lifecycle settings per batch.
* ⚙️ **Per-batch settings:** Each batch can override the global defaults for editors-per-lab and the lifecycle policy (months, action, warning days); leaving a field empty inherits the site default.
* 🔗 **Shareable panel URL:** Managers can copy and share a direct link to each batch's student panel.
* ♻️ **Reset / 🗑 delete / ☑️ bulk:** Reset clears user data and enrolments while keeping the course shell; delete removes the course and registry row; bulk actions reset or delete several labs at once.
* 🏭 **Delete batch:** Removes a whole batch, its labs and its (now empty) subcategory.
* ⏰ **Automatic lifecycle maintenance:** A nightly scheduled task resets or deletes overdue labs, applying each batch's own settings, with advance warning emails.
* 📧 **Lifecycle notifications:** Warning and summary emails are sent to the responsible teachers and to each affected course's editors; the administrator copy is optional.
* 🙋 **Helpful access notice:** A logged-in visitor who reaches a lab course without access sees a notice naming the responsible teacher(s) to contact.
* 🔐 **Ownership guard & audit events:** Every write operation validates that the lab belongs to the expected batch; events are emitted on course reset, course deletion and batch deletion.

---

### 🎓 Educational Purpose

Lab Virtual is designed for courses where each student or group needs an **isolated Moodle course** to build prototypes, pages, or deliverables without interfering with the official class or with other students' work.

Typical scenarios:

* Interface Design, Web Development, and similar project-based disciplines
* Technical and vocational training where learners need a personal sandbox
* Any discipline where students require their own configurable Moodle space each semester

Without this plugin, an admin must manually create dozens of courses, configure enrolments, and clean up at the end of every semester. Lab Virtual automates the entire cycle and lets each teacher run their own batch.

---

### 📦 Requirements

| Component | Version |
|-----------|---------|
| Moodle    | 4.5+    |
| PHP       | 8.2+    |

Tested against: Moodle 4.5, 5.0, 5.1, 5.2 × PostgreSQL and MariaDB.

---

### 🛠️ Installation

1. Download the `.zip` file or clone this repository.
2. Extract the folder into your Moodle `local/` directory.
3. Rename the folder to `labvirtual` (if necessary).
   Final path: `your-moodle/local/labvirtual/`
4. Visit **Site administration → Notifications** to complete the installation.

On install, the plugin provisions a shared **"Virtual labs"** course category and a delegated **"Lab Virtual batch manager"** role; the per-batch subcategory is created the first time you create a batch.

---

### 👤 Roles & access

| Who | Scope | Can do |
|-----|-------|--------|
| **Administrator** (system `local/labvirtual:manage`) | All batches | Create batches, assign responsible teachers, manage every batch and lab, change global settings. |
| **Responsible teacher** (delegated role on the batch subcategory) | Their own batch only | Create / reset / delete labs, edit the batch, edit course content, grades and enrolments, open the lab courses without enrolling. |
| **Student** | The student panel | Browse the batch labs and join one as editor or visitor. |

The delegated role mirrors the *editingteacher* capabilities (plus "view course without participation"), scoped to the batch subcategory — so a teacher has full control over their labs without seeing or managing the rest of the site.

---

### ⚙️ Settings

Navigate to **Site administration → Plugins → Local plugins → Lab Virtual**. These are **global defaults**; the first four can be overridden per batch from the *Edit batch* form.

| Setting | Description | Default |
|---------|-------------|---------|
| **Maximum editors per lab** | Number of `editingteacher` enrolments allowed before a lab is marked *Full*. | `3` |
| **Lifecycle (months)** | Labs older than this threshold (since last reset or creation) are processed by the nightly task. Set to `0` to disable. | `0` |
| **Automatic action** | What the nightly task does to overdue labs: *None*, *Reset*, or *Delete*. | `None` |
| **Warning days before action** | Days before the action when a warning email is sent. Set to `0` to disable warnings. | `7` |
| **Send a copy to the administrator** | When enabled, the admin also receives a consolidated copy of the lifecycle warning and summary emails. | Off |

The **Manage Lab Virtual** button in the settings page links directly to the management panel.

---

### 📖 Usage

#### Admin workflow

1. Go to **Site administration → Plugins → Local plugins → Lab Virtual → Manage Lab Virtual**.
2. Click **New batch** and fill in just the **batch name** and one or more **responsible teachers**. The batch gets its own subcategory automatically.
3. Open the batch to manage it, or hand it over to the responsible teacher.

#### Teacher workflow

1. The responsible teacher opens the management page and sees **only their own batches**.
2. **Create labs:** choose the lab name prefix (remembered for next time) and how many labs to create.
3. From the batch page they can add more labs, reset/delete labs (individually or in bulk), open any lab course to edit content and grades, copy the student panel URL, and **edit the batch** (name, co-responsible teachers, prefix, and per-batch lifecycle settings).

#### Student workflow

1. Open the student panel URL shared by the teacher.
2. Browse the labs showing name, status and current editors.
3. Click **Editor** or **Visitor** on an available lab to join and be taken into the course.
   * One click enrols as `editingteacher` (editor) or `student` (visitor) — no keys involved.
4. Once enrolled, the row shows **Enrolled**; a student already editing one lab cannot take an editor slot in another lab of the same batch.

---

### ⏰ Lifecycle Maintenance

The plugin ships a nightly scheduled task (`maintenance_task`) that runs at **02:00** by default. It processes **each batch with its own effective settings** (per-batch override, otherwise the global default).

For every lab it uses a reference date — `lastreset` when set, otherwise `timecreated` — and:

| Action | Effect |
|--------|--------|
| `None` | Batch is skipped (safe default). |
| `Reset` | Clears all user data and enrolments; updates `lastreset`. The course shell is preserved. |
| `Delete` | Removes the course and its registry row permanently. |

Before acting, the task emails a **warning** the configured number of days in advance; after acting, it emails a **summary**. Both go to the batch's responsible teachers and to the editors of each affected course, plus an optional consolidated copy to the administrator. Setting the batch (or global) `lifecycle months`/`action` to `0` disables processing without unregistering the task.

---

### 🧪 Automated Tests

Lab Virtual ships with a PHPUnit integration test suite. Every CI push runs against the full matrix (Moodle 4.5 → 5.2, PostgreSQL and MariaDB).

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `batch_manager_test.php` | 7 | Batch create/get, auto subcategory, multiple teachers, delegated-role isolation, update batch, set prefix, listing |
| `batch_settings_test.php` | 2 | Effective settings: global default vs per-batch override |
| `category_manager_test.php` | 3 | Safe deletion guard: empty batch subcategory removed; non-child or non-empty category kept |
| `course_factory_test.php` | 3 | Correct lab count; labs use the manual enrolment instance and no self instance; all IDs returned |
| `course_registry_test.php` | 10 | Ownership checks, bulk lookup and enrol-for-batch validation |
| `maintenance_service_test.php` | 5 | Reset/delete behaviour and wrong-batch guards; batch deletion |
| `maintenance_task_test.php` | 10 | Disabled cases, reset/delete overdue labs, per-batch override, reference-date logic |
| `notification_service_test.php` | 7 | Warning/summary to teachers and editors; admin copy gated by setting; course link |
| `panel_status_test.php` | 6 | Available/in-use/full flags; enrolment and one-editor-per-batch rules |
| **Total** | **53** | |

```bash
vendor/bin/phpunit --testsuite local_labvirtual_testsuite
```

---

### 🔐 Security & Compliance

* Two capabilities: `local/labvirtual:manage` (assignable at system for admins and at a course category for delegated teachers) and `local/labvirtual:view`.
* The management page is scoped by category context — admins manage every batch, a delegated teacher only their own; creating a batch stays admin-only.
* Every write action checks the capability in the correct context and verifies `require_sesskey()`.
* All DB queries use named parameters — no SQL injection surface.
* Cross-batch ownership check on every reset and delete: a lab can only be modified via the batch it was created in. Batch subcategory deletion is guarded so only an empty, plugin-owned subcategory is ever removed.
* The plugin does not store any personal data. The Privacy Provider declares this explicitly.

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

O **Lab Virtual** (`local_labvirtual`) é um plugin local Moodle para **criação e manutenção em lote de cursos-laboratório** (sandboxes isolados), organizados por **turmas**, com **painel self-service** para estudantes escolherem e acessarem seus ambientes sem intervenção do administrador a cada semestre.

Cada turma vive na sua própria subcategoria de cursos, e seus **professores responsáveis gerenciam apenas a turma deles** — criando, editando, resetando e excluindo seus labs, e entrando nos cursos sem se inscrever — enquanto o administrador supervisiona tudo. Todas as operações de escrita são restritas aos cursos registrados nas tabelas do plugin, então ele nunca toca em cursos de outras disciplinas ou professores.

---

### ✨ Funcionalidades

* 🗂 **Criação em lote:** Cria N cursos-lab em uma etapa, cada um com shortname único, dentro da subcategoria própria da turma.
* 🔓 **Inscrição sem chaves:** O estudante entra direto pelo painel — um clique escolhe o papel (editor ou visitante) e o inscreve pela instância de inscrição manual do curso. Sem chaves, e a tela padrão de "opções de inscrição" não mostra mais blocos de chave confusos.
* 👥 **Múltiplos professores responsáveis:** Uma turma pode ter vários responsáveis; todos gerenciam a turma e recebem as notificações.
* 🎓 **Gestão delegada:** Os responsáveis recebem um papel escopado na subcategoria da turma, com controle total dos cursos-lab *da turma deles* (editar conteúdo, notas e inscrições, entrar sem se inscrever) — sem acesso ao resto da plataforma.
* 🖥 **Painel self-service para estudantes:** Estudantes autenticados veem todos os labs da turma, o status atual e entram com um clique.
* 🚦 **Lógica de status do lab:** Classificados automaticamente como *Disponível*, *Em uso* ou *Cheio* conforme o número de editores inscritos.
* 🔒 **Regra de um editor por turma:** Quem já é editor em um lab não assume a vaga de editor em outro lab da mesma turma — mas ainda entra como visitante.
* ✏️ **Editar turma:** Renomear, adicionar/remover co-responsáveis, mudar o prefixo dos labs e sobrescrever as configurações de ciclo de vida por turma.
* ⚙️ **Configurações por turma:** Cada turma pode sobrescrever os padrões globais de editores-por-lab e da política de ciclo de vida (meses, ação, dias de aviso); campo vazio herda o padrão do site.
* 🔗 **URL do painel compartilhável:** Gestores copiam e compartilham o link direto do painel de cada turma.
* ♻️ **Resetar / 🗑 excluir / ☑️ em lote:** Reset limpa dados e inscrições preservando o curso; exclusão remove curso e registro; ações em lote resetam ou excluem vários labs de uma vez.
* 🏭 **Excluir turma:** Remove a turma inteira, seus labs e a subcategoria (já vazia).
* ⏰ **Manutenção automática do ciclo de vida:** Tarefa noturna reseta ou exclui labs vencidos, aplicando as configurações próprias de cada turma, com e-mails de aviso antecipado.
* 📧 **Notificações de ciclo de vida:** E-mails de aviso e resumo para os professores responsáveis e para os editores de cada curso afetado; a cópia ao administrador é opcional.
* 🙋 **Aviso de acesso:** Um visitante logado que chega a um curso-lab sem acesso vê um aviso com o nome do(s) professor(es) responsável(is) para contato.
* 🔐 **Proteção de propriedade e eventos de auditoria:** Toda operação de escrita valida que o lab pertence à turma esperada; eventos são emitidos ao resetar curso, excluir curso e excluir turma.

---

### 🎓 Finalidade Educacional

O Lab Virtual é projetado para disciplinas em que cada estudante ou grupo precisa de um **curso Moodle isolado** para construir protótipos, páginas ou entregas sem interferir na turma oficial nem no trabalho dos colegas.

Cenários típicos:

* Design de Interface, Desenvolvimento Web e disciplinas similares baseadas em projetos
* Formação técnica e profissional em que os estudantes precisam de um sandbox pessoal
* Qualquer disciplina em que os estudantes necessitam de um espaço Moodle configurável próprio a cada semestre

Sem este plugin, um administrador precisa criar manualmente dezenas de cursos, configurar inscrições e fazer a limpeza ao final de cada semestre. O Lab Virtual automatiza todo esse ciclo e permite que cada professor cuide da própria turma.

---

### 📦 Requisitos

| Componente | Versão |
|------------|--------|
| Moodle     | 4.5+   |
| PHP        | 8.2+   |

Testado contra: Moodle 4.5, 5.0, 5.1, 5.2 × PostgreSQL e MariaDB.

---

### 🛠️ Instalação

1. Baixe o arquivo `.zip` ou clone este repositório.
2. Extraia na pasta `local/` do seu Moodle.
3. Renomeie para `labvirtual` (se necessário).
   Caminho final: `seu-moodle/local/labvirtual/`
4. Acesse **Administração do site → Notificações** para concluir a instalação.

Na instalação, o plugin provisiona uma categoria de cursos compartilhada **"Laboratórios Virtuais"** e um papel delegado **"Gestor de turma do Laboratório Virtual"**; a subcategoria de cada turma é criada na primeira vez que você cria uma turma.

---

### 👤 Papéis & acesso

| Quem | Escopo | Pode fazer |
|------|--------|-----------|
| **Administrador** (`local/labvirtual:manage` no sistema) | Todas as turmas | Criar turmas, indicar responsáveis, gerenciar qualquer turma e lab, alterar configurações globais. |
| **Professor responsável** (papel delegado na subcategoria da turma) | Apenas a turma dele | Criar / resetar / excluir labs, editar a turma, editar conteúdo, notas e inscrições, abrir os cursos sem se inscrever. |
| **Estudante** | O painel do estudante | Navegar pelos labs da turma e entrar como editor ou visitante. |

O papel delegado espelha as capabilities de *editingteacher* (mais "ver curso sem participar"), escopado à subcategoria da turma — controle total dos labs sem ver ou gerenciar o resto do site.

---

### ⚙️ Configurações

Acesse **Administração do site → Plugins → Plugins locais → Lab Virtual**. São **padrões globais**; os quatro primeiros podem ser sobrescritos por turma no formulário *Editar turma*.

| Configuração | Descrição | Padrão |
|--------------|-----------|--------|
| **Máximo de editores por lab** | Inscrições como `editingteacher` permitidas antes do lab ser marcado como *Cheio*. | `3` |
| **Ciclo de vida (meses)** | Labs mais antigos que esse limite (desde o último reset ou criação) são processados pela tarefa noturna. Use `0` para desabilitar. | `0` |
| **Ação automática** | O que a tarefa noturna faz com labs vencidos: *Nenhuma*, *Resetar* ou *Excluir*. | `Nenhuma` |
| **Dias de aviso antes da ação** | Dias antes da ação em que um e-mail de aviso é enviado. Use `0` para desabilitar. | `7` |
| **Enviar cópia ao administrador** | Quando ativo, o admin também recebe uma cópia consolidada dos e-mails de aviso e resumo. | Desligado |

O botão **Gerenciar Lab Virtual** na página de configurações leva direto ao painel de gerenciamento.

---

### 📖 Como Usar

#### Fluxo do administrador

1. Acesse **Administração do site → Plugins → Plugins locais → Lab Virtual → Gerenciar Lab Virtual**.
2. Clique em **Nova turma** e informe apenas o **nome da turma** e um ou mais **professores responsáveis**. A turma ganha a própria subcategoria automaticamente.
3. Abra a turma para gerenciá-la, ou entregue-a ao professor responsável.

#### Fluxo do professor

1. O professor responsável abre a página de gerenciamento e vê **apenas as turmas dele**.
2. **Criar labs:** escolhe o prefixo do nome dos labs (lembrado para a próxima vez) e quantos labs criar.
3. Na página da turma ele pode adicionar mais labs, resetar/excluir labs (individual ou em lote), abrir qualquer curso-lab para editar conteúdo e notas, copiar a URL do painel e **editar a turma** (nome, co-responsáveis, prefixo e configurações de ciclo de vida da turma).

#### Fluxo do estudante

1. Abra a URL do painel compartilhada pelo professor.
2. Navegue pelos labs com nome, status e editores atuais.
3. Clique em **Editor** ou **Visitante** em um lab disponível para entrar e ser levado ao curso.
   * Um clique inscreve como `editingteacher` (editor) ou `student` (visitante) — sem chaves.
4. Após a inscrição, a linha mostra **Inscrito**; quem já edita um lab não assume vaga de editor em outro lab da mesma turma.

---

### ⏰ Manutenção do Ciclo de Vida

O plugin inclui uma tarefa agendada noturna (`maintenance_task`) que roda às **02:00** por padrão. Ela processa **cada turma com as configurações efetivas dela** (override da turma ou, na falta, o padrão global).

Para cada lab usa uma data de referência — `lastreset` quando definido, senão `timecreated` — e:

| Ação | Efeito |
|------|--------|
| `Nenhuma` | A turma é ignorada (padrão seguro). |
| `Resetar` | Limpa dados de usuários e inscrições; atualiza `lastreset`. A estrutura do curso é preservada. |
| `Excluir` | Remove o curso e o registro permanentemente. |

Antes de agir, a tarefa envia um **aviso** com a antecedência configurada; depois de agir, envia um **resumo**. Ambos vão para os professores responsáveis da turma e para os editores de cada curso afetado, mais uma cópia consolidada opcional ao administrador. Definir `meses`/`ação` (da turma ou global) como `0` desabilita o processamento sem cancelar o registro da tarefa.

---

### 🧪 Testes Automatizados

O Lab Virtual inclui uma suíte de testes de integração PHPUnit. Todo push de CI executa a matriz completa (Moodle 4.5 → 5.2, PostgreSQL e MariaDB).

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `batch_manager_test.php` | 7 | Criar/obter turma, subcategoria automática, múltiplos professores, isolamento do papel delegado, editar turma, definir prefixo, listagem |
| `batch_settings_test.php` | 2 | Configuração efetiva: padrão global vs override da turma |
| `category_manager_test.php` | 3 | Guard de exclusão segura: subcategoria vazia da turma é removida; categoria não-filha ou não-vazia é mantida |
| `course_factory_test.php` | 3 | Quantidade correta de labs; labs usam a instância manual e nenhuma self; todos os IDs retornados |
| `course_registry_test.php` | 10 | Verificações de propriedade, busca em lote e validação de inscrição por turma |
| `maintenance_service_test.php` | 5 | Reset/exclusão e guards de turma errada; exclusão de turma |
| `maintenance_task_test.php` | 10 | Casos desabilitados, reset/exclusão de labs vencidos, override por turma, lógica de data de referência |
| `notification_service_test.php` | 7 | Aviso/resumo para professores e editores; cópia ao admin condicionada à config; link do curso |
| `panel_status_test.php` | 6 | Flags disponível/em uso/cheio; regras de inscrição e de um editor por turma |
| **Total** | **53** | |

```bash
vendor/bin/phpunit --testsuite local_labvirtual_testsuite
```

---

### 🔐 Segurança e Conformidade

* Duas capabilities: `local/labvirtual:manage` (assinável no sistema para admins e numa categoria de curso para professores delegados) e `local/labvirtual:view`.
* A página de gerenciamento é escopada por contexto de categoria — admins gerenciam todas as turmas, o professor delegado só as dele; criar turma continua exclusivo do admin.
* Cada ação de escrita verifica a capability no contexto correto e valida `require_sesskey()`.
* Todas as queries usam parâmetros nomeados — sem superfície de SQL injection.
* Verificação de propriedade cruzada em todo reset e exclusão: um lab só pode ser modificado pela turma em que foi criado. A exclusão da subcategoria é protegida para remover apenas uma subcategoria vazia e pertencente ao plugin.
* O plugin não armazena dados pessoais. O Privacy Provider declara isso explicitamente.

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
