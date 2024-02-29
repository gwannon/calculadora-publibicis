<?php

use Gwannon\PHPClientifyAPI\contactClientify;
use Mpdf\Mpdf;

/* Libs */
function calc_pb_generate_pdf ($html) {
  $pdf = new Mpdf();
  $pagecount = $pdf->SetSourceFile(get_attached_file(get_option('_calc_pb_dossier_pdf_id')));
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

function calc_pb_generate_html($timetables, $sizes, $extras, $prices, $total) {
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
  return $html;
}
