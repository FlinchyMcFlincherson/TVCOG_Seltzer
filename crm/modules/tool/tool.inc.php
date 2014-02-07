<?php

/*
    Copyright 2009-2013 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    tool_inventory.inc.php - Tool tracking module
    
    Seltzer is free software: you can redistribute it and/or modify
    it under the terms of the GNU General Public License as published by
    the Free Software Foundation, either version 3 of the License, or
    any later version.
    
    Seltzer is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.
    
    You should have received a copy of the GNU General Public License
    along with Seltzer.  If not, see <http://www.gnu.org/licenses/>.
*/

/**
 * @return This module's revision number.  Each new release should increment
 * this number.
 */
function tool_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function tool_permissions () {
    return array(
        'tool_view'
        , 'tool_edit'
        , 'tool_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function tool_install($old_revision = 0) {
    // Create initial database table
    if ($old_revision < 1) {
        $sql = '
            CREATE TABLE IF NOT EXISTS `tool` (
            `tlid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
            `name` varchar(255) NOT NULL,
            `mfgr` varchar(255) NOT NULL,
            `modelNum` varchar(255) NOT NULL,
            `serialNum` varchar(255) NOT NULL,
            `class` varchar(255) NOT NULL,
            `acquiredDate` date DEFAULT NULL,
            `releasedDate` date DEFAULT NULL,
            `purchasePrice` mediumint(8) NOT NULL,
            `deprecSched` varchar(255) NOT NULL,
            `recoveredCost` mediumint(8) NOT NULL,
            `owner` varchar(255) NOT NULL,
            `notes` text NOT NULL,
            `created` timestamp NOT NULL DEFAULT CURRENT_TIMESTAMP,
            `createdBy` varchar(255) NOT NULL,
              PRIMARY KEY (`tlid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ';
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        // Set default permissions
        $roles = array(
            '1' => 'authenticated'
            , '2' => 'member'
            , '3' => 'director'
            , '4' => 'president'
            , '5' => 'vp'
            , '6' => 'secretary'
            , '7' => 'treasurer'
            , '8' => 'webAdmin'
        );
        $default_perms = array(
            'director' => array('tool_view', 'tool_edit', 'tool_delete')
            , 'webAdmin' => array('tool_view', 'tool_edit', 'tool_delete')
        );
        foreach ($roles as $rid => $role) {
            $esc_rid = mysql_real_escape_string($rid);
            if (array_key_exists($role, $default_perms)) {
                foreach ($default_perms[$role] as $perm) {
                    $esc_perm = mysql_real_escape_string($perm);
                    $sql = "INSERT INTO `role_permission` (`rid`, `permission`) VALUES ('$esc_rid', '$esc_perm')";
                    $res = mysql_query($sql);
                    if (!$res) die(mysql_error());
                }
            }
        }
    }
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more tools.
 *
 * @param $opts An associative array of options, possible keys are:
 *   'tlid' If specified, returns a single tool with the matching id;
 *   'cid' If specified, returns all tools assigned to the contact with specified id;
 *   'filter' An array mapping filter names to filter values;
 *   'join' A list of tables to join to the tool table;
 *   'order' An array of associative arrays of the form 'field'=>'order'.
 * @return An array with each element representing a single tool.
*/ 
function tool_data ($opts = array()) {
    $sql = "
        SELECT
        `tlid`
        , `date`
        , `description`
        , `code`
        , `value`
        , `credit`
        , `debit`
        , `method`
        , `confirmation`
        , `notes`
        FROM `tool`
    ";
    $sql .= "WHERE 1 ";
    if (array_key_exists('tlid', $opts)) {
        $tlid = mysql_real_escape_string($opts['tlid']);
        $sql .= " AND `tlid`='$tlid' ";
    }
    if (array_key_exists('cid', $opts)) {
        $cid = mysql_real_escape_string($opts['cid']);
        $sql .= " AND (`debit`='$cid' OR `credit`='$cid') ";
    }
    if (array_key_exists('filter', $opts) && !empty($opts['filter'])) {
        foreach($opts['filter'] as $name => $value) {
            $esc_value = mysql_real_escape_string($value);
            switch ($name) {
                case 'confirmation':
                    $sql .= " AND (`confirmation`='$esc_value') ";
                    break;
                case 'credit_cid':
                    $sql .= " AND (`credit`='$esc_value') ";
                    break;
                case 'debit_cid':
                    $sql .= " AND (`debit`='$esc_value') ";
                    break;
            }
        }
    }
    // Specify the order the results should be returned in
    if (isset($opts['order'])) {
        $field_list = array();
        foreach ($opts['order'] as $field => $order) {
            $clause = '';
            switch ($field) {
                case 'date':
                    $clause .= "`date` ";
                    break;
                case 'created':
                    $clause .= "`created` ";
                    break;
                default:
                    continue;
            }
            if (strtolower($order) === 'asc') {
                $clause .= 'ASC';
            } else {
                $clause .= 'DESC';
            }
            $field_list[] = $clause;
        }
        if (!empty($field_list)) {
            $sql .= " ORDER BY " . implode(',', $field_list) . " ";
        }
    } else {
        // Default to date, created from newest to oldest
        $sql .= " ORDER BY `date` DESC, `created` DESC ";
    }
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    $tools = array();
    $row = mysql_fetch_assoc($res);
    while ($row) {
        $tool = array(
            'tlid' => $row['tlid']
            , 'date' => $row['date']
            , 'description' => $row['description']
            , 'code' => $row['code']
            , 'value' => $row['value']
            , 'credit_cid' => $row['credit']
            , 'debit_cid' => $row['debit']
            , 'method' => $row['method']
            , 'confirmation' => $row['confirmation']
            , 'notes' => $row['notes']
        );
        $tools[] = $tool;
        $row = mysql_fetch_assoc($res);
    }
    return $tools;
}

/**
 * Save a tool to the database.  If the tool has a key called "tlid"
 * an existing tool will be updated in the database.  Otherwise a new tool
 * will be added to the database.  If a new tool is added to the database,
 * the returned array will have a "tlid" field corresponding to the database id
 * of the new tool.
 * 
 * @param $tool An associative array representing a tool.
 * @return A new associative array representing the tool.
 */
function tool_save ($tool) {
    // Verify permissions and validate input
    if (!user_access('tool_edit')) {
        error_register('Permission denied: tool_edit');
        return NULL;
    }
    if (empty($tool)) {
        return NULL;
    }
    // Sanitize input
    $esc_tlid = mysql_real_escape_string($tool['tlid']);
    $esc_date = mysql_real_escape_string($tool['date']);
    $esc_description = mysql_real_escape_string($tool['description']);
    $esc_code = mysql_real_escape_string($tool['code']);
    $esc_value = mysql_real_escape_string($tool['value']);
    $esc_credit = mysql_real_escape_string($tool['credit_cid']);
    $esc_debit = mysql_real_escape_string($tool['debit_cid']);
    $esc_method = mysql_real_escape_string($tool['method']);
    $esc_confirmation = mysql_real_escape_string($tool['confirmation']);
    $esc_notes = mysql_real_escape_string($tool['notes']);
    // Query database
    if (array_key_exists('tlid', $tool) && !empty($tool['tlid'])) {
        // tool already exists, update
        $sql = "
            UPDATE `tool`
            SET
            `date`='$esc_date'
            , `description` = '$esc_description'
            , `code` = '$esc_code'
            , `value` = '$esc_value'
            , `credit` = '$esc_credit'
            , `debit` = '$esc_debit'
            , `method` = '$esc_method'
            , `confirmation` = '$esc_confirmation'
            , `notes` = '$esc_notes'
            WHERE
            `tlid` = '$esc_tlid'
        ";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $tool = module_invoke_api('tool', $tool, 'update');
    } else {
        // tool does not yet exist, create
        $sql = "
            INSERT INTO `tool`
            (
                `date`
                , `description`
                , `code`
                , `value`
                , `credit`
                , `debit`
                , `method`
                , `confirmation`
                , `notes`
            )
            VALUES
            (
                '$esc_date'
                , '$esc_description'
                , '$esc_code'
                , '$esc_value'
                , '$esc_credit'
                , '$esc_debit'
                , '$esc_method'
                , '$esc_confirmation'
                , '$esc_notes'
            )
        ";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $tool['tlid'] = mysql_insert_id();
        $tool = module_invoke_api('tool', $tool, 'insert');
    }
    return $tool;
}

/**
 * Delete the tool identified by $tlid.
 * @param $tlid The tool id.
 */
function tool_delete ($tlid) {
    $tool = crm_get_one('tool', array('tlid'=>$tlid));
    $tool = module_invoke_api('tool', $tool, 'delete');
    // Query database
    $esc_tlid = mysql_real_escape_string($tlid);
    $sql = "
        DELETE FROM `tool`
        WHERE `tlid`='$esc_tlid'";
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    if (mysql_affected_rows() > 0) {
        message_register('Deleted tool with id ' . $tlid);
    }
}

/**
 * Return an array of cids matching the given filters.
 * @param $filter An associative array of filters, keys are:
 *   'balance_due' - If true, only include contacts with a balance due.
 * @return An array of cids for matching contacts, or NULL if all match.
 */
function tool_contact_filter ($filter) {
    $cids = NULL;
    foreach ($filter as $key => $value) {
        $new_cids = array();
        switch ($key) {
            case 'balance_due':
                if ($value) {
                    $balances = tool_accounts();
                    foreach ($balances as $cid => $bal) {
                        if ($bal['value'] > 0) {
                            $new_cids[] = $cid;
                        }
                    }
                }
                break;
            default:
                $new_cids = NULL;
        }
        if (is_null($cids)) {
            $cids = $new_cids;
        } else {
            // This is inefficient and can be optimized
            // -Ed 2013-06-08
            $result = array();
            foreach ($cids as $cid) {
                if (in_array($cid, $new_cids)) {
                    $result[] = $cid;
                }
            }
            $cids = $result;
        }
    }
    return $cids;
}

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return a table structure for a table of tools.
 *
 * @param $opts The options to pass to tool_data().
 * @return The table structure.
*/
function tool_table ($opts) {
    // Determine settings
    $export = false;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get tool data
    $data = crm_get_data('tool', $opts);
    if (count($data) < 1) {
        return array();
    }
    $cids = array();
    // Create array of cids referenced from all tools
    foreach ($data as $tool) {
        $cids[$tool['credit_cid']] = true;
        $cids[$tool['debit_cid']] = true;
    }
    $cids = array_keys($cids);
    // Get map from cid to contact
    $contacts = crm_get_data('contact', array('cid'=>$cids));
    $cid_to_contact = crm_map($contacts, 'cid');
    // Initialize table
    $table = array(
        "id" => '',
        "class" => '',
        "rows" => array(),
        "columns" => array()
    );
    // Add columns
    if (user_access('tool_view')) { // Permission check
        $table['columns'][] = array("title"=>'date');
        $table['columns'][] = array("title"=>'description');
        $table['columns'][] = array("title"=>'credit');
        $table['columns'][] = array("title"=>'debit');
        $table['columns'][] = array("title"=>'amount');
        $table['columns'][] = array("title"=>'method');
        $table['columns'][] = array("title"=>'confirmation');
        $table['columns'][] = array("title"=>'notes');
    }
    // Add ops column
    if (!$export && (user_access('tool_edit') || user_access('tool_delete'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $tool) {
        $row = array();
        if (user_access('tool_view')) {
            $row[] = $tool['date'];
            $row[] = $tool['description'];
            if (array_key_exists('credit_cid', $tool) && $tool['credit_cid']) {
                $contact = $cid_to_contact[$tool['credit_cid']];
                $row[] = theme('contact_name', $contact, true);
            } else {
                $row[] = '';
            }
            if ($tool['debit_cid']) {
                $contact = $cid_to_contact[$tool['debit_cid']];
                $row[] = theme('contact_name', $contact, true);
            } else {
                $row[] = '';
            }
            $row[] = tool_format_currency($tool, true);
            $row[] = $tool['method'];
            $row[] = $tool['confirmation'];
            $row[] = $tool['notes'];
        }
        if (!$export && (user_access('tool_edit') || user_access('tool_delete'))) {
            // Add ops column
            // TODO
            $ops = array();
            if (user_access('tool_edit')) {
               $ops[] = '<a href=' . crm_url('tool&tlid=' . $tool['tlid']) . '>edit</a>';
            }
            if (user_access('tool_delete')) {
                $ops[] = '<a href=' . crm_url('delete&type=tool&id=' . $tool['tlid']) . '>delete</a>';
            }
            $row[] = join(' ', $ops);
        }
        $table['rows'][] = $row;
    }
    return $table;
}

// Forms ///////////////////////////////////////////////////////////////////////

/**
 * @return The form structure for adding a tool.
*/
function tool_add_form () {
    
    // Ensure user is allowed to edit tools
    if (!user_access('tool_edit')) {
        return NULL;
    }
    
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'tool_add'
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Add tool'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Credit'
                        , 'name' => 'credit'
                        , 'autocomplete' => 'contact_name'
                        , 'class' => 'focus float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Date'
                        , 'name' => 'date'
                        , 'value' => date("Y-m-d")
                        , 'class' => 'date float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Description'
                        , 'name' => 'description'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Amount'
                        , 'name' => 'amount'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'select'
                        , 'label' => 'Method'
                        , 'name' => 'method'
                        , 'options' => tool_method_options()
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Check/Rcpt Num'
                        , 'name' => 'confirmation'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Debit'
                        , 'name' => 'debit'
                        , 'autocomplete' => 'contact_name'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Add'
                    )
                )
            )
        )
    );
    
    return $form;
}

/**
 * Create a form structure for editing a tool.
 *
 * @param $tlid The id of the tool to edit.
 * @return The form structure.
*/
function tool_edit_form ($tlid) {
    // Ensure user is allowed to edit tools
    if (!user_access('tool_edit')) {
        error_register('User does not have permission: tool_edit');
        return NULL;
    }
    // Get tool data
    $data = crm_get_data('tool', array('tlid'=>$tlid));
    if (count($data) < 1) {
        return NULL;
    }
    $tool = $data[0];
    $credit = '';
    $debit = '';
    // Add contact info
    if ($tool['credit_cid']) {
        $credit = theme('contact_name', $tool['credit_cid']);
    }
    if ($tool['debit_cid']) {
        $debit = theme('contact_name', $tool['debit_cid']);
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'tool_edit'
        , 'hidden' => array(
            'tlid' => $tool['tlid']
            , 'code' => $tool['code']
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Edit tool'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Credit'
                        , 'name' => 'credit_cid'
                        , 'description' => $credit
                        , 'value' => $tool['credit_cid']
                        , 'autocomplete' => 'contact_name'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Date'
                        , 'name' => 'date'
                        , 'value' => $tool['date']
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Description'
                        , 'name' => 'description'
                        , 'value' => $tool['description']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Amount'
                        , 'name' => 'value'
                        , 'value' => tool_format_currency($tool, false)
                    )
                    , array(
                        'type' => 'select'
                        , 'label' => 'Method'
                        , 'name' => 'method'
                        , 'options' => tool_method_options()
                        , 'selected' => $tool['method']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Check/Rcpt Num'
                        , 'name' => 'confirmation'
                        , 'value' => $tool['confirmation']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Debit'
                        , 'name' => 'debit_cid'
                        , 'description' => $debit
                        , 'value' => $tool['debit_cid']
                        , 'autocomplete' => 'contact_name'
                    )
                    , array(
                        'type' => 'textarea'
                        , 'label' => 'Notes'
                        , 'name' => 'notes'
                        , 'value' => $tool['notes']
                    )
                    , array(
                        'type' => 'submit'
                        , 'value' => 'Save'
                    )
                )
            )
        )
    );
    // Make data accessible for other modules modifying this form
    $form['data']['tool'] = $tool;
    return $form;
}

/**
 * Return the tool form structure.
 *
 * @param $tlid The id of the key assignment to delete.
 * @return The form structure.
*/
function tool_delete_form ($tlid) {
    // Ensure user is allowed to delete keys
    if (!user_access('tool_delete')) {
        return NULL;
    }
    // Get data
    $data = crm_get_data('tool', array('tlid'=>$tlid));
    $tool = $data[0];
    // Construct key name
    $amount = tool_format_currency($tool);
    $tool_name = "tool:$tool[tlid] - $amount";
    if ($tool['credit_cid']) {
        $name = theme('contact_name', $tool['credit_cid']);
        $tool_name .= " - Credit: $name";
    }
    if ($tool['debit_cid']) {
        $name = theme('contact_name', $tool['debit_cid']);
        $tool_name .= " - Debit: $name";
    }
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'tool_delete',
        'hidden' => array(
            'tlid' => $tool['tlid']
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Delete tool',
                'fields' => array(
                    array(
                        'type' => 'message',
                        'value' => '<p>Are you sure you want to delete the tool "' . $tool_name . '"? This cannot be undone.',
                    ),
                    array(
                        'type' => 'submit',
                        'value' => 'Delete'
                    )
                )
            )
        )
    );
    return $form;
}

// Command handlers ////////////////////////////////////////////////////////////

/**
 * Handle tool delete request.
 *
 * @return The url to display on completion.
 */
function command_tool_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('tool_delete')) {
        error_register('Permission denied: tool_delete');
        return crm_url('tool&tlid=' . $esc_post['tlid']);
    }
    tool_delete($_POST['tlid']);
    return crm_url('tools');
}

/**
 * Return the form structure for a tool filter.
 * @return The form structure.
 */
function tool_filter_form () {
    // Available filters
    $filters = array(
        'all' => 'All',
        'orphaned' => 'Orphaned'
    );
    // Default filter
    $selected = empty($_SESSION['tool_filter_option']) ? 'all' : $_SESSION['tool_filter_option'];
    // Construct hidden fields to pass GET params
    $hidden = array();
    foreach ($_GET as $key=>$val) {
        $hidden[$key] = $val;
    }
    $form = array(
        'type' => 'form'
        , 'method' => 'get'
        , 'command' => 'tool_filter'
        , 'hidden' => $hidden,
        'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Filter'
                ,'fields' => array(
                    array(
                        'type' => 'select'
                        , 'name' => 'filter'
                        , 'options' => $filters
                        , 'selected' => $selected
                    ),
                    array(
                        'type' => 'submit'
                        , 'value' => 'Filter'
                    )
                )
            )
        )
    );
    return $form;
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function tool_page_list () {
    $pages = array();
    if (user_access('tool_view')) {
        $pages[] = 'accounts';
    }
    if (user_access('tool_edit')) {
        $pages[] = 'tools';
        $pages[] = 'tool';
    }
    return $pages;
}

/**
 * Page hook.  Adds module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
*/
function tool_page (&$page_data, $page_name, $options) {
    switch ($page_name) {
        case 'tools':
            page_set_title($page_data, 'tools');
            if (user_access('tool_edit')) {
                $filter = array_key_exists('tool_filter', $_SESSION) ? $_SESSION['tool_filter'] : '';
                $content = theme('form', crm_get_form('tool_add'));
                $content .= theme('form', crm_get_form('tool_filter'));
                $opts = array(
                    'show_export' => true
                    , 'filter' => $filter
                );
                $content .= theme('table', 'tool', $opts);
                page_add_content_top($page_data, $content, 'View');
            }
            break;
        case 'tool':
            page_set_title($page_data, 'tool');
            if (user_access('tool_edit')) {
                $content = theme('form', crm_get_form('tool_edit', $_GET['tlid']));
                page_add_content_top($page_data, $content);
            }
            break;
        case 'accounts':
            page_set_title($page_data, 'Accounts');
            if (user_access('tool_view')) {
                $content = theme('table', 'tool_accounts', array('show_export'=>true));
                page_add_content_top($page_data, $content);
            }
            break;
        case 'contact':
            if (user_id() == $_GET['cid'] || user_access('tool_view')) {
                $content = theme('table', 'tool_history', array('cid' => $_GET['cid']));
                page_add_content_top($page_data, $content, 'Account');
                page_add_content_bottom($page_data, theme('tool_account_info', $_GET['cid']), 'Account');
                if (function_exists('billing_revision')) {
                    page_add_content_bottom($page_data, theme('tool_first_month', $_GET['cid']), 'Plan');
                }
            }
            break;
    }
}

// Command handlers ////////////////////////////////////////////////////////////

/**
 * Handle tool add request.
 *
 * @return The url to display on completion.
 */
function command_tool_add() {
    $value = tool_parse_currency($_POST['amount'], $_POST['code']);
    $tool = array(
        'date' => $_POST['date']
        , 'description' => $_POST['description']
        , 'code' => $value['code']
        , 'value' => $value['value']
        , 'credit_cid' => $_POST['credit']
        , 'debit_cid' => $_POST['debit']
        , 'method' => $_POST['method']
        , 'confirmation' => $_POST['confirmation']
        , 'notes' => $_POST['notes']
    );
    $tool = tool_save($tool);
    message_register('1 tool added.');
    return crm_url('tools');
}

/**
 * Handle tool edit request.
 *
 * @return The url to display on completion.
 */
function command_tool_edit() {
    // Verify permissions
    if (!user_access('tool_edit')) {
        error_register('Permission denied: tool_edit');
        return crm_url('tools');
    }
    // Parse and save tool
    $tool = $_POST;
    $value = tool_parse_currency($_POST['value'], $_POST['code']);
    $tool['code'] = $value['code'];
    $tool['value'] = $value['value'];
    tool_save($tool);
    message_register('1 tool updated.');
    return crm_url('tools');
}

/**
 * Handle tool filter request.
 * @return The url to display on completion.
 */
function command_tool_filter () {
    // Set filter in session
    $_SESSION['tool_filter_option'] = $_GET['filter'];
    // Set filter
    if ($_GET['filter'] == 'all') {
        $_SESSION['tool_filter'] = array();
    }
    if ($_GET['filter'] == 'orphaned') {
        $_SESSION['tool_filter'] = array('credit_cid'=>'0', 'debit_cid'=>'0');
    }
    // Construct query string
    $params = array();
    foreach ($_GET as $k=>$v) {
        if ($k == 'command' || $k == 'filter' || $k == 'q') {
            continue;
        }
        $params[] = urlencode($k) . '=' . urlencode($v);
    }
    if (!empty($params)) {
        $query = '&' . implode('&', $params);
    }
    return crm_url('tools') . $query;
}
