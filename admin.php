<?php

//Administrador 
add_action( 'admin_menu', 'calc_pb_plugin_menu' );
function calc_pb_plugin_menu() {
	add_options_page( __('Calculadora', 'calc-pb'), __('Calculadora', 'calc-pb'), 'manage_options', 'calc-pb', 'calc_pb_page_settings');
}

function calc_pb_page_settings() { 
	$prices = [
		"weekend" => __("Publibicis One (weekend) 3 Dias", "calc-pb"),
		"workweek" => __("Publibicis One (week) 5 Dias", "calc-pb"),
		"week" => __("Publibicis One (week) 7 Dias", "calc-pb"),
		"one" => __("Lona One", "calc-pb"),
		"plus" => __("Lona + Plus", "calc-pb"),
		"xl-plus" => __("Lona XL Plus", "calc-pb"),
		"flyers" => __("Flyers (1.000 uns)", "calc-pb"),
		"design" => __("Diseño de la lona", "calc-pb")
	];
	?><h1><?php _e("Configuración", 'calc-pb'); ?></h1><?php 
	if(isset($_REQUEST['send']) && $_REQUEST['send'] != '') { 
		?><p style="border: 1px solid green; color: green; text-align: center;"><?php _e("¡Datos guardados correctamente!", 'calc-pb'); ?></p><?php
		update_option('_calc_pb_clientify_api_key', $_POST['_calc_pb_clientify_api_key']);
		update_option('_calc_pb_clientify_tags', $_POST['_calc_pb_clientify_tags']);
		update_option('_calc_pb_send_emails', $_POST['_calc_pb_send_emails']);
		update_option('_calc_pb_prices', $_POST['_calc_pb_prices']);
	} ?>
	<form method="post">
    <b><?php _e("Clientify API key", 'calc-pb'); ?>:</b><br/>
		<input type="text" name="_calc_pb_clientify_api_key" value="<?php echo get_option("_calc_pb_clientify_api_key"); ?>" style="width: calc(100% - 20px);" /><br/>
		<b><?php _e("Etiquetas clientify", 'calc-pb'); ?>:<br/><small>(<?php _e("separados por comas", 'calc-pb'); ?>)</small></b><br/>
		<input type="text" name="_calc_pb_clientify_tags" value="<?php echo get_option("_calc_pb_clientify_tags"); ?>" style="width: calc(100% - 20px);" /><br/>
		<b><?php _e("Emails de aviso", 'calc-pb'); ?>:<br/><small>(<?php _e("separados por comas", 'calc-pb'); ?>)</small></b><br/>
		<input type="text" name="_calc_pb_send_emails" value="<?php echo get_option("_calc_pb_send_emails"); ?>" style="width: calc(100% - 20px);" /><br/>
		<h2><?php _e("Precios", 'calc-pb'); ?>:</h2>
		<?php $current_prices = get_option("_calc_pb_prices"); foreach($prices as $label => $price) {?>
			<b><?=$price?></b><br/>
			<input type="number" name="_calc_pb_prices[<?=$label?>]" value="<?php echo $current_prices[$label]; ?>" min-value="0" style="width: 100px; text-align: right;" />&euro;<br/>
		<?php } ?>
		<br/><input type="submit" name="send" class="button button-primary" min-value=" value="<?php _e("Guardar", 'calc-pb'); ?>" />
	</form>
	<?php
}