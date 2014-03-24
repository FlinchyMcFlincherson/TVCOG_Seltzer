 <?php 

/*
    Copyright 2009-2013 Edward L. Platt <ed@elplatt.com>
    
    This file is part of the Seltzer CRM Project
    command.inc.php - Member module - request handlers

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
 * Handle member add request.
 *
 * @return The url to display when complete.
 */
function command_member_add () {
    global $esc_post;
    global $config_email_to;
    global $config_email_from;
    global $config_org_name;
    
    // Verify permissions
    if (!user_access('member_add')) {
        error_register('Permission denied: member_add');
        return crm_url('members');
    }
    if (!user_access('contact_add')) {
        error_register('Permission denied: contact_add');
        return crm_url('members');
    }
    if (!user_access('user_add')) {
        error_register('Permission denied: user_add');
        return crm_url('members');
    }
    
    // Validate the presence of required fields
    $firstName = $_POST['firstName'];
    $lastName = $_POST['lastName'];
    $email = $_POST['email'];

    if (empty($firstName)
        or empty($lastName)
        or empty($email)) {
        error_register('<p>Error: Required fields are blank.</p>
                        <p>The following fields are required:</p>
                        <li>First Name</li>
                        <li>Last Name</li>
                        <li>Email</li>');
        return crm_url('members&tab=add');
    }

    // Automatically construct a username
    $username = strtolower($_POST[firstName]{0} . $_POST[lastName] . $_POST[memberNumber]);

    // If username exists, add incremental digit to the end
    $validName = 0;
    $n = 0;
    while ($validName === 0 && $n < 100) {
        
        // Contruct test username
        $test_username = $username;
        if ($n > 0) {
            $test_username .= $n;
        }
        
        // Check whether username is taken
        $esc_test_name = mysql_real_escape_string($test_username);
        $sql = "SELECT * FROM `user` WHERE `username`='$esc_test_name'";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $row = mysql_fetch_assoc($res);
        if (!$row) {
            $username = $test_username;
            $validName = 1;
        }
        $n++;
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

    // Add user fields
    $user = array('username' => $username);
    $contact['user'] = $user;
    
    // Add member fields
    $membership = array(
        array(
            'pid' => $_POST['pid']
            , 'start' => $_POST['start']
            , 'end' => $_POST['end']
        )
    );
    $member = array('membership' => $membership);
    $contact['member'] = $member;
    
    // Save to database
    $contact = contact_save($contact);
    
    // Add role entry
    $sql = "SELECT `rid` FROM `role` WHERE `name`='member'";
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    $row = mysql_fetch_assoc($res);
    $esc_cid = mysql_real_escape_string($contact['cid']);
    $esc_rid = mysql_real_escape_string($row['rid']);
    
    if ($row) {
        $sql = "
            INSERT INTO `user_role`
            (`cid`, `rid`)
            VALUES
            ('$esc_cid', '$esc_rid')";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
    }
    
    if (function_exists('paypal_payment_revision')) {
        $esc_create_paypal_contact = $_POST['create_paypal_contact'] ? '1' : '0';
        $esc_paypal_email = $_POST['email'];
        if ($esc_create_paypal_contact === '1') {
            if (!empty($esc_paypal_email)) {
                $sql = "
                    INSERT INTO `contact_paypal`
                    (`paypal_email`, `cid`)
                    VALUES
                    ('$esc_paypal_email', '$esc_cid')
                ";
                $res = mysql_query($sql);
                if (!$res) crm_error(mysql_error());
            }
        }
    }
    
    // Notify admins
    $from = "\"$config_org_name\" <$config_email_from>";
    $headers = "From: $from\r\nContent-Type: text/html; charset=ISO-8859-1\r\n";
    if (!empty($config_email_to)) {
        $name = theme_contact_name($contact['cid']);
        $content = theme('member_created_email', $contact['cid']);
        mail($config_email_to, "New Member: $name", $content, $headers);
    }
    
    // Notify user if indicated
    $esc_send_user_email = $_POST['send_user_email'] ? '1' : '0';
    if ($esc_send_user_email === '1') {
        $confirm_url = user_reset_password_url($contact['user']['username']);
        $content = theme('member_welcome_email', $contact['user']['cid'], $confirm_url);
        mail($_POST['email'], "Welcome to $config_org_name", $content, $headers);
    }

    return crm_url("contact&cid=$esc_cid");
}

/**
 * Handle membership plan add request.
 *
 * @return The url to display on completion.
 */
function command_member_plan_add () {
    $esc_name = mysql_real_escape_string($_POST['name']);
    $esc_price = mysql_real_escape_string($_POST['price']);
    $esc_voting = $_POST['voting'] ? '1' : '0';
    $esc_active = $_POST['active'] ? '1' : '0';
    
    // Verify permissions
    if (!user_access('member_plan_edit')) {
        error_register('Permission denied: member_plan_edit');
        return crm_url('plans');
    }
    
    // Add plan
    $sql = "
        INSERT INTO `plan`
        (`name`,`price`, `voting`, `active`)
        VALUES
        ('$esc_name', '$esc_price', '$esc_voting', '$esc_active')
    ";
    
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    
    return crm_url('plans');
}

/**
 * Handle membership plan update request.
 *
 * @return The url to display on completion.
 */
function command_member_plan_update () {
    $esc_name = mysql_real_escape_string($_POST['name']);
    $esc_price = mysql_real_escape_string($_POST['price']);
    $esc_active = $_POST['active'] ? '1' : '0';
    $esc_voting = $_POST['voting'] ? '1' : '0';
    $esc_pid = mysql_real_escape_string($_POST['pid']);
    
    // Verify permissions
    if (!user_access('member_plan_edit')) {
        error_register('Permission denied: member_plan_edit');
        return crm_url('plans');
    }
    
    // Update plan
    $sql = "
        UPDATE `plan`
        SET
            `name`='$esc_name',
            `price`='$esc_price',
            `active`='$esc_active',
            `voting`='$esc_voting'
        WHERE `pid`='$esc_pid'
    ";
    
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    
    return crm_url('plans');
}

/**
 * Handle delete membership plan request.
 *
 * @return The url to display on completion.
 */
function command_member_plan_delete () {
    global $esc_post;
    
    // Verify permissions
    if (!user_access('member_plan_edit')) {
        error_register('Permission denied: member_plan_edit');
        return crm_url('members');
    }
    
    // Delete plan
    $sql = "DELETE FROM `plan` WHERE `pid`='$esc_post[pid]'";
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    
    return crm_url('plans');
}

/**
 * Handle membership add request.
 *
 * @return The url to display on completion.
 */
function command_member_membership_add () {
    global $esc_post;
    
    // Verify permissions
    if (!user_access('member_edit')) {
        error_register('Permission denied: member_edit');
        return crm_url('members');
    }
    if (!user_access('member_membership_edit')) {
        error_register('Permission denied: member_membership_edit');
        return crm_url('members');
    }
    
    // Add membership
    $sql = "
        INSERT INTO `membership`
        (`cid`,`pid`,`start`";
    if (!empty($esc_post['end'])) {
        $sql .= ", `end`";
    }
    if (!empty($esc_post['autoRenew'])) {
        $sql .= ", `autoRenew`";
    }
    $sql .= ")
        VALUES
        ('$esc_post[cid]','$esc_post[pid]','$esc_post[start]'";
        
    if (!empty($esc_post['end'])) {
        $sql .= ",'$esc_post[end]'";
    }
    if (!empty($esc_post['autoRenew'])) {
        $sql .= ",'$esc_post[autoRenew]'";
    }
    $sql .= ")";
    
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    
    //return crm_url("contact&cid=$_POST[cid]");
    return crm_url("contact&cid=$_POST[cid]&tab=plan");
}

/**
 * Handle membership update request.
 *
 * @param $sid The sid of the membership to update.
 * @return The url to display on completion.
 */
function command_member_membership_update () {
    global $esc_post;
    // Verify permissions
    if (!user_access('member_edit')) {
        error_register('Permission denied: member_edit');
        return crm_url('members');
    }
    if (!user_access('member_membership_edit')) {
        error_register('Permission denied: member_membership_edit');
        return crm_url('members');
    }
    // Construct membership object and save
    $membership = array(
        'sid' => $_POST['sid']
        , 'cid' => $_POST['cid']
        , 'pid' => $_POST['pid']
        , 'start' => $_POST['start']
        , 'end' => $_POST['end']
        , 'autoRenew' => $_POST['autoRenew']
    );
    member_membership_save($membership);
    return crm_url("contact&cid=$_POST[cid]&tab=plan");
}

/**
 * Handle member filter request.
 *
 * @return The url to display on completion.
 */
function command_member_filter () {
    
    // Set filter in session
    $_SESSION['member_filter_option'] = $_GET['filter'];
    
    // Set filter
    if ($_GET['filter'] == 'all') {
        $_SESSION['member_filter'] = array();
    }
    if ($_GET['filter'] == 'active') {
        $_SESSION['member_filter'] = array('active'=>true);
    }
    if ($_GET['filter'] == 'voting') {
        $_SESSION['member_filter'] = array('voting'=>true);
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
        $query = '&' . join('&', $params);
    }
    
    return crm_url('members') . $query;
}

/**
 * Handle membership delete request.
 *
 * @return The url to display on completion.
 */
function command_member_membership_delete () {
    global $esc_post;
    
    // Verify permissions
    if (!user_access('member_membership_edit')) {
        error_register('Permission denied: member_membership_edit');
        return crm_url('members');
    }
    
    // Delete membership
    $sql = "DELETE FROM `membership` WHERE `sid`='$esc_post[sid]'";
    $res = mysql_query($sql);
    if (!$res) crm_error(mysql_error());
    
    return crm_url('members');
}

/**
 * Handle member import request.
 *
 * @return The url to display on completion.
 */
function command_member_import () {
    global $config_org_name;
    
    if (!user_access('contact_edit')) {
        error_register('User does not have permission: contact_edit');
        return crm_url('members');
    }
    if (!user_access('member_edit')) {
        error_register('User does not have permission: member_edit');
        return crm_url('members');
    }
    
    if (!array_key_exists('member-file', $_FILES)) {
        error_register('No member file uploaded');
        return crm_url('members&tab=import');
    }
    
    $csv = file_get_contents($_FILES['member-file']['tmp_name']);
    
    $data = csv_parse($csv);
    
    foreach ($data as $row) {
        
        // Convert row keys to lowercase and remove spaces
        foreach ($row as $key => $value) {
            $new_key = str_replace(' ', '', strtolower($key));
            unset($row[$key]);
            $row[$new_key] = $value;
        }
        
        // Add contact
        $memberNumber = mysql_real_escape_string($row['membernumber']);
        $parentNumber = mysql_real_escape_string($row['parentnumber']);
        $firstName = mysql_real_escape_string($row['firstname']);
        $lastName = mysql_real_escape_string($row['lastname']);
        $joined = mysql_real_escape_string($row['joined']);
        $company = mysql_real_escape_string($row['company']);
        $school = mysql_real_escape_string($row['school']);
        $studentID = mysql_real_escape_string($row['studentid']);
        $address1 = mysql_real_escape_string($row['address1']);
        $address2 = mysql_real_escape_string($row['address2']);
        $city = mysql_real_escape_string($row['city']);
        $state = mysql_real_escape_string($row['state']);
        $zip = mysql_real_escape_string($row['zip']);
        $email = mysql_real_escape_string($row['email']);
        $phone = mysql_real_escape_string($row['phone']);
        $over18 = intval($row['over18']);
        $emergencyName = mysql_real_escape_string($row['emergencyname']);
        $emergencyRelation = mysql_real_escape_string($row['emergencyrelation']);
        $emergencyPhone = mysql_real_escape_string($row['emergencyphone']);
        $emergencyEmail = mysql_real_escape_string($row['emergencyemail']);
        $notes = mysql_real_escape_string($row['notes']);
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
                ('$memberNumber'
                ,'$parentNumber'
                ,'$firstName'
                ,'$lastName'
                ,'$joined'
                ,'$company'
                ,'$school'
                ,'$studentID'
                ,'$address1'
                ,'$address2'
                ,'$city'
                ,'$state'
                ,'$zip'
                ,'$email'
                ,'$phone'
                ,'$over18'
                ,'$emergencyName'
                ,'$emergencyRelation'
                ,'$emergencyPhone'
                ,'$emergencyEmail'
                ,'$notes')";

        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $cid = mysql_insert_id();
        $esc_cid = mysql_real_escape_string($cid);
        
        // Add member
        $sql = "
            INSERT INTO `member`
            (`cid`)
            VALUES
            ('$esc_cid')";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());

        // Add RFID
        $RFID = $row['rfid'];
        $esc_RFID = mysql_real_escape_string($RFID);
        $sql = "
            INSERT INTO `key`
            (`cid`,`serial`)
            VALUES
            ('$esc_cid','$esc_RFID')";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        
        // Find Username
        $username = $row['username'];
        $n = 0;
        while (empty($username) && $n < 100) {
            
            // Contruct test username
            $test_username = strtolower($row['firstname']{0} . $row['lastName']);
            if ($n > 0) {
                $test_username .= $n;
            }
            
            // Check whether username is taken
            $esc_test_name = mysql_real_escape_string($test_username);
            $sql = "SELECT * FROM `user` WHERE `username`='$esc_test_name'";
            $res = mysql_query($sql);
            if (!$res) crm_error(mysql_error());
            $user_row = mysql_fetch_assoc($res);
            if (!$user_row) {
                $username = $test_username;
            }
            $n++;
        }
        if (empty($username)) {
            error_register('Please specify a username');
            return crm_url('members&tab=import');
        }
        
        // Add user
        $user = array();
        $user['username'] = $username;
        $user['cid'] = $cid;
        user_save($user);
        
        // Add role entry
        $sql = "SELECT `rid` FROM `role` WHERE `name`='member'";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $role_row = mysql_fetch_assoc($res);
        $esc_rid = mysql_real_escape_string($role_row['rid']);
        
        if ($role_row) {
            $sql = "
                INSERT INTO `user_role`
                (`cid`, `rid`)
                VALUES
                ('$esc_cid', '$esc_rid')";
            $res = mysql_query($sql);
            if (!$res) crm_error(mysql_error());
        }

        // Add plan if necessary
        $esc_plan_name = mysql_real_escape_string($row['plan']);
        $sql = "SELECT `pid` FROM `plan` WHERE `name`='$esc_plan_name'";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        if (mysql_num_rows($res) < 1) {
            $sql = "
                INSERT INTO `plan`
                (`name`, `active`)
                VALUES
                ('$esc_plan_name', '1')
            ";
            $res = mysql_query($sql);
            if (!$res) crm_error(mysql_error());
            $pid = mysql_insert_id();
        } else {
            $plan_row = mysql_fetch_assoc($res);
            $pid = $plan_row['pid'];
        }
        
        // Add membership
        $esc_start = mysql_real_escape_string($row['startdate']);
        $esc_end = mysql_real_escape_string($row['enddate']);
        $esc_pid = mysql_real_escape_string($pid);
        $sql = "
            INSERT INTO `membership`
            (`cid`, `pid`, `start`, `end`)
            VALUES
            ('$esc_cid', '$esc_pid', '$esc_start', '$esc_end')
        ";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        
        if (function_exists('paypal_payment_revision')) {
            if (!empty($email)) {
                $sql = "
                    INSERT INTO `contact_paypal`
                    (`paypal_email`, `cid`)
                    VALUES
                    ('$email', '$esc_cid')
                ";
                $res = mysql_query($sql);
                if (!$res) crm_error(mysql_error());
            }
        }
        
        // Notify admins
        $from = "\"$config_org_name\" <$config_email_from>";
        $headers = "From: $from\r\nContent-Type: text/html; charset=ISO-8859-1\r\n";
        if (!empty($config_email_to)) {
            $name = theme_contact_name($_POST['cid']);
            $content = theme('member_created_email', $user['cid']);
            mail($config_email_to, "New Member: $name", $content, $headers);
        }
        
        // Notify user
        /*$confirm_url = user_reset_password_url($user['username']);
        $content = theme('member_welcome_email', $user['cid'], $confirm_url);
        mail($email, "Welcome to $config_org_name", $content, $headers);*/
    }
    
    return crm_url('members');
}

/**
 * Handle plan import request.
 *
 * @return The url to display on completion.
 */
function command_member_plan_import () {
    
    if (!user_access('member_plan_edit')) {
        error_register('User does not have permission: member_plan_edit');
        return crm_url('members');
    }
    
    if (!array_key_exists('plan-file', $_FILES)) {
        error_register('No plan file uploaded');
        return crm_url('plans&tab=import');
    }
    
    $csv = file_get_contents($_FILES['plan-file']['tmp_name']);
    
    $data = csv_parse($csv);
    
    foreach ($data as $row) {
        
        // Convert row keys to lowercase and remove spaces
        foreach ($row as $key => $value) {
            $new_key = str_replace(' ', '', strtolower($key));
            unset($row[$key]);
            $row[$new_key] = $value;
        }
        
        // Add plan
        $name = mysql_real_escape_string($row['planname']);
        $price = mysql_real_escape_string($row['price']);
        $active = mysql_real_escape_string($row['active']);
        $voting = mysql_real_escape_string($row['voting']);
        $sql = "
            INSERT INTO `plan`
            (`name`,`price`,`active`,`voting`)
            VALUES
            ('$name','$price','$active','$voting')";
        $res = mysql_query($sql);
        if (!$res) crm_error(mysql_error());
        $pid = mysql_insert_id();
    }
    
    return crm_url('plans');
}