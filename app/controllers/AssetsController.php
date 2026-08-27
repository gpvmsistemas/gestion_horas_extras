<?php
class AssetsController {
 private $m; public function __construct(){if(!isStaffAdmin())redirect('login');$this->m=new HrSuite();}
 public function index(){require_capability('assets.assign');$cid=requireAdminCompany('admin/dashboard');$this->view('admin/assets/index',['assets'=>$this->m->assets($cid),'users'=>(new User())->getUsersByCompany($cid),'branches'=>(new Company())->getBranches($cid,false)]);}
 public function save(){require_capability('assets.catalog','assets/index');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('assets/index');csrf_verify();$ok=$this->m->saveAsset(adminCompanyId(),(int)$_SESSION['user_id'],$_POST);$_SESSION[$ok?'flash_success':'flash_error']=$ok?'Activo incorporado.':'No se pudo incorporar.';redirect('assets/index');}
 public function move($id){require_capability('assets.assign','assets/index');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('assets/index');csrf_verify();$type=$_POST['movement_type']??'';if(in_array($type,['maintenance','repair','lost','damaged','retire'],true))require_capability('assets.maintain','assets/index');$ok=$this->m->moveAsset(adminCompanyId(),(int)$_SESSION['user_id'],(int)$id,$type,(int)($_POST['to_user_id']??0),trim($_POST['notes']??''));$_SESSION[$ok?'flash_success':'flash_error']=$ok?'Movimiento registrado.':'Movimiento inválido.';redirect('assets/index');}
 private function view($v,$d){require APPROOT.'/views/'.$v.'.php';}
}
