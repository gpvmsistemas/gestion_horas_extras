<?php
require dirname(__DIR__).'/app/services/SimplePdfService.php';
$pdf=(new SimplePdfService())->build(['Novedades mensuales','Periodo: 2026-08','Version: 1','Empleado | Dias | Tardanzas | Ausencias | HE 50 | HE 100','Persona de prueba | 22 | 1 | 0 | 2.5 | 0']);
$dir=dirname(__DIR__).'/output/pdf';if(!is_dir($dir))mkdir($dir,0755,true);file_put_contents($dir.'/attendance_closure_sample.pdf',$pdf);
if(substr($pdf,0,8)!=="%PDF-1.4"||strpos($pdf,'startxref')===false)exit(1);echo "PDF generado\n";
