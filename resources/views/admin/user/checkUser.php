<button type="button" class="button button-primary" onclick="startSCHUFAIDCheck(<?php echo $user->ID ?>">Diesen Kunden Prüfen</button>
<p>Geburtsdatum: <?php echo get_user_meta($user->ID, 'billing_birthdate', true) ?></p>
<script>
	function startSCHUFAIDCheck(userid)
	{
	    jQuery.ajax({
		    url: "<?php echo admin_url('admin-ajax.php') ?>",
		    type: 'POST',
		    data:{
		        action: 'manualCustomerCheck',
			    userID: userid,
		    },
		    success: function(result){
		        jQuery('#status').html(result);
		    }
	    })
	}
</script>
