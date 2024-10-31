<div class="wrap">
    <h2>Teilnehmerdaten:</h2>
    <p>Bitte beachten Sie: Mit diesem Update müssen Sie eine neue Lizenz über Digistore24 erwerben.</p>
    <small>Der Standardwert für Prüfungsrelevante Felder beträgt 85%</small>
	<?php
	foreach ( SCHUFA_IDCheck::SETTINGS as $setting => $label ):
		?>
        <p><?php echo $label ?>
            : <?php if ( array_key_exists( $setting, SCHUFA_IDCheck::DEFAULT_FOR_FIELDS ) and ! get_option( $setting, false ) ) {
				echo SCHUFA_IDCheck::DEFAULT_FOR_FIELDS[ $setting ];
			} else {
				echo get_option( $setting, 'KEIN WERT' );
			} ?></p>
		<?php
	endforeach;
	$month = date( 'm', time() );
	$year  = date( 'Y', time() );
	?>
    <p>Diesen Monat durchgeführte
        Prüfungen: <?php echo ( isset( get_option( 'ID_CHECKS' )[ $year ][ $month ] ) ) ? get_option( 'ID_CHECKS' )[ $year ][ $month ] : 0 ?></p>
    <p>Q-Bit Status:
		<?php if ( get_option( 'ID_CHECK_QBIT' ) ): ?>
            Q-Bit kann positive Prüfung ablehnen
		<?php else: ?>
            Q-Bit wird ignoriert
		<?php endif; ?>
    </p>
</div>