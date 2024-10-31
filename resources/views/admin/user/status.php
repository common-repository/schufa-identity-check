<h3>Identitätscheck Plugin Status:</h3>
<table class="form-table">
    <tr>
        <th>SCHUFA Prüfung:</th>
        <td id="status"><?php
            try {
                if(!is_object($user))
                {
	                $user = get_user_by('ID', $user);
                }

	                SCHUFA_IDCheck::getUserStatus( $user );
            }catch(Exception $e)
            {
                echo $e->getMessage();
            }
			?></td>
    </tr>
</table>