<?php

/*
    Copyright 2009-2013 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    tool.inc.php - Tool module
    
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
 * Install or upgrade this module.
 * @param $old_revision The last installed revision of this module, or 0 if the
 *   module has never been installed.
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
    );
}

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
        if (!$res) die(mysql_error());

        // Create default permissions
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
            'member' => array('tool_view')
            , 'director' => array('tool_view', 'tool_edit')
            , 'webAdmin' => array('tool_view', 'tool_edit')
        );

        foreach ($roles as $rid => $role) {
            if (array_key_exists($role, $default_perms)) {
                foreach ($default_perms[$role] as $perm) {
                    $sql = "INSERT INTO `role_permission` (`rid`, `permission`) VALUES ('$rid', '$perm')";
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
 *   'tlid' If specified, returns a single tool with the matching id,
 *   'filter' An array mapping filter names to filter values
 * @return An array with each element representing a tool.
 */
function tool_data ($opts = array()) {
    
    // Construct query for tools
    $sql = "SELECT * FROM `tool` WHERE 1";
    
    /*
    if (isset($opts['filter'])) {
        foreach ($opts['filter'] as $name => $param) {
            switch ($name) {
                case 'flag':
                    if ($param) {
                        $sql .= " AND `tool`.`flag` <> 0";
                    } else {
                        $sql .= " AND `tool`.`flag` = 0";
                    }
                    break;
            }
        }
    }
    */

    if (!empty($opts['tlid'])) {
        $tlid = mysql_real_escape_string($opts[tlid]);
        $sql .= " AND `tool`.`tlid`='$tlid' ";
    }

    // Query database for tools
    $res = mysql_query($sql);
    if (!$res) { crm_error(mysql_error()); }
    
    // Store tools
    $tools = array();
    $row = mysql_fetch_assoc($res);
    while ($row) {
        $tools[] = $row;
        $row = mysql_fetch_assoc($res);
    }
    
    return $tools;
}

/**
 * Generates an associative array mapping tool tlids to
 * strings describing those tools.
 * 
 * @param $opts Options to be passed to tool_data().
 * @return The associative array of tool descriptions.
 */
function tool_options ($opts = NULL) {
    
    // Get tool data
    $tools = tool_data($opts);
    
    // Add option for each tool
    $options = array();
    foreach ($tools as $tool) {
        $options[$tool['tlid']] = "Tool ID: $tool[tlid] - $tool[name]";
    }
    
    return $options;
}

// Command handlers ////////////////////////////////////////////////////////////

/**
 * Handle tool add request.
 *
 * @return The url to display on completion.
 */
function command_tool_add () {
    $esc_name = mysql_real_escape_string($_POST['name']);
    $esc_mfgr = mysql_real_escape_string($_POST['mfgr']);
    $esc_modelNum = mysql_real_escape_string($_POST['modelNum']);
    $esc_serialNum = mysql_real_escape_string($_POST['serialNum']);
    $esc_class = mysql_real_escape_string($_POST['class']);
    $esc_acquiredDate = mysql_real_escape_string($_POST['acquiredDate']);
    $esc_releasedDate = mysql_real_escape_string($_POST['releasedDate']);
    $esc_purchasePrice = mysql_real_escape_string($_POST['purchasePrice']);
    $esc_deprecSched = mysql_real_escape_string($_POST['deprecSched']);
    $esc_recoveredCost = mysql_real_escape_string($_POST['recoveredCost']);
    $esc_owner = mysql_real_escape_string($_POST['owner']);
    $esc_notes = mysql_real_escape_string($_POST['notes']);
    
    // Verify permissions
    if (!user_access('tool_edit')) {
        error_register('Permission denied: tool_add');
        return crm_url('tools');
    }
    
    // Add tool
    $sql = "
        INSERT INTO `tool`
        (`name`
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
        , `notes`)
    VALUES
        ('$esc_name'
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
        , '$esc_notes')
    ";
    
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    message_register('1 tool added.');
    return crm_url('tools');
}

/**
 * Handle tool update request.
 *
 * @return The url to display on completion.
 */
function command_tool_update () {
    $esc_tlid = mysql_real_escape_string($_POST['tlid']);
    $esc_name = mysql_real_escape_string($_POST['name']);
    $esc_mfgr = mysql_real_escape_string($_POST['mfgr']);
    $esc_modelNum = mysql_real_escape_string($_POST['modelNum']);
    $esc_serialNum = mysql_real_escape_string($_POST['serialNum']);
    $esc_class = mysql_real_escape_string($_POST['class']);
    $esc_acquiredDate = mysql_real_escape_string($_POST['acquiredDate']);
    $esc_releasedDate = mysql_real_escape_string($_POST['releasedDate']);
    $esc_purchasePrice = mysql_real_escape_string($_POST['purchasePrice']);
    $esc_deprecSched = mysql_real_escape_string($_POST['deprecSched']);
    $esc_recoveredCost = mysql_real_escape_string($_POST['recoveredCost']);
    $esc_owner = mysql_real_escape_string($_POST['owner']);
    $esc_notes = mysql_real_escape_string($_POST['notes']);
    
    // Verify permissions
    if (!user_access('tool_edit')) {
        error_register('Permission denied: tool_edit');
        return crm_url('tools');
    }
    
    // Update tool
    $sql = "
        UPDATE `tool`
        SET
            `name`='$esc_name',
            `mfgr`='$esc_mfgr',
            `modelNum`='$esc_modelNum',
            `serialNum`='$esc_serialNum',
            `class`='$esc_class',
            `acquiredDate`='$esc_acquiredDate',
            `releasedDate`='$esc_releasedDate',
            `purchasePrice`='$esc_purchasePrice',
            `deprecSched`='$esc_deprecSched',
            `recoveredCost`='$esc_recoveredCost',
            `owner`='$esc_owner',
            `notes`='$esc_notes'
        WHERE `tlid`='$esc_tlid'
        ";
    
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    message_register('1 tool updated.');
    return crm_url('tools');
}

/**
 * Handle delete tool request.
 *
 * @return The url to display on completion.
 */
function command_tool_delete () {
    global $esc_post;
    
    // Verify permissions
    if (!user_access('tool_edit')) {
        error_register('Permission denied: tool_edit');
        return crm_url('tools');
    }
    
    // Delete tool
    $sql = "DELETE FROM `tool` WHERE `tlid`='$esc_post[tlid]'";
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    
    return crm_url('tools');
}

/**
 * Handle tool import request.
 *
 * @return The url to display on completion.
 */
/*function command_tool_import () {
    
    if (!user_access('tool_edit')) {
        error_register('User does not have permission: tool_edit');
        return crm_url('tools');
    }
    
    if (!array_key_exists('tool-file', $_FILES)) {
        error_register('No tool file uploaded');
        return crm_url('tools&tab=import');
    }
    
    $csv = file_get_contents($_FILES['tool-file']['tmp_name']);
    
    $data = csv_parse($csv);
    
    foreach ($data as $row) {
        
        // Convert row keys to lowercase and remove spaces
        foreach ($row as $key => $value) {
            $new_key = str_replace(' ', '', strtolower($key));
            unset($row[$key]);
            $row[$new_key] = $value;
        }
        
        // Add tool
        $name = mysql_real_escape_string($row['toolname']);
        $price = mysql_real_escape_string($row['price']);
        $date = mysql_real_escape_string($row['date']);
        $flag = mysql_real_escape_string($row['flag']);
        $sql = "
            INSERT INTO `tool`
            (`name`,`price`,`date`,`flag`)
            VALUES
            ('$name','$price','$date','$flag')";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $tlid = mysql_insert_id();
    }
    
    return crm_url('tools');
}*/

// Table data structures ///////////////////////////////////////////////////////

/**
 * Return an abbreviated table structure representing tools.
 *
 * @param $opts Options to pass to tool_data().
 * @return The table structure.
 */
function tool_table ($opts = NULL) {
    
    // Ensure user is allowed to view tools
    if (!user_access('tool_edit')) {
        return NULL;
    }
    
    // Get tool data
    $tools = tool_data($opts);
    
    // Create table structure
    $table = array(
        'id' => '',
        'class' => '',
        'rows' => array()
    );
    
    // Add columns
    $table['columns'] = array();
    if (user_access('tool_view')) {
        $table['columns'][] = array("title"=>'Tool ID');
        $table['columns'][] = array("title"=>'Name');
        //$table['columns'][] = array("title"=>'Manufacturer');
        //$table['columns'][] = array("title"=>'Model Number');
        $table['columns'][] = array("title"=>'Serial Number');
        $table['columns'][] = array("title"=>'Class');
        $table['columns'][] = array("title"=>'Acquired');
        $table['columns'][] = array("title"=>'Released');
        //$table['columns'][] = array("title"=>'Purchased Price');
        //$table['columns'][] = array("title"=>'Deprec Schedule');
        //$table['columns'][] = array("title"=>'Recovered Cost');
        $table['columns'][] = array("title"=>'Owner');
        //$table['columns'][] = array("title"=>'Notes');
        $table['columns'][] = array("title"=>'Ops');
    }

    // Loop through tool data
    foreach ($tools as $tool) {
        
        // Add tool data to table
        $row = array();

        // Construct name
        $tool_link = theme('tool_name', $tool, true);

        if (user_access('tool_edit')) {
            
            // Add cells
            $row[] = $tool['tlid'];
            $row[] = $tool_link;
            //$row[] = $tool['mfgr'];
            //$row[] = $tool['modelNum'];
            $row[] = $tool['serialNum'];
            $row[] = $tool['class'];
            $row[] = $tool['acquiredDate'];
            $row[] = $tool['releasedDate'];
            //$row[] = $tool['purchasePrice'];
            //$row[] = $tool['deprecSched'];
            //$row[] = $tool['recoveredCost'];
            $row[] = $tool['owner'];
            //$row[] = $tool['notes'];
        }
        
        // Construct ops array
        $ops = array();
        
        // Add edit op
        if (user_access('tool_edit')) {
            $ops[] = '<a href=' . crm_url('tool&tlid=' . $tool['tlid'] . '&tab=edit') . '>edit</a>';
        }
        
        // Add delete op
        if (user_access('tool_edit')) {
            $ops[] = '<a href=' . crm_url('delete&type=tool&amp;id=' . $tool['tlid']) . '>delete</a>';
        }
        
        // Add ops row
        if (user_access('tool_edit')) {
            $row[] = join(' ', $ops);
        }
        
        // Add row to table
        $table['rows'][] = $row;
    }
    
    // Return table
    return $table;
}

/**
 * Return a detailed table structure representing tools.
 *
 * @param $opts Options to pass to tool_data().
 * @return The table structure.
 */
function tool_detail_table ($opts = NULL) {
    
    // Ensure user is allowed to view tools
    if (!user_access('tool_edit')) {
        return NULL;
    }
    
    // Get tool data
    $tools = tool_data($opts);
    
    // Create table structure
    $table = array(
        'id' => '',
        'class' => '',
        'rows' => array()
    );
    
    // Add columns
    $table['columns'] = array();
    if (user_access('tool_view')) {
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

    // Loop through tool data
    foreach ($tools as $tool) {
        
        // Add tool data to table
        $row = array();

        // Construct name
        $tool_link = theme('tool_name', $tool, true);

        if (user_access('tool_edit')) {
            
            // Add cells
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
        
        // Construct ops array
        $ops = array();
        
        // Add edit op
        if (user_access('tool_edit')) {
            $ops[] = '<a href=' . crm_url('tool&tlid=' . $tool['tlid'] . '&tab=edit') . '>edit</a>';
        }
        
        // Add delete op
        if (user_access('tool_edit')) {
            $ops[] = '<a href=' . crm_url('delete&type=tool&amp;id=' . $tool['tlid']) . '>delete</a>';
        }
        
        // Add ops row
        if (user_access('tool_edit')) {
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
                        , 'class' => 'date float'
                    )
                    , array(
                        'type' => 'text'
                        , 'label' => 'Realeased Date'
                        , 'name' => 'releasedDate'
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
                        'type' => 'text'
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
 * Returns the form structure for editing a tool.
 *
 * @param $tlid The tlid of the tool to edit.
 * @return The form structure.
 */
function tool_edit_form ($tlid) {
    
    // Ensure user is allowed to edit tools
    if (!user_access('tool_edit')) {
        return NULL;
    }
    
    // Get tool data
    $tools = tool_data(array('tlid'=>$tlid));
    $tool = $tools[0];
    if (!$tool) {
        return NULL;
    }
    
    // Create form structure
    $form = array(
        'type' => 'form'
        , 'method' => 'post'
        , 'command' => 'tool_update'
        , 'hidden' => array(
            'tlid' => $tool['tlid']
        )
        , 'fields' => array(
            array(
                'type' => 'fieldset'
                , 'label' => 'Edit Tool'
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
                        'type' => 'text'
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
    
    return $form;
}

/**
 * Return the form structure to delete a tool.
 *
 * @param $tlid The tlid of the tool to delete.
 * @return The form structure.
 */
function tool_delete_form ($tlid) {
    
    // Ensure user is allowed to edit tools
    if (!user_access('tool_edit')) {
        return NULL;
    }
    
    // Get tool description
    $description = theme('tool_description', $tlid);
    
    // Create form structure
    $form = array(
        'type' => 'form',
        'method' => 'post',
        'command' => 'tool_delete',
        'hidden' => array(
            'tlid' => $tlid,
        ),
        'fields' => array(
            array(
                'type' => 'fieldset',
                'label' => 'Delete Tool',
                'fields' => array(
                    array(
                        'type' => 'message',
                        'value' => '<p>Are you sure you want to delete Tool ID #' . $description. '? This cannot be undone.',
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

/**
 * @return the form structure for a tool import form.
 */
/*function tool_import_form () {
    return array(
        'type' => 'form'
       , 'method' => 'post'
        , 'enctype' => 'multipart/form-data'
        , 'command' => 'tool_import'
        , 'fields' => array(
            array(
                'type' => 'message'
                , 'value' => '<p>To import tools, upload a csv.  The csv should have a header row with the following fields:</p>
                <ul>
                <li>Tool Name</li>
                <li>Price</li>
                <li>Date in YYYY-MM-DD format</li>
                <li>Flag (Set to 1/0 signalling Y/N)</li>
                </ul>'
            )
            , array(
                'type' => 'file'
                , 'label' => 'CSV File'
                , 'name' => 'tool-file'
            )
            , array(
                'type' => 'submit'
                , 'value' => 'Import'
            )
        )
    );
}*/

// Pages ///////////////////////////////////////////////////////////////////////

/**
 * @return An array of pages provided by this module.
 */
function tool_page_list () {
    $pages = array();
    if (user_access('tool_view')) {
        $pages[] = 'tools';
        $pages[] = 'tool';
    }
    return $pages;
}

/**
 * Page hook.  Adds tool module content to a page before it is rendered.
 *
 * @param &$page_data Reference to data about the page being rendered.
 * @param $page_name The name of the page being rendered.
 * @param $options The array of options passed to theme('page').
 */
function tool_page (&$page_data, $page_name, $options) {
    
    switch ($page_name) {
        
        case 'tools':
            
            // Set page title
            page_set_title($page_data, 'Tool Inventory');
            
            // Add view, add and import tabs
            if (user_access('tool_view')) {
                page_add_content_top($page_data, theme('table', 'tool'), 'View');
            }
            if (user_access('tool_edit')) {
                page_add_content_top($page_data, theme('tool_add_form'), 'Add');
                //TODO
                //page_add_content_top($page_data, theme('form', tool_import_form()), 'Import');
            }
            
            break;
        
        case 'tool':
            
            // Capture tool id
            $tlid = $_GET['tlid'];
            if (empty($tlid)) {
                return;
            }
            
            // Set page title
            page_set_title($page_data, 'Tool ID #' . theme('tool_description', $tlid));

            // Add view tab
            $view_content = '';
            if (user_access('tool_view')) {
                $view_content .= '<h3>Tool Info</h3>';
                $opts = array(
                    'tlid' => $tlid
                    , 'ops' => false
                );
                $view_content .= theme('table_vertical', 'tool_detail', array('tlid' => $tlid));
            }
            if (!empty($view_content)) {
                page_add_content_top($page_data, $view_content, 'View');
            }
            
            // Add edit tab
            if (user_access('tool_edit')) {
                page_add_content_top($page_data, theme('tool_edit_form', $tlid), 'Edit');
            }
            
            break;

    }
}

// Themeing ////////////////////////////////////////////////////////////////////

/**
 * Return the themed html for a tool table.
 *
 * @param $opts The options to pass to tool_data().
 * @return The themed html string.
 */
function theme_tool_table ($opts = NULL) {
    return theme('table', tool_table($opts));
}

/**
 * Return the themed html for a tool add form.
 *
 * @return The themed html string.
 */
function theme_tool_add_form () {
    return theme('form', tool_add_form());
}

/**
 * Return the themed html for a tool edit form.
 *
 * @param $tlid The tlid of the tool to edit.
 * @return The themed html string.
 */
function theme_tool_edit_form ($tlid) {
    return theme('form', tool_edit_form($tlid));
}

/**
 * Return the themed html description for a tool.
 *
 * @param $tlid The tlid of the tool.
 * @return The themed html string.
 */
function theme_tool_description ($tlid) {
    
    // Get tool data
    $data = tool_data(array('tlid' => $tlid));
    if (count($data) < 1) {
        return '';
    }
    
    $output = $data[0]['tlid'] . ': ' . $data[0]['name'];
    
    return $output;
}

/**
 * Theme a tool name.
 * 
 * @param $tool The tool data structure or tlid.
 * @param $link True if the name should be a link (default: false).
 * @param $path The path that should be linked to.  The tlid will always be added
 *   as a parameter.
 *
 * @return the name string.
 */
function theme_tool_name ($tool, $link = false, $path = 'tool') {
    if (!is_array($tool)) {
        $tool = crm_get_one('tool', array('tlid'=>$tool));
    }
    $name = $tool['name'];
    if ($link) {
        $url_opts = array('query' => array('tlid' => $tool['tlid']));
        $name = crm_link($name, $path, $url_opts);
    }
    return $name;
}


