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

All write operations are scoped to courses registered in the plugin's own tables — it will never touch courses that belong to other teachers or disciplines.

---

### ✨ Features

* 🗂 **Batch Creation:** Create N lab courses in one step, each with a unique short name and two self-enrolment instances (editor + visitor key).
* 🔑 **Dual Enrolment Keys:** Each lab gets a key for the `editingteacher` role and a separate key for the `student` role.
* 🖥 **Self-Service Student Panel:** Authenticated students see every lab in the batch, its current status, and can enrol themselves without teacher intervention.
* 🚦 **Lab Status Logic:** Labs are automatically classified as *Available*, *In Use*, or *Full* based on the number of enrolled editors.
* 🔒 **One-Editor-Anywhere Rule:** A student who is already an editor in one lab cannot take the editor slot in another lab in the same batch — they can still join as visitor.
* 🔗 **Shareable Panel URL:** Admins can copy and share a direct link to the student panel for each batch.
* ♻️ **Reset Labs:** Clears all user data, enrolments and activity state from a lab while keeping the course shell intact.
* 🗑 **Delete Labs:** Removes the Moodle course and the plugin registry row in a single action.
* ☑️ **Bulk Operations:** Select multiple labs and reset or delete them all at once.
* 🏭 **Delete Batch:** Removes a whole batch and every lab course it contains.
* ⏰ **Automatic Lifecycle Maintenance:** A nightly scheduled task resets or deletes labs that have exceeded the configured lifecycle threshold (measured from last reset or creation date).
* 🔐 **Ownership Guard:** Every write operation validates that the lab belongs to the expected batch, preventing cross-batch modifications.
* 📋 **Audit Events:** Emits Moodle events on course reset, course deletion, and batch deletion.

---

### 🎓 Educational Purpose

Lab Virtual is designed for courses where each student or group needs an **isolated Moodle course** to build prototypes, pages, or deliverables without interfering with the official class or with other students' work.

Typical scenarios:

* Interface Design, Web Development, and similar project-based disciplines
* Technical and vocational training where learners need a personal sandbox
* Any discipline where students require their own configurable Moodle space each semester

Without this plugin, an admin must manually create dozens of courses, configure enrolment keys, and clean up at the end of every semester. Lab Virtual automates the entire cycle.

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

---

### ⚙️ Settings

Navigate to **Site administration → Plugins → Local plugins → Lab Virtual**.

| Setting | Description | Default |
|---------|-------------|---------|
| **Maximum editors per lab** | Number of `editingteacher` enrolments allowed before a lab is marked *Full*. | `3` |
| **Show enrolment keys in student panel** | Whether editor and visitor keys are displayed to authenticated students. | Enabled |
| **Lifecycle (months)** | Labs older than this threshold (since last reset or creation) are processed by the nightly task. Set to `0` to disable. | `0` |
| **Automatic action** | What the nightly task does to overdue labs: *None*, *Reset*, or *Delete*. | `None` |

The **Manage Lab Virtual** button in the settings page links directly to the admin management panel.

---

### 📖 Usage

#### Admin workflow

1. Go to **Site administration → Plugins → Local plugins → Lab Virtual → Manage Lab Virtual**.
2. Fill in the **New Batch** form:
   * Batch name and lab name prefix
   * Responsible teacher
   * Destination Moodle category
   * Number of labs to create
   * Editor key and visitor key
3. Click **Create labs**. The plugin creates all courses and enrolment instances immediately.
4. From the batch management page, you can:
   * Add more labs to an existing batch
   * Reset or delete individual labs
   * Use the bulk-select checkboxes to reset or delete multiple labs at once
   * Copy the student panel URL to share with the class
   * Delete an entire batch (removes all labs and the batch record)

#### Student workflow

1. Open the student panel URL shared by the teacher.
2. Browse the list of labs showing name, status, and current editors.
3. Click **Enrol and join** on an available lab.
   * The editor key enrols as `editingteacher`; the visitor key enrols as `student`.
4. Once enrolled, the button changes to **View course** — no re-enrolment is possible in the same batch.

---

### ⏰ Lifecycle Maintenance

The plugin ships a nightly scheduled task (`maintenance_task`) that runs at **02:00** by default.

The task selects labs whose reference date exceeds the configured `lifecycle_months` threshold:

* If `lastreset > 0`: uses `lastreset` as the reference date.
* If `lastreset = 0` (never reset): uses `timecreated`.

Depending on `lifecycle_action`:

| Action | Effect |
|--------|--------|
| `None` | Task runs but takes no action (safe default). |
| `Reset` | Clears all user data and enrolments; updates `lastreset`. The course shell is preserved. |
| `Delete` | Removes the course and its registry row permanently. |

Setting `lifecycle_months = 0` or `lifecycle_action = None` completely disables automatic processing without unregistering the task.

---

### 🧪 Automated Tests

Lab Virtual ships with a PHPUnit integration test suite. Every CI push runs against the full matrix (Moodle 4.5 → 5.2, PostgreSQL and MariaDB).

| Test file | Cases | What is covered |
|-----------|------:|----------------|
| `batch_manager_test.php` | 3 | Batch creation, joined list query, not-found exception |
| `course_factory_test.php` | 4 | Correct lab count, exactly 2 enrol_self instances per lab, keys stored in `{enrol}.password`, all course IDs returned and registered |
| `maintenance_service_test.php` | 5 | Reset updates `lastreset` and keeps course; reset with wrong batch throws; delete removes course and row; delete with wrong batch throws; delete batch removes all labs |
| `maintenance_task_test.php` | 7 | Task no-ops when months=0 or action=0; resets overdue lab; deletes overdue lab; skips recent lab; uses `lastreset` over `timecreated`; processes all overdue labs in a batch |
| `panel_status_test.php` | 7 | Available / in-use / full status flags; full lab hides editor key and disables editor button; enrolled user blocked from re-enrolment; editor-elsewhere blocks editor button on other labs |
| **Total** | **26** | |

```bash
vendor/bin/phpunit --testsuite local_labvirtual
```

---

### 🔐 Security & Compliance

* Two capabilities: `local/labvirtual:manage` (manager/admin) and `local/labvirtual:view` (all authenticated users).
* Every write action checks the capability and verifies `require_sesskey()`.
* All DB queries use named parameters — no SQL injection surface.
* Cross-batch ownership check on every reset and delete: a lab can only be modified via the batch it was created in.
* The plugin does not store any personal data. Privacy Provider declares this explicitly.

---

## 📄 License / Licença

This project is licensed under the **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio

---

## Português

O **Lab Virtual** (`local_labvirtual`) é um plugin local Moodle para **criação e manutenção em lote de cursos-laboratório** (sandboxes isolados), organizados por **turmas**, com **painel self-service** para alunos escolherem e acessarem seus ambientes de trabalho sem intervenção do administrador a cada semestre.

Todas as operações de escrita são restritas aos cursos registrados nas tabelas do plugin — ele nunca toca em cursos de outras disciplinas ou professores.

---

### ✨ Funcionalidades

* 🗂 **Criação em Lote:** Cria N cursos-lab em uma única etapa, cada um com shortname único e duas instâncias de auto-inscrição (chave editor + chave visitante).
* 🔑 **Chaves de Acesso Duplas:** Cada lab recebe uma chave para o papel `editingteacher` (editor) e uma chave separada para o papel `student` (visitante).
* 🖥 **Painel Self-Service para Estudantes:** Estudantes autenticados visualizam todos os labs da turma, o status atual e podem se inscrever sem intervenção do professor.
* 🚦 **Lógica de Status do Lab:** Os labs são classificados automaticamente como *Disponível*, *Em uso* ou *Cheio* com base no número de editores inscritos.
* 🔒 **Regra de Um Editor por Turma:** O estudante que já é editor em um lab não pode assumir a vaga de editor em outro lab da mesma turma — mas ainda pode entrar como visitante.
* 🔗 **URL do Painel Compartilhável:** O admin pode copiar e compartilhar o link direto para o painel de estudantes de cada turma.
* ♻️ **Resetar Labs:** Limpa dados de usuários, inscrições e estado das atividades, preservando a estrutura do curso.
* 🗑 **Deletar Labs:** Remove o curso Moodle e o registro do plugin em uma única ação.
* ☑️ **Operações em Lote:** Selecione múltiplos labs e resete ou delete todos de uma vez.
* 🏭 **Deletar Turma:** Remove uma turma inteira e todos os seus labs de uma vez.
* ⏰ **Manutenção Automática do Ciclo de Vida:** Uma tarefa agendada noturna reseta ou deleta labs que ultrapassaram o limite de tempo configurado (medido desde o último reset ou a criação).
* 🔐 **Proteção de Propriedade:** Cada operação de escrita valida que o lab pertence à turma esperada, evitando modificações cruzadas.
* 📋 **Eventos de Auditoria:** Emite eventos Moodle ao resetar curso, deletar curso e deletar turma.

---

### 🎓 Finalidade Educacional

O Lab Virtual é projetado para disciplinas em que cada aluno ou grupo precisa de um **curso Moodle isolado** para construir protótipos, páginas ou entregas sem interferir na turma oficial nem no trabalho dos colegas.

Cenários típicos:

* Design de Interface, Desenvolvimento Web e disciplinas similares baseadas em projetos
* Formação técnica e profissional em que os estudantes precisam de um sandbox pessoal
* Qualquer disciplina em que os alunos necessitam de um espaço Moodle configurável próprio a cada semestre

Sem este plugin, um administrador precisa criar manualmente dezenas de cursos, configurar chaves de inscrição e fazer a limpeza ao final de cada semestre. O Lab Virtual automatiza todo esse ciclo.

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

---

### ⚙️ Configurações

Acesse **Administração do site → Plugins → Plugins locais → Lab Virtual**.

| Configuração | Descrição | Padrão |
|--------------|-----------|--------|
| **Máximo de editores por lab** | Número de inscrições como `editingteacher` permitidas antes do lab ser marcado como *Cheio*. | `3` |
| **Exibir chaves no painel do estudante** | Define se as chaves (editor e visitante) ficam visíveis para estudantes autenticados. | Ativado |
| **Ciclo de vida (meses)** | Labs mais antigos que esse limite (desde o último reset ou criação) são processados pela tarefa noturna. Use `0` para desabilitar. | `0` |
| **Ação automática** | O que a tarefa noturna faz com labs vencidos: *Nenhuma*, *Resetar* ou *Deletar*. | `Nenhuma` |

O botão **Gerenciar Lab Virtual** na página de configurações leva diretamente ao painel de administração.

---

### 📖 Como Usar

#### Fluxo do administrador

1. Acesse **Administração do site → Plugins → Plugins locais → Lab Virtual → Gerenciar Lab Virtual**.
2. Preencha o formulário **Nova Turma**:
   * Nome da turma e prefixo do nome dos labs
   * Professor responsável
   * Categoria Moodle de destino
   * Quantidade de labs a criar
   * Chave de editor e chave de visitante
3. Clique em **Criar labs**. O plugin cria todos os cursos e instâncias de inscrição imediatamente.
4. Na página de gerenciamento da turma, você pode:
   * Adicionar mais labs a uma turma existente
   * Resetar ou deletar labs individualmente
   * Usar os checkboxes para resetar ou deletar múltiplos labs de uma vez
   * Copiar a URL do painel para compartilhar com a turma
   * Deletar a turma inteira (remove todos os labs e o registro da turma)

#### Fluxo do estudante

1. Abra a URL do painel compartilhada pelo professor.
2. Navegue pela lista de labs com nome, status e editores atuais.
3. Clique em **Inscrever-se e entrar** em um lab disponível.
   * A chave de editor inscreve como `editingteacher`; a chave de visitante inscreve como `student`.
4. Após a inscrição, o botão muda para **Ver curso** — não é possível se inscrever novamente na mesma turma.

---

### ⏰ Manutenção do Ciclo de Vida

O plugin inclui uma tarefa agendada noturna (`maintenance_task`) que roda às **02:00** por padrão.

A tarefa seleciona labs cuja data de referência ultrapassou o limite configurado em `lifecycle_months`:

* Se `lastreset > 0`: usa `lastreset` como data de referência.
* Se `lastreset = 0` (nunca foi resetado): usa `timecreated`.

Dependendo de `lifecycle_action`:

| Ação | Efeito |
|------|--------|
| `Nenhuma` | A tarefa roda mas não age (padrão seguro). |
| `Resetar` | Limpa dados de usuários e inscrições; atualiza `lastreset`. A estrutura do curso é preservada. |
| `Deletar` | Remove o curso e o registro permanentemente. |

Definir `lifecycle_months = 0` ou `lifecycle_action = Nenhuma` desabilita o processamento automático sem cancelar o registro da tarefa.

---

### 🧪 Testes Automatizados

O Lab Virtual inclui uma suíte de testes de integração PHPUnit. Todo push de CI executa a matriz completa (Moodle 4.5 → 5.2, PostgreSQL e MariaDB).

| Arquivo de teste | Casos | O que é coberto |
|-----------------|------:|----------------|
| `batch_manager_test.php` | 3 | Criação de turma, consulta com JOIN, exceção de não encontrado |
| `course_factory_test.php` | 4 | Quantidade correta de labs, exatamente 2 instâncias enrol_self por lab, chaves salvas em `{enrol}.password`, todos os IDs retornados e registrados |
| `maintenance_service_test.php` | 5 | Reset atualiza `lastreset` e mantém o curso; reset com turma errada lança exceção; delete remove curso e registro; delete com turma errada lança exceção; delete de turma remove todos os labs |
| `maintenance_task_test.php` | 7 | Tarefa não age quando months=0 ou action=0; reseta lab vencido; deleta lab vencido; ignora lab recente; usa `lastreset` em vez de `timecreated`; processa todos os labs vencidos da turma |
| `panel_status_test.php` | 7 | Flags de status disponível / em uso / cheio; lab cheio oculta chave de editor e desabilita botão; estudante inscrito bloqueado de nova inscrição; editor-em-outro-lab bloqueia botão de editor nos demais |
| **Total** | **26** | |

```bash
vendor/bin/phpunit --testsuite local_labvirtual
```

---

### 🔐 Segurança e Conformidade

* Duas capabilities: `local/labvirtual:manage` (gerente/admin) e `local/labvirtual:view` (todos os usuários autenticados).
* Cada ação de escrita verifica a capability e valida `require_sesskey()`.
* Todas as queries usam parâmetros nomeados — sem superfície de SQL injection.
* Verificação de propriedade cruzada em todo reset e delete: um lab só pode ser modificado pela turma em que foi criado.
* O plugin não armazena dados pessoais. O Privacy Provider declara isso explicitamente.

---

## 📄 Licença

Este projeto é licenciado sob a **GNU General Public License v3 (GPLv3)**.

**Copyright:** 2026 Jean Lúcio
