<label>Geburtsdatum zur Identitätsprüfung erforderlich:</label>
<input type="text" id="birthdate">
<button type="button" class="button button-primary" onclick="proceedIDCheck()">Geburtsdatum speichern</button>
<script>
	function proceedIDCheck()
	{
	    var birthdate = jQuery('#birthdate').val();
	    jQuery.ajax({
		    url: "<?php echo admin_url('admin-ajax.php') ?>",
		    type: 'POST',
		    data: {
		        action: 'proceedIDCheck',
			    birthdate: birthdate,
			    userid: <?php echo $userID ?>,
		    },
		    success: function(result)
		    {
				jQuery('#status').html(result);
		    }
	    })
	}
</script>