<?php
/**
 * Portal público de vacantes ("Trabajá con nosotros") — Suite P&M.
 *
 * Un portal por organización, cada uno con su marca (org_careers_brand):
 *   /careers                  → redirige (o elige) según organizaciones habilitadas
 *   /careers/moderna|paviotti → búsquedas abiertas de esa organización
 *   /careers/unete/{org}      → postulación espontánea (port del /unete del
 *                               sistema viejo de Moderna): sin vacante puntual,
 *                               con ciudad y área de interés
 *   /careers/vacancy/{slug} · /careers/apply/{slug} · /careers/status/{token}
 *
 * La postulación espontánea se materializa contra una vacante especial por
 * organización (slug espontanea-{org}) excluida del listado público; RRHH
 * puede pausarla desde el panel para cerrar la recepción espontánea.
 * El seguimiento por token sigue funcionando aunque la organización se
 * deshabilite después.
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
 /** Postulación espontánea, estilo /unete del sistema viejo de Moderna. */
 public function unete($group=''){
  if(!in_array($group,org_valid_groups(),true)||!org_recruiting_enabled($group)){http_response_code(404);exit('Portal no disponible.');}
  $v=$this->spontaneousVacancy($group);
  $abierta=$v&&$v->status==='published';
  if($_SERVER['REQUEST_METHOD']==='POST'&&$abierta){
   csrf_verify();
   $ciudades=array_keys(org_branches_by_city($group));
   $ciudad=trim($_POST['ciudad']??''); if(!in_array($ciudad,$ciudades,true)){$_SESSION['flash_error']='Elegí una ciudad de la lista.';redirect('careers/unete/'.$group);}
   $area=trim(mb_substr($_POST['area_interes']??'',0,120));
   $nota='Postulación espontánea · Ciudad: '.$ciudad.($area!==''?' · Área de interés: '.$area:'');
   $this->processApplication($v,'careers/unete/'.$group,$nota);
  }
  $_SESSION['career_challenge']=random_int(2,8);
  $this->view('careers/unete',['org'=>$group,'brand'=>org_careers_brand($group),'abierta'=>$abierta,'ciudades'=>array_keys(org_branches_by_city($group)),'challenge'=>$_SESSION['career_challenge']]);
 }
 /** Vacante contenedora de las postulaciones espontáneas (una por organización). */
 private function spontaneousVacancy($group){
  $v=$this->m->vacancyBySlug('espontanea-'.$group,true);
  if($v)return $v;
  $companies=org_group_company_ids($group); if(!$companies)return null;
  $admin=$this->m->one("SELECT id FROM users WHERE role='admin' ORDER BY id ASC LIMIT 1"); if(!$admin)return null;
  $this->m->execute('INSERT INTO job_vacancies(company_id,title,slug,description,requirements_json,pipeline_json,status,published_at,created_by) VALUES(?,?,?,?,?,?,?,NOW(),?)',[
   $companies[0],'Postulación espontánea','espontanea-'.$group,
   'Dejanos tu CV aunque no haya una búsqueda puntual abierta: lo tenemos en cuenta para las próximas incorporaciones.',
   json_encode([],JSON_UNESCAPED_UNICODE),json_encode(org_default_pipeline(),JSON_UNESCAPED_UNICODE),'published',(int)$admin->id]);
  return $this->m->vacancyBySlug('espontanea-'.$group,true);
 }
 public function vacancy($slug){
  $v=$this->m->vacancyBySlug($slug);
  if(!$v||!org_recruiting_enabled($v->org_group)){http_response_code(404);exit('Vacante no encontrada');}
  if(strpos($v->slug,'espontanea-')===0)redirect('careers/unete/'.$v->org_group);
  $_SESSION['career_challenge']=random_int(2,8);
  $this->view('careers/vacancy',['vacancy'=>$v,'org'=>$v->org_group,'brand'=>org_careers_brand($v->org_group),'challenge'=>$_SESSION['career_challenge']]);
 }
 public function apply($slug){
  if($_SERVER['REQUEST_METHOD']!=='POST')redirect('careers/vacancy/'.$slug); csrf_verify();
  $v=$this->m->vacancyBySlug($slug); if(!$v||!org_recruiting_enabled($v->org_group)||!empty($_POST['website'])){$this->fail(400,'Solicitud inválida');}
  $this->processApplication($v,'careers/vacancy/'.$slug);
 }
 /**
  * Núcleo compartido de postulación (apply puntual y unete espontánea):
  * rate limit por IP, captcha de sesión, CV validado (MIME real, 5MB,
  * antivirus opcional), consentimiento versionado, candidato deduplicado
  * por email, CV en storage privado y token de seguimiento de un solo uso.
  */
 private function processApplication($v,$redirectTo,$eventNote=''){
  if(!empty($_POST['website']))$this->fail(400,'Solicitud inválida');
  $ip=$_SERVER['REMOTE_ADDR']??'unknown'; $window=date('Y-m-d H:i:00',time()-time()%600); $ipHash=hash('sha256',$ip.'|career');
  $this->m->execute('INSERT INTO career_rate_limits(ip_hash,window_start,request_count) VALUES(?,?,1) ON DUPLICATE KEY UPDATE request_count=request_count+1',[$ipHash,$window]);
  $limit=$this->m->one('SELECT request_count FROM career_rate_limits WHERE ip_hash=? AND window_start=?',[$ipHash,$window]); if((int)($limit->request_count??0)>5)$this->fail(429,'Demasiados intentos. Probá nuevamente en unos minutos.');
  if((int)($_POST['challenge']??-1)!==((int)($_SESSION['career_challenge']??-2)+3)){$_SESSION['flash_error']='Verificación incorrecta.';redirect($redirectTo);}
  if(empty($_FILES['cv'])){$_SESSION['flash_error']='Adjuntá tu CV.';redirect($redirectTo);}
  $valid=uploads_validate_uploaded_file($_FILES['cv'],['pdf','docx'],['application/pdf','application/vnd.openxmlformats-officedocument.wordprocessingml.document'],5*1024*1024); if(!$valid['ok']){$_SESSION['flash_error']=$valid['message'];redirect($redirectTo);}
  if(!$this->virusScan($_FILES['cv']['tmp_name'])){$_SESSION['flash_error']='El archivo no superó el control de seguridad.';redirect($redirectTo);}
  $cons=$this->m->one('SELECT * FROM career_consents WHERE is_active=1 ORDER BY version_no DESC LIMIT 1'); if(!$cons||empty($_POST['consent'])){$_SESSION['flash_error']='Debés aceptar el consentimiento.';redirect($redirectTo);}
  $email=strtolower(trim($_POST['email']??'')); $name=trim($_POST['full_name']??''); if(!filter_var($email,FILTER_VALIDATE_EMAIL)||$name===''){$_SESSION['flash_error']='Completá nombre y email válidos.';redirect($redirectTo);}
  $token=bin2hex(random_bytes(24)); $candidate=$this->m->one('SELECT * FROM candidates WHERE email=? AND anonymized_at IS NULL ORDER BY id DESC LIMIT 1',[$email]);
  if(!$candidate){$this->m->execute('INSERT INTO candidates(email,full_name,phone,token_hash,retention_until) VALUES(?,?,?,?,DATE_ADD(CURDATE(),INTERVAL 24 MONTH))',[$email,$name,trim($_POST['phone']??'')?:null,hash('sha256',$token)]);$candidateId=$this->m->one('SELECT LAST_INSERT_ID() id')->id;}else{$candidateId=$candidate->id;$this->m->execute('UPDATE candidates SET retention_until=DATE_ADD(CURDATE(),INTERVAL 24 MONTH) WHERE id=?',[$candidateId]);}
  $dir=dirname(APPROOT).'/storage/private/cv/'.(int)$candidateId; if(!is_dir($dir)&&!mkdir($dir,0750,true))throw new RuntimeException('No se pudo preparar el almacenamiento privado.');
  $stored='cv_'.bin2hex(random_bytes(8)).'.'.$valid['ext']; $path=$dir.'/'.$stored; if(!move_uploaded_file($_FILES['cv']['tmp_name'],$path))throw new RuntimeException('No se pudo guardar el CV');
  $firstStage=(json_decode($v->pipeline_json??'',true)?:org_default_pipeline())[0]??'nuevo';
  try{$this->m->execute('INSERT INTO job_applications(vacancy_id,candidate_id,current_stage,cv_path,cv_original_name,cv_sha256,tracking_token_hash,consent_id,consent_ip) VALUES(?,?,?,?,?,?,?,?,?)',[$v->id,$candidateId,$firstStage,'cv/'.(int)$candidateId.'/'.$stored,basename($_FILES['cv']['name']),hash_file('sha256',$path),hash('sha256',$token),$cons->id,$ip]);}
  catch(Throwable $e){
   // Duplicado (misma vacante + candidato): respuesta indistinguible del alta
   // para no revelar a terceros si un email ya postuló; sin nuevo enlace de
   // seguimiento (el original sigue vigente).
   @unlink($path);$_SESSION['flash_success']='Postulación recibida. Si ya te habías postulado, RRHH la tiene en cuenta con tu envío anterior.';redirect($redirectTo);
  }
  $appId=(int)($this->m->one('SELECT LAST_INSERT_ID() id')->id??0);
  if($eventNote!==''&&$appId){$this->m->execute('INSERT INTO application_events(application_id,event_type,from_stage,to_stage,notes) VALUES(?,?,NULL,?,?)',[$appId,'application_received',$v->pipeline_json?(json_decode($v->pipeline_json,true)[0]??'nuevo'):'nuevo',$eventNote]);}
  $_SESSION['flash_success']='Postulación recibida. Guardá este enlace privado de seguimiento.'; $_SESSION['career_tracking_url']=URLROOT.'/careers/status/'.$token; redirect($redirectTo);
 }
 public function status($token){$hash=hash('sha256',(string)$token);$a=$this->m->one('SELECT ja.current_stage,ja.status,ja.created_at,jv.title,c.name company_name,c.organization_group org_group FROM job_applications ja JOIN job_vacancies jv ON jv.id=ja.vacancy_id JOIN companies c ON c.id=jv.company_id WHERE ja.tracking_token_hash=?',[$hash]);if(!$a){http_response_code(404);exit('Enlace de seguimiento inválido.');}$this->view('careers/status',['application'=>$a,'org'=>$a->org_group,'brand'=>org_careers_brand($a->org_group)]);}
 private function virusScan($path){$bin=defined('CLAMSCAN_BIN')?CLAMSCAN_BIN:'';if($bin==='')return true;$out=[];$code=2;@exec(escapeshellarg($bin).' --no-summary '.escapeshellarg($path),$out,$code);return $code===0;}
 private function fail($code,$message){http_response_code($code);exit(htmlspecialchars($message,ENT_QUOTES,'UTF-8'));}
 private function view($v,$d){require APPROOT.'/views/'.$v.'.php';}
}
