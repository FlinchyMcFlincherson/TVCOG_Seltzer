<?php 

/*
    Copyright 2009-2013 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    discount.inc.php - Discount tracking module

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
function discount_revision () {
    return 1;
}

/**
 * @return An array of the permissions provided by this module.
 */
function discount_permissions () {
    return array(
        'discount_view'
        , 'discount_edit'
        , 'discount_delete'
    );
}

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 *
 * Discounts Table Fields (Per TTongue):
 *  -Discount Trans ID
 *  -User ID (Contact ID)
 *  -Date Applied
 *  -Amount
 *  -Applied By
 *  -Reason
 *  -Invoice/Bill ID (Braintree Trans ID)
 */
function discount_install($old_revision = 0) {
    if ($old_revision < 1) {
        $sql = '
            CREATE TABLE IF NOT EXISTS `discount` (
              `did` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
              `cid` mediumint(8) unsigned NOT NULL,
              `amount` mediumint(8) unsigned NOT NULL,
              `reason` varchar(255) NOT NULL,
              `invoiceID` varchar(255) NOT NULL,
              `appliedOn` date DEFAULT NULL,
              `appliedBy` varchar(255) NOT NULL,
              PRIMARY KEY (`did`)
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
            'director' => array('discount_view', 'discount_edit', 'discount_delete')
            , 'webAdmin' => array('discount_view', 'discount_edit', 'discount_delete')
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
 * Generate a descriptive string for a single discount.
 *
 * @param $did The did of the discount to describe.
 * @return The description string.
 */
function discount_description ($did) {
    
    // Get discount data
    $data = crm_get_data('discount', array('did' => $did));
    if (empty($data)) {
        return '';
    }
    $discount = $data[0];
    
    // Construct description
    $description = 'Discount Record: ';
    $description .= $discount['did'];
    
    return $description;
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more discount records.
 *
 * @param $opts An associative array of options, possible keys are:
 *   'did' If specified, returns a single memeber with the matching discount id;
 *   'cid' If specified, returns all discounts assigned to the contact with specified id;
 *   'filter' An array mapping filter names to filter values;
 *   'join' A list of tables to join to the discount table.
 * @return An array with each element representing a single discount record.
*/ 
function discount_data ($opts = array()) {
    // Query database
    $sql = "
        SELECT
        `did`
        , `cid`
        , `amount`
        , `reason`
        , `invoiceID`
        , `appliedOn`
        , `appliedBy`
        FROM `discount`
        WHERE 1";
    if (!empty($opts['did'])) {
        $esc_did = mysql_real_escape_string($opts['did']);
        $sql .= " AND `did`='$esc_did'";
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
                        $sql .= " AND (`appliedOn` IS NOT NULL AND `invoiceID` IS NULL)";
                    } else {
                        $sql .= " AND (`appliedOn` IS NULL OR `invoiceID` IS NOT NULL)";
                    }
                    break;
            }
        }
    }
    $sql .= "
        ORDER BY `appliedOn`, `did` ASC";
    $res = mysql_query($sql);
    if (!$res) die(mysql_error());
    // Store data
    $discounts = array();
    $row = mysql_fetch_assoc($res);
    while (!empty($row)) {
        // Contents of row are did, cid, amount, reason, invoiceID, appliedOn, appliedBy
        $discounts[] = $row;
        $row = mysql_fetch_assoc($res);
    }
    // Return data
    return $discounts;
}

/**
 * Implementation of hook_data_alter().
 * @param $type The type of the data being altered.
 * @param $data An array of structures of the given $type.
 * @param $opts An associative array of options.
 * @return An array of modified structures.
 */
function discount_data_alter ($type, $data = array(), $opts = array()) {
    switch ($type) {
        case 'contact':
            // Get cids of all contacts passed into $data
            $cids = array();
            foreach ($data as $contact) {
                $cids[] = $contact['cid'];
            }
            // Add the cids to the options
            $discount_opts = $opts;
            $discount_opts['cid'] = $cids;
            // Get an array of discount structures for each cid
            $discount_data = crm_get_data('discount', $discount_opts);
            // Create a map from cid to an array of discount structures
            $cid_to_discounts = array();
            foreach ($discount_data as $discount) {
                $cid_to_discounts[$discount['cid']][] = $discount;
            }
            // Add discount structures to the contact structures
            foreach ($data as $i => $contact) {
                if (array_key_exists($contact['cid'], $cid_to_discounts)) {
                    $discounts = $cid_to_discounts[$contact['cid']];
                    $data[$i]['discounts'] = $discounts;
                }
            }
            break;
    }
    return $data;
}

/**
 * Save a discount structure.  If $discount has a 'did' element, an existing discount will
 * be updated, otherwise a new discount will be created.
 * @param $did The discount structure
 * @return The discount structure with as it now exists in the database.
 */
function discount_save ($discount) {
    // Escape values
    $fields = array('did', 'cid', 'amount', 'reason', 'invoiceID', 'appliedOn', 'appliedBy');
    if (isset($discount['did'])) {
        // Update existing discount
        $did = $discount['did'];
        $esc_did = mysql_real_escape_string($did);
        $clauses = array();
        foreach ($fields as $d) {
            if ($d == 'appliedBy' && empty($discount[$d])) {
                continue;
            }
            if (isset($discount[$d]) && $d != 'did') {
                $clauses[] = "`$d`='" . mysql_real_escape_string($discount[$d]) . "' ";
            }
        }
        $sql = "UPDATE `discount` SET " . implode(', ', $clauses) . " ";
        $sql .= "WHERE `did`='$esc_did'";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        message_register('Discount updated');
    } else {
        // Insert new discount
        $cols = array();
        $values = array();
        foreach ($fields as $d) {
            if (isset($discount[$d])) {
                if ($d == 'appliedBy' && empty($discount[$d])) {
                    continue;
                }
                $cols[] = "`$d`";
                $values[] = "'" . mysql_real_escape_string($discount[$d]) . "'";
            }
        }
        $sql = "INSERT INTO `discount` (" . implode(', ', $cols) . ") ";
        $sql .= " VALUES (" . implode(', ', $values) . ")";
        $res = mysql_query($sql);
        if (!$res) die(mysql_error());
        $did = mysql_insert_id();
        message_register('Discount added');
    }
    return crm_get_one('discount', array('did'=>$did));
}

/**
 * Delete a discount.
 * @param $discount The discount data structure to delete, must have a 'did' element.
 */
function discount_delete ($discount) {
    $esc_did = mysql_real_escape_string($discount['did']);
    $sql = "DELETE FROM `discount` WHERE `did`='$esc_did'";
    $res = mysql_query($sql);
    if (!$res) die(mysql_error());
    if (mysql_affected_rows() > 0) {
        message_register('Discount deleted.');
    }
}

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return a table structure for a table of discount assignments.
 *
 * @param $opts The options to pass to discount_data().
 * @return The table structure.
*/
function discount_table ($opts) {
    // Determine settings
    $export = false;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get discount data
    $data = crm_get_data('discount', $opts);
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
    if (user_access('discount_view') || $opts['cid'] == user_id()) {
        $table['columns'][] = array("title"=>'Discount ID', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Name', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Amount', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Reason', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Invoice ID', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Applied On', 'class'=>'', 'id'=>'');
        $table['columns'][] = array("title"=>'Applied By', 'class'=>'', 'id'=>'');
    }
    // Add ops column
    if (!$export && (user_access('discount_edit') || user_access('discount_delete'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Add rows
    foreach ($data as $discount) {
        // Add discount data
        $row = array();
        if (user_access('discount_view') || $opts['cid'] == user_id()) {
            // Add cells
            $row[] = $discount['did'];
            $row[] = theme('contact_name', $cid_to_contact[$discount['cid']], true);
            $row[] = $discount['amount'];
            $row[] = $discount['reason'];
            $row[] = $discount['invoiceID'];
            $row[] = $discount['appliedOn'];
            $row[] = $discount['appliedBy'];
        }
        if (!$export && (user_access('discount_edit') || user_access('discount_delete'))) {
            // Construct ops array
            $ops = array();
            // Add edit op
            if (user_access('discount_edit')) {
                $ops[] = '<a href=' . crm_url('discount&did=' . $discount['did'] . '#tab-edit') . '>edit</a> ';
            }
            // Add delete op
            if (user_access('discount_delete')) {
                $ops[] = '<a href=' . crm_url('delete&type=discount&id=' . $discount['did']) . '>delete</a>';
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
 * Return the form structure for the add discount assignment form.
 *
 * @param The cid of the contact to add a discount assignment for.
 * @return The form structure.
*/
function discount_add_form ($cid) {
    
    // Ensure user is allowed to edit discounts
    if (!user_access('discount_edit')) {
        return NULL;
    }
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'discount_add',
        'hidden' => array(
            'cid' => $cid
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Add Discount Record',
                'fields' => array(
                    array(
                        'type' => 'text',
                        'label' => 'Amount',
                        'name' => 'amount'
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Reason',
                        'name' => 'reason',
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Invoice ID',
                        'name' => 'invoiceID',
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Applied On',
                        'name' => 'appliedOn',
                        'value' => "0000-00-00",
                        'class' => 'date'
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Applied By',
                        'name' => 'appliedBy',
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
 * Return the form structure for an edit discount record form.
 *
 * @param $did The did of the discount record to edit.
 * @return The form structure.
*/
function discount_edit_form ($did) {
    // Ensure user is allowed to edit discounts
    if (!user_access('discount_edit')) {
        return NULL;
    }
    // Get discount data
    $data = crm_get_data('discount', array('did'=>$did));
    $discount = $data[0];
    if (empty($discount) || count($discount) < 1) {
        return array();
    }
    // Get corresponding contact data
    $contact = crm_get_one('contact', array('cid'=>$discount['cid']));
    // Construct member name
    $name = theme('contact_name', $contact, true);
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'discount_update',
        'hidden' => array(
            'did' => $did
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Edit Discount Info',
                'fields' => array(
                    array(
                        'type' => 'readonly',
                        'label' => 'Discount ID',
                        'value' => $discount['did']
                    ),
                    array(
                        'type' => 'readonly',
                        'label' => 'Name',
                        'value' => $name
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Amount',
                        'name' => 'amount',
                        'value' => $discount['amount']
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Reason',
                        'name' => 'reason',
                        'value' => $discount['reason']
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Invoice ID',
                        'name' => 'invoiceID',
                        'value' => $discount['invoiceID']
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Applied On',
                        'name' => 'appliedOn',
                        'value' => $discount['appliedOn'],
                        'class' => 'date'
                    ),
                    array(
                        'type' => 'text',
                        'label' => 'Applied By',
                        'name' => 'appliedBy',
                        'value' => $discount['appliedBy']
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
 * Return the delete discount record form structure.
 *
 * @param $did The did of the discount record to delete.
 * @return The form structure.
*/
function discount_delete_form ($did) {
    
    // Ensure user is allowed to delete discounts
    if (!user_access('discount_delete')) {
        return NULL;
    }
    
    // Get discount data
    $data = crm_get_data('discount', array('did'=>$did));
    $discount = $data[0];
    
    // Construct discount name
    $discount_name = "Discount ID: $discount[did]\nAmount: $discount[amount]\nReason: $discount[reason]\nInvoice ID: $discount[invoiceID]\nApplied On: $discount[appliedOn]\nApplied By: $discount[appliedBy]";
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'discount_delete',
        'hidden' => array(
            'did' => $discount['did']
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Delete Discount',
                'fields' => array(
                    array(
                        'type' => 'message',
                        'value' => '<p>Are you sure you want to delete the discount record:\n' . $discount_name . '\nThis cannot be undone.',
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
function discount_command ($command, &$url, &$params) {
    switch ($command) {
        case 'member_add':
            $params['tab'] = 'discounts';
            break;
    }
}

/**
 * Handle discount add request.
 *
 * @return The url to display on completion.
 */
function command_discount_add() {
    // Verify permissions
    if (!user_access('discount_edit')) {
        error_register('Permission denied: discount_edit');
        return crm_url('discount&did=' . $_POST['did']);
    }
    discount_save($_POST);
    return crm_url('contact&cid=' . $_POST['cid'] . '&tab=discounts');
}

/**
 * Handle discount update request.
 *
 * @return The url to display on completion.
 */
function command_discount_update() {
    // Verify permissions
    if (!user_access('discount_edit')) {
        error_register('Permission denied: discount_edit');
        return crm_url('discount&did=' . $_POST['did']);
    }
    // Save discount
    discount_save($_POST);
    return crm_url('discount&did=' . $_POST['did'] . '&tab=edit');
}

/**
 * Handle discount delete request.
 *
 * @return The url to display on completion.
 */
function command_discount_delete() {
    global $esc_post;
    // Verify permissions
    if (!user_access('discount_delete')) {
        error_register('Permission denied: discount_delete');
        return crm_url('discount&did=' . $esc_post['did']);
    }
    discount_delete($_POST);
    return crm_url('members');
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function discount_page_list () {
    $pages = array();
    if (user_access('discount_view')) {
        $pages[] = 'discounts';
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
function discount_page (&$page_data, $page_name, $options) {
    
    switch ($page_name) {
        
        case 'contact':
            
            // Capture contact cid
            $cid = $options['cid'];
            if (empty($cid)) {
                return;
            }
            
            // Add discounts tab
            if (user_access('discount_view') || user_access('discount_edit') || user_access('discount_delete') || $cid == user_id()) {
                $discounts = theme('table', 'discount', array('cid' => $cid));
                $discounts .= theme('discount_add_form', $cid);
                page_add_content_bottom($page_data, $discounts, 'Discounts');
            }
            
            break;
        
        case 'discounts':
            page_set_title($page_data, 'Discounts');
            if (user_access('discount_view')) {
                $discounts = theme('table', 'discount', array('join'=>array('contact', 'member'), 'show_export'=>true));
                page_add_content_top($page_data, $discounts, 'View');
            }
            break;
        
        case 'discount':
            
            // Capture discount id
            $did = $options['did'];
            if (empty($did)) {
                return;
            }
            
            // Set page title
            page_set_title($page_data, discount_description($did));
            
            // Add edit tab
            if (user_access('discount_view') || user_access('discount_edit') || user_access('discount_delete')) {
                page_add_content_top($page_data, theme('discount_edit_form', $did), 'Edit');
            }
            
            break;
    }
}

// Themeing ////////////////////////////////////////////////////////////////////

/**
 * Return the themed html for an add discount record form.
 *
 * @param $cid The id of the contact to add a discount record for.
 * @return The themed html string.
 */
function theme_discount_add_form ($cid) {
    return theme('form', crm_get_form('discount_add', $cid));
}

/**
 * Return themed html for an edit discount assignment form.
 *
 * @param $did The did of the discount assignment to edit.
 * @return The themed html string.
 */
function theme_discount_edit_form ($did) {
    return theme('form', crm_get_form('discount_edit', $did));
}

?>