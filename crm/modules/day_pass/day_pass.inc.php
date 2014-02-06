<?php 

/*
    Copyright 2009-2013 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    day_pass.inc.php - Day Pass tracking module

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

// Installation functions //////////////////////////////////////////////////////

/**
 * @return This module's revision number.  Each new release should increment
 * this number.
 */
function daypass_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function daypass_permissions () {
    return array(
        'daypass_view'
        , 'daypass_edit'
        , 'daypass_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function daypass_install($old_revision = 0) {
    if ($old_revision < 1) {
        $sql = '
            CREATE TABLE IF NOT EXISTS `daypass` (
              `dpid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
              `cid` mediumint(8) unsigned NOT NULL,
              `start` date DEFAULT NULL,
              `end` date DEFAULT NULL,
              `serial` varchar(255) NOT NULL,
              PRIMARY KEY (`dpid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ';
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
    }
    // Permissions moved to DB, set defaults on install/upgrade
    if ($old_revision < 2) {
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
            'director' => array('daypass_view', 'daypass_edit', 'daypass_delete')
            , 'webAdmin' => array('daypass_view', 'daypass_edit', 'daypass_delete')
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

// Utility functions ///////////////////////////////////////////////////////////

/**
 * Generate a descriptive string for a single day pass.
 *
 * @param $dpid The dpid of the day pass to describe.
 * @return The description string.
 */
function daypass_description ($dpid) {
    
    // Get day pass data
    $data = crm_get_data('daypass', array('dpid' => $dpid));
    if (empty($data)) {
        return '';
    }
    $daypass = $data[0];
    
    // Construct description
    $description = 'Day Pass ';
    $description .= $daypass['serial'];
    
    return $description;
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more day passes.
 *
 * @param $opts An associative array of options, possible keys are:
 *   'dpid' If specified, returns a single memeber with the matching day pass id;
 *   'cid' If specified, returns all daypasses assigned to the contact with specified id;
 *   'filter' An array mapping filter names to filter values;
 *   'join' A list of tables to join to the daypass table.
 * @return An array with each element representing a single day pass.
*/ 
function daypass_data ($opts = array()) {
    // Query database
    $sql = "
        SELECT
        `dpid`
        , `cid`
        , `start`
        , `end`
        , `serial`
        FROM `daypass`
        WHERE 1";
    if (!empty($opts['dpid'])) {
        $esc_dpid = mysql_real_escape_string($opts['dpid']);
        $sql .= " AND `dpid`='$esc_dpid'";
    }
    if (!empty($opts['cid'])) {
        if (is_array($opts['cid'])) {
            $terms = array();
            foreach ($opts['cid'] as $cid) {
                $esc_cid = mysql_real_escape_string($cid);
                $terms[] = "'$cid'";
            }
            $sql .= " AND `cid` IN (" . implode(', ', $terms) . ") ";
        } else {
            $esc_cid = mysql_real_escape_string($opts['cid']);
            $sql .= " AND `cid`='$esc_cid'";
        }
    }
    if (!empty($opts['filter'])) {
        foreach ($opts['filter'] as $name => $param) {
            switch ($name) {
                case 'active':
                    if ($param) {
                        $sql .= " AND (`start` IS NOT NULL AND `end` IS NULL)";
                    } else {
                        $sql .= " AND (`start` IS NULL OR `end` IS NOT NULL)";
                    }
                    break;
            }
        }
    }
    $sql .= "
        ORDER BY `start`, `dpid` ASC";
    $res = mysql_query($sql);
    if (!$res) die(mysql_error());
    // Store data
    $daypasses = array();
    $row = mysql_fetch_assoc($res);
    while (!empty($row)) {
        // Contents of row are dpid, cid, start, end, serial
        $daypasses[] = $row;
        $row = mysql_fetch_assoc($res);
    }
    // Return data
    return $daypasses;
}

/**
 * Implementation of hook_data_alter().
 * @param $type The type of the data being altered.
 * @param $data An array of structures of the given $type.
 * @param $opts An associative array of options.
 * @return An array of modified structures.
 */
function daypass_data_alter ($type, $data = array(), $opts = array()) {
    switch ($type) {
        case 'contact':
            // Get cids of all contacts passed into $data
            $cids = array();
            foreach ($data as $contact) {
                $cids[] = $contact['cid'];
            }
            // Add the cids to the options
            $daypass_opts = $opts;
            $daypass_opts['cid'] = $cids;
            // Get an array of day pass structures for each cid
            $daypass_data = crm_get_data('daypass', $daypass_opts);
            // Create a map from cid to an array of day pass structures
            $cid_to_daypasses = array();
            foreach ($daypass_data as $daypass) {
                $cid_to_daypasses[$daypass['cid']][] = $daypass;
            }
            // Add day pass structures to the contact structures
            foreach ($data as $i => $contact) {
                if (array_key_exists($contact['cid'], $cid_to_daypasses)) {
                    $daypasses = $cid_to_daypasses[$contact['cid']];
                    $data[$i]['daypasses'] = $daypasses;
                }
            }
            break;
    }
    return $data;
}

/**
 * Save a day pass structure.  If $daypass has a 'dpid' element, an existing day pass will
 * be updated, otherwise a new day pass will be created.
 * @param $dpid The day pass structure
 * @return The day pass structure with as it now exists in the database.
 */
function daypass_save ($daypass) {
    // Escape values
    $fields = array('dpid', 'cid', 'serial', 'start', 'end');
    if (isset($daypass['dpid'])) {
        // Update existing daypass
        $dpid = $daypass['dpid'];
        $esc_dpid = mysql_real_escape_string($dpid);
        $clauses = array();
        foreach ($fields as $k) {
            if ($k == 'end' && empty($daypass[$k])) {
                continue;
            }
            if (isset($daypass[$k]) && $k != 'dpid') {
                $clauses[] = "`$k`='" . mysql_real_escape_string($daypass[$k]) . "' ";
            }
        }
        $sql = "UPDATE `daypass` SET " . implode(', ', $clauses) . " ";
        $sql .= "WHERE `dpid`='$esc_dpid'";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        message_register('Day Pass updated');
    } else {
        // Insert new daypass
        $cols = array();
        $values = array();
        foreach ($fields as $k) {
            if (isset($daypass[$k])) {
                if ($k == 'end' && empty($daypass[$k])) {
                    continue;
                }
                $cols[] = "`$k`";
                $values[] = "'" . mysql_real_escape_string($daypass[$k]) . "'";
            }
        }
        $sql = "INSERT INTO `daypass` (" . implode(', ', $cols) . ") ";
        $sql .= " VALUES (" . implode(', ', $values) . ")";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        $dpid = mysql_insert_id();
        message_register('Day Pass added');
    }
    return crm_get_one('daypass', array('dpid'=>$dpid));
}

/**
 * Delete a day pass.
 * @param $daypass The day pass data structure to delete, must have a 'dpid' element.
 */
function daypass_delete ($daypass) {
    $esc_dpid = mysql_real_escape_string($daypass['dpid']);
    $sql = "DELETE FROM `daypass` WHERE `dpid`='$esc_dpid'";
    $res = mysql_query($sql);
    if (!$res) die(mysql_error());
    if (mysql_affected_rows() > 0) {
        message_register('Day Pass deleted.');
    }
}

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return a table structure for a table of day pass assignments.
 *
 * @param $opts The options to pass to daypass_data().
 * @return The table structure.
*/
function daypass_table ($opts) {
    // Determine settings
    $export = false;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get day pass data
    $data = crm_get_data('daypass', $opts);
    if (count($data) < 1) {
        return array();
    }
    // Get contact info
    $contact_opts = array();
    foreach ($data as $row) {
        $contact_opts['cid'][] = $row['cid'];
    }
    $contact_data = crm_get_data('contact', $contact_opts);
    $cid_to_contact = crm_map($contact_data, 'cid');
    // Initialize table
    $table = array(
        "id" => '',
        "class" => '',
        "rows" => array(),
        "columns" => array()
    );
    // Add columns
    if (user_access('daypass_view') || $opts['cid'] == user_id()) {
        $table['columns'][] = array("title"=>'Name', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Serial', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Start', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'End', 'class'=>'', 'id'=>'');
    }
    // Add ops column
    if (!$export && (user_access('daypass_edit') || user_access('daypass_delete'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $daypass) {
        // Add day pass data
        $row = array();
        if (user_access('daypass_view') || $opts['cid'] == user_id()) {
            // Add cells
            $row[] = theme('contact_name', $cid_to_contact[$daypass['cid']], true);
            $row[] = $daypass['serial'];
            $row[] = $daypass['start'];
            $row[] = $daypass['end'];
        }
        if (!$export && (user_access('daypass_edit') || user_access('daypass_delete'))) {
            // Construct ops array
            $ops = array();
            // Add edit op
            if (user_access('daypass_edit')) {
                $ops[] = '<a href=' . crm_url('daypass&dpid=' . $daypass['dpid'] . '#tab-edit') . '>edit</a> ';
            }
            // Add delete op
            if (user_access('daypass_delete')) {
                $ops[] = '<a href=' . crm_url('delete&type=daypass&id=' . $daypass['dpid']) . '>delete</a>';
            }
            // Add ops row
            $row[] = join(' ', $ops);
        }
        $table['rows'][] = $row;
    }
    return $table;
}

// Forms ///////////////////////////////////////////////////////////////////////

/**
 * Return the form structure for the add day pass assignment form.
 *
 * @param The cid of the contact to add a day pass assignment for.
 * @return The form structure.
*/
function daypass_add_form ($cid) {
    
    // Ensure user is allowed to edit day passes
    if (!user_access('daypass_edit')) {
        return NULL;
    }
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'daypass_add',
        'hidden' => array(
            'cid' => $cid
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Add Day Pass Assignment',
                'fields' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Serial',
                        'name' => 'serial'
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Start',
                        'name' => 'start',
                        'value' => date("Y-m-d"),
                        'class' => 'date'
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'End',
                        'name' => 'end',
                        'class' => 'date'
                    ),
                    array(
                        'type' => 'submit',
                        'value' => 'Add'
                    )
                )
            )
        )
    );
    
    return $form;
}

/**
 * Return the form structure for an edit day passes form.
 *
 * @param $dpid The dpid of the day pass to edit.
 * @return The form structure.
*/
function daypass_edit_form ($dpid) {
    // Ensure user is allowed to edit daypass
    if (!user_access('daypass_edit')) {
        return NULL;
    }
    // Get day pass data
    $data = crm_get_data('daypass', array('dpid'=>$dpid));
    $daypass = $data[0];
    if (empty($daypass) || count($daypass) < 1) {
        return array();
    }
    // Get corresponding contact data
    $contact = crm_get_one('contact', array('cid'=>$daypass['cid']));
    // Construct member name
    $name = theme('contact_name', $contact, true);
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'daypass_update',
        'hidden' => array(
            'dpid' => $dpid
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Edit Day Pass Info',
                'fields' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Serial',
                        'name' => 'serial',
                        'value' => $daypass['serial']
                    ),
                    array(
                        'type' => 'readonly',
                        'label' => 'Name',
                        'value' => $name
                    ),
                    array(
                        'type' => 'text',
                        'class' => 'date',
                        'label' => 'Start',
                        'name' => 'start',
                        'value' => $daypass['start']
                    ),
                    array(
                        'type' => 'text',
                        'class' => 'date',
                        'label' => 'End',
                        'name' => 'end',
                        'value' => $daypass['end']
                    ),
                    array(
                        'type' => 'submit',
                        'value' => 'Update'
                    )
                )
            )
        )
    );
    
    return $form;
}

/**
 * Return the delete day pass form structure.
 *
 * @param $dpid The dpid of the day pass to delete.
 * @return The form structure.
*/
function daypass_delete_form ($dpid) {
    
    // Ensure user is allowed to delete day passes
    if (!user_access('daypass_delete')) {
        return NULL;
    }
    
    // Get day pass data
    $data = crm_get_data('daypass', array('dpid'=>$dpid));
    $daypass = $data[0];
    
    // Construct day pass name
    $daypass_name = "Day Pass:$daypass[dpid] serial:$daypass[serial] $daypass[start] -- $daypass[end]";
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'daypass_delete',
        'hidden' => array(
            'dpid' => $daypass['dpid']
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Delete Day Pass',
                'fields' => array(
                    array(
                        'type' => 'message',
                        'value' => '<p>Are you sure you want to delete the daypass assignment "' . $daypass_name . '"? This cannot be undone.',
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

// Request Handlers ////////////////////////////////////////////////////////////

/**
 * Command handler.
 * @param $command The name of the command to handle.
 * @param &$url A reference to the url to be loaded after completion.
 * @param &$params An associative array of query parameters for &$url.
 */
function daypass_command ($command, &$url, &$params) {
    switch ($command) {
        case 'member_add':
            $params['tab'] = 'daypasses';
            break;
    }
}

/**
 * Handle day pass add request.
 *
 * @return The url to display on completion.
 */
function command_daypass_add() {
    // Verify permissions
    if (!user_access('daypass_edit')) {
        error_register('Permission denied: daypass_edit');
        return crm_url('daypass&dpid=' . $_POST['dpid']);
    }
    daypass_save($_POST);
    return crm_url('contact&cid=' . $_POST['cid'] . '&tab=daypasses');
}

/**
 * Handle day pass update request.
 *
 * @return The url to display on completion.
 */
function command_daypass_update() {
    // Verify permissions
    if (!user_access('daypass_edit')) {
        error_register('Permission denied: daypass_edit');
        return crm_url('daypass&dpid=' . $_POST['dpid']);
    }
    // Save day pass
    daypass_save($_POST);
    return crm_url('daypass&dpid=' . $_POST['dpid'] . '&tab=edit');
}

/**
 * Handle day pass delete request.
 *
 * @return The url to display on completion.
 */
function command_daypass_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('daypass_delete')) {
        error_register('Permission denied: daypass_delete');
        return crm_url('daypass&dpid=' . $esc_post['dpid']);
    }
    daypass_delete($_POST);
    return crm_url('members');
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function daypass_page_list () {
    $pages = array();
    if (user_access('daypass_view')) {
        $pages[] = 'daypasses';
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
function daypass_page (&$page_data, $page_name, $options) {
    
    switch ($page_name) {
        
        case 'contact':
            
            // Capture contact cid
            $cid = $options['cid'];
            if (empty($cid)) {
                return;
            }
            
            // Add day passes tab
            if (user_access('daypass_view') || user_access('daypass_edit') || user_access('daypass_delete') || $cid == user_id()) {
                $daypasses = theme('table', 'daypass', array('cid' => $cid));
                $daypasses .= theme('daypass_add_form', $cid);
                page_add_content_bottom($page_data, $daypasses, 'Day Passes');
            }
            
            break;
        
        case 'daypasses':
            page_set_title($page_data, 'Day Passes');
            if (user_access('daypass_view')) {
                $daypasses = theme('table', 'daypass', array('join'=>array('contact', 'member'), 'show_export'=>true));
                page_add_content_top($page_data, $daypasses, 'View');
            }
            break;
        
        case 'daypass':
            
            // Capture day pass id
            $dpid = $options['dpid'];
            if (empty($dpid)) {
                return;
            }
            
            // Set page title
            page_set_title($page_data, daypass_description($dpid));
            
            // Add edit tab
            if (user_access('daypass_view') || user_access('daypass_edit') || user_access('daypass_delete')) {
                page_add_content_top($page_data, theme('daypass_edit_form', $dpid), 'Edit');
            }
            
            break;
    }
}

// Themeing ////////////////////////////////////////////////////////////////////

/**
 * Return the themed html for an add day pass form.
 *
 * @param $cid The id of the contact to add a day pass assignment for.
 * @return The themed html string.
 */
function theme_daypass_add_form ($cid) {
    return theme('form', crm_get_form('daypass_add', $cid));
}

/**
 * Return themed html for an edit day pass form.
 *
 * @param $dpid The dpid of the day pass to edit.
 * @return The themed html string.
 */
function theme_daypass_edit_form ($dpid) {
    return theme('form', crm_get_form('daypass_edit', $dpid));
}

?>