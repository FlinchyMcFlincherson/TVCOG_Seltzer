<p>Welcome to <?php print $org_name; ?>!</p>
<p>
    You are recieving this email because you have recently been entered into the Tech Valley Center of Gravity membership management system, Seltzer.
</p>
<p>
    Your Seltzer username is:<br>
    <?php print $username; ?>
</p>
<p>  
	Please login to the system as soon as possible to confirm your email and set your password by visiting:<br> 
	<a href="<?php print $confirm_url; ?>"><?php print $confirm_url; ?></a>.
</p>
<p>
    You may manage your contact info at: <a href="<?php print "http://$hostname$base_path"; ?>"><?php print "http://$hostname$base_path"; ?></a><br>
    Please ensure the information we have on file for you is complete and accurate.
</p>
<p>
	If you have any additional questions, please contact: Treasurer@TechValleyCenterofGravity.com
</p>
