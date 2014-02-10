<?php 

/*
    Copyright 2009-2013 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    contact.inc.php - Defines contact entity

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
function contact_revision () {
    return 1;
}

/**
 * @return Array of paths to stylesheets relative to this module's directory.
 */
function contact_stylesheets () {
}

/**
 * @return An array of the permissions provided by this module.
 */
function contact_permissions () {
    return array(
        'contact_view'
        , 'contact_add'
        , 'contact_edit'
        , 'contact_delete'
    );
}

// Installation functions //////////////////////////////////////////////////////

/**
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
 */
function contact_install ($old_revision = 0) {
    if ($old_revision < 1) {
        $sql = '
            CREATE TABLE IF NOT EXISTS `contact` (
              `cid` mediumint(8) unsigned NOT NULL AUTO_INCREMENT,
              `memberNumber` varchar(8) NULL,
              `parentNumber` varchar(8) NULL,
              `firstName` varchar(255) NOT NULL,
              `lastName` varchar(255) NOT NULL,
              `joined` date DEFAULT NULL,
              `company` varchar(255) NOT NULL,
              `school` varchar(255) NOT NULL,
              `studentID` varchar(255) NOT NULL,
              `address1` varchar(255) NOT NULL,
              `address2` varchar(255) NOT NULL,
              `city` varchar(255) NOT NULL,
              `state` varchar(255) NOT NULL,
              `zip` char(5) NOT NULL,
              `email` varchar(255) NOT NULL,
              `phone` varchar(32) NOT NULL,
              `over18` tinyint(1) NOT NULL,
              `emergencyName` varchar(255) NOT NULL,
              `emergencyRelation` varchar(255) NOT NULL,
              `emergencyPhone` varchar(16) NOT NULL,
              `emergencyEmail` varchar(255) NOT NULL,
              `notes` varchar(255) NOT NULL,' .
              /* TODO Add logging functionality
              `created` datetime DEFAULT CURRENT_TIMESTAMP,
              `createdBy` varchar(255) NOT NULL,
              `modified` datetime ON UPDATE CURRENT_TIMESTAMP,
              `modifiedBy` varchar(255) NOT NULL,*/
              'PRIMARY KEY (`cid`)
            ) ENGINE=MyISAM DEFAULT CHARSET=utf8 AUTO_INCREMENT=1 ;
        ';
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
    }
}

// DB to Object mapping ////////////////////////////////////////////////////////

/**
 * Return data for one or more contacts.
 * 
 * @param $opts An associative array of options, possible keys are:
 *   'cid' A cid or array of cids to return contacts for.
 *   'filter' An array mapping filter names to filter values
 * @return An array with each element representing a contact.
*/ 
function contact_data ($opts = array()) {
    // Query database
    $sql = "
        SELECT * FROM `contact`
        WHERE 1 ";
    // Add contact id
    if (isset($opts['cid'])) {
        if (is_array($opts['cid'])) {
            if (!empty($opts['cid'])) {
                $terms = array();
                foreach ($opts['cid'] as $cid) {
                    $terms[] = "'" . mysql_real_escape_string($cid) . "'";
                }
                $esc_list = '(' . implode(',', $terms) . ')';
                $sql .= " AND `cid` IN $esc_list";
            }
        } else {
            $esc_cid = mysql_real_escape_string($opts['cid']);
            $sql .= " AND `cid`='$esc_cid'";
        }
    }
    // Add filters
    if (isset($opts['filter'])) {
        foreach ($opts['filter'] as $name => $param) {
            switch ($name) {
                case 'nameLike':                    
                    // Split on first comma and create an array of name parts in "first last" order
                    $parts = explode(',', $param, 2);
                    $names = array();
                    foreach (array_reverse($parts) as $part) {
                        $nameParts = preg_split('/\s+/', $part);
                        foreach ($nameParts as $name) {
                            if (!empty($name)) {
                               $names[] = mysql_real_escape_string($name);
                            }
                        }
                    }
                    // Set where clauses based on number of name segments given
                    if (sizeof($names) === 1) {
                        $sql .= "AND (`firstName` LIKE '%$names[0]%' OR `lastName` LIKE '%$names[0]%')";
                    } else if (sizeof($names) === 2) {
                        $sql .= "AND (`firstName` LIKE '%$names[0]%' AND `lastName` LIKE '%$names[1]%')";
                    } 
                    break;
                default:
                break;
            }
        }
    }
    $sql .= "
        ORDER BY `lastName`, `firstName` ASC";
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    // Store data
    $contacts = array();
    $row = mysql_fetch_assoc($res);
    while (!empty($row)) {
        $contacts[] = array(
            'cid' => $row['cid'],
            'memberNumber' => $row['memberNumber'],
            'parentNumber' => $row['parentNumber'],
            'firstName' => $row['firstName'],
            'lastName' => $row['lastName'],
            'joined' => $row['joined'],
            'company' => $row['company'],
            'school' => $row['school'],
            'studentID' => $row['studentID'],
            'address1' => $row['address1'],
            'address2' => $row['address2'],
            'city' => $row['city'],
            'state' => $row['state'],
            'zip' => $row['zip'],
            'email' => $row['email'],
            'phone' => $row['phone'],
            'over18' => $row['over18'],
            'emergencyName' => $row['emergencyName'],
            'emergencyRelation' => $row['emergencyRelation'],
            'emergencyPhone' => $row['emergencyPhone'],
            'emergencyEmail' => $row['emergencyEmail'],
            'notes' => $row['notes']
        );
        $row = mysql_fetch_assoc($res);
    }
    // Return data
    return $contacts;
}

/**
 * Saves a contact.
 */
function contact_save ($contact) {
    $fields = array('cid'
                    , 'memberNumber'
                    , 'parentNumber'
                    , 'firstName'
                    , 'lastName'
                    , 'joined'
                    , 'company'
                    , 'school'
                    , 'studentID'
                    , 'address1'
                    , 'address2'
                    , 'city'
                    , 'state'
                    , 'zip'
                    , 'email'
                    , 'phone'
                    , 'over18'
                    , 'emergencyName'
                    , 'emergencyRelation'
                    , 'emergencyPhone'
                    , 'emergencyEmail'
                    , 'notes');
    $escaped = array();
    foreach ($fields as $field) {
        $escaped[$field] = mysql_real_escape_string($contact[$field]);
    }
    if (isset($contact['cid'])) {
        // Update contact
        $sql = "
            UPDATE `contact`
            SET `memberNumber`='$escaped[memberNumber]'
                , `parentNumber`='$escaped[parentNumber]'
                , `firstName`='$escaped[firstName]'
                , `lastName`='$escaped[lastName]'
                , `joined`='$escaped[joined]'
                , `company`='$escaped[company]'
                , `school`='$escaped[school]'
                , `studentID`='$escaped[studentID]'
                , `address1`='$escaped[address1]'
                , `address2`='$escaped[address2]'
                , `city`='$escaped[city]'
                , `state`='$escaped[state]'
                , `zip`='$escaped[zip]'
                , `email`='$escaped[email]'
                , `phone`='$escaped[phone]'
                , `over18`='$escaped[over18]'
                , `emergencyName`='$escaped[emergencyName]'
                , `emergencyRelation`='$escaped[emergencyRelation]'
                , `emergencyPhone`='$escaped[emergencyPhone]'
                , `emergencyEmail`='$escaped[emergencyEmail]'
                , `notes`='$escaped[notes]'
            WHERE `cid`='$escaped[cid]'
        ";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        if (mysql_affected_rows() < 1) {
            return null;
        }
        $contact = module_invoke_api('contact', $contact, 'update');
    } else {
        // Add contact
        $sql = "
            INSERT INTO `contact`
                (`memberNumber`
                ,`parentNumber`
                ,`firstName`
                ,`lastName`
                ,`joined`
                ,`company`
                ,`school`
                ,`studentID`
                ,`address1`
                ,`address2`
                ,`city`
                ,`state`
                ,`zip`
                ,`email`
                ,`phone`
                ,`over18`
                ,`emergencyName`
                ,`emergencyRelation`
                ,`emergencyPhone`
                ,`emergencyEmail`
                ,`notes`)
            VALUES
                ('$escaped[memberNumber]'
                ,'$escaped[parentNumber]'
                ,'$escaped[firstName]'
                ,'$escaped[lastName]'
                ,'$escaped[joined]'
                ,'$escaped[company]'
                ,'$escaped[school]'
                ,'$escaped[studentID]'
                ,'$escaped[address1]'
                ,'$escaped[address2]'
                ,'$escaped[city]'
                ,'$escaped[state]'
                ,'$escaped[zip]'
                ,'$escaped[email]'
                ,'$escaped[phone]'
                ,'$escaped[over18]'
                ,'$escaped[emergencyName]'
                ,'$escaped[emergencyRelation]'
                ,'$escaped[emergencyPhone]'
                ,'$escaped[emergencyEmail]'
                ,'$escaped[notes]')";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $contact['cid'] = mysql_insert_id();
        $contact = module_invoke_api('contact', $contact, 'create');
    }
    return $contact;
}

/**
 * Delete a contact.
 * @param $cid The contact id.
 */
function contact_delete ($cid) {
    $contact = crm_get_one('contact', array('cid'=>$cid));
    if (empty($contact)) {
        error_register("No contact with cid $cid");
        return;
    }
    // Notify other modules the contact is being deleted
    $contact = module_invoke_api('contact', $contact, 'delete');
    // Remove the contact from the database
    $esc_cid = mysql_real_escape_string($cid);
    $sql = "DELETE FROM `contact` WHERE `cid`='$esc_cid'";
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    message_register('Deleted contact: ' . theme('contact_name', $contact));
}

// Autocomplete functions //////////////////////////////////////////////////////

/**
 * Return a list of contacts matching a text fragment.
 * @param $fragment
 */
function contact_name_autocomplete ($fragment) {
    $data = array();
    if (user_access('contact_view')) {
        $contacts = crm_get_data('contact', array('filter'=>array('nameLike'=>$fragment)));
        foreach ($contacts as $contact) {
            $row = array();
            $row['value'] = $contact['cid'];
            $row['label'] = theme('contact_name', $contact);
            $data[] = $row;
        }
    }
    return $data;
}

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return a table structure representing contacts.
 *
 * @param $opts Options to pass to contact_data().
 * @return The table structure.
*/
function contact_table ($opts = array()) {
    // Ensure user is allowed to view contacts
    if (!user_access('contact_view')) {
        return NULL;
    }
    // Create table structure
    $table = array();
    // Determine settings
    $export = false;
    $show_ops = isset($opts['ops']) ? $opts['ops'] : true;
    foreach ($opts as $option => $value) {
        switch ($option) {
            case 'export':
                $export = $value;
                break;
        }
    }
    // Get contact data
    $contact_data = crm_get_data('contact', $opts);
    $table['data'] = $contact_data;
    // Add columns
    $table['columns'] = array();
    if ($export) {
        $table['columns'][] = array('title'=>'Contact ID','class'=>'');
        $table['columns'][] = array('title'=>'Member #','class'=>'');
        $table['columns'][] = array('title'=>'Last','class'=>'');
        $table['columns'][] = array('title'=>'First','class'=>'');
    } else {
        if (!array_key_exists('exclude', $opts) || !in_array('memberNumber', $opts['exclude'])) {
            $table['columns'][] = array('title'=>'Mem #','class'=>'');
        }
        if (!array_key_exists('exclude', $opts) || !in_array('parentNumber', $opts['exclude'])) {
            $table['columns'][] = array('title'=>'Parent #','class'=>'');
        }
        $table['columns'][] = array('title'=>'Name','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('joined', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Joined','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('company', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Company','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('school', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'School','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('studentID', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Student ID #','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('address1', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Address Line 1','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('address2', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Address Line 2','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('city', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'City','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('state', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'State','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('zip', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'ZIP','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('phone', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Phone','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('email', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Email','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('over18', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Over 18?','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('emergencyName', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Emergency Contact','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('emergencyRelation', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'E.C. Relation','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('emergencyPhone', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'E.C. Phone','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('emergencyEmail', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'E.C. Email','class'=>'');
    }
    if (!array_key_exists('exclude', $opts) || !in_array('notes', $opts['exclude'])) {
        $table['columns'][] = array('title'=>'Notes','class'=>'');
    }
    // Add ops column
    if ($show_ops && !$export && (user_access('contact_edit') || user_access('contact_delete'))) {
        $table['columns'][] = array('title'=>'Ops','class'=>'');
    }
    // Loop through contact data and add rows to the table
    $table['rows'] = array();
    foreach ($contact_data as $contact) {
        $row = array();
        // Construct name
        $name_link = theme('contact_name', $contact, true);
        
        // Add cells
        if ($export) {
            $row[] = $contact['cid'];
            $row[] = $contact['memberNumber'];
            $row[] = $contact['lastName'];
            $row[] = $contact['firstName'];
        } else {
            if (!array_key_exists('exclude', $opts) || !in_array('memberNumber', $opts['exclude'])) {
                $row[] = $contact['memberNumber'];
            }
            if (!array_key_exists('exclude', $opts) || !in_array('parentNumber', $opts['exclude'])) {
                $row[] = $contact['parentNumber'];
            }
            $row[] = $name_link;
        }
        if (!array_key_exists('exclude', $opts) || !in_array('joined', $opts['exclude'])) {
            $row[] = $contact['joined'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('company', $opts['exclude'])) {
            $row[] = $contact['company'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('school', $opts['exclude'])) {
            $row[] = $contact['school'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('studentID', $opts['exclude'])) {
            $row[] = $contact['studentID'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('address1', $opts['exclude'])) {
            $row[] = $contact['address1'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('address2', $opts['exclude'])) {
            $row[] = $contact['address2'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('city', $opts['exclude'])) {
            $row[] = $contact['city'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('state', $opts['exclude'])) {
            $row[] = $contact['state'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('zip', $opts['exclude'])) {
            $row[] = $contact['zip'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('phone', $opts['exclude'])) {
            $row[] = $contact['phone'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('email', $opts['exclude'])) {
            $row[] = $contact['email'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('over18', $opts['exclude'])) {
            $row[] = $contact['over18'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('emergencyName', $opts['exclude'])) {
            $row[] = $contact['emergencyName'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('emergencyRelation', $opts['exclude'])) {
            $row[] = $contact['emergencyRelation'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('emergencyPhone', $opts['exclude'])) {
            $row[] = $contact['emergencyPhone'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('emergencyEmail', $opts['exclude'])) {
            $row[] = $contact['emergencyEmail'];
        }
        if (!array_key_exists('exclude', $opts) || !in_array('notes', $opts['exclude'])) {
            $row[] = $contact['notes'];
        }
        
        // Construct ops array
        $ops = array();
        
        // Add edit op
        if (user_access('contact_edit')) {
            $ops[] = '<a href=' . crm_url('contact&cid=' . $contact['cid'] . '&tab=edit') . '>edit</a> ';
        }
        
        // Add delete op
        if (user_access('contact_delete')) {
            $ops[] = '<a href=' . crm_url('delete&type=contact&amp;id=' . $contact['cid']) . '>delete</a>';
        }
        
        // Add ops row
        if ($show_ops && !$export && (user_access('contact_edit') || user_access('contact_delete'))) {
            $row[] = join(' ', $ops);
        }
        
        // Add row to table
        $table['rows'][] = $row;
    }
    
    // Return table
    return $table;
}

// Forms ///////////////////////////////////////////////////////////////////////

/**
 * Return the form structure for adding or editing a contact.  If $opts['cid']
 * is specified, an edit form will be returned, otherwise an add form will be
 * returned.
 * 
 * @param $opts An associative array of options, possible keys are:
 * @return The form structure.
*/
function contact_form ($opts = array()) {
    // Create form
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'contact_add'
        , 'fields' => array()
        , 'submit' => 'Add'
    );
    // Get contact data
    if (array_key_exists('cid', $opts)) {
        $cid = $opts['cid'];
        $data = crm_get_data('contact', array('cid'=>$cid));
        $contact = $data[0];
    }
    // Change to an edit form
    if (isset($contact)) {
        $form['command'] = 'contact_update';
        $form['submit'] = 'Update';
        $form['hidden'] = array('cid' => $cid);
        $form['values'] = array();
        foreach ($contact as $key => $value) {
            if (is_string($value)) {
                $form['values'][$key] = $value;
            }
        }
        $label = 'Edit Contact Info';
    } else {
        $label = 'Add Contact';
    }

    // Add fields
    $form['fields'][] = array(
        'type' => 'fieldset',
        'label' => $label,
        'fields' => array(
            array(
                'type' => 'readonly'
                , 'label' => 'Member Number'
                , 'name' => 'memberNumber'
                , 'value' => get_member_number()
            )
            , array(
                'type' => 'text'
                , 'label' => 'Parent Member Number'
                , 'name' => 'parentNumber'
            )
            , array(
                'type' => 'text'
                , 'label' => 'First Name'
                , 'name' => 'firstName'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Last Name'
                , 'name' => 'lastName'
            )
            , array(
                'type' => 'text'
                , 'class' => 'date'
                , 'label' => 'Joined'
                , 'name' => 'joined'
                , 'value' => date("Y-m-d")
            )
            , array(
                'type' => 'text'
                , 'label' => 'Company'
                , 'name' => 'company'
            )
            , array(
                'type' => 'text'
                , 'label' => 'School'
                , 'name' => 'school'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Student ID #'
                , 'name' => 'studentID'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Address Line 1'
                , 'name' => 'address1'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Address Line 2'
                , 'name' => 'address2'
            )
            , array(
                'type' => 'text'
                , 'label' => 'City'
                , 'name' => 'city'
            )
            , array(
                'type' => 'text'
                , 'label' => 'State'
                , 'name' => 'state'
            )
            , array(
                'type' => 'text'
                , 'label' => 'ZIP'
                , 'name' => 'zip'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Email'
                , 'name' => 'email'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Phone'
                , 'name' => 'phone'
            )
            , array(
                'type' => 'checkbox'
                , 'label' => 'Over 18?'
                , 'name' => 'over18'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Emergency Contact'
                , 'name' => 'emergencyName'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Emergency Contact Relation'
                , 'name' => 'emergencyRelation'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Emergency Contact Phone'
                , 'name' => 'emergencyPhone'
            )
            , array(
                'type' => 'text'
                , 'label' => 'Emergency Contact Email'
                , 'name' => 'emergencyEmail'
            )
            , array(
                'type' => 'textarea'
                , 'label' => 'Notes'
                , 'name' => 'notes'
            )
        )
    );
    return $form;
}

/**
 * Return the form structure to delete a contact.
 *
 * @param $cid The cid of the contact to delete.
 * @return The form structure.
*/
function contact_delete_form ($cid) {
    // Ensure user is allowed to delete contacts
    if (!user_access('contact_delete')) {
        return array();
    }
    // Get contact data
    $data = contact_data(array('cid'=>$cid));
    $contact = $data[0];
    if (empty($contact) || count($contact) < 1) {
        error_register('No contact for cid ' . $cid);
        return array();
    }
    // Create form structure
    $name = theme('contact_name', $contact);
    $message = "<p>Are you sure you want to delete the contact \"$name\"? This cannot be undone.";
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'contact_delete'
        , 'submit' => 'Delete'
        , 'hidden' => array(
            'cid' => $contact['cid']
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Delete Contact'
                , 'fields' => array(
                    array(
                        'type' => 'message'
                        , 'value' => $message
                    )
                )
            )
        )
    );
    return $form;
}

// Request Handlers ////////////////////////////////////////////////////////////

/**
 * Handle contact add request.
 *
 * @return The url to display when complete.
 */
function command_contact_add () {
    // Check permissions
    if (!user_access('contact_add')) {
        error_register('Permission denied: contact_add');
        //return crm_url('contacts');
        return crm_url('members');
    }
    // Build contact object
    $contact = array(
        'memberNumber' => $_POST['memberNumber']
        , 'parentNumber' => $_POST['parentNumber']
        , 'firstName' => $_POST['firstName']
        , 'lastName' => $_POST['lastName']
        , 'joined' => $_POST['joined']
        , 'company' => $_POST['company']
        , 'school' => $_POST['school']
        , 'studentID' => $_POST['studentID']
        , 'address1' => $_POST['address1']
        , 'address2' => $_POST['address2']
        , 'city' => $_POST['city']
        , 'state' => $_POST['state']
        , 'zip' => $_POST['zip']
        , 'email' => $_POST['email']
        , 'phone' => $_POST['phone']
        , 'over18' => $_POST['over18']
        , 'emergencyName' => $_POST['emergencyName']
        , 'emergencyRelation' => $_POST['emergencyRelation']
        , 'emergencyPhone' => $_POST['emergencyPhone']
        , 'emergencyEmail' => $_POST['emergencyEmail']
        , 'notes' => $_POST['notes']
    );
    // Save to database
    $contact = contact_save($contact);
    $cid = $contact['cid'];
    return crm_url("contact&cid=$cid");
}

/**
 * Handle contact update request.
 *
 * @return The url to display on completion.
 */
function command_contact_update () {
    global $esc_post;
    // Verify permissions
    if (!user_access('contact_edit') && $_POST['cid'] != user_id()) {
        error_register('Permission denied: contact_edit');
        //return crm_url('contacts');
        return crm_url('members');
    }
    $contact_data = crm_get_data('contact', array('cid'=>$_POST['cid']));
    $contact = $contact_data[0];
    if (empty($contact)) {
        error_register("No contact for cid: $_POST[cid]");
        //return crm_url('contacts');
        return crm_url('members');
    }
    // Update contact data
    $contact['memberNumber'] = $_POST['memberNumber'];
    $contact['parentNumber'] = $_POST['parentNumber'];
    $contact['firstName'] = $_POST['firstName'];
    $contact['lastName'] = $_POST['lastName'];
    $contact['joined'] = $_POST['joined'];
    $contact['company'] = $_POST['company'];
    $contact['school'] = $_POST['school'];
    $contact['studentID'] = $_POST['studentID'];
    $contact['address1'] = $_POST['address1'];
    $contact['address2'] = $_POST['address2'];
    $contact['city'] = $_POST['city'];
    $contact['state'] = $_POST['state'];
    $contact['zip'] = $_POST['zip'];
    $contact['email'] = $_POST['email'];
    $contact['phone'] = $_POST['phone'];
    $contact['over18'] = $_POST['over18'];
    $contact['emergencyName'] = $_POST['emergencyName'];
    $contact['emergencyRelation'] = $_POST['emergencyRelation'];
    $contact['emergencyPhone'] = $_POST['emergencyPhone'];
    $contact['emergencyEmail'] = $_POST['emergencyEmail'];
    $contact['notes'] = $_POST['notes'];
    // Save changes to database
    $contact = contact_save($contact);
    return crm_url('members');
}

/**
 * Handle contact delete request.
 *
 * @return The url to display on completion.
 */
function command_contact_delete () {
    // Verify permissions
    if (!user_access('contact_delete')) {
        error_register('Permission denied: contact_delete');
        //return crm_url('contacts');
        return crm_url('members');
    }
    contact_delete($_POST['cid']);
    //return crm_url('contacts');
    return crm_url('members');
}

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function contact_page_list () {
    $pages = array();
    if (user_access('contact_view')) {
        $pages[] = 'contacts';
        $pages[] = 'contact';
    }
    return $pages;
}

/**
 * Page hook.  Adds contact module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
*/
function contact_page (&$page_data, $page_name) {
    switch ($page_name) {
        case 'contacts':
            // Set page title
            page_set_title($page_data, 'Contacts');
            // Add view tab
            if (user_access('contact_view')) {
                $opts = array(
                    'show_export'=>true
                    //Fields displayed in the contact "view contacts" table can be hidden or revealed by commenting out elements and their corresponding
                    //commas in this array (yes, this is ghetto as hell and I know it)
                    , 'exclude'=>array(
                        'memberNumber'
                        ,
                        'parentNumber'
                        ,  
                        'joined'
                        ,
                        //'company'
                        //,
                        //'school' 
                        //,
                        'studentID'
                        ,
                        'address1' 
                        ,
                        'address2' 
                        ,
                        'city'
                        ,
                        'state' 
                        ,
                        'zip'
                        ,
                        //'phone'
                        //,
                        //'email'
                        //,
                        'over18'
                        ,
                        'emergencyName'
                        ,
                        'emergencyRelation'
                        ,
                        'emergencyPhone'
                        ,
                        'emergencyEmail'
                        ,
                        'notes'
                        )
                );
                $view = theme('table', 'contact', $opts);
                page_add_content_top($page_data, $view, 'View');
            }
            // Add add tab
            if (user_access('contact_add')) {
                page_add_content_top($page_data, theme('form', crm_get_form('contact')), 'Add');
            }
            break;
        case 'contact':
            // Capture contact id
            $cid = $_GET['cid'];
            if (empty($cid)) {
                return;
            }
            $contact_data = crm_get_data('contact', array('cid'=>$cid));
            $contact = $contact_data[0];
            // Set page title
            page_set_title($page_data, theme('contact_name', $contact));
            // Add view tab
            $view_content = '';
            if (user_access('contact_view')) {
                $view_content .= '<h3>Contact Info</h3>';
                $opts = array(
                    'cid' => $cid
                    , 'ops' => false
                );
                $view_content .= theme('table_vertical', 'contact', array('cid' => $cid));
            }
            if (!empty($view_content)) {
                page_add_content_top($page_data, $view_content, 'View');
            }
            // Add edit tab
            if (user_access('contact_edit') || $cid == user_id()) {
                $opts = array('cid' => $cid);
                $form = crm_get_form('contact', $opts);
                page_add_content_top($page_data, theme('form', $form), 'Edit');
            }
            break;
    }
}

// Reports /////////////////////////////////////////////////////////////////////
//require_once('report.inc.php');

// Themeing ////////////////////////////////////////////////////////////////////

/**
 * Theme a contact's name.
 * 
 * @param $contact The contact data structure or cid.
 * @param $link True if the name should be a link (default: false).
 * @param $path The path that should be linked to.  The cid will always be added
 *   as a parameter.
 *
 * @return the name string.
 */
function theme_contact_name ($contact, $link = false, $path = 'contact') {
    if (!is_array($contact)) {
        $contact = crm_get_one('contact', array('cid'=>$contact));
    }
    $first = $contact['firstName'];
    $last = $contact['lastName'];
    $name = "$last, $first";
    if ($link) {
        $url_opts = array('query' => array('cid' => $contact['cid']));
        $name = crm_link($name, $path, $url_opts);
    }
    return $name;
}

?>