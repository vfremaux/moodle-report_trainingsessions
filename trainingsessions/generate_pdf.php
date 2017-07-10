<?php
/**
 * Course trainingsessions report. Gives a transversal view of all courses for a user.
 * this script is used as inclusion of the index.php file.
 *
 * @package    report_trainingsessions
 * @category   report
 * @copyright UPMC 2017
 */

if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

if ( $_SERVER['REQUEST_METHOD'] == 'POST') {
    $_SESSION['printtopdf'] = $_POST['result'];
    $_SESSION['coursename'] = $_POST['coursename'];
    $_SESSION['view'] = $_POST['view'];
} else if(!isset($_SESSION['printtopdf']) ) {
    die("You cannot access this page directly");
}

require __DIR__.'/html2pdf/vendor/autoload.php';

use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Spipu\Html2Pdf\Exception\ExceptionFormatter;

$content = '<page backtop="7mm" backbottom="7mm" backleft="10mm" backright="10mm">';
$content .= '<page_header style="text-align: right"><img src="pix/logoupmc.png" alt="Logo UPMC" width="200"/></page_header>';
if($_SESSION['view']=='userdetail') $content .= '<h1>Détails de l\'étudiant :</h1>';
else $content .= '<h1>Liste des étudiants :</h1>';
$content .= '<h2>'.$_SESSION['coursename'].'</h2>';
$content .= $_SESSION['printtopdf'];
$content .= '<page_footer style="text-align: right"></page_footer>';
$content .= '</page>';


try {
    $html2pdf = new Html2Pdf();
    //$html2pdf->writeHTML('<img src="pix/logoupmc.png" alt="Logo UPMC" width="200"/>');
    $html2pdf->writeHTML($content);
    $html2pdf->output();
} catch (Html2PdfException $e) {
    $formatter = new ExceptionFormatter($e);
    echo $formatter->getHtmlMessage();
    echo $_POST['result'];
}

 ?>
