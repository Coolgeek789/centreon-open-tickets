<?php
/*
 * Copyright 2019 Centreon (http://www.centreon.com/)
 *
 * Centreon is a full-fledged industry-strength solution that meets
 * the needs in IT infrastructure and application monitoring for
 * service performance.
 *
 * Licensed under the Apache License, Version 2.0 (the "License");
 * you may not use this file except in compliance with the License.
 * You may obtain a copy of the License at
 *
 *    http://www.apache.org/licenses/LICENSE-2.0
 *
 * Unless required by applicable law or agreed to in writing, software
 * distributed under the License is distributed on an "AS IS" BASIS,*
 * WITHOUT WARRANTIES OR CONDITIONS OF ANY KIND, either express or implied.
 * See the License for the specific language governing permissions and
 * limitations under the License.
 */

class JiraProvider extends AbstractProvider
{
    protected $_proxy_enabled = 1;

    const JIRA_PROJECT = 30;
    const JIRA_ASSIGNEE = 31;
    const JIRA_ISSUETYPE = 32;
    const JIRA_PRIORITY = 33;

    const ARG_PROJECT = 1;
    const ARG_SUMMARY = 2;
    const ARG_DESCRIPTION = 3;
    const ARG_ASSIGNEE = 4;
    const ARG_ISSUETYPE = 5;
    const ARG_PRIORITY = 6;

    protected $_internal_arg_name = array(
        self::ARG_PROJECT => 'Project',
        self::ARG_SUMMARY => 'Summary',
        self::ARG_DESCRIPTION => 'Description',
        self::ARG_ASSIGNEE => 'Assignee',
        self::ARG_PRIORITY => 'Priority',
        self::ARG_ISSUETYPE => 'IssueType',
    );

    function __destruct()
    {
    }

    /**
     * Set default extra value
     *
     * @return void
     */
    protected function _setDefaultValueExtra()
    {
        $this->default_data['address'] = 'xxx.atlassian.net';
        $this->default_data['rest_api_resource'] = '/rest/api/latest/';
        $this->default_data['timeout'] = 60;

        $this->default_data['clones']['mappingTicket'] = array(
            array(
                'Arg' => self::ARG_SUMMARY,
                'Value' => 'Issue {include file="file:$centreon_open_tickets_path/providers/' .
                    'Abstract/templates/display_title.ihtml"}'
            ),
            array('Arg' => self::ARG_DESCRIPTION, 'Value' => '{$body}'),
            array('Arg' => self::ARG_PROJECT, 'Value' => '{$select.jira_project.id}'),
            array('Arg' => self::ARG_ASSIGNEE, 'Value' => '{$select.jira_assignee.value}'),
            array('Arg' => self::ARG_PRIORITY, 'Value' => '{$select.jira_priority.id}'),
            array('Arg' => self::ARG_ISSUETYPE, 'Value' => '{$select.jira_issuetype.id}'),
        );
    }

    protected function _setDefaultValueMain($body_html = 0)
    {
        parent::_setDefaultValueMain($body_html);

        #$this->default_data['url'] = 'http://{$address}/index.pl?Action=AgentTicketZoom;TicketNumber={$ticket_id}';
        $this->default_data['clones']['groupList'] = array(
            array(
                'Id' => 'jira_project',
                'Label' => _('Jira project'),
                'Type' => self::JIRA_PROJECT,
                'Filter' => '',
                'Mandatory' => '1'
            ),
            array(
                'Id' => 'jira_priority',
                'Label' => _('Jira priority'),
                'Type' => self::JIRA_PRIORITY,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'jira_assignee',
                'Label' => _('Jira assignee'),
                'Type' => self::JIRA_ASSIGNEE,
                'Filter' => '',
                'Mandatory' => ''
            ),
            array(
                'Id' => 'jira_issuetype',
                'Label' => _('Jira issue type'),
                'Type' => self::JIRA_ISSUETYPE,
                'Filter' => '',
                'Mandatory' => '1'
            ),
        );
    }

    /**
     * Check form
     *
     * @return a string
     */
    protected function _checkConfigForm()
    {
        $this->_check_error_message = '';
        $this->_check_error_message_append = '';

        $this->_checkFormValue('address', "Please set 'Address' value");
        $this->_checkFormValue('rest_api_resource', "Please set 'Rest Api Resource' value");
        $this->_checkFormValue('timeout', "Please set 'Timeout' value");
        $this->_checkFormValue('username', "Please set 'Username' value");
        $this->_checkFormValue('user_token', "Please set 'User Token' value");
        $this->_checkFormValue('macro_ticket_id', "Please set 'Macro Ticket ID' value");
        $this->_checkFormInteger('timeout', "'Timeout' must be a number");
        $this->_checkFormInteger('confirm_autoclose', "'Confirm popup autoclose' must be a number");

        $this->_checkLists();

        if ($this->_check_error_message != '') {
            throw new Exception($this->_check_error_message);
        }
    }

    /**
     * Build the specifc config: from, to, subject, body, headers
     *
     * @return void
     */
    protected function _getConfigContainer1Extra()
    {
        $tpl = $this->initSmartyTemplate('providers/Jira/templates');

        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign("img_brick", "./modules/centreon-open-tickets/images/brick.png");
        $tpl->assign("header", array("jira" => _("Jira")));

        // Form
        $address_html = '<input size="50" name="address" type="text" value="' .
            $this->_getFormValue('address') . '" />';
        $rest_api_resource_html = '<input size="50" name="rest_api_resource" type="text" value="' .
            $this->_getFormValue('rest_api_resource') . '" />';
        $username_html = '<input size="50" name="username" type="text" value="' .
            $this->_getFormValue('username') . '" />';
        $user_token_html = '<input size="50" name="user_token" type="password" value="' .
            $this->_getFormValue('user_token') . '" autocomplete="off" />';
        $timeout_html = '<input size="2" name="timeout" type="text" value="' .
            $this->_getFormValue('timeout') . '" />';

        $array_form = array(
            'address' => array(
                'label' => _("Address") . $this->_required_field,
                'html' => $address_html
            ),
            'rest_api_resource' => array(
                'label' => _("Rest Api Resource") . $this->_required_field,
                'html' => $rest_api_resource_html
            ),
            'username' => array(
                'label' => _("Username") . $this->_required_field,
                'html' => $username_html
            ),
            'user_token' => array(
                'label' => _("User Token") . $this->_required_field,
                'html' => $user_token_html
            ),
            'timeout' => array('label' => _("Timeout"), 'html' => $timeout_html),
            'mappingticket' => array('label' => _("Mapping ticket arguments")),
        );

        // mapping Ticket clone
        $mappingTicketValue_html = '<input id="mappingTicketValue_#index#" name="mappingTicketValue[#index#]" ' .
            'size="20"  type="text" />';
        $mappingTicketArg_html = '<select id="mappingTicketArg_#index#" name="mappingTicketArg[#index#]" ' .
            'type="select-one">' .
        '<option value="' . self::ARG_PROJECT . '">' . _('Project') . '</options>' .
        '<option value="' . self::ARG_SUMMARY . '">' . _('Summary') . '</options>' .
        '<option value="' . self::ARG_DESCRIPTION . '">' . _('Description') . '</options>' .
        '<option value="' . self::ARG_ASSIGNEE . '">' . _('Assignee') . '</options>' .
        '<option value="' . self::ARG_PRIORITY . '">' . _('Priority') . '</options>' .
        '<option value="' . self::ARG_ISSUETYPE . '">' . _('Issue Type') . '</options>' .
        '</select>';
        $array_form['mappingTicket'] = array(
            array('label' => _("Argument"), 'html' => $mappingTicketArg_html),
            array('label' => _("Value"), 'html' => $mappingTicketValue_html),
        );

        $tpl->assign('form', $array_form);

        $this->_config['container1_html'] .= $tpl->fetch('conf_container1extra.ihtml');

        $this->_config['clones']['mappingTicket'] = $this->_getCloneValue('mappingTicket');
    }

    /**
     * Build the specific advanced config: -
     *
     * @return void
     */
    protected function _getConfigContainer2Extra()
    {
    }

    protected function saveConfigExtra()
    {
        $this->_save_config['simple']['address'] = $this->_submitted_config['address'];
        $this->_save_config['simple']['rest_api_resource'] = $this->_submitted_config['rest_api_resource'];
        $this->_save_config['simple']['username'] = $this->_submitted_config['username'];
        $this->_save_config['simple']['user_token'] = $this->_submitted_config['user_token'];
        $this->_save_config['simple']['timeout'] = $this->_submitted_config['timeout'];

        $this->_save_config['clones']['mappingTicket'] = $this->_getCloneSubmitted(
            'mappingTicket',
            array('Arg', 'Value')
        );
    }

    protected function getGroupListOptions()
    {
        $str = '<option value="' . self::JIRA_PROJECT . '">Jira project</options>' .
        '<option value="' . self::JIRA_ASSIGNEE . '">Jira assignee</options>' .
        '<option value="' . self::JIRA_ISSUETYPE . '">Jira issue type</options>' .
        '<option value="' . self::JIRA_PRIORITY . '">Jira priority</options>';
        return $str;
    }

    protected function assignJiraProject($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listProjectJira();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_jira_call_response as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('jira_project', $this->_jira_call_response);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignJiraPriority($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listPriorityJira();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_jira_call_response as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('jira_priority', $this->_jira_call_response);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignJiraIssuetype($entry, &$groups_order, &$groups)
    {
        // no filter $entry['Filter']. preg_match used
        $code = $this->listIssuetypeJira();

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) .  (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_jira_call_response as $row) {
            if (!isset($entry['Filter']) || is_null($entry['Filter']) || $entry['Filter'] == '') {
                $result[$row['id']] = $this->to_utf8($row['name']);
                continue;
            }

            if (preg_match('/' . $entry['Filter'] . '/', $row['name'])) {
                $result[$row['id']] = $this->to_utf8($row['name']);
            }
        }

        $this->saveSession('jira_issuetype', $this->_jira_call_response);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignJiraUser($entry, &$groups_order, &$groups, $label_session)
    {
        $code = $this->listUserJira($entry['Filter']);

        $groups[$entry['Id']] = array(
            'label' => _($entry['Label']) . (
                isset($entry['Mandatory']) && $entry['Mandatory'] == 1 ? $this->_required_field : ''
            ),
            'sort' => (isset($entry['Sort']) && $entry['Sort'] == 1 ? 1 : 0)
        );
        $groups_order[] = $entry['Id'];

        if ($code == -1) {
            $groups[$entry['Id']]['code'] = -1;
            $groups[$entry['Id']]['msg_error'] = $this->ws_error;
            return 0;
        }

        $result = array();
        foreach ($this->_jira_call_response as $row) {
            $result[$row['key']] = $this->to_utf8($row['name']);
        }

        $this->saveSession($label_session, $this->_jira_call_response);
        $groups[$entry['Id']]['values'] = $result;
    }

    protected function assignOthers($entry, &$groups_order, &$groups)
    {
        if ($entry['Type'] == self::JIRA_PROJECT) {
            $this->assignJiraProject($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::JIRA_ASSIGNEE) {
            $this->assignJiraUser($entry, $groups_order, $groups, 'jira_assignee');
        } elseif ($entry['Type'] == self::JIRA_ISSUETYPE) {
            $this->assignJiraIssuetype($entry, $groups_order, $groups);
        } elseif ($entry['Type'] == self::JIRA_PRIORITY) {
            $this->assignJiraPriority($entry, $groups_order, $groups);
        }
    }

    public function validateFormatPopup()
    {
        $result = array('code' => 0, 'message' => 'ok');

        $this->validateFormatPopupLists($result);

        return $result;
    }

    protected function assignSubmittedValuesSelectMore($select_input_id, $selected_id)
    {
        $session_name = null;
        foreach ($this->rule_data['clones']['groupList'] as $value) {
            if ($value['Id'] == $select_input_id) {
                if ($value['Type'] == self::JIRA_PROJECT) {
                    $session_name = 'jira_project';
                } elseif ($value['Type'] == self::JIRA_ASSIGNEE) {
                    $session_name = 'jira_assignee';
                } elseif ($value['Type'] == self::JIRA_ISSUETYPE) {
                    $session_name = 'jira_issuetype';
                } elseif ($value['Type'] == self::JIRA_PRIORITY) {
                    $session_name = 'jira_priority';
                }
            }
        }

        if (is_null($session_name) && $selected_id == -1) {
            return array();
        }
        if ($selected_id == -1) {
            return array('id' => null, 'value' => null);
        }

        $result = $this->getSession($session_name);

        if (is_null($result)) {
            return array();
        }

        foreach ($result as $value) {
            if ($value['id'] == $selected_id) {
                return $value;
            }
        }

        return array();
    }

    protected function doSubmit($db_storage, $contact, $host_problems, $service_problems)
    {
        $result = array('ticket_id' => null, 'ticket_error_message' => null,
                        'ticket_is_ok' => 0, 'ticket_time' => time());

        $tpl = $this->initSmartyTemplate();

        $tpl->assign("centreon_open_tickets_path", $this->_centreon_open_tickets_path);
        $tpl->assign('user', $contact);
        $tpl->assign('host_selected', $host_problems);
        $tpl->assign('service_selected', $service_problems);

        $this->assignSubmittedValues($tpl);

        $ticket_arguments = array();
        if (isset($this->rule_data['clones']['mappingTicket'])) {
            foreach ($this->rule_data['clones']['mappingTicket'] as $value) {
                $tpl->assign('string', $value['Value']);
                $result_str = $tpl->fetch('eval.ihtml');

                if ($result_str == '') {
                    $result_str = null;
                }

                $ticket_arguments[$this->_internal_arg_name[$value['Arg']]] = $result_str;
            }
        }

        $code = $this->createTicketJira($ticket_arguments);
        if ($code == -1) {
            $result['ticket_error_message'] = $this->ws_error;
            return $result;
        }

        //Array ( [id] => 41261 [key] => TES-2 [self] => https://centreon.atlassian.net/rest/api/latest/issue/41261 )
        $this->saveHistory(
            $db_storage,
            $result,
            array(
                'contact' => $contact,
                'host_problems' => $host_problems,
                'service_problems' => $service_problems,
                'ticket_value' => $this->_jira_call_response['id'],
                'subject' => $ticket_arguments[$this->_internal_arg_name[self::ARG_SUMMARY]],
                'data_type' => self::DATA_TYPE_JSON,
                'data' => json_encode(
                    array('ticket_key' => $this->_jira_call_response['key'], 'arguments' => $ticket_arguments)
                )
            )
        );

        return $result;
    }

    /*
     *
     * REST API
     *
     */
    protected function setWsError($error)
    {
        $this->ws_error = $error;
    }

    protected function listProjectJira()
    {
        if ($this->callRest('project') == 1) {
            return -1;
        }

        return 0;
    }

    protected function listPriorityJira()
    {
        if ($this->callRest('priority') == 1) {
            return -1;
        }

        return 0;
    }

    protected function listIssuetypeJira()
    {
        if ($this->callRest('issuetype') == 1) {
            return -1;
        }

        return 0;
    }

    protected function listUserJira($filter)
    {
        $search = 'username=';
        if (isset($filter)) {
            $search .= urlencode($filter);
        }
        if ($this->callRest('user/search?' . $search) == 1) {
            return -1;
        }

        return 0;
    }

    protected function createTicketJira($ticket_arguments)
    {
        $argument = array(
            'fields' => array(
                'project'     => array ('id' => $ticket_arguments[$this->_internal_arg_name[self::ARG_PROJECT]]),
                'summary'     => $ticket_arguments[$this->_internal_arg_name[self::ARG_SUMMARY]],
                'description' => $ticket_arguments[$this->_internal_arg_name[self::ARG_DESCRIPTION]],
            ),
        );

        if (isset($ticket_arguments[$this->_internal_arg_name[self::ARG_ASSIGNEE]])
            && $ticket_arguments[$this->_internal_arg_name[self::ARG_ASSIGNEE]] != ''
        ) {
            $argument['fields']['assignee'] = array(
                'name' => $ticket_arguments[$this->_internal_arg_name[self::ARG_ASSIGNEE]]
            );
        }
        if (isset($ticket_arguments[$this->_internal_arg_name[self::ARG_PRIORITY]])
            && $ticket_arguments[$this->_internal_arg_name[self::ARG_PRIORITY]] != ''
        ) {
            $argument['fields']['priority'] = array(
                'id' => $ticket_arguments[$this->_internal_arg_name[self::ARG_PRIORITY]]
            );
        }
        if (isset($ticket_arguments[$this->_internal_arg_name[self::ARG_ISSUETYPE]])
            && $ticket_arguments[$this->_internal_arg_name[self::ARG_ISSUETYPE]] != ''
        ) {
            $argument['fields']['issuetype'] = array(
                'id' => $ticket_arguments[$this->_internal_arg_name[self::ARG_ISSUETYPE]]
            );
        }

        if ($this->callRest('issue', $argument) == 1) {
            return -1;
        }

        return 0;
    }

    protected function callRest($function, $argument = null)
    {
        $this->_jira_call_response = null;

        $proto = 'https';

        $base_url = $proto . '://' . $this->rule_data['address'] . $this->rule_data['rest_api_resource'] .
            '/' . $function;
        $ch = curl_init($base_url);
        if ($ch == false) {
            $this->setWsError("cannot init curl object");
            return 1;
        }

        $method = 'GET';
        $headers = array('Content-Type: application/json', 'Accept: application/json');
        if (!is_null($argument)) {
            $argument_json = json_encode($argument);
            curl_setopt($ch, CURLOPT_POSTFIELDS, $argument_json);
            $headers[] = 'Content-Length: ' . strlen($argument_json);
            $method = 'POST';
        }
        curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $method);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_TIMEOUT, $this->rule_data['timeout']);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
        curl_setopt($ch, CURLOPT_USERPWD, $this->_getFormValue('username') . ':' . $this->_getFormValue('user_token'));
        self::setProxy(
            $ch,
            array(
                'proxy_address' => $this->_getFormValue('proxy_address'),
                'proxy_port' => $this->_getFormValue('proxy_port'),
                'proxy_username' => $this->_getFormValue('proxy_username'),
                'proxy_password' => $this->_getFormValue('proxy_password'),
            )
        );
        curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
        $result = curl_exec($ch);
        if ($result == false) {
            $this->setWsError(curl_error($ch));
            curl_close($ch);
            return 1;
        }

        // 401 it's an error (unauthorized maybe)
        $http_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        if (!preg_match_all('/^2/', $http_code)) {
            curl_close($ch);
            $this->setWsError($http_code . ' code error');
            return 1;
        }

        $decoded_result = json_decode($result, true);
        if (is_null($decoded_result) || $decoded_result == false) {
            $this->setWsError($result);
            return 1;
        }

        curl_close($ch);

        $this->_jira_call_response = $decoded_result;
        return 0;
    }
}
