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
function day_pass_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function day_pass_permissions () {
    return array(
        'day_pass_view'
        , 'day_pass_edit'
        , 'day_pass_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function day_pass_install($old_revision = 0) {
    if ($old_revision < 1) {
        $sql = '
            CREATE TABLE IF NOT EXISTS `day_pass` (
              `dpid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
              `cid` mediumint(8) unsigned NOT NULL,
              `guid` varchar(255) NOT NULL,
              `purchased` date DEFAULT NULL,
              `used` date DEFAULT NULL,
              PRIMARY KEY (`dpid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ';
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
    }
    // Permissions moved to DB, set defaults on install/upgrade
/*    if ($old_revision < 2) {
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
            'director' => array('day_pass_view', 'day_pass_edit', 'day_pass_delete')
            , 'webAdmin' => array('day_pass_view', 'day_pass_edit', 'day_pass_delete')
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
    }*/
}

// Utility functions ///////////////////////////////////////////////////////////

/**
 * Generate a descriptive string for a single day pass.
 *
 * @param $dpid The dpid of the day pass to describe.
 * @return The description string.
 */
function day_pass_description ($dpid) {
    
    // Get day pass data
    $data = crm_get_data('day_pass', array('dpid' => $dpid));
    if (empty($data)) {
        return '';
    }
    $day_pass = $data[0];
    
    // Construct description
    $description = 'Day Pass ID: ';
    $description .= $day_pass['guid'];
    
    return $description;
}

function guid () {
    if (function_exists('com_create_guid')){
        return com_create_guid();
    }else{
        mt_srand((double)microtime()*10000);//optional for php 4.2.0 and up.
        $charid = strtoupper(md5(uniqid(rand(), true)));
        $hyphen = chr(45);// "-"
        $guid = substr($charid, 0, 8).$hyphen
                .substr($charid, 8, 4).$hyphen
                .substr($charid,12, 4).$hyphen
                .substr($charid,16, 4).$hyphen
                .substr($charid,20,12);
        return $guid;
    }
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more day passes.
 *
 * @param $opts An associative array of options, possible keys are:
 *   'dpid' If specified, returns a single memeber with the matching day pass id;
 *   'cid' If specified, returns all day_passes assigned to the contact with specified id;
 *   'filter' An array mapping filter names to filter values;
 *   'join' A list of tables to join to the day_pass table.
 * @return An array with each element representing a single day pass.
*/ 
function day_pass_data ($opts = array()) {
    // Query database
    $sql = "
        SELECT
        `dpid`
        , `cid`
        , `guid`
        , `purchased`
        , `used`
        FROM `day_pass`
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
                        $sql .= " AND (`purchased` IS NOT NULL AND `used` IS NULL)";
                    } else {
                        $sql .= " AND (`purchased` IS NULL OR `used` IS NOT NULL)";
                    }
                    break;
            }
        }
    }
    $sql .= "
        ORDER BY `purchased`, `dpid` ASC";
    $res = mysql_query($sql);
    if (!$res) die(mysql_error());
    // Store data
    $day_passes = array();
    $row = mysql_fetch_assoc($res);
    while (!empty($row)) {
        // Contents of row are dpid, cid, purchased, used, guid
        $day_passes[] = $row;
        $row = mysql_fetch_assoc($res);
    }
    // Return data
    return $day_passes;
}

/**
 * Implementation of hook_data_alter().
 * @param $type The type of the data being altered.
 * @param $data An array of structures of the given $type.
 * @param $opts An associative array of options.
 * @return An array of modified structures.
 */
function day_pass_data_alter ($type, $data = array(), $opts = array()) {
    switch ($type) {
        case 'contact':
            // Get cids of all contacts passed into $data
            $cids = array();
            foreach ($data as $contact) {
                $cids[] = $contact['cid'];
            }
            // Add the cids to the options
            $day_pass_opts = $opts;
            $day_pass_opts['cid'] = $cids;
            // Get an array of day pass structures for each cid
            $day_pass_data = crm_get_data('day_pass', $day_pass_opts);
            // Create a map from cid to an array of day pass structures
            $cid_to_day_passes = array();
            foreach ($day_pass_data as $day_pass) {
                $cid_to_day_passes[$day_pass['cid']][] = $day_pass;
            }
            // Add day pass structures to the contact structures
            foreach ($data as $i => $contact) {
                if (array_key_exists($contact['cid'], $cid_to_day_passes)) {
                    $day_passes = $cid_to_day_passes[$contact['cid']];
                    $data[$i]['day_passes'] = $day_passes;
                }
            }
            break;
    }
    return $data;
}

/**
 * Save a day pass structure.  If $day_pass has a 'dpid' element, an existing day pass will
 * be updated, otherwise a new day pass will be created.
 * @param $dpid The day pass structure
 * @return The day pass structure with as it now exists in the database.
 */
function day_pass_save ($day_pass) {
    // Escape values
    $fields = array('dpid', 'cid', 'guid', 'purchased', 'used');
    if (isset($day_pass['dpid'])) {
        // Update existing day_pass
        $dpid = $day_pass['dpid'];
        $esc_dpid = mysql_real_escape_string($dpid);
        $clauses = array();
        foreach ($fields as $dp) {
            if ($dp == 'used' && empty($day_pass[$dp])) {
                continue;
            }
            if (isset($day_pass[$dp]) && $dp != 'dpid') {
                $clauses[] = "`$dp`='" . mysql_real_escape_string($day_pass[$dp]) . "' ";
            }
        }
        $sql = "UPDATE `day_pass` SET " . implode(', ', $clauses) . " ";
        $sql .= "WHERE `dpid`='$esc_dpid'";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        message_register('Day Pass updated');
    } else {
        // Insert new day_pass
        $cols = array();
        $values = array();
        foreach ($fields as $dp) {
            if (isset($day_pass[$dp])) {
                if ($dp == 'used' && empty($day_pass[$dp])) {
                    continue;
                }
                $cols[] = "`$dp`";
                $values[] = "'" . mysql_real_escape_string($day_pass[$dp]) . "'";
            }
        }
        $sql = "INSERT INTO `day_pass` (" . implode(', ', $cols) . ") ";
        $sql .= " VALUES (" . implode(', ', $values) . ")";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        $dpid = mysql_insert_id();
        message_register('Day Pass added');
    }
    return crm_get_one('day_pass', array('dpid'=>$dpid));
}

/**
 * Delete a day pass.
 * @param $day_pass The day pass data structure to delete, must have a 'dpid' element.
 */
function day_pass_delete ($day_pass) {
    $esc_dpid = mysql_real_escape_string($day_pass['dpid']);
    $sql = "DELETE FROM `day_pass` WHERE `dpid`='$esc_dpid'";
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
 * @param $opts The options to pass to day_pass_data().
 * @return The table structure.
*/
function day_pass_table ($opts) {
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
    $data = crm_get_data('day_pass', $opts);
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
    if (user_access('day_pass_view') || $opts['cid'] == user_id()) {
        $table['columns'][] = array("title"=>'Name', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Pass ID', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Purchased', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Used', 'class'=>'', 'id'=>'');
    }
    // Add ops column
    if (!$export && (user_access('day_pass_edit') || user_access('day_pass_delete'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $day_pass) {
        // Add day pass data
        $row = array();
        if (user_access('day_pass_view') || $opts['cid'] == user_id()) {
            // Add cells
            $row[] = theme('contact_name', $cid_to_contact[$day_pass['cid']], true);
            $row[] = $day_pass['guid'];
            $row[] = $day_pass['purchased'];
            $row[] = $day_pass['used'];
        }
        if (!$export && (user_access('day_pass_edit') || user_access('day_pass_delete'))) {
            // Construct ops array
            $ops = array();
            // Add edit op
            if (user_access('day_pass_edit')) {
                $ops[] = '<a href=' . crm_url('day_pass&dpid=' . $day_pass['dpid'] . '#tab-edit') . '>edit</a> ';
            }
            // Add delete op
            if (user_access('day_pass_delete')) {
                $ops[] = '<a href=' . crm_url('delete&type=day_pass&id=' . $day_pass['dpid']) . '>delete</a>';
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
function day_pass_add_form ($cid) {
    
    // Ensure user is allowed to edit day passes
    if (!user_access('day_pass_edit')) {
        return NULL;
    }
    
    $guid = guid();

    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'day_pass_add',
        'hidden' => array(
            'cid' => $cid
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Add Day Pass Assignment',
                'fields' => array(
                    array(
                        'type' => 'readonly',
                        'label' => 'Pass ID',
                        'name' => 'guid',
                        'value' => $guid,
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Purchased',
                        'name' => 'purchased',
                        'value' => date("Y-m-d"),
                        'class' => 'date'
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Used',
                        'name' => 'used',
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
function day_pass_edit_form ($dpid) {
    // Ensure user is allowed to edit day_pass
    if (!user_access('day_pass_edit')) {
        return NULL;
    }
    // Get day pass data
    $data = crm_get_data('day_pass', array('dpid'=>$dpid));
    $day_pass = $data[0];
    if (empty($day_pass) || count($day_pass) < 1) {
        return array();
    }
    // Get corresponding contact data
    $contact = crm_get_one('contact', array('cid'=>$day_pass['cid']));
    // Construct member name
    $name = theme('contact_name', $contact, true);
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'day_pass_update',
        'hidden' => array(
            'dpid' => $dpid
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Edit Day Pass Info',
                'fields' => array(
                    array(
                        'type' => 'readonly',
                        'label' => 'Name',
                        'value' => $name
                    ),
                    array(
                        'type' => 'readonly',
                        'label' => 'Pass ID',
                        'name' => 'guid',
                        'value' => $day_pass['guid']
                    ),
                    array(
                        'type' => 'text',
                        'class' => 'date',
                        'label' => 'Purchased',
                        'name' => 'purchased',
                        'value' => $day_pass['purchased']
                    ),
                    array(
                        'type' => 'text',
                        'class' => 'date',
                        'label' => 'Used',
                        'name' => 'used',
                        'value' => $day_pass['used']
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
function day_pass_delete_form ($dpid) {
    
    // Ensure user is allowed to delete day passes
    if (!user_access('day_pass_delete')) {
        return NULL;
    }
    
    // Get day pass data
    $data = crm_get_data('day_pass', array('dpid'=>$dpid));
    $day_pass = $data[0];
    
    // Construct day pass name
    $day_pass_name = "ID: $day_pass[guid], Purchased: $day_pass[purchased], Used: $day_pass[used]\n";
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'day_pass_delete',
        'hidden' => array(
            'dpid' => $day_pass['dpid']
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Delete Day Pass',
                'fields' => array(
                    array(
                        'type' => 'message',
                        'value' => '<p>Are you sure you want to delete the day pass ' . $day_pass_name . 'This cannot be undone.',
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
function day_pass_command ($command, &$url, &$params) {
    switch ($command) {
        case 'member_add':
            $params['tab'] = 'day_passes';
            break;
    }
}

/**
 * Handle day pass add request.
 *
 * @return The url to display on completion.
 */
function command_day_pass_add() {
    // Verify permissions
    if (!user_access('day_pass_edit')) {
        error_register('Permission denied: day_pass_edit');
        return crm_url('day_pass&dpid=' . $_POST['dpid']);
    }
    day_pass_save($_POST);
    return crm_url('contact&cid=' . $_POST['cid'] . '&tab=day_passes');
}

/**
 * Handle day pass update request.
 *
 * @return The url to display on completion.
 */
function command_day_pass_update() {
    // Verify permissions
    if (!user_access('day_pass_edit')) {
        error_register('Permission denied: day_pass_edit');
        return crm_url('day_pass&dpid=' . $_POST['dpid']);
    }
    // Save day pass
    day_pass_save($_POST);
    return crm_url('day_pass&dpid=' . $_POST['dpid'] . '&tab=edit');
}

/**
 * Handle day pass delete request.
 *
 * @return The url to display on completion.
 */
function command_day_pass_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('day_pass_delete')) {
        error_register('Permission denied: day_pass_delete');
        return crm_url('day_pass&dpid=' . $esc_post['dpid']);
    }
    day_pass_delete($_POST);
    return crm_url('members');
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function day_pass_page_list () {
    $pages = array();
    if (user_access('day_pass_view')) {
        $pages[] = 'day_passes';
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
function day_pass_page (&$page_data, $page_name, $options) {
    
    switch ($page_name) {
        
        case 'contact':
            
            // Capture contact cid
            $cid = $options['cid'];
            if (empty($cid)) {
                return;
            }
            
            // Add day passes tab
            if (user_access('day_pass_view') || user_access('day_pass_edit') || user_access('day_pass_delete') || $cid == user_id()) {
                $day_passes = theme('table', 'day_pass', array('cid' => $cid));
                $day_passes .= theme('day_pass_add_form', $cid);
                page_add_content_bottom($page_data, $day_passes, 'Day_Passes');
            }
            
            break;
        
        case 'day_passes':
            page_set_title($page_data, 'Day Passes');
            if (user_access('day_pass_view')) {
                $day_passes = theme('table', 'day_pass', array('join'=>array('contact', 'member'), 'show_export'=>true));
                page_add_content_top($page_data, $day_passes, 'View');
            }
            break;
        
        case 'day_pass':
            
            // Capture day pass id
            $dpid = $options['dpid'];
            if (empty($dpid)) {
                return;
            }
            
            // Set page title
            page_set_title($page_data, day_pass_description($dpid));
            
            // Add edit tab
            if (user_access('day_pass_view') || user_access('day_pass_edit') || user_access('day_pass_delete')) {
                page_add_content_top($page_data, theme('day_pass_edit_form', $dpid), 'Edit');
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
function theme_day_pass_add_form ($cid) {
    return theme('form', crm_get_form('day_pass_add', $cid));
}

/**
 * Return themed html for an edit day pass form.
 *
 * @param $dpid The dpid of the day pass to edit.
 * @return The themed html string.
 */
function theme_day_pass_edit_form ($dpid) {
    return theme('form', crm_get_form('day_pass_edit', $dpid));
}

?>