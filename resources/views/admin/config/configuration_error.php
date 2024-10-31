<div class="wrap">
    <h3 style="color:red">Das Plugin kann nicht gestartet werden</h3>
    Bitte stellen Sie sicher dass:
    <ul>
		<?php
		if ( ! in_array( 'woocommerce/woocommerce.php', apply_filters( 'active_plugins', get_option( 'active_plugins' ) ) ) ):echo "<li style='color:red'>";
		else: echo "<li style='color:green'>"; endif;
		?>
        WooCommerce installiert und aktiviert ist</li>
		<?php
		if ( ! function_exists( 'curl_init' ) ):echo "<li style='color:red'>";
		else: echo "<li style='color:green'>"; endif;
		?>
        cURL aktiv ist</li>
    </ul>
    <h4>Bestellungen werden im Moment noch ohne SCHUFA Abfrage angenommen</h4>
</div>