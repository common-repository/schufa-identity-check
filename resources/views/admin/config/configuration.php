<div class="wrap">
    <h2>Konfiguration des Schufa Identitätschecks:</h2>

    <h3>Teilnehmerdaten:</h3>
	<?php
	try {
		foreach (
			SCHUFA_IDCheck::SETTINGS as $setting => $translation
		):
			?>
            <p>
                <label><?php echo $translation ?>:</label><br/>
                <input type="<?php
                if(in_array($setting, SCHUFA_IDCheck::NUMERIC_FIELDS)):
                    echo 'number';
                else: echo'text';
                endif;
                ?>" id="<?php echo $setting ?>" name="<?php echo $setting ?>"
                       value="<?php echo get_option( $setting ) ?>">
            </p>
			<?php
		endforeach;
	} catch ( Exception $e ) {
		echo $e->getMessage();
	}
	?>
    <p>
        <label>Q-Bit ist entscheidend:</label>
        <select id="qbit">
            <option value="1" <?php if ( get_option( 'ID_CHECK_QBIT' ) ): ?> selected <?php endif; ?>>Ja</option>
            <option value="0" <?php if ( ! get_option( 'ID_CHECK_QBIT' ) ): ?> selected <?php endif; ?>>Nein</option>
        </select>
        <small>
            Wenn das Q-Bit entscheidend ist, kann die gesamte Prüfung erfolgreich sein - jedoch wird die Bestellung
            abgelehnt, sollte keine Ausweisgeprüfte Identität vorhanden sein.
        </small>
    </p>
    <input onclick="saveSettings();" type="submit" class="button-primary" name="submit"
           value="Einstellungen speichern"/>
</div>
<script>
  function saveSettings() {
	  <?php
	  foreach(SCHUFA_IDCheck::SETTINGS as $setting => $translation):
	  ?>
    var <?php echo $setting ?> =
    jQuery('#<?php echo $setting ?>').val();
	  <?php endforeach; ?>
    var qbit = jQuery('#qbit').val();
    jQuery.ajax({
      url: '<?php echo admin_url( 'admin-ajax.php' ) ?>',
      type: 'POST',
      data: {
        action: 'updateIDCHECKSettings',
	  <?php foreach (SCHUFA_IDCheck::SETTINGS as $setting => $translation): ?>
	  <?php echo $setting?>:<?php echo $setting ?>,
	  <?php endforeach; ?>
    QBIT: qbit,
  },
    success: function(result) {
      window.location.reload();
    }
  })
  }
</script>