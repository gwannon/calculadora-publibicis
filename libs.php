<?php

use Gwannon\PHPClientifyAPI\contactClientify;
use Mpdf\Mpdf;

/* Libs */
function calc_pb_generate_pdf ($html, $size) {
  $pdf = new Mpdf([
    //'format' => 'A4',
    //'margin_header' => 30,     // 30mm not pixel
    //'margin_footer' => 30,     // 10mm
    //'setAutoBottomMargin' => 'pad',
    //'setAutoTopMargin' => 'pad',
    'fontDir' => __DIR__ . '/fonts/',
    'fontdata' => [
      'poppins' => [
          'R' => 'Poppins-Regular.ttf',
          'I' => 'Poppins-Italic.ttf'
      ]
    ],
    'default_font' => 'poppins'
  ]);
  $pagecount = $pdf->SetSourceFile(get_attached_file(get_option('_calc_pb_dossier_'.$size.'_pdf_id')));
  for ($i=1; $i<=$pagecount; $i++) {
    $import_page = $pdf->ImportPage($i);
    $pdf->UseTemplate($import_page);
    if ($i < $pagecount) $pdf->AddPage();
  }

  $pdf->AddPage();
  $pdf->WriteHTML($html."<style>body { background-color: #ececec; }</style>");
  $pdf->SetTitle(sprintf(__("Presupuesto para %s %s", "calc-pb"), $_REQUEST['fullname'], date("Y/m/d H:i")));
  $pdf->SetAuthor("URBAN MOBILITY BIKES");
  $file = __DIR__ .'/presupuesto-'.hash('ripemd160', date("YmdHis").rand(1, 1000000)).'.pdf';
  $pdf->Output($file,'F');
  return $file;
}

function calc_pb_create_clientify_contact ($email, $name, $phone) {
  $contact = new contactClientify($email, true); //Si no existe se crea
  $name = explode(' ', $name, 2);
  $contact->setFirstName($name[0]);
  $contact->setLastName($name[1]);
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

function calc_pb_generate_html($timetables, $sizes, $extras, $prices, $total) {
  $html = '<table class="presu" border="0" cellpadding="10" cellspacing="0" width="100%" style="font-family: Poppins;">
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
      <td>'.strip_tags($timetables[$_REQUEST['days']]).'</td>
      <td style="text-align: center;">'.$_REQUEST['bikes'].'</td>
      <td style="text-align: right;">'.number_format($prices[$_REQUEST['days']], 2, ",", ".")." €".'</td>
      <td style="text-align: right;">'.number_format(($_REQUEST['bikes'] * $prices[$_REQUEST['days']]), 2, ",", ".")." €".'</td>
    </tr>';

  $html .= '<tr>
      <td>'.strip_tags($sizes[$_REQUEST['size']]).'</td>
      <td style="text-align: center;">'.$_REQUEST['bikes'].'</td>
      <td style="text-align: right;">'.number_format($prices[$_REQUEST['size']], 2, ",", ".")." €".'</td>
      <td style="text-align: right;">'.number_format(($_REQUEST['bikes'] * $prices[$_REQUEST['size']]), 2, ",", ".")." €".'</td>
    </tr>';
  //Desactivamos el 15% de descuento por número de bicis
  /*if($_REQUEST['bikes'] > 1) {
    $html .= '<tr>
      <td>'.__("Descuento al contratar 2 o más bicis", "calc-pb").'</td>
      <td style="text-align: center;">1</td>
      <td style="text-align: right;">15%</td>
      <td style="text-align: right;">-'.number_format(($_REQUEST['bikes'] * $prices[$_REQUEST['days']] * 0.15), 2, ",", ".")." €".'</td>
    </tr>';
  }*/
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

  if($_REQUEST['state'] != 'BIZKAIA') {
    $html .= '<tr>
      <td>'.__("Incremento por campaña fuera de Bizkaia", "calc-pb").'</td>
      <td style="text-align: center;">1</td>
      <td style="text-align: right;">'.number_format($prices['transport'], 2, ",", ".")." €".'</td>
      <td style="text-align: right;">'.number_format($prices['transport'], 2, ",", ".")." €".'</td>
    </tr>';
  }

  $html .= '<tr>
        <th colspan="2">'.__("Total", "calc-pb").'</th>
        <td colspan="2" style="text-align: right;">'.number_format($total, 2, ",", ".")." €".'</td>
      </tr>
    </tbody>
  </table><style>table.presu { border-collapse: collapse;} table.presu td { border: 1px solid #000000; }</style>';
  return $html;
}
