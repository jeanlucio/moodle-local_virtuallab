<?php
// This file is part of Moodle - https://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

/**
 * Brazilian Portuguese language strings for local_virtuallab.
 *
 * @package    local_virtuallab
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['access_as_editor'] = 'Acessar {$a} como titular';
$string['access_as_visitor'] = 'Acessar {$a} como visitante';
$string['access_via_teacher'] = 'O acesso a este laboratório é fornecido pelo(s) professor(es) responsável(is): {$a}. Entre em contato para ser inscrito.';
$string['already_enrolled'] = 'Inscrito';
$string['apply_checklist_existing'] = 'Aplicar lista de tarefas aos laboratórios existentes';
$string['batch_category'] = 'Categoria de destino';
$string['batch_checklist_tasks'] = 'Lista de tarefas dos laboratórios';
$string['batch_checklist_tasks_help'] = 'As tarefas informadas aqui, uma por linha, são adicionadas ao bloco Checklist do Professor de cada laboratório criado depois disso. Use "Aplicar lista de tarefas aos laboratórios existentes" para propagar mudanças aos laboratórios já criados. Deixe em branco para desativar.';
$string['batch_created'] = 'Turma criada com sucesso.';
$string['batch_deleted'] = 'Turma e todos os seus laboratórios excluídos com sucesso.';
$string['batch_lifecycle_recount_note'] = 'Alterar a política de ciclo de vida abaixo reinicia a contagem a partir de hoje para todos os labs desta turma, então labs existentes nunca são resetados ou excluídos sem aviso.';
$string['batch_lifecycle_zero_hint'] = '(0 = desabilitado)';
$string['batch_name'] = 'Nome da turma';
$string['batch_nameprefix'] = 'Prefixo do nome dos laboratórios';
$string['batch_override_default'] = 'Usar o padrão do site';
$string['batch_override_default_hint'] = 'Usar o padrão do site ({$a})';
$string['batch_override_placeholder'] = 'Padrão: {$a}';
$string['batch_overrides'] = 'Configurações próprias da turma (opcional)';
$string['batch_overrides_help'] = 'Deixe um campo vazio para usar o padrão do site nesta turma.';
$string['batch_teacher'] = 'Professor responsável';
$string['batch_updated'] = 'Turma atualizada com sucesso';
$string['bulk_action'] = 'Com selecionados';
$string['bulk_action_delete'] = 'Excluir';
$string['bulk_action_reset'] = 'Resetar';
$string['checklist_synced'] = 'Lista de tarefas aplicada a {$a} laboratório(s).';
$string['confirm_bulk_delete'] = 'Tem certeza que deseja excluir {$a} laboratório(s) selecionado(s)? Todos os cursos e seus conteúdos serão removidos permanentemente. Esta ação não pode ser desfeita.';
$string['confirm_bulk_reset'] = 'Tem certeza que deseja resetar {$a} laboratório(s) selecionado(s)? Todos os dados de usuários e atividades serão apagados.';
$string['confirm_delete_batch'] = 'Tem certeza que deseja excluir a turma <strong>{$a}</strong> e TODOS os seus laboratórios? Todos os cursos e seus conteúdos serão removidos permanentemente. Esta ação não pode ser desfeita.';
$string['confirm_delete_lab'] = 'Tem certeza que deseja excluir o laboratório <strong>{$a}</strong>? O curso e todo o seu conteúdo serão removidos permanentemente.';
$string['confirm_reset_lab'] = 'Tem certeza que deseja resetar o laboratório <strong>{$a}</strong>? Todos os dados de usuários e atividades serão apagados.';
$string['copy_panel_link'] = 'Copiar link';
$string['create_batch'] = 'Nova turma';
$string['create_labs'] = 'Criar laboratórios';
$string['currenteditors'] = 'Titulares atuais';
$string['delete_batch'] = 'Excluir turma';
$string['delete_batch_label'] = 'Excluir turma: {$a}';
$string['delete_lab'] = 'Excluir';
$string['delete_lab_label'] = 'Excluir laboratório: {$a}';
$string['edit_batch'] = 'Editar turma';
$string['email_action_delete'] = 'excluídos';
$string['email_action_reset'] = 'resetados';
$string['email_manage_link'] = 'Abrir o gerenciamento do Lab Virtual';
$string['email_panel_link'] = 'Abrir o painel de laboratórios';
$string['email_summary_body'] = 'A manutenção automática de ciclo de vida acabou de ser executada. Os laboratórios abaixo foram processados:';
$string['email_summary_editor_body'] = 'A manutenção automática de ciclo de vida acabou de ser executada. O(s) laboratório(s) que você editava foram processados:';
$string['email_summary_failed'] = 'falhou';
$string['email_summary_ok'] = 'concluído';
$string['email_summary_subject'] = 'Lab Virtual — resumo de manutenção';
$string['email_warning_body'] = 'Os laboratórios abaixo serão {$a->action} até {$a->date} pela política automática de ciclo de vida. Acesse-os ou reset-os antes dessa data se ainda precisar do conteúdo.';
$string['email_warning_editor_body'] = 'O(s) laboratório(s) que você edita abaixo serão {$a->action} até {$a->date}. Salve o que precisar antes dessa data.';
$string['email_warning_subject'] = 'Lab Virtual — seus laboratórios serão {$a->action} em {$a->days} dia(s)';
$string['enrol_join'] = 'Acessar e ingressar';
$string['error_already_editor_in_batch'] = 'Você já está inscrito como titular em outro laboratório desta turma.';
$string['error_batch_not_found'] = 'Turma não encontrada ou não pertence a este site.';
$string['error_course_not_managed'] = 'Este curso não é gerenciado pelo Lab Virtual.';
$string['error_enrol_mismatch'] = 'Instância de inscrição não corresponde ao laboratório solicitado.';
$string['error_lab_full'] = 'Este laboratório atingiu o número máximo de titulares.';
$string['error_no_labs_selected'] = 'Nenhum laboratório selecionado. Selecione ao menos um laboratório.';
$string['error_too_many_labs'] = 'Você pode criar no máximo {$a} laboratórios por vez.';
$string['eventbatchdeleted'] = 'Turma excluída';
$string['eventbatchdeleted_desc'] = 'A turma com id {$a->batchid} foi excluída ({$a->labcount} laboratório(s) removido(s)).';
$string['eventcoursedeleted'] = 'Laboratório excluído';
$string['eventcoursedeleted_desc'] = 'O laboratório {$a->courseid} foi excluído da turma {$a->batchid}.';
$string['eventcoursereset'] = 'Laboratório resetado';
$string['eventcoursereset_desc'] = 'O laboratório {$a->courseid} foi resetado na turma {$a->batchid}.';
$string['export_csv'] = 'Exportar CSV';
$string['export_excel'] = 'Exportar Excel';
$string['lab_available'] = 'Disponível';
$string['lab_count'] = 'Quantidade de laboratórios';
$string['lab_deleted'] = 'Laboratório excluído com sucesso.';
$string['lab_full'] = 'Lotado';
$string['lab_in_use'] = 'Em uso';
$string['lab_reset'] = 'Laboratório resetado com sucesso.';
$string['lab_slots_left'] = '{$a} vagas';
$string['lab_slots_left_one'] = '1 vaga';
$string['labs_bulk_deleted'] = '{$a} laboratório(s) excluído(s) com sucesso.';
$string['labs_bulk_reset'] = '{$a} laboratório(s) resetado(s) com sucesso.';
$string['labs_created'] = '{$a} laboratório(s) criado(s) com sucesso.';
$string['lastreset'] = 'Último reset';
$string['manage_batches'] = 'Gerenciar Lab Virtual';
$string['manage_labs'] = 'Gerenciar laboratórios — {$a}';
$string['message_batch_assigned_body'] = 'Você foi designado como professor responsável pela turma "{$a}". Agora você pode gerenciar os laboratórios pelo link abaixo.';
$string['message_batch_assigned_small'] = 'Você foi designado como professor responsável de uma turma do Lab Virtual.';
$string['message_batch_assigned_subject'] = 'Lab Virtual: você foi designado à turma "{$a}"';
$string['messageprovider:batch_assigned'] = 'Designado como professor responsável de turma do Lab Virtual';
$string['next_action'] = 'Próxima ação';
$string['next_action_delete'] = 'Excluir em {$a}';
$string['next_action_reset'] = 'Resetar em {$a}';
$string['nobatches'] = 'Nenhuma turma encontrada. Clique no botão abaixo para adicionar uma nova turma.';
$string['nolabs'] = 'Nenhum laboratório nesta turma ainda.';
$string['panel_url'] = 'URL do painel de estudantes';
$string['panel_url_copied'] = 'URL copiada para a área de transferência.';
$string['panel_url_help'] = 'Compartilhe esta URL com os estudantes para que acessem e escolham seu laboratório sandbox.';
$string['panel_url_qrcode'] = 'QR code do painel de estudantes';
$string['parentcategory'] = 'Laboratórios Virtuais';
$string['pluginname'] = 'Lab Virtual';
$string['privacy:metadata'] = 'O plugin Lab Virtual não armazena dados pessoais diretamente. Registros de cursos e inscrições são gerenciados pelo núcleo do Moodle.';
$string['report'] = 'Relatório';
$string['report_back_to_batch'] = 'Voltar ao relatório da turma';
$string['report_col_action'] = 'Ação';
$string['report_col_component'] = 'Módulo';
$string['report_col_count'] = 'Qtd';
$string['report_col_enrolled_at'] = 'Entrou em';
$string['report_col_events'] = 'Eventos';
$string['report_col_lab'] = 'Laboratório';
$string['report_col_last_access'] = 'Último acesso';
$string['report_col_last_time'] = 'Último em';
$string['report_col_role'] = 'Papel';
$string['report_col_student'] = 'Estudante';
$string['report_heading'] = 'Relatório de uso dos labs: {$a}';
$string['report_lab_detail_heading'] = 'Detalhe do lab: {$a}';
$string['report_never'] = 'Nunca';
$string['report_no_activity'] = 'Nenhuma atividade registrada';
$string['report_no_enrolments'] = 'Nenhum estudante inscrito em algum laboratório ainda.';
$string['report_role_editor'] = 'Titular';
$string['report_role_visitor'] = 'Visitante';
$string['report_view_report'] = 'Relatório';
$string['report_view_report_label'] = 'Ver relatório de uso: {$a}';
$string['reset_lab'] = 'Resetar';
$string['reset_lab_label'] = 'Resetar laboratório: {$a}';
$string['role_batchmanager'] = 'Gestor de turma do Laboratório Virtual';
$string['role_batchmanager_desc'] = 'Dá ao professor responsável o controle total dos cursos da própria turma (editar conteúdo, notas e inscrições, sem precisar se inscrever) além das ferramentas de turma do Laboratório Virtual.';
$string['save_batch'] = 'Salvar turma';
$string['settings_lifecycle'] = 'Ciclo de vida';
$string['settings_lifecycle_action'] = 'Ação automática';
$string['settings_lifecycle_action_delete'] = 'Excluir';
$string['settings_lifecycle_action_desc'] = 'Ação a realizar nos laboratórios vencidos. Defina como "Nenhuma" para desabilitar o processamento automático.';
$string['settings_lifecycle_action_none'] = 'Nenhuma (desabilitado)';
$string['settings_lifecycle_action_reset'] = 'Resetar';
$string['settings_lifecycle_months'] = 'Ciclo de vida (meses)';
$string['settings_lifecycle_months_desc'] = 'Número de meses antes de um laboratório ser considerado vencido para ação automática. O prazo é medido a partir da data do último reset ou, se nunca foi resetado, da data de criação. Defina como 0 para desabilitar.';
$string['settings_max_teachers'] = 'Máximo de titulares por laboratório';
$string['settings_max_teachers_desc'] = 'Número máximo de titulares por laboratório antes de ser marcado como lotado. Padrão: 3.';
$string['settings_notify_admin'] = 'Enviar cópia ao administrador';
$string['settings_notify_admin_desc'] = 'Quando habilitado, o administrador do site também recebe uma cópia consolidada dos e-mails de aviso e de resumo do ciclo de vida.';
$string['settings_warning_days'] = 'Dias de aviso antes da ação';
$string['settings_warning_days_desc'] = 'Número de dias antes da ação de ciclo de vida em que um e-mail de aviso é enviado ao professor responsável. Defina como 0 para desabilitar os e-mails de aviso.';
$string['show_qrcode'] = 'Exibir QR code';
$string['task_maintenance'] = 'Lab Virtual — manutenção de ciclo de vida';
$string['view_course'] = 'Visualizar curso';
$string['view_course_label'] = 'Visualizar curso: {$a}';
$string['view_panel'] = 'Painel de estudantes';
$string['virtuallab:manage'] = 'Gerenciar Lab Virtual (criar turmas e laboratórios, resetar, excluir)';
$string['virtuallab:view'] = 'Visualizar painel de estudantes do Lab Virtual';
