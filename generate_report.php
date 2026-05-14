<?php

require('fpdf/fpdf.php');

$pdf = new FPDF();

$pdf->AddPage();

$pdf->SetFont('Arial','B',16);

$pdf->Cell(40,10,'EchoShield AI Report');

$pdf->Ln(20);

$pdf->SetFont('Arial','',12);

$pdf->Cell(40,10,'Cyberbullying Evidence Generated');

$pdf->Output();

?>