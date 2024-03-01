<?php 
/**
 * Plugin Name: Calculadora PubliBicis
 * Description: Plugin de Wordpress para meter una calculadora de presupuestos para PubliBicis.es con el shorrtocode [calc-pb]
 * Version:     1.0
 * Author:      Gwannon
 * Author URI:  https://github.com/gwannon/
 * License:     GNU General Public License v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Text Domain: calc-pb
 *
 * PHP 8.2
 * WordPress 6.4.3
 */

require_once __DIR__ . '/vendor/autoload.php';
require_once __DIR__ . '/admin.php';
require_once __DIR__ . '/libs.php';

//Cargamos el multi-idioma
function calc_pb_plugins_loaded() {
  load_plugin_textdomain('calc-pb', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
}
add_action('plugins_loaded', 'calc_pb_plugins_loaded', 0 );

//Configuración básica de la conexión a CLIENTIFY
define("CLIENTIFY_API_URL", "https://api.clientify.net/v1");
define("CLIENTIFY_LOG_API_CALLS", false);
define('CLIENTIFY_API_KEY', get_option("_calc_pb_clientify_api_key"));

//Shortcode [calc-pb]
add_shortcode('calc-pb', 'calc_pb_shortcode');
function calc_pb_shortcode ($atts, $content) {
  ob_start(); 
  $prices = get_option("_calc_pb_prices");
  $timetables = [
    "3-days" => __("3 DÍAS", "calc-pb"),
    "5-days" => __("5 DÍAS", "calc-pb"),
    "7-days" => __("7 DÍAS", "calc-pb"),
  ];
  $sizes = [
    "1200x1770" => __("<span>Lona de </span>1200x1770 mm", "calc-pb"),
    "2340x1770" => __("<span>Lona de </span>22340x1770 mm", "calc-pb"),
    "1770x1770" => __("<span>Lona de </span>1770x1770 mm", "calc-pb"),
  ];
  $extras = [
    "flyers" => __("Flyers <span>1.000 uds.</span>", "calc-pb"),
    "design" => __("Diseño de la lona", "calc-pb")
  ];

  $states = ['Álava/Araba', "Bizkaia", "Guipuzkoa", "Cantabria", "Navarra", "Rioja"];
  
  if(isset($_REQUEST['calculate'])) { 
    $total = $prices[$_REQUEST['size']] + ($_REQUEST['weeks'] * $prices[$_REQUEST['days']]);
    foreach ($extras as $label => $text) {
      if(isset($_REQUEST[$label]) && $_REQUEST[$label] == 1) {
        $total = $total + $prices[$label];
      }
    }
    $html = calc_pb_generate_html($timetables, $sizes, $extras, $prices, $total);

    //Mostramos el mensaje de gracias
    echo "<div id='cp-thanks'>".apply_filters("the_content", stripslashes(get_option('_calc_pb_afterform_message')))."</div>";

    //Metemos el usuario en Clientify y añadimos etiqueta #publibicis #form-publibicis 
    calc_pb_create_clientify_contact ($_REQUEST['email'], $_REQUEST['fullname'], $_REQUEST['phone']);

    //Generamos el PDF del presupuesto
    $file = calc_pb_generate_pdf($html."<br><br>".get_option('_calc_pb_conditions'));
   
    //Mandamos email con el presupuesto
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $message = str_replace("[HTML]", $html, get_option('_calc_pb_email_html'));
    $message = apply_filters("the_content", stripslashes(str_replace("[CONDITIONS]", get_option('_calc_pb_conditions'), $message)));
    wp_mail($_REQUEST['email'], get_option('_calc_pb_email_subject'), $message, $headers, $file);

    //Mandamos email al administrador
    $headers[] = 'Reply-to: '.$_REQUEST['email'];
    $message = "<b>Nombre:</b> ".$_REQUEST['fullname']."<br/>\n".
      "<b>Teléfono:</b> ".$_REQUEST['phone']."<br/>\n".
      "<b>Email:</b> ".$_REQUEST['email']."<br/>\n".
      "<b>Provincia:</b> ".$_REQUEST['state']."<br/><br/><br/>\n".$html;
    foreach (explode(",", get_option("_calc_pb_send_emails")) as $admin_email) {
      $admin_email = trim($admin_email);
      if(is_email($admin_email)) wp_mail($admin_email, "Solicitud de presupuesto", $message, $headers, $file);
    }

    //Guardamos en CSV
    unset($_REQUEST['calculate']);
    unset($_REQUEST['privacy']);
    $f=fopen(__DIR__."/csv/presupuestos.csv", "a+");
    $csv['fecha'] = date("Y-m-d H:i:s");
    $csv['url'] = get_the_permalink();
    foreach($_REQUEST as $item => $value) {
      $csv[$item] = $value;
    }
    fputcsv($f, $csv);
    fclose($f);

    //Borramos el PDF
    unlink($file);
  } else { ?>
    <form id="cp-form" method="post">
      <div id="counter">
        <div><?php _e("¡PSS! ¿TIENES UN MIN?", "calc-pb"); ?></div>
        <div><?php _e("Calcula el presupuesto de tu campaña.", "calc-pb"); ?></div> 
        <img src="/wp-content/plugins/calculadora-publibicis/images/1.svg">
      </div>
      <div id="cp-step1" class="cp-step current">
        <div><?php _e("Tamaño de la lona", "calc-pb"); ?></div>
        <div>
          <?php $control = 0; foreach ($sizes as $label => $text) { ?> 
            <label>
              <input type="radio" name="size" value="<?=$label?>"<?=((isset($_REQUEST['size']) && $label == $_REQUEST['size']) || (!isset($_REQUEST['size']) && $control == 0) ? " checked='checked'" : "")?> required>
              <img src="<?=plugin_dir_url( __FILE__ );?>images/<?=$label?>.svg" alt="<?=$text?>" />
              <?=$text?>
            </label>
          <?php $control ++; } ?>
        </div>
        <button class="next"><?php _e("Siguiente", "calc-pb"); ?></button>
      </div>

      <div id="cp-step2" class="cp-step">
        <div><?php _e("Duración de campaña", "calc-pb"); ?></div>
        <div>
          <?php $control = 0; $control = 0;foreach ($timetables as $label => $text) { ?> 
            <label>
              <input type="radio" name="days" value="<?=$label?>"<?=((isset($_REQUEST['days']) && $label == $_REQUEST['days']) || (!isset($_REQUEST['days']) && $control == 0) ? " checked='checked'" : "")?> required>
              <?=$text?>
            </label>
          <?php $control ++; } ?>
        </div>
        <label>
          <?php _e("Número de semanas", "calc-pb"); ?>
          <select name="weeks">
            <?php for($i = 1; $i <= 10; $i++) { ?><option value="<?=$i?>"<?=((isset($_REQUEST['weeks']) && $i == $_REQUEST['weeks']) || (!isset($_REQUEST['weeks']) && $control == 0) ? " selected='selected'" : "")?>><?=$i?></option><?php } ?>
          </select>
        </label>
        <button class="next"><?php _e("Siguiente", "calc-pb"); ?></button>
      </div>
     
      <div id="cp-step3" class="cp-step">
        <div><?php _e("Opciones", "calc-pb"); ?></div>
        <div>
          <?php $control = 0; foreach ($extras as $label => $text) { ?> 
            <label>
              <input type="checkbox" name="<?=$label?>" value="1"<?=(isset($_REQUEST[$label]) && 1 == $_REQUEST[$label] ? " checked='checked'" : "")?>>
              <img src="<?=plugin_dir_url( __FILE__ );?>images/<?=$label?>.svg" alt="<?=$text?>" />
              <?=$text?>
            </label>
          <?php $control ++; } ?>
        </div>
        <button class="next"><?php _e("Siguiente", "calc-pb"); ?></button>
      </div>

      <div id="cp-step4" class="cp-step">
        <div><?php _e("Rellena tus datos", "calc-pb"); ?></div>
        <div>
          <label>
            <input type="text" name="fullname" placeholder="<?php _e("Nombre completo", "calc-pb"); ?>*" value="<?=(isset($_REQUEST['fullname']) && $_REQUEST['fullname'] != '' ? strip_tags($_REQUEST['fullname']) : "")?>" required>
          </label>
          <label>
            <input type="text" name="phone" placeholder="<?php _e("Teléfono", "calc-pb"); ?>*" value="<?=(isset($_REQUEST['phone']) && $_REQUEST['phone'] != '' ? strip_tags($_REQUEST['phone']) : "")?>" required>
          </label>
          <label>
            <input type="email" name="email" placeholder="<?php _e("Email", "calc-pb"); ?>*" value="<?=(isset($_REQUEST['email']) && $_REQUEST['email'] != '' ? strip_tags($_REQUEST['email']) : "")?>" required>
          </label>
          <label>
            <select name="state" required>
              <?php foreach($states as $state) { ?> 
                <option value="<?=$state?>"<?=(isset($_REQUEST['state']) && $state == $_REQUEST['state'] ? " selected='selected'" : "")?>><?=$state?></option>
              <?php } ?> 
            </select>
          </label>
          <label>
            <input type="checkbox" name="privacy" value="1" required> <p><?php _e("Acepto la <a href='/politica-privacidad/'>política de privacidad</a>.", ''); ?></p>
          </label>
        </div>
        <button type="submit" name="calculate"><?php _e("Solicitar presupuesto", "calc-pb"); ?></button>
      </div>
    </form>
  <?php } ?>
  <script>
    <?php echo file_get_contents(__DIR__ . '/assets/script.js'); ?>
  </script>
  <style>
    <?php echo file_get_contents(__DIR__ . '/assets/style.css'); ?>
  </style>
  <?php return ob_get_clean();
}
