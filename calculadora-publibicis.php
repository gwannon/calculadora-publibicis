<?php /**
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
use Gwannon\PHPClientifyAPI\contactClientify;

require __DIR__ . '/admin.php';

//Cargamos el multi-idioma
function calc_pb_plugins_loaded() {
  load_plugin_textdomain('calc-pb', false, dirname( plugin_basename( __FILE__ ) ) . '/langs/' );
}
add_action('plugins_loaded', 'calc_pb_plugins_loaded', 0 );

define("CLIENTIFY_API_URL", "https://api.clientify.net/v1");
define("CLIENTIFY_LOG_API_CALLS", false);
define('CLIENTIFY_API_KEY', get_option("_calc_pb_clientify_api_key"));

//Shortcode [calc-pb]
add_shortcode('calc-pb', 'calc_pb_shortcode');
function calc_pb_shortcode ($atts, $content) {
  ob_start(); 

  $prices = get_option("_calc_pb_prices"); /*[
    "weekend" => 1200,
    "workweek" => 1500,
    "week" => 1900,
    "one" => 250,
    "plus" => 350,
    "xl-plus" => 450,
    "flyers" => 370,
    "design" => 200
  ];*/

  $timetables = [
    "weekend" => __("Publibicis One (weekend) 3 Dias", "calc-pb"),
    "workweek" => __("Publibicis One (week) 5 Dias", "calc-pb"),
    "week" => __("Publibicis One (week) 7 Dias", "calc-pb"),
  ];

  $sizes = [
    "one" => __("Lona One", "calc-pb"),
    "plus" => __("Lona + Plus", "calc-pb"),
    "xl-plus" => __("Lona XL Plus", "calc-pb"),
  ];

  $extras = [
    "flyers" => __("Flyers (1.000 uns)", "calc-pb"),
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
    
   $html = '<table border="1" cellpadding="10" cellspacing="0" width="100%">
      <thead>
        <tr>
          <th>'.__("Concepto", "calc-pb").'</th>
          <th>'.__("Unidades", "calc-pb").'</th>
          <th>'.__("Coste por unidad", "calc-pb").'</th>
          <th>'.__("Coste total", "calc-pb").'</th>
        </tr>
      </thead>
      <tbody>
        <tr>
          <td>'.$timetables[$_REQUEST['days']].'</td>
          <td style="text-align: center;">'.$_REQUEST['weeks'].'</td>
          <td style="text-align: right;">'.number_format($prices[$_REQUEST['days']], 2, ",", ".")." €".'</td>
          <td style="text-align: right;">'.number_format(($_REQUEST['weeks'] * $prices[$_REQUEST['days']]), 2, ",", ".")." €".'</td>
        </tr>
        <tr>
          <td>'.$sizes[$_REQUEST['size']].'</td>
          <td style="text-align: center;">1</td>
          <td style="text-align: right;">'.number_format($prices[$_REQUEST['size']], 2, ",", ".")." €".'</td>
          <td style="text-align: right;">'.number_format($prices[$_REQUEST['size']], 2, ",", ".")." €".'</td>
        </tr>';
        foreach ($extras as $label => $text) { 
          if(isset($_REQUEST[$label]) && $_REQUEST[$label] == 1) { 
            $html .= '<tr>
                <td>'.$text.'</td>
                <td style="text-align: center;">1</td>
                <td style="text-align: right;">'.number_format($prices[$label], 2, ",", ".")." €".'</td>
                <td style="text-align: right;">'.number_format($prices[$label], 2, ",", ".")." €".'</td>
              </tr>';
          } 
        }
        $html .= '<tr>
          <th colspan="2">'.__("Total", "calc-pb").'</th>
          <td colspan="2" style="text-align: right;">'.number_format($total, 2, ",", ".")." €".'</td>
        </tr>
      </tbody>
    </table>';
    echo $html;

    //Metemos el usuario en Clientify y añadimos etiqueta #publibicis #form-publibicis 
    calc_pb_create_clientify_contact ($_REQUEST['email'], $_REQUEST['fullname'], $_REQUEST['phone']);

    //Generamos el PDF del presupuesto
    $file = calc_pb_generate_pdf($html);
   
    //Mandamos email con el presupuesto
    $headers[] = 'MIME-Version: 1.0';
    $headers[] = 'Content-type: text/html; charset=utf-8';
    $message = "Aquí tienes tu presupuesto";
    wp_mail($_REQUEST['email'], "Presupuesto", $message, $headers, $file);

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
    //Borramos el PDF
    unlink($file);
  } else { ?>
    <form method="post">
      <label>
        <?php _e("Nombre completo", "calc-pb"); ?>*
        <input type="text" name="fullname" value="<?=(isset($_REQUEST['fullname']) && $_REQUEST['fullname'] != '' ? strip_tags($_REQUEST['fullname']) : "")?>" required>
      </label>
      <label>
        <?php _e("Teléfono", "calc-pb"); ?>*
        <input type="phone" name="phone" value="<?=(isset($_REQUEST['phone']) && $_REQUEST['phone'] != '' ? strip_tags($_REQUEST['phone']) : "")?>" required>
      </label>
      <label>
        <?php _e("Email", "calc-pb"); ?>*
        <input type="email" name="email" value="<?=(isset($_REQUEST['email']) && $_REQUEST['email'] != '' ? strip_tags($_REQUEST['email']) : "")?>" required>
      </label>
      <label>
        <select name="state" required>
          <?php foreach($states as $state) { ?> 
            <option value="<?=$state?>"<?=(isset($_REQUEST['state']) && $state == $_REQUEST['state'] ? " selected='selected'" : "")?>><?=$state?></option>
          <?php } ?> 
        </select>
      </label>
      <?php _e("Tipo de semana", "calc-pb"); ?>
      <div style="display: flex; gap: 10px;">
        <?php foreach ($timetables as $label => $text) { ?> 
          <label>
            <input type="radio" name="days" value="<?=$label?>"<?=(isset($_REQUEST['days']) && $label == $_REQUEST['days'] ? " checked='checked'" : "")?> required>
            <img src="/wp-content/plugins/calculadora-publibicis/images/<?=$label?>.jpg" alt="<?=$text?>" />
            <?=$text?>
          </label>
        <?php } ?>
      </div>
      <label>
        <select name="weeks">
          <?php for($i = 1; $i <= 10; $i++) { ?><option value="<?=$i?>"<?=(isset($_REQUEST['weeks']) && $i == $_REQUEST['weeks'] ? " selected='selected'" : "")?>><?=$i?></option><?php } ?>
        </select>
        <?php _e("Número de semanas", "calc-pb"); ?>
      </label>
      <?php _e("Tamaño lona", "calc-pb"); ?>
      <div style="display: flex; gap: 10px;">
        <?php foreach ($sizes as $label => $text) { ?> 
          <label>
            <input type="radio" name="size" value="<?=$label?>"<?=(isset($_REQUEST['size']) && $label == $_REQUEST['size'] ? " checked='checked'" : "")?> required>
            <img src="/wp-content/plugins/calculadora-publibicis/images/<?=$label?>.jpg" alt="<?=$text?>" />
            <?=$text?>
          </label>
        <?php } ?>
      </div>
      <?php _e("Opciones", "calc-pb"); ?>
      <div style="display: flex; gap: 10px;">
        <?php foreach ($extras as $label => $text) { ?> 
          <label>
            <input type="checkbox" name="<?=$label?>" value="1"<?=(isset($_REQUEST[$label]) && 1 == $_REQUEST[$label] ? " checked='checked'" : "")?>>
            <img src="/wp-content/plugins/calculadora-publibicis/images/<?=$label?>.jpg" alt="<?=$text?>" />
            <?=$text?>
          </label>
        <?php } ?>
      </div>
      <input type="submit" name="calculate" value="<?php _e("Solicitar presupuesto", "calc-pb"); ?>">
    </form>
  <?php } ?>
  <script>

  </script>
  <style>
    form input[type=radio],
    form input[type=checkbox] {
      display: none;
    }

    form img {
      border: 4px solid transparent;
    }

    input[type=radio]:checked ~ img,
    input[type=checkbox]:checked ~ img {
      border: 4px solid green;
    } 
  </style>
  <?php return ob_get_clean();
}


/* Libs */
function calc_pb_generate_pdf ($html) {
  $pdf = new \Mpdf\Mpdf();
  $pagecount = $pdf->SetSourceFile(__DIR__ . '/dossier.pdf');
  for ($i=1; $i<=$pagecount; $i++) {
    $import_page = $pdf->ImportPage($i);
    $pdf->UseTemplate($import_page);
    if ($i < $pagecount) $pdf->AddPage();
  }
  $pdf->AddPage();
  $pdf->WriteHTML($html);
  $pdf->SetTitle(sprintf(__("Presupuesto para %s %s", "calc-pb"), $_REQUEST['fullname'], date("Y/m/d H:i")));
  $pdf->SetAuthor("PubliBicis");
  $file = __DIR__ .'/presupuesto-'.hash('ripemd160', date("YmdHis").rand(1, 1000000)).'.pdf';
  $pdf->Output($file,'F');
  return $file;
}

function calc_pb_create_clientify_contact ($email, $name, $phone) {
  $contact = new contactClientify($email, true); //Si no existe se crea
  $contact->setFirstName($name);
  foreach (explode(",", get_option("_calc_pb_clientify_tags")) as $tag) {
    $tag = trim($tag);
    if(!$contact->hasTag($tag)) $contact->addTag($tag);
  }
  if(!$contact->hasphone($phone)) {
    $phone = str_replace([" ", "-"], "", $phone);
    $contact->addPhone((str_contains("+34", $phone) ? "" : "+34").$phone, 1);
  }
  $contact->update();
  return;
}
