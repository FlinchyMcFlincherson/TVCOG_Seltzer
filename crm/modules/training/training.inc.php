<?php 

/*
    Copyright 2009-2013 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    training.inc.php - training tracking module

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
function training_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function training_permissions () {
    return array(
        'training_view'
        , 'training_edit'
        , 'training_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function training_install($old_revision = 0) {
    if ($old_revision < 1) {
        $sql = '
            CREATE TABLE IF NOT EXISTS `training` (
              `tid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
              `cid` mediumint(8) unsigned NOT NULL,
              `start` date DEFAULT NULL,
              `end` date DEFAULT NULL,
              `serial` varchar(255) NOT NULL,
              PRIMARY KEY (`tid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ';

        $res = mysql_query($sql);
        if (!$res) die(mysql_error());

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
            'director' => array('training_view', 'training_edit', 'training_delete')
            , 'webAdmin' => array('training_view', 'training_edit', 'training_delete')
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
 * Generate a descriptive string for a single training.
 *
 * @param $tid The tid of the training to describe.
 * @return The description string.
 */
function training_description ($tid) {
    
    // Get training data
    $data = crm_get_data('training', array('tid' => $tid));
    if (empty($data)) {
        return '';
    }
    $training = $data[0];
    
    // Construct description
    $description = 'Training ';
    $description .= $training['serial'];
    
    return $description;
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more training records.
 *
 * @param $opts An associative array of options, possible keys are:
 *   'tid' If specified, returns a single memeber with the matching training id;
 *   'cid' If specified, returns all trainings completed by the contact with specified id;
 *   'filter' An array mapping filter names to filter values;
 *   'join' A list of tables to join to the training table.
 * @return An array with each element representing a single training record.
*/ 
function training_data ($opts = array()) {
    // Query database
    $sql = "
        SELECT
        `tid`
        , `cid`
        , `start`
        , `end`
        , `serial`
        FROM `training`
        WHERE 1";
    if (!empty($opts['tid'])) {
        $esc_tid = mysql_real_escape_string($opts['tid']);
        $sql .= " AND `tid`='$esc_tid'";
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
        ORDER BY `start`, `tid` ASC";
    $res = mysql_query($sql);
    if (!$res) die(mysql_error());
    // Store data
    $trainings = array();
    $row = mysql_fetch_assoc($res);
    while (!empty($row)) {
        // Contents of row are tid, cid, start, end, serial
        $trainings[] = $row;
        $row = mysql_fetch_assoc($res);
    }
    // Return data
    return $trainings;
}

/**
 * Implementation of hook_data_alter().
 * @param $type The type of the data being altered.
 * @param $data An array of structures of the given $type.
 * @param $opts An associative array of options.
 * @return An array of modified structures.
 */
function training_data_alter ($type, $data = array(), $opts = array()) {
    switch ($type) {
        case 'contact':
            // Get cids of all contacts passed into $data
            $cids = array();
            foreach ($data as $contact) {
                $cids[] = $contact['cid'];
            }
            // Add the cids to the options
            $training_opts = $opts;
            $training_opts['cid'] = $cids;
            // Get an array of training structures for each cid
            $training_data = crm_get_data('training', $training_opts);
            // Create a map from cid to an array of training structures
            $cid_to_trainings = array();
            foreach ($training_data as $training) {
                $cid_to_trainings[$training['cid']][] = $training;
            }
            // Add training structures to the contact structures
            foreach ($data as $i => $contact) {
                if (array_key_exists($contact['cid'], $cid_to_trainings)) {
                    $trainings = $cid_to_trainings[$contact['cid']];
                    $data[$i]['trainings'] = $trainings;
                }
            }
            break;
    }
    return $data;
}

/**
 * Save a training structure.  If $training has a 'tid' element, an existing training will
 * be updated, otherwise a new training will be created.
 * @param $tid The training structure
 * @return The training structure as it now exists in the database.
 */
function training_save ($training) {
    // Escape values
    $fields = array('tid', 'cid', 'serial', 'start', 'end');
    if (isset($training['tid'])) {
        // Update existing training
        $tid = $training['tid'];
        $esc_tid = mysql_real_escape_string($tid);
        $clauses = array();
        foreach ($fields as $t) {
            if ($t == 'end' && empty($training[$t])) {
                continue;
            }
            if (isset($training[$t]) && $t != 'tid') {
                $clauses[] = "`$t`='" . mysql_real_escape_string($training[$t]) . "' ";
            }
        }
        $sql = "UPDATE `training` SET " . implode(', ', $clauses) . " ";
        $sql .= "WHERE `tid`='$esc_tid'";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        message_register('Training record updated');
    } else {
        // Insert new training
        $cols = array();
        $values = array();
        foreach ($fields as $t) {
            if (isset($training[$t])) {
                if ($t == 'end' && empty($training[$t])) {
                    continue;
                }
                $cols[] = "`$t`";
                $values[] = "'" . mysql_real_escape_string($training[$t]) . "'";
            }
        }
        $sql = "INSERT INTO `training` (" . implode(', ', $cols) . ") ";
        $sql .= " VALUES (" . implode(', ', $values) . ")";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        $tid = mysql_insert_id();
        message_register('Training record added');
    }
    return crm_get_one('training', array('tid'=>$tid));
}

/**
 * Delete a training record.
 * @param $training The training data structure to delete, must have a 'tid' element.
 */
function training_delete ($training) {
    $esc_tid = mysql_real_escape_string($training['tid']);
    $sql = "DELETE FROM `training` WHERE `tid`='$esc_tid'";
    $res = mysql_query($sql);
    if (!$res) die(mysql_error());
    if (mysql_affected_rows() > 0) {
        message_register('Training record deleted.');
    }
}

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return a table structure for a table of training records.
 *
 * @param $opts The options to pass to training_data().
 * @return The table structure.
*/
function training_table ($opts) {
    // Determine settings
    $export = false;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get training data
    $data = crm_get_data('training', $opts);
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
    if (user_access('training_view') || $opts['cid'] == user_id()) {
        $table['columns'][] = array("title"=>'Name', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Serial', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Start', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'End', 'class'=>'', 'id'=>'');
    }
    // Add ops column
    if (!$export && (user_access('training_edit') || user_access('training_delete'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $training) {
        // Add training data
        $row = array();
        if (user_access('training_view') || $opts['cid'] == user_id()) {
            // Add cells
            $row[] = theme('contact_name', $cid_to_contact[$training['cid']], true);
            $row[] = $training['serial'];
            $row[] = $training['start'];
            $row[] = $training['end'];
        }
        if (!$export && (user_access('training_edit') || user_access('training_delete'))) {
            // Construct ops array
            $ops = array();
            // Add edit op
            if (user_access('training_edit')) {
                $ops[] = '<a href=' . crm_url('training&tid=' . $training['tid'] . '#tab-edit') . '>edit</a> ';
            }
            // Add delete op
            if (user_access('training_delete')) {
                $ops[] = '<a href=' . crm_url('delete&type=training&id=' . $training['tid']) . '>delete</a>';
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
 * Return the form structure for the add training record form.
 *
 * @param The cid of the contact to add a training record for.
 * @return The form structure.
*/
function training_add_form ($cid) {
    
    // Ensure user is allowed to edit training records
    if (!user_access('training_edit')) {
        return NULL;
    }
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'training_add',
        'hidden' => array(
            'cid' => $cid
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Add Training Record',
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
 * Return the form structure for an edit training record form.
 *
 * @param $tid The tid of the training record to edit.
 * @return The form structure.
*/
function training_edit_form ($tid) {
    // Ensure user is allowed to edit training
    if (!user_access('training_edit')) {
        return NULL;
    }
    // Get training data
    $data = crm_get_data('training', array('tid'=>$tid));
    $training = $data[0];
    if (empty($training) || count($training) < 1) {
        return array();
    }
    // Get corresponding contact data
    $contact = crm_get_one('contact', array('cid'=>$training['cid']));
    // Construct member name
    $name = theme('contact_name', $contact, true);
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'training_update',
        'hidden' => array(
            'tid' => $tid
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Edit Training Record',
                'fields' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Serial',
                        'name' => 'serial',
                        'value' => $training['serial']
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
                        'value' => $training['start']
                    ),
                    array(
                        'type' => 'text',
                        'class' => 'date',
                        'label' => 'End',
                        'name' => 'end',
                        'value' => $training['end']
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
 * Return the delete training record form structure.
 *
 * @param $tid The tid of the training record to delete.
 * @return The form structure.
*/
function training_delete_form ($tid) {
    
    // Ensure user is allowed to delete training records
    if (!user_access('training_delete')) {
        return NULL;
    }
    
    // Get training data
    $data = crm_get_data('training', array('tid'=>$tid));
    $training = $data[0];
    
    // Construct training name
    $training_name = "training:$training[tid] serial:$training[serial] $training[start] -- $training[end]";
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'training_delete',
        'hidden' => array(
            'tid' => $training['tid']
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Delete Training Record',
                'fields' => array(
                    array(
                        'type' => 'message',
                        'value' => '<p>Are you sure you want to delete the training record "' . $training_name . '"? This cannot be undone.',
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
function training_command ($command, &$url, &$params) {
    switch ($command) {
        case 'member_add':
            $params['tab'] = 'trainings';
            break;
    }
}

/**
 * Handle training add request.
 *
 * @return The url to display on completion.
 */
function command_training_add() {
    // Verify permissions
    if (!user_access('training_edit')) {
        error_register('Permission denied: training_edit');
        return crm_url('training&tid=' . $_POST['tid']);
    }
    training_save($_POST);
    return crm_url('contact&cid=' . $_POST['cid'] . '&tab=trainings');
}

/**
 * Handle training record update request.
 *
 * @return The url to display on completion.
 */
function command_training_update() {
    // Verify permissions
    if (!user_access('training_edit')) {
        error_register('Permission denied: training_edit');
        return crm_url('training&tid=' . $_POST['tid']);
    }
    // Save training record
    training_save($_POST);
    return crm_url('training&tid=' . $_POST['tid'] . '&tab=edit');
}

/**
 * Handle training record delete request.
 *
 * @return The url to display on completion.
 */
function command_training_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('training_delete')) {
        error_register('Permission denied: training_delete');
        return crm_url('training&tid=' . $esc_post['tid']);
    }
    training_delete($_POST);
    return crm_url('members');
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function training_page_list () {
    $pages = array();
    if (user_access('training_view')) {
        $pages[] = 'trainings';
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
function training_page (&$page_data, $page_name, $options) {
    
    switch ($page_name) {
        
        case 'contact':
            
            // Capture contact cid
            $cid = $options['cid'];
            if (empty($cid)) {
                return;
            }
            
            // Add trainings tab
            if (user_access('training_view') || user_access('training_edit') || user_access('training_delete') || $cid == user_id()) {
                $trainings = theme('table', 'training', array('cid' => $cid));
                $trainings .= theme('training_add_form', $cid);
                page_add_content_bottom($page_data, $trainings, 'Training');
            }
            
            break;
        
        case 'trainings':
            page_set_title($page_data, 'All Training Records');
            if (user_access('training_view')) {
                $trainings = theme('table', 'training', array('join'=>array('contact', 'member'), 'show_export'=>true));
                page_add_content_top($page_data, $training, 'View');
            }
            break;
        
        case 'training':
            
            // Capture training id
            $tid = $options['tid'];
            if (empty($tid)) {
                return;
            }
            
            // Set page title
            page_set_title($page_data, training_description($tid));
            
            // Add edit tab
            if (user_access('training_view') || user_access('training_edit') || user_access('training_delete')) {
                page_add_content_top($page_data, theme('training_edit_form', $tid), 'Edit');
            }
            
            break;
    }
}

// Themeing ////////////////////////////////////////////////////////////////////

/**
 * Return the themed html for an add training record form.
 *
 * @param $cid The id of the contact to add a training record for.
 * @return The themed html string.
 */
function theme_training_add_form ($cid) {
    return theme('form', crm_get_form('training_add', $cid));
}

/**
 * Return themed html for an edit training record form.
 *
 * @param $tid The tid of the training record to edit.
 * @return The themed html string.
 */
function theme_training_edit_form ($tid) {
    return theme('form', crm_get_form('training_edit', $tid));
}

?>