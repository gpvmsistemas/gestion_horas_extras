<?php
class ExpirationsController {
 private $m; public function __construct(){if(!isStaffAdmin())redirect('login');$this->m=new HrSuite();}
 public function index(){require_capability('expirations.manage');$cid=requireAdminCompany('admin/dashboard');$this->view('admin/expirations/index',['rows'=>$this->m->expirations($cid),'types'=>$this->m->expirationTypes($cid),'users'=>(new User())->getUsersByCompany($cid),'branches'=>(new Company())->getBranches($cid,false)]);}
 public function save(){require_capability('expirations.manage');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('expirations/index');csrf_verify();$ok=$this->m->saveExpiration(adminCompanyId(),(int)$_SESSION['user_id'],$_POST);$_SESSION[$ok?'flash_success':'flash_error']=$ok?'Vencimiento registrado.':'No se pudo registrar.';redirect('expirations/index');}
 private function view($v,$d){require APPROOT.'/views/'.$v.'.php';}
}
