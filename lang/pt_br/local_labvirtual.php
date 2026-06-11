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
 * Brazilian Portuguese language strings for local_labvirtual.
 *
 * @package    local_labvirtual
 * @copyright  2026 Jean Lúcio
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();
// phpcs:disable moodle.Files.LineLength

$string['access_as_editor'] = 'Acessar {$a} como editor';
$string['access_as_visitor'] = 'Acessar {$a} como visitante';
$string['already_enrolled'] = 'Inscrito';
$string['batch_category'] = 'Categoria de destino';
$string['batch_created'] = 'Turma criada com sucesso.';
$string['batch_deleted'] = 'Turma e todos os seus laboratórios excluídos com sucesso.';
$string['batch_keys'] = 'Chaves de inscrição';
$string['batch_name'] = 'Nome da turma';
$string['batch_nameprefix'] = 'Prefixo do nome dos laboratórios';
$string['batch_teacher'] = 'Professor responsável';
$string['bulk_action'] = 'Com selecionados';
$string['bulk_action_delete'] = 'Excluir';
$string['bulk_action_reset'] = 'Resetar';
$string['confirm_bulk_delete'] = 'Tem certeza que deseja excluir {$a} laboratório(s) selecionado(s)? Todos os cursos e seus conteúdos serão removidos permanentemente. Esta ação não pode ser desfeita.';
$string['confirm_bulk_reset'] = 'Tem certeza que deseja resetar {$a} laboratório(s) selecionado(s)? Todos os dados de usuários e atividades serão apagados.';
$string['confirm_delete_batch'] = 'Tem certeza que deseja excluir a turma <strong>{$a}</strong> e TODOS os seus laboratórios? Todos os cursos e seus conteúdos serão removidos permanentemente. Esta ação não pode ser desfeita.';
$string['confirm_delete_lab'] = 'Tem certeza que deseja excluir o laboratório <strong>{$a}</strong>? O curso e todo o seu conteúdo serão removidos permanentemente.';
$string['confirm_reset_lab'] = 'Tem certeza que deseja resetar o laboratório <strong>{$a}</strong>? Todos os dados de usuários e atividades serão apagados.';
$string['copy_panel_link'] = 'Copiar link';
$string['create_batch'] = 'Nova turma';
$string['create_labs'] = 'Criar laboratórios';
$string['currenteditors'] = 'Editores atuais';
$string['delete_batch'] = 'Excluir turma';
$string['delete_batch_label'] = 'Excluir turma: {$a}';
$string['delete_lab'] = 'Excluir';
$string['delete_lab_label'] = 'Excluir laboratório: {$a}';
$string['email_action_delete'] = 'excluídos';
$string['email_action_reset'] = 'resetados';
$string['email_manage_link'] = 'Abrir o gerenciamento do Lab Virtual';
$string['email_panel_link'] = 'Abrir o painel de laboratórios';
$string['email_summary_body'] = 'A manutenção automática de ciclo de vida acabou de ser executada. Os laboratórios abaixo foram processados:';
$string['email_summary_failed'] = 'falhou';
$string['email_summary_ok'] = 'concluído';
$string['email_summary_subject'] = 'Lab Virtual — resumo de manutenção';
$string['email_warning_body'] = 'Os laboratórios abaixo serão {$a->action} até {$a->date} pela política automática de ciclo de vida. Acesse-os ou reset-os antes dessa data se ainda precisar do conteúdo.';
$string['email_warning_subject'] = 'Lab Virtual — seus laboratórios serão {$a->action} em {$a->days} dia(s)';
$string['enrol_join'] = 'Acessar e ingressar';
$string['error_already_editor_in_batch'] = 'Você já está inscrito como editor em outro laboratório desta turma.';
$string['error_batch_not_found'] = 'Turma não encontrada ou não pertence a este site.';
$string['error_course_not_managed'] = 'Este curso não é gerenciado pelo Lab Virtual.';
$string['error_enrol_mismatch'] = 'Instância de inscrição não corresponde ao laboratório solicitado.';
$string['error_lab_full'] = 'Este laboratório atingiu o número máximo de editores.';
$string['error_no_labs_selected'] = 'Nenhum laboratório selecionado. Selecione ao menos um laboratório.';
$string['eventbatchdeleted'] = 'Turma excluída';
$string['eventbatchdeleted_desc'] = 'A turma com id {$a->batchid} foi excluída ({$a->labcount} laboratório(s) removido(s)).';
$string['eventcoursedeleted'] = 'Laboratório excluído';
$string['eventcoursedeleted_desc'] = 'O laboratório {$a->courseid} foi excluído da turma {$a->batchid}.';
$string['eventcoursereset'] = 'Laboratório resetado';
$string['eventcoursereset_desc'] = 'O laboratório {$a->courseid} foi resetado na turma {$a->batchid}.';
$string['key_editor'] = 'Chave de editor';
$string['key_visitor'] = 'Chave de visitante';
$string['lab_available'] = 'Disponível';
$string['lab_count'] = 'Quantidade de laboratórios';
$string['lab_deleted'] = 'Laboratório excluído com sucesso.';
$string['lab_full'] = 'Lotado';
$string['lab_in_use'] = 'Em uso';
$string['lab_reset'] = 'Laboratório resetado com sucesso.';
$string['labs_bulk_deleted'] = '{$a} laboratório(s) excluído(s) com sucesso.';
$string['labs_bulk_reset'] = '{$a} laboratório(s) resetado(s) com sucesso.';
$string['labs_created'] = '{$a} laboratório(s) criado(s) com sucesso.';
$string['lastreset'] = 'Último reset';
$string['local/labvirtual:manage'] = 'Gerenciar Lab Virtual (criar turmas e laboratórios, resetar, excluir)';
$string['local/labvirtual:view'] = 'Visualizar painel de estudantes do Lab Virtual';
$string['manage_batches'] = 'Gerenciar Lab Virtual';
$string['manage_labs'] = 'Gerenciar laboratórios — {$a}';
$string['nobatches'] = 'Nenhuma turma encontrada. Crie a primeira usando o formulário acima.';
$string['nolabs'] = 'Nenhum laboratório encontrado nesta turma. Use o formulário acima para criá-los.';
$string['panel_url'] = 'URL do painel de estudantes';
$string['panel_url_copied'] = 'URL copiada para a área de transferência.';
$string['panel_url_help'] = 'Compartilhe esta URL com os estudantes para que acessem e escolham seu laboratório sandbox.';
$string['pluginname'] = 'Lab Virtual';
$string['privacy:metadata'] = 'O plugin Lab Virtual não armazena dados pessoais diretamente. Registros de cursos e inscrições são gerenciados pelo núcleo do Moodle.';
$string['reset_lab'] = 'Resetar';
$string['reset_lab_label'] = 'Resetar laboratório: {$a}';
$string['settings_lifecycle'] = 'Ciclo de vida';
$string['settings_lifecycle_action'] = 'Ação automática';
$string['settings_lifecycle_action_delete'] = 'Excluir';
$string['settings_lifecycle_action_desc'] = 'Ação a realizar nos laboratórios vencidos. Defina como "Nenhuma" para desabilitar o processamento automático.';
$string['settings_lifecycle_action_none'] = 'Nenhuma (desabilitado)';
$string['settings_lifecycle_action_reset'] = 'Resetar';
$string['settings_lifecycle_months'] = 'Ciclo de vida (meses)';
$string['settings_lifecycle_months_desc'] = 'Número de meses antes de um laboratório ser considerado vencido para ação automática. O prazo é medido a partir da data do último reset ou, se nunca foi resetado, da data de criação. Defina como 0 para desabilitar.';
$string['settings_max_teachers'] = 'Máximo de editores por laboratório';
$string['settings_max_teachers_desc'] = 'Número máximo de usuários com o papel de professor editor permitidos por laboratório antes de ser marcado como lotado. Padrão: 3.';
$string['settings_show_keys'] = 'Exibir chaves de inscrição no painel de estudantes';
$string['settings_show_keys_desc'] = 'Quando habilitado, ambas as chaves de inscrição (editor e visitante) são exibidas aos estudantes autenticados no painel.';
$string['settings_warning_days'] = 'Dias de aviso antes da ação';
$string['settings_warning_days_desc'] = 'Número de dias antes da ação de ciclo de vida em que um e-mail de aviso é enviado ao professor responsável. Defina como 0 para desabilitar os e-mails de aviso.';
$string['task_maintenance'] = 'Lab Virtual — manutenção de ciclo de vida';
$string['view_course'] = 'Visualizar curso';
$string['view_course_label'] = 'Visualizar curso: {$a}';
$string['view_panel'] = 'Painel de estudantes';
