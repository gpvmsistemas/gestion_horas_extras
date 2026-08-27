<?php
class PpeController {
 private $m; public function __construct(){if(!isStaffAdmin())redirect('login');$this->m=new HrSuite();}
 public function index(){require_capability('ppe.issue');$cid=requireAdminCompany('admin/dashboard');$this->view('admin/ppe/index',['items'=>$this->m->ppeItems($cid),'deliveries'=>$this->m->listPpe($cid),'users'=>(new User())->getUsersByCompany($cid),'branches'=>(new Company())->getBranches($cid,false)]);}
 public function saveItem(){require_capability('ppe.catalog','ppe/index');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('ppe/index');csrf_verify();$ok=$this->m->savePpeItem(adminCompanyId(),$_POST);$_SESSION[$ok?'flash_success':'flash_error']=$ok?'Artículo guardado.':'No se pudo guardar.';redirect('ppe/index');}
 public function addStock(){require_capability('ppe.catalog','ppe/index');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('ppe/index');csrf_verify();$ok=$this->m->addPpeStock(adminCompanyId(),(int)$_SESSION['user_id'],$_POST);$_SESSION[$ok?'flash_success':'flash_error']=$ok?'Ingreso de stock registrado.':'No se pudo ingresar stock.';redirect('ppe/index');}
 public function deliver(){require_capability('ppe.issue','ppe/index');if($_SERVER['REQUEST_METHOD']!=='POST')redirect('ppe/index');csrf_verify();$ok=$this->m->deliverPpe(adminCompanyId(),(int)$_SESSION['user_id'],$_POST);$_SESSION[$ok?'flash_success':'flash_error']=$ok?'Entrega registrada; queda pendiente de acuse.':'No se pudo registrar.';redirect('ppe/index');}
 private function view($v,$d){require APPROOT.'/views/'.$v.'.php';}
}
