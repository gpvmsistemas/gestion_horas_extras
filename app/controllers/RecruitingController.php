<?php
/**
 * ATS admin (RRHH Integral → Reclutamiento) — panel centrado en CANDIDATOS,
 * port del panel del sistema viejo de Moderna sobre el motor de la suite:
 * KPIs, filtros server-side con paginación, cambio de estado directo por
 * fila, ficha del candidato, y ABM de vacantes con ciclo de vida completo.
 *
 * Alcance ORGANIZACIONAL (como Registro de Horas): RRHH ve las postulaciones
 * de todas las sociedades de su grupo. Piloto por org: org_recruiting_enabled.
 */
class RecruitingController {
 private $m;public function __construct(){if(!isStaffAdmin())redirect('login');$this->m=new HrSuite();}
 /**
  * Contexto validado: empresa activa + candado organizacional (la empresa
  * activa DEBE ser del grupo del usuario — defensa en profundidad contra
  * caminos que fijen la empresa sin pasar por setAdminActiveCompany) +
  * módulo habilitado. Devuelve [companyId, group, companyIdsDelGrupo].
  */
 private function gate(){
  $cid=requireAdminCompany();
  $group=org_group_of_company($cid);
  $locked=org_locked_group();
  if($locked!==''&&$group!==$locked){$_SESSION['flash_error']='La empresa activa no pertenece a tu organización.';redirect('admin/dashboard');}
  if(!org_recruiting_enabled($group)){$_SESSION['flash_error']='El módulo de Reclutamiento no está habilitado para esta organización todavía.';redirect('admin/dashboard');}
  $ids=org_group_company_ids($group);
  return [$cid,$group,$ids?:[$cid]];
 }
 /** Redirección conservando los filtros del panel (whitelist). */
 private function backToPanel(){
  parse_str((string)($_POST['back_qs']??''),$qs);
  $keep=array_intersect_key($qs,array_flip(['q','estado','vacante_id','desde','hasta','page','vacancy_id']));
  redirect('recruiting/index'.($keep?'?'.http_build_query($keep):''));
 }
 public function index(){require_capability('recruiting.review');[$cid,$group,$ids]=$this->gate();
  $filters=['q'=>trim($_GET['q']??''),'estado'=>trim($_GET['estado']??''),'vacante_id'=>(int)($_GET['vacante_id']??0),'desde'=>trim($_GET['desde']??''),'hasta'=>trim($_GET['hasta']??'')];
  $selected=(int)($_GET['vacancy_id']??0);
  $vacancy=$selected?$this->m->vacancyByIdIn($selected,$ids):null;
  $vacancies=$this->m->vacanciesForCompanies($ids);
  // Opciones del filtro de estado: solo las etapas que EXISTEN en los
  // pipelines de las búsquedas de esta organización (sin restos legacy).
  $estadoOpts=[];
  foreach($vacancies as $v){foreach(json_decode($v->pipeline_json??'',true)?:[] as $s){$estadoOpts[$s]=true;}}
  $estadoOpts=array_keys($estadoOpts)?:org_default_pipeline();
  $this->view('admin/recruiting/index',[
   'kpis'=>$this->m->recruitingKpis($ids),
   'vacancies'=>$vacancies,
   'estado_opts'=>$estadoOpts,
   'vacancy'=>$vacancy,
   'selected'=>$vacancy?(int)$vacancy->id:0,
   'applications'=>$this->m->applicationsOrg($ids,$filters,(int)($_GET['page']??1)),
   'filters'=>$filters,
   'org'=>$group,
   'companies'=>$this->m->query('SELECT id,name FROM companies WHERE id IN ('.implode(',',array_map('intval',$ids)).') ORDER BY name'),
   'positions'=>$vacancy?$this->m->positionsForCompany((int)$vacancy->company_id):[],
   'branches'=>$vacancy?$this->m->branchesForCompany((int)$vacancy->company_id):[],
  ]);}
 /** Campos comunes de alta/edición, saneados y validados contra la empresa. */
 private function vacancyInput($companyId){
  $criteria=array_values(array_filter(array_map('trim',preg_split('/\r?\n/',$_POST['criteria']??''))));
  $pipeline=array_values(array_unique(array_filter(array_map(fn($s)=>preg_replace('/[^a-z0-9_áéíóúñ-]/iu','_',trim($s)),preg_split('/\r?\n/',$_POST['pipeline']??'')))));
  if(!$pipeline)$pipeline=org_default_pipeline();
  $branch=(int)($_POST['branch_id']??0);if($branch&&!$this->m->one('SELECT id FROM company_branches WHERE id=? AND company_id=?',[$branch,$companyId]))$branch=0;
  $position=(int)($_POST['position_id']??0);if($position&&!$this->m->one('SELECT id FROM job_positions WHERE id=? AND company_id=?',[$position,$companyId]))$position=0;
  $closes=trim($_POST['closes_at']??'');if($closes!==''&&!preg_match('/^\d{4}-\d{2}-\d{2}$/',$closes))$closes='';
  return ['title'=>trim($_POST['title']??''),'description'=>trim($_POST['description']??''),'criteria'=>$criteria,'pipeline'=>$pipeline,'branch_id'=>$branch?:null,'position_id'=>$position?:null,'closes_at'=>$closes?:null];
 }
 public function saveVacancy(){require_capability('recruiting.publish');[$cid,$group,$ids]=$this->gate();if($_SERVER['REQUEST_METHOD']!=='POST')redirect('recruiting/index');csrf_verify();
  $company=(int)($_POST['company_id']??$cid);if(!in_array($company,array_map('intval',$ids),true))$company=$cid;
  $in=$this->vacancyInput($company);if($in['title']===''){$_SESSION['flash_error']='Indicá el título.';redirect('recruiting/index');}
  $slug=strtolower(trim(preg_replace('/[^a-z0-9]+/i','-',$in['title']),'-')).'-'.substr(bin2hex(random_bytes(3)),0,6);
  $status=($_POST['status']??'draft')==='published'?'published':'draft';
  $ok=$this->m->execute('INSERT INTO job_vacancies(company_id,branch_id,position_id,title,slug,description,requirements_json,pipeline_json,status,published_at,closes_at,created_by) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$company,$in['branch_id'],$in['position_id'],$in['title'],$slug,$in['description'],json_encode($in['criteria'],JSON_UNESCAPED_UNICODE),json_encode($in['pipeline'],JSON_UNESCAPED_UNICODE),$status,$status==='published'?date('Y-m-d H:i:s'):null,$in['closes_at'],(int)$_SESSION['user_id']]);
  if($ok)$this->m->audit()->record('recruiting.vacancy.created','job_vacancy',$this->m->one('SELECT LAST_INSERT_ID() id')->id,null,['title'=>$in['title'],'company_id'=>$company,'criteria'=>$in['criteria'],'pipeline'=>$in['pipeline']],'',$company);
  redirect('recruiting/index');}
 /** Ciclo de vida completo: editar campos y pasar por draft/published/paused/closed. */
 public function updateVacancy($id){require_capability('recruiting.publish');[$cid,$group,$ids]=$this->gate();if($_SERVER['REQUEST_METHOD']!=='POST')redirect('recruiting/index');csrf_verify();
  $v=$this->m->vacancyByIdIn($id,$ids);if(!$v){$_SESSION['flash_error']='Vacante inexistente.';redirect('recruiting/index');}
  $in=$this->vacancyInput((int)$v->company_id);if($in['title']===''){$_SESSION['flash_error']='Indicá el título.';redirect('recruiting/index?vacancy_id='.(int)$v->id);}
  $status=in_array($_POST['status']??'',['draft','published','paused','closed'],true)?$_POST['status']:$v->status;
  $publishedAt=$v->published_at;if($status==='published'&&!$publishedAt)$publishedAt=date('Y-m-d H:i:s');
  $ok=$this->m->execute('UPDATE job_vacancies SET title=?,description=?,requirements_json=?,pipeline_json=?,branch_id=?,position_id=?,closes_at=?,status=?,published_at=? WHERE id=?',[$in['title'],$in['description'],json_encode($in['criteria'],JSON_UNESCAPED_UNICODE),json_encode($in['pipeline'],JSON_UNESCAPED_UNICODE),$in['branch_id'],$in['position_id'],$in['closes_at'],$status,$publishedAt,(int)$v->id]);
  if($ok){$this->m->audit()->record('recruiting.vacancy.updated','job_vacancy',(int)$v->id,['status'=>$v->status,'title'=>$v->title,'closes_at'=>$v->closes_at],['status'=>$status,'title'=>$in['title'],'closes_at'=>$in['closes_at']],'',(int)$v->company_id);$_SESSION['flash_success']='Vacante actualizada.';}
  redirect('recruiting/index?vacancy_id='.(int)$v->id);}
 public function move($id){require_capability('recruiting.review');[$cid,$group,$ids]=$this->gate();if($_SERVER['REQUEST_METHOD']!=='POST')redirect('recruiting/index');csrf_verify();
  $app=$this->application($id,$ids);
  if(!$app)$this->backToPanel();
  $pipeline=json_decode($app->pipeline_json??'',true)?:org_default_pipeline();
  $stage=(string)($_POST['stage']??'');
  if(!in_array($stage,$pipeline,true)&&$stage!==$app->current_stage){$_SESSION['flash_error']='Etapa inválida: no pertenece al pipeline de esta vacante.';$this->backToPanel();}
  if($stage!==$app->current_stage){
   $status=in_array($stage,['hired','contratado'],true)?'hired':(in_array($stage,['rejected','rechazado'],true)?'rejected':'active');
   $this->m->execute('UPDATE job_applications SET current_stage=?,status=? WHERE id=?',[$stage,$status,$id]);
   $this->m->execute('INSERT INTO application_events(application_id,event_type,from_stage,to_stage,notes,actor_user_id) VALUES(?,?,?,?,?,?)',[$id,'stage_changed',$app->current_stage,$stage,trim($_POST['notes']??'')?:null,(int)$_SESSION['user_id']]);
   $this->m->audit()->record('recruiting.stage.changed','job_application',$id,['stage'=>$app->current_stage],['stage'=>$stage],trim($_POST['notes']??''),(int)$app->company_id);
  }
  $this->backToPanel();}
 /** Ficha del candidato (modal): datos, historial de etapas y resultado IA. */
 public function candidate($id){require_capability('recruiting.review');[$cid,$group,$ids]=$this->gate();
  $a=$this->application($id,$ids);if(!$a){http_response_code(404);header('Content-Type: application/json');exit(json_encode(['ok'=>false]));}
  $c=$this->m->one('SELECT c.full_name,c.email,c.phone,c.created_at,c.retention_until FROM candidates c JOIN job_applications ja ON ja.candidate_id=c.id WHERE ja.id=?',[(int)$id]);
  $events=$this->m->query('SELECT ae.event_type,ae.from_stage,ae.to_stage,ae.notes,ae.created_at,u.full_name actor FROM application_events ae LEFT JOIN users u ON u.id=ae.actor_user_id WHERE ae.application_id=? ORDER BY ae.created_at DESC',[(int)$id]);
  $in=implode(',',array_map('intval',$ids));
  $otras=$this->m->query("SELECT jv.title,ja2.current_stage,ja2.status,ja2.created_at FROM job_applications ja2 JOIN job_vacancies jv ON jv.id=ja2.vacancy_id WHERE ja2.candidate_id=(SELECT candidate_id FROM job_applications WHERE id=?) AND ja2.id<>? AND jv.company_id IN ($in) ORDER BY ja2.created_at DESC",[(int)$id,(int)$id]);
  $this->m->audit()->record('recruiting.candidate.viewed','job_application',(int)$id,null,['candidate_email'=>$c->email??''],'Ficha de candidato consultada',(int)$a->company_id);
  header('Content-Type: application/json');
  exit(json_encode(['ok'=>true,'candidate'=>$c,'application'=>['current_stage'=>$a->current_stage,'status'=>$a->status,'created_at'=>$a->created_at,'cv_original_name'=>$a->cv_original_name,'ai_score'=>$a->ai_score,'ai_model'=>$a->ai_model,'ai_scored_at'=>$a->ai_scored_at,'ai_result'=>json_decode($a->ai_result_json??'',true)],'events'=>$events,'other_applications'=>$otras],JSON_UNESCAPED_UNICODE));}
 public function score($id){require_capability('recruiting.ai');[$cid,$group,$ids]=$this->gate();if($_SERVER['REQUEST_METHOD']!=='POST')redirect('recruiting/index');csrf_verify();$app=$this->application($id,$ids);if(!$app)$this->backToPanel();$path=dirname(APPROOT).'/storage/private/'.$app->cv_path;$text=$this->extractText($path);if(trim($text)===''){$_SESSION['flash_error']='No se pudo extraer texto del CV. Podés continuar con revisión manual.';$this->backToPanel();}$svc=new OpenAiCvService();$result=$svc->analyze($text,json_decode($app->requirements_json,true)?:[]);if(!$result['ok']){$_SESSION['flash_error']=$result['message'];}else{$r=$result['result'];$this->m->execute('UPDATE job_applications SET ai_score=?,ai_result_json=?,ai_model=?,ai_criteria_hash=?,ai_scored_at=NOW() WHERE id=?',[$r['score'],json_encode($r,JSON_UNESCAPED_UNICODE),$result['model'],hash('sha256',$app->requirements_json),$id]);$this->m->audit()->record('recruiting.ai.scored','job_application',$id,null,['score'=>$r['score'],'model'=>$result['model'],'criteria_hash'=>hash('sha256',$app->requirements_json)],'Revisión humana obligatoria',(int)$app->company_id);$_SESSION['flash_success']='Ranking IA actualizado; requiere revisión humana.';}$this->backToPanel();}
 public function downloadCv($id){require_capability('recruiting.review');[$cid,$group,$ids]=$this->gate();$a=$this->application($id,$ids);if(!$a){http_response_code(404);exit;}$abs=dirname(APPROOT).'/storage/private/'.$a->cv_path;if(!is_file($abs)){http_response_code(404);exit;}$this->m->audit()->record('recruiting.cv.downloaded','job_application',(int)$id,null,['cv'=>$a->cv_original_name],'Descarga de CV',(int)$a->company_id);header('Content-Type: '.(mime_content_type($abs)?:'application/octet-stream'));header('Content-Disposition: attachment; filename="'.str_replace('"','',basename($a->cv_original_name)).'"');header('X-Content-Type-Options: nosniff');readfile($abs);exit;}
 public function onboard($id){require_capability('recruiting.review');[$cid,$group,$ids]=$this->gate();if($_SERVER['REQUEST_METHOD']!=='POST')redirect('recruiting/index');csrf_verify();$a=$this->application($id,$ids);if(!$a)$this->backToPanel();$candidate=$this->m->one('SELECT c.* FROM candidates c JOIN job_applications ja ON ja.candidate_id=c.id WHERE ja.id=?',[(int)$id]);$duplicate=$this->m->one('SELECT id FROM users WHERE email=? LIMIT 1',[$candidate->email]);if($duplicate){$_SESSION['flash_error']='Existe un usuario con ese email. RRHH debe revisar el posible duplicado.';$this->backToPanel();}$username='pre'.(int)$candidate->id.'_'.substr(bin2hex(random_bytes(3)),0,6);$password=password_hash(bin2hex(random_bytes(16)),PASSWORD_DEFAULT);$this->m->execute("INSERT INTO users(username,password,full_name,email,phone_number,role,company_id,branch_id,is_active,employment_status) VALUES(?,?,?,?,?,'empleado',?,?,0,'preingreso')",[$username,$password,$candidate->full_name,$candidate->email,$candidate->phone,$a->company_id,$a->branch_id?:null]);$uid=$this->m->one('SELECT LAST_INSERT_ID() id')->id;$this->m->execute("INSERT INTO onboarding_checklists(user_id,company_id,source_application_id,status,created_by) VALUES(?,?,?,'active',?)",[$uid,$a->company_id,$id,(int)$_SESSION['user_id']]);$check=$this->m->one('SELECT LAST_INSERT_ID() id')->id;foreach(['personal_data'=>'Datos personales y domicilio','documents'=>'Documentación y cobertura médica','account'=>'Cuenta y accesos','position'=>'Puesto, área y sucursal','ppe'=>'EPP y talles','assets'=>'Activos asignados','training'=>'Capacitación inicial','policies'=>'Acuse de políticas'] as $key=>$label)$this->m->execute('INSERT INTO onboarding_tasks(checklist_id,task_key,label) VALUES(?,?,?)',[$check,$key,$label]);$this->m->execute("UPDATE job_applications SET status='hired',current_stage=? WHERE id=?",[in_array('contratado',json_decode($a->pipeline_json??'',true)?:[],true)?'contratado':'hired',$id]);$this->m->audit()->record('recruiting.onboarding.created','user',$uid,null,['application_id'=>$id,'status'=>'preingreso'],'',(int)$a->company_id);$_SESSION['flash_success']='Preingreso creado con checklist. La cuenta queda inactiva hasta completar la revisión.';redirect('admin/employeeProfile/'.$uid);}
 private function application($id,array $companyIds){$a=$this->m->one('SELECT ja.*,jv.requirements_json,jv.pipeline_json,jv.company_id,jv.branch_id FROM job_applications ja JOIN job_vacancies jv ON jv.id=ja.vacancy_id WHERE ja.id=?',[(int)$id]);return $a&&in_array((int)$a->company_id,array_map('intval',$companyIds),true)?$a:null;}
 private function extractText($path){if(strtolower(pathinfo($path,PATHINFO_EXTENSION))==='docx'&&class_exists('ZipArchive')){$z=new ZipArchive();if($z->open($path)===true){$xml=$z->getFromName('word/document.xml');$z->close();return trim(strip_tags(str_replace(['</w:p>','</w:tr>'],["\n","\n"],$xml)));}}$bin=defined('PDFTOTEXT_BIN')?PDFTOTEXT_BIN:'pdftotext';$tmp=tempnam(sys_get_temp_dir(),'cvtxt');$cmd=escapeshellarg($bin).' -layout '.escapeshellarg($path).' '.escapeshellarg($tmp);@exec($cmd,$o,$code);$text=$code===0&&is_file($tmp)?file_get_contents($tmp):'';@unlink($tmp);return $text;}
 private function view($v,$d){require APPROOT.'/views/'.$v.'.php';}
}
