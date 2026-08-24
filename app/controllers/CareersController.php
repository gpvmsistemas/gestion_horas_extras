<?php
/**
 * Portal público de vacantes ("Trabajá con nosotros") — Suite P&M.
 *
 * Un portal por organización, cada uno con su marca (org_careers_brand):
 *   /careers            → redirige (o elige) según qué organizaciones tienen
 *                         el módulo habilitado (org_recruiting_groups)
 *   /careers/moderna    → búsquedas de Red Farmacias Moderna
 *   /careers/paviotti   → búsquedas de Grupo Paviotti · Ecofarma
 *   /careers/vacancy/{slug} · /careers/apply/{slug} · /careers/status/{token}
 *
 * El seguimiento por token sigue funcionando aunque la organización se
 * deshabilite después (el candidato ya postuló).
 */
class CareersController {
 private $m; public function __construct(){$this->m=new HrSuite();if(session_status()!==PHP_SESSION_ACTIVE)session_start();}
 public function index(){
  $groups=org_recruiting_groups();
  if(count($groups)===1)redirect('careers/'.$groups[0]);
  $this->view('careers/index',['groups'=>$groups]);
 }
 public function moderna(){$this->orgIndex('moderna');}
 public function paviotti(){$this->orgIndex('paviotti');}
 private function orgIndex($group){
  if(!org_recruiting_enabled($group)){http_response_code(404);exit('Portal no disponible.');}
  $this->view('careers/listing',['org'=>$group,'brand'=>org_careers_brand($group),'vacancies'=>$this->m->vacancies(0,true,$group)]);
 }
 public function vacancy($slug){
  $v=$this->m->vacancyBySlug($slug);
  if(!$v||!org_recruiting_enabled($v->org_group)){http_response_code(404);exit('Vacante no encontrada');}
  $_SESSION['career_challenge']=random_int(2,8);
  $this->view('careers/vacancy',['vacancy'=>$v,'org'=>$v->org_group,'brand'=>org_careers_brand($v->org_group),'challenge'=>$_SESSION['career_challenge']]);
 }
 public function apply($slug){
  if($_SERVER['REQUEST_METHOD']!=='POST')redirect('careers/vacancy/'.$slug); csrf_verify();
  $v=$this->m->vacancyBySlug($slug); if(!$v||!org_recruiting_enabled($v->org_group)||!empty($_POST['website'])){$this->fail(400,'Solicitud inválida');}
  $ip=$_SERVER['REMOTE_ADDR']??'unknown'; $window=date('Y-m-d H:i:00',time()-time()%600); $ipHash=hash('sha256',$ip.'|career');
  $this->m->execute('INSERT INTO career_rate_limits(ip_hash,window_start,request_count) VALUES(?,?,1) ON DUPLICATE KEY UPDATE request_count=request_count+1',[$ipHash,$window]);
  $limit=$this->m->one('SELECT request_count FROM career_rate_limits WHERE ip_hash=? AND window_start=?',[$ipHash,$window]); if((int)($limit->request_count??0)>5)$this->fail(429,'Demasiados intentos. Probá nuevamente en unos minutos.');
  if((int)($_POST['challenge']??-1)!==((int)($_SESSION['career_challenge']??-2)+3)){$_SESSION['flash_error']='Verificación incorrecta.';redirect('careers/vacancy/'.$slug);}
  if(empty($_FILES['cv'])){$_SESSION['flash_error']='Adjuntá tu CV.';redirect('careers/vacancy/'.$slug);}
  $valid=uploads_validate_uploaded_file($_FILES['cv'],['pdf','docx'],['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],5*1024*1024); if(!$valid['ok']){$_SESSION['flash_error']=$valid['message'];redirect('careers/vacancy/'.$slug);}
  if(!$this->virusScan($_FILES['cv']['tmp_name'])){$_SESSION['flash_error']='El archivo no superó el control de seguridad.';redirect('careers/vacancy/'.$slug);}
  $cons=$this->m->one('SELECT * FROM career_consents WHERE is_active=1 ORDER BY version_no DESC LIMIT 1'); if(!$cons||empty($_POST['consent'])){$_SESSION['flash_error']='Debés aceptar el consentimiento.';redirect('careers/vacancy/'.$slug);}
  $email=strtolower(trim($_POST['email']??'')); $name=trim($_POST['full_name']??''); if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name===''){$_SESSION['flash_error']='Completá nombre y email válidos.';redirect('careers/vacancy/'.$slug);}
  $token=bin2hex(random_bytes(24)); $candidate=$this->m->one('SELECT * FROM candidates WHERE email=? AND anonymized_at IS NULL ORDER BY id DESC LIMIT 1',[$email]);
  if(!$candidate){$this->m->execute('INSERT INTO candidates(email,full_name,phone,token_hash,retention_until) VALUES(?,?,?,?,DATE_ADD(CURDATE(),INTERVAL 24 MONTH))',[$email,$name,trim($_POST['phone']??'')?:null,hash('sha256',$token)]);$candidateId=$this->m->one('SELECT LAST_INSERT_ID() id')->id;}else{$candidateId=$candidate->id;$this->m->execute('UPDATE candidates SET retention_until=DATE_ADD(CURDATE(),INTERVAL 24 MONTH) WHERE id=?',[$candidateId]);}
  $dir=dirname(APPROOT).'/storage/private/cv/'.(int)$candidateId; if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
  $stored='cv_'.bin2hex(random_bytes(8)).'.'.$valid['ext']; $path=$dir.'/'.$stored; if(!move_uploaded_file($_FILES['cv']['tmp_name'],$path))throw new RuntimeException('No se pudo guardar el CV');
  try{$this->m->execute('INSERT INTO job_applications(vacancy_id,candidate_id,cv_path,cv_original_name,cv_sha256,tracking_token_hash,consent_id,consent_ip) VALUES(?,?,?,?,?,?,?,?)',[$v->id,$candidateId,'cv/'.(int)$candidateId.'/'.$stored,basename($_FILES['cv']['name']),hash_file('sha256',$path),hash('sha256',$token),$cons->id,$ip]);}
  catch(Throwable $e){@unlink($path);$_SESSION['flash_error']='Ya existe una postulación para esta vacante.';redirect('careers/vacancy/'.$slug);}
  $_SESSION['flash_success']='Postulación recibida. Guardá este enlace privado de seguimiento.'; $_SESSION['career_tracking_url']=URLROOT.'/careers/status/'.$token; redirect('careers/vacancy/'.$slug);
 }
 public function status($token){$hash=hash('sha256',(string)$token);$a=$this->m->one('SELECT ja.current_stage,ja.status,ja.created_at,jv.title,c.name company_name,c.organization_group org_group FROM job_applications ja JOIN job_vacancies jv ON jv.id=ja.vacancy_id JOIN companies c ON c.id=jv.company_id WHERE ja.tracking_token_hash=?',[$hash]);if(!$a){http_response_code(404);exit('Enlace de seguimiento inválido.');}$this->view('careers/status',['application'=>$a,'org'=>$a->org_group,'brand'=>org_careers_brand($a->org_group)]);}
 private function virusScan($path){$bin=defined('CLAMSCAN_BIN')?CLAMSCAN_BIN:'';if($bin==='')return true;$out=[];$code=2;@exec(escapeshellarg($bin).' --no-summary '.escapeshellarg($path),$out,$code);return $code===0;}
 private function fail($code,$message){http_response_code($code);exit(htmlspecialchars($message,ENT_QUOTES,'UTF-8'));}
 private function view($v,$d){require APPROOT.'/views/'.$v.'.php';}
}
