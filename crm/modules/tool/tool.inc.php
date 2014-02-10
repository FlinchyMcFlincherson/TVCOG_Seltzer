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
 * Generate a descriptive string for a single tool.
 *
 * @param $tlid The tlid of the day pass to describe.
 * @return The description string.
 */
function tool_description ($tlid) {
    
    // Get day pass data
    $data = crm_get_data('tool', array('tlid' => $tlid));
    if (empty($data)) {
        return '';
    }
    $tool = $data[0];
    
    // Construct description
    $description = 'Tool ID: ';
    $description .= $tool['tlid'];
    $description .= ' - '
    $description .= $tool['name'];
    
    return $description;
}

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
        `tlid
        ,`name`
        ,`mfgr`
        ,`modelNum`
        ,`serialNum`
        ,`class`
        ,`acquiredDate`
        ,`releasedDate`
        ,`purchasePrice`
        ,`deprecSched`
        ,`recoveredCost`
        ,`owner`
        ,`notes`
        FROM `tool`
    ";
    $sql .= "WHERE 1 ";
    if (array_key_exists('tlid', $opts)) {
        $tlid = mysql_real_escape_string($opts['tlid']);
        $sql .= " AND `tlid`='$tlid' ";
    }
    /* TODO: This code could be used to implement a filter of some kind...
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
    }*/
    // Specify the order the results should be returned in
    if (isset($opts['order'])) {
        $field_list = array();
        foreach ($opts['order'] as $field => $order) {
            $clause = '';
            switch ($field) {
                case 'name':
                    $clause .= "`name` ";
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
        // Default to name, created from newest to oldest
        $sql .= " ORDER BY `name` DESC, `created` DESC ";
    }
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    $tools = array();
    $row = mysql_fetch_assoc($res);
    while ($row) {
        $tool = array(
            'tlid' => $row['tlid']
            ,'name' => $row['name']
            ,'mfgr' => $row['mfgr']
            ,'modelNum' => $row['modelNum']
            ,'serialNum' => $row['serialNum']
            ,'class' => $row['class']
            ,'acquiredDate' => $row['aquiredDate']
            ,'releasedDate' => $row['releasedDate']
            ,'purchasePrice' => $row['purchasedPrice']
            ,'deprecSched' => $row['deprecSched']
            ,'recoveredCost' => $row['recoveredCost']
            ,'owner' => $row['owner']
            ,'notes' => $row['notes']
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
    $esc_name = mysql_real_escape_string($tool['name']);
    $esc_mfgr = mysql_real_escape_string($tool['mfgr']);
    $esc_modelNum = mysql_real_escape_string($tool['modelNum']);
    $esc_serialNum = mysql_real_escape_string($tool['serialNum']);
    $esc_class = mysql_real_escape_string($tool['class']);
    $esc_acquiredDate = mysql_real_escape_string($tool['acquiredDate']);
    $esc_releasedDate = mysql_real_escape_string($tool['releasedDate']);
    $esc_purchasePrice = mysql_real_escape_string($tool['purchasePrice']);
    $esc_deprecSched = mysql_real_escape_string($tool['deprecSched']);
    $esc_recoveredCost = mysql_real_escape_string($tool['recoveredCost']);
    $esc_owner = mysql_real_escape_string($tool['owner']);
    $esc_notes = mysql_real_escape_string($tool['notes']);

    // Query database
    if (array_key_exists('tlid', $tool) && !empty($tool['tlid'])) {
        // tool already exists, update
        $sql = "
            UPDATE `tool`
            SET
            `name name
            , `mfgr`='$esc_mfgr'
            , `modelNum`='$esc_modelNum'
            , `serialNum`='$esc_serialNum'
            , `class`='$esc_class'
            , `acquiredDate`='$esc_acquiredDate'
            , `releasedDate`='$esc_releasedDate'
            , `purchasePrice`='$esc_purchasePrice'
            , `deprecSched`='$esc_deprecSched'
            , `recoveredCost`='$esc_recoveredCost'
            , `owner`='$esc_owner'
            , `notes`='$esc_notes'
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
                `name`
                , `mfgr`
                , `modelNum`
                , `serialNum`
                , `class`
                , `acquiredDate`
                , `releasedDate`
                , `purchasePrice`
                , `deprecSched`
                , `recoveredCost`
                , `owner`
                , `notes`
            )
            VALUES
            (
                '$esc_name'
                , '$esc_mfgr'
                , '$esc_modelNum'
                , '$esc_serialNum'
                , '$esc_class'
                , '$esc_acquiredDate'
                , '$esc_releasedDate'
                , '$esc_purchasePrice'
                , '$esc_deprecSched'
                , '$esc_recoveredCost'
                , '$esc_owner'
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
        message_register('Deleted Tool ID: ' . $tlid);
    }
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
    // Initialize table
    $table = array(
        "id" => '',
        "class" => '',
        "rows" => array(),
        "columns" => array()
    );
    // Add columns
    if (user_access('tool_view')) { // Permission check
        $table['columns'][] = array("title"=>'Tool ID');
        $table['columns'][] = array("title"=>'Name');
        $table['columns'][] = array("title"=>'Manufacturer');
        $table['columns'][] = array("title"=>'Model Number');
        $table['columns'][] = array("title"=>'Serial Number');
        $table['columns'][] = array("title"=>'Class');
        $table['columns'][] = array("title"=>'Acquired');
        $table['columns'][] = array("title"=>'Released');
        $table['columns'][] = array("title"=>'Purchased Price');
        $table['columns'][] = array("title"=>'Deprec Schedule');
        $table['columns'][] = array("title"=>'Recovered Cost');
        $table['columns'][] = array("title"=>'Owner');
        $table['columns'][] = array("title"=>'Notes');
    }
    // Add ops column
    if (!$export && (user_access('tool_edit') || user_access('tool_delete'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $tool) {
        $row = array();
        if (user_access('tool_view')) {
            $row[] = $tool['tlid'];
            $row[] = $tool['name'];
            $row[] = $tool['mfgr'];
            $row[] = $tool['modelNum'];
            $row[] = $tool['serialNum'];
            $row[] = $tool['class'];
            $row[] = $tool['acquiredDate'];
            $row[] = $tool['releasedDate'];
            $row[] = $tool['purchasePrice'];
            $row[] = $tool['deprecSched'];
            $row[] = $tool['recoveredCost'];
            $row[] = $tool['owner'];
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
    
    /*
    name
    mfgr
    modelNum
    serialNum
    class
    acquiredDate
    releasedDate
    purchasePrice
    deprecSched
    recoveredCost
    owner
    notes
    */


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
                        , 'label' => 'Name'
                        , 'name' => 'name'
                        , 'class' => 'focus float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Manufacturer'
                        , 'name' => 'mfgr'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Model Number'
                        , 'name' => 'modelNum'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Serial Number'
                        , 'name' => 'serialNum'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Class'
                        , 'name' => 'class'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Acquired Date'
                        , 'name' => 'acquiredDate'
                        , 'value' => date("Y-m-d")
                        , 'class' => 'date float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Realeased Date'
                        , 'name' => 'releasedDate'
                        , 'value' => date("Y-m-d")
                        , 'class' => 'date float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Purchased Price'
                        , 'name' => 'purchasedPrice'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Depreciation Schedule'
                        , 'name' => 'deprecSched'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Recovered Cost'
                        , 'name' => 'recoveredCost'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Owner'
                        , 'name' => 'owner'
                        , 'class' => 'float'
                    )
                    , array(
                        'type' => 'textArea'
                        , 'label' => 'Notes'
                        , 'name' => 'notes'
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
    if (!user_access('payment_edit')) {
        error_register('User does not have permission: payment_edit');
        return NULL;
    }
    // Get tool data
    $data = crm_get_data('payment', array('pmtid'=>$pmtid));
    if (count($data) < 1) {
        return NULL;
    }
    $payment = $data[0];
    $credit = '';
    $debit = '';
    // Add contact info
    if ($payment['credit_cid']) {
        $credit = theme('contact_name', $payment['credit_cid']);
    }
    if ($payment['debit_cid']) {
        $debit = theme('contact_name', $payment['debit_cid']);
    }
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'payment_edit'
        , 'hidden' => array(
            'pmtid' => $payment['pmtid']
            , 'code' => $payment['code']
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Edit Payment'
                , 'fields' => array(
                    array(
                        'type' => 'text'
                        , 'label' => 'Name'
                        , 'name' => 'name'
                        , 'value' => $tool['name']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Manufacturer'
                        , 'name' => 'mfgr'
                        , 'value' => $tool['mfgr']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Model Number'
                        , 'name' => 'modelNum'
                        , 'value' => $tool['modelNum']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Serial Number'
                        , 'name' => 'serialNum'
                        , 'value' => $tool['serialNum']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Class'
                        , 'name' => 'class'
                        , 'value' => $tool['class']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Acquired Date'
                        , 'name' => 'acquiredDate'
                        , 'value' => date("Y-m-d")
                        , 'value' => $tool['acquiredDate']
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Realeased Date'
                        , 'name' => 'releasedDate'
                        , 'value' => date("Y-m-d")
                        , 'value' => $tool['releasedDate']
                        , 'class' => 'date'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Purchased Price'
                        , 'name' => 'purchasedPrice'
                        , 'value' => $tool['purchasedPrice']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Depreciation Schedule'
                        , 'name' => 'deprecSched'
                        , 'value' => $tool['deprecSched']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Recovered Cost'
                        , 'name' => 'recoveredCost'
                        , 'value' => $tool['recoveredCost']
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Owner'
                        , 'name' => 'owner'
                        , 'value' => $tool['owner']
                    )
                    , array(
                        'type' => 'textArea'
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
    // Construct name
    $tool_name = tool_description($tlid);
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
/*function tool_filter_form () {
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
}*/

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function tool_page_list () {
    $pages = array();
    if (user_access('tool_view')) {
        $pages[] = 'tools';
    }
    if (user_access('tool_edit')) {
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
                //$content .= theme('form', crm_get_form('tool_filter'));
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
    }
}

// Command handlers ////////////////////////////////////////////////////////////

/**
 * Handle tool add request.
 *
 * @return The url to display on completion.
 */
function command_tool_add() {
    $tool = array(
        'name' => $_POST['name']
        , 'mfgr' => $_POST['mfgr']
        , 'modelNum' => $_POST['modelNum']
        , 'serialNum' => $_POST['serialNum']
        , 'class' => $_POST['class']
        , 'acquiredDate' => $_POST['acquiredDate']
        , 'releasedDate' => $_POST['releasedDate']
        , 'purchasePrice' => $_POST['purchasePrice']
        , 'deprecSched' => $_POST['deprecSched']
        , 'recoveredCost' => $_POST['recoveredCost']
        , 'owner' => $_POST['owner']
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
    $tool['name'] = $value['name'];
    $tool['mfgr'] = $value['mfgr'];
    $tool['modelNum'] = $value['modelNum'];
    $tool['serialNum'] = $value['serialNum'];
    $tool['class'] = $value['class'];
    $tool['acquiredDate'] = $value['acquiredDate'];
    $tool['releasedDate'] = $value['releasedDate'];
    $tool['purchasePrice'] = $value['purchasePrice'];
    $tool['deprecSched'] = $value['deprecSched'];
    $tool['recoveredCost'] = $value['recoveredCost'];
    $tool['owner'] = $value['owner'];
    $tool['notes'] = $value['notes'];
    tool_save($tool);
    message_register('1 tool updated.');
    return crm_url('tools');
}

/**
 * Handle tool filter request.
 * @return The url to display on completion.
 */

/*TODO: This function, plus the "tool_filter_form" function, and the tool filter
component of the tool_data function can be used to create tool filtering
functionality
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
}*/
