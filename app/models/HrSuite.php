<?php
class HrSuite {
    private $db; private $audit;
    public function __construct($db=null){$this->db=$db instanceof Database?$db:new Database();$this->audit=new AuditService($this->db);}
    public function ready(){try{$this->db->query("SHOW TABLES LIKE 'audit_events'");return(bool)$this->db->single();}catch(Throwable $e){return false;}}
    public function query($sql,$params=[]){$this->db->query($sql);return $this->db->resultSet($params);}
    public function one($sql,$params=[]){$this->db->query($sql);return $this->db->single($params);}
    public function execute($sql,$params=[]){$this->db->query($sql);return $this->db->execute($params);}
    public function dashboardCounts($companyId){
        $out=[];$defs=['ppe_due'=>"SELECT COUNT(*) n FROM ppe_deliveries WHERE company_id=? AND status IN ('issued','acknowledged') AND next_replacement_on<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)",'assets_assigned'=>"SELECT COUNT(*) n FROM assets WHERE company_id=? AND status='assigned'",'expirations_due'=>"SELECT COUNT(*) n FROM employee_expirations WHERE company_id=? AND status='active' AND expires_on<=DATE_ADD(CURDATE(),INTERVAL 30 DAY)",'applications'=>"SELECT COUNT(*) n FROM job_applications ja JOIN job_vacancies jv ON jv.id=ja.vacancy_id WHERE jv.company_id=? AND ja.status='active'",'reviews_pending'=>"SELECT COUNT(*) n FROM performance_reviews pr JOIN performance_cycles pc ON pc.id=pr.cycle_id WHERE pc.company_id=? AND pr.status='pending'"];
        foreach($defs as $k=>$sql){$r=$this->one($sql,[$companyId]);$out[$k]=(int)($r->n??0);}return $out;
    }
    public function listPpe($companyId){return $this->query('SELECT pd.*,u.full_name,pi.name item_name,b.name branch_name FROM ppe_deliveries pd JOIN users u ON u.id=pd.user_id JOIN ppe_items pi ON pi.id=pd.item_id LEFT JOIN company_branches b ON b.id=pd.branch_id WHERE pd.company_id=? ORDER BY pd.delivered_on DESC,pd.id DESC',[$companyId]);}
    public function ppeItems($companyId){return $this->query('SELECT pi.*,COALESCE(SUM(ps.quantity),0) stock FROM ppe_items pi LEFT JOIN ppe_stock ps ON ps.item_id=pi.id WHERE pi.company_id=? GROUP BY pi.id ORDER BY pi.name',[$companyId]);}
    public function savePpeItem($companyId,$data){$ok=$this->execute('INSERT INTO ppe_items(company_id,name,category,requires_size,useful_life_days) VALUES(?,?,?,?,?) ON DUPLICATE KEY UPDATE category=VALUES(category),requires_size=VALUES(requires_size),useful_life_days=VALUES(useful_life_days),is_active=1',[$companyId,trim($data['name']),trim($data['category']??'')?:null,!empty($data['requires_size'])?1:0,(int)($data['useful_life_days']??0)?:null]);if($ok)$this->audit->record('ppe.catalog.saved','ppe_item',0,null,$data,'',$companyId);return $ok;}
    public function addPpeStock($companyId,$actor,$d){$item=(int)$d['item_id'];$branch=(int)($d['branch_id']??0);$size=trim($d['size_label']??'');$lot=trim($d['lot_no']??'');$qty=(int)($d['quantity']??0);if($qty<=0||!$this->one('SELECT id FROM ppe_items WHERE id=? AND company_id=?',[$item,$companyId]))return false;$this->db->beginTransaction();try{$existing=$this->one('SELECT id FROM ppe_stock WHERE item_id=? AND branch_id'.($branch?'=?':' IS NULL').' AND size_label=? AND lot_no=? FOR UPDATE',$branch?[$item,$branch,$size,$lot]:[$item,$size,$lot]);$ok=$existing?$this->execute('UPDATE ppe_stock SET quantity=quantity+? WHERE id=?',[$qty,$existing->id]):$this->execute('INSERT INTO ppe_stock(item_id,branch_id,size_label,lot_no,quantity) VALUES(?,?,?,?,?)',[$item,$branch?:null,$size,$lot,$qty]);if(!$ok)throw new RuntimeException('No se actualizó stock');$this->audit->record('ppe.stock.received','ppe_item',$item,null,['quantity'=>$qty,'size'=>$size,'lot'=>$lot],'',$companyId,$branch);$this->db->commit();return true;}catch(Throwable $e){$this->db->rollBack();return false;}}
    public function deliverPpe($companyId,$actor,$d){$user=(int)$d['user_id'];$item=(int)$d['item_id'];$qty=max(1,(int)$d['quantity']);$date=$d['delivered_on'];$branch=(int)($d['branch_id']??0);$size=trim($d['size_label']??'');$lot=trim($d['lot_no']??'');$hash=hash('sha256',json_encode([$companyId,$user,$item,$qty,$date,$size]));$this->db->beginTransaction();try{$stock=$this->one('SELECT id,quantity FROM ppe_stock WHERE item_id=? AND branch_id'.($branch?'=?':' IS NULL').' AND size_label=? AND lot_no=? FOR UPDATE',$branch?[$item,$branch,$size,$lot]:[$item,$size,$lot]);if(!$stock||(int)$stock->quantity<$qty)throw new RuntimeException('Stock insuficiente');$this->execute('UPDATE ppe_stock SET quantity=quantity-? WHERE id=?',[$qty,$stock->id]);$this->execute('INSERT INTO ppe_deliveries(company_id,branch_id,user_id,item_id,size_label,lot_no,quantity,delivered_on,next_replacement_on,delivered_by,document_hash,notes) VALUES(?,?,?,?,?,?,?,?,?,?,?,?)',[$companyId,$branch?:null,$user,$item,$size?:null,$lot?:null,$qty,$date,trim($d['next_replacement_on']??'')?:null,$actor,$hash,trim($d['notes']??'')?:null]);$id=$this->db->lastInsertId();$this->audit->record('ppe.delivered','ppe_delivery',$id,null,$d,'',$companyId,$branch);$this->db->commit();return true;}catch(Throwable $e){$this->db->rollBack();return false;}}
    public function assets($companyId){return $this->query('SELECT a.*,ac.name category_name,u.full_name custodian_name,b.name branch_name FROM assets a JOIN asset_categories ac ON ac.id=a.category_id LEFT JOIN users u ON u.id=a.current_custodian_user_id LEFT JOIN company_branches b ON b.id=a.branch_id WHERE a.company_id=? ORDER BY a.asset_tag',[$companyId]);}
    public function assetCategories($companyId){return $this->query('SELECT * FROM asset_categories WHERE company_id=? ORDER BY name',[$companyId]);}
    public function saveAsset($companyId,$actor,$d){$cat=trim($d['category']??'');if($cat==='')return false;$this->execute('INSERT INTO asset_categories(company_id,name) VALUES(?,?) ON DUPLICATE KEY UPDATE name=VALUES(name)',[$companyId,$cat]);$c=$this->one('SELECT id FROM asset_categories WHERE company_id=? AND name=?',[$companyId,$cat]);$ok=$this->execute('INSERT INTO assets(company_id,branch_id,category_id,asset_tag,brand,model,serial_no,license_plate,condition_notes) VALUES(?,?,?,?,?,?,?,?,?)',[$companyId,(int)($d['branch_id']??0)?:null,$c->id,trim($d['asset_tag']),trim($d['brand']??'')?:null,trim($d['model']??'')?:null,trim($d['serial_no']??'')?:null,trim($d['license_plate']??'')?:null,trim($d['condition_notes']??'')?:null]);if($ok)$this->audit->record('asset.created','asset',$this->db->lastInsertId(),null,$d,'',$companyId);return $ok;}
    public function moveAsset($companyId,$actor,$assetId,$type,$toUser,$notes){$asset=$this->one('SELECT * FROM assets WHERE id=? AND company_id=?',[$assetId,$companyId]);if(!$asset)return false;$statuses=['assign'=>'assigned','transfer'=>'assigned','return'=>'available','maintenance'=>'maintenance','repair'=>'repair','lost'=>'lost','damaged'=>'damaged','retire'=>'retired'];if(!isset($statuses[$type]))return false;$target=in_array($type,['assign','transfer'],true)?$toUser:null;if($target&&$this->one("SELECT id FROM assets WHERE current_custodian_user_id=? AND id=?",[$target,$assetId])){}$hash=hash('sha256',json_encode([$assetId,$type,$asset->current_custodian_user_id,$target,$notes,date('c')]));$this->db->beginTransaction();try{$this->execute('INSERT INTO asset_movements(asset_id,movement_type,from_user_id,to_user_id,actor_user_id,condition_notes,document_hash) VALUES(?,?,?,?,?,?,?)',[$assetId,$type,$asset->current_custodian_user_id?:null,$target?:null,$actor,$notes?:null,$hash]);$this->execute('UPDATE assets SET status=?,current_custodian_user_id=?,condition_notes=? WHERE id=?',[$statuses[$type],$target?:null,$notes?:null,$assetId]);$this->audit->record('asset.'.$type,'asset',$assetId,$asset,['status'=>$statuses[$type],'custodian'=>$target],$notes,$companyId,(int)$asset->branch_id);$this->db->commit();return true;}catch(Throwable $e){$this->db->rollBack();return false;}}
    public function expirations($companyId){return $this->query('SELECT ee.*,et.name type_name,u.full_name,DATEDIFF(ee.expires_on,CURDATE()) days_left FROM employee_expirations ee JOIN expiration_types et ON et.id=ee.expiration_type_id JOIN users u ON u.id=ee.user_id WHERE ee.company_id=? ORDER BY ee.expires_on',[$companyId]);}
    public function expirationTypes($companyId){return $this->query('SELECT * FROM expiration_types WHERE is_active=1 AND(company_id IS NULL OR company_id=?) ORDER BY name',[$companyId]);}
    public function saveExpiration($companyId,$actor,$d){$ok=$this->execute('INSERT INTO employee_expirations(user_id,company_id,branch_id,expiration_type_id,reference_no,issued_on,expires_on,responsible_user_id,notes,created_by) VALUES(?,?,?,?,?,?,?,?,?,?)',[(int)$d['user_id'],$companyId,(int)($d['branch_id']??0)?:null,(int)$d['expiration_type_id'],trim($d['reference_no']??'')?:null,trim($d['issued_on']??'')?:null,$d['expires_on'],(int)($d['responsible_user_id']??0)?:null,trim($d['notes']??'')?:null,$actor]);if($ok)$this->audit->record('expiration.created','employee_expiration',$this->db->lastInsertId(),null,$d,'',$companyId);return $ok;}
    public function vacancies($companyId=0,$public=false,$orgGroup=''){$sql='SELECT jv.*,c.name company_name,c.organization_group org_group,b.name branch_name,(SELECT COUNT(*) FROM job_applications ja WHERE ja.vacancy_id=jv.id) application_count FROM job_vacancies jv JOIN companies c ON c.id=jv.company_id LEFT JOIN company_branches b ON b.id=jv.branch_id WHERE 1=1';$p=[];if($companyId){$sql.=' AND jv.company_id=?';$p[]=$companyId;}if($orgGroup!==''){$sql.=' AND c.organization_group=?';$p[]=$orgGroup;}if($public)$sql.=" AND jv.status='published' AND(jv.closes_at IS NULL OR jv.closes_at>=CURDATE()) AND jv.slug NOT LIKE 'espontanea-%'";$sql.=' ORDER BY jv.created_at DESC';return $this->query($sql,$p);}
    public function vacanciesForCompanies(array $companyIds){if(!$companyIds)return [];$in=implode(',',array_map('intval',$companyIds));return $this->query("SELECT jv.*,c.name company_name,b.name branch_name,(SELECT COUNT(*) FROM job_applications ja WHERE ja.vacancy_id=jv.id) application_count FROM job_vacancies jv JOIN companies c ON c.id=jv.company_id LEFT JOIN company_branches b ON b.id=jv.branch_id WHERE jv.company_id IN ($in) ORDER BY jv.created_at DESC");}
    /**
     * Candidatos/postulaciones de TODAS las sociedades de la organización
     * (panel centrado en candidatos, port del panel del sistema viejo de
     * Moderna). $f: q (nombre/email), estado, vacante_id, desde, hasta.
     */
    public function applicationsOrg(array $companyIds,array $f=[],$page=1,$per=50){
        if(!$companyIds)return ['rows'=>[],'total'=>0,'page'=>1,'pages'=>1];
        $in=implode(',',array_map('intval',$companyIds));
        $where="jv.company_id IN ($in)";$p=[];
        if(($f['q']??'')!==''){$where.=' AND (c.full_name LIKE ? OR c.email LIKE ?)';$like='%'.$f['q'].'%';$p[]=$like;$p[]=$like;}
        if(($f['estado']??'')!==''){$where.=' AND ja.current_stage=?';$p[]=$f['estado'];}
        if((int)($f['vacante_id']??0)>0){$where.=' AND ja.vacancy_id=?';$p[]=(int)$f['vacante_id'];}
        if(($f['desde']??'')!==''){$where.=' AND ja.created_at>=?';$p[]=$f['desde'].' 00:00:00';}
        if(($f['hasta']??'')!==''){$where.=' AND ja.created_at<=?';$p[]=$f['hasta'].' 23:59:59';}
        $base='FROM job_applications ja JOIN candidates c ON c.id=ja.candidate_id JOIN job_vacancies jv ON jv.id=ja.vacancy_id';
        $total=(int)($this->one("SELECT COUNT(*) n $base WHERE $where",$p)->n??0);
        $pages=max(1,(int)ceil($total/$per));$page=min(max(1,(int)$page),$pages);
        $rows=$this->query("SELECT ja.*,c.full_name,c.email,c.phone,jv.title vacancy_title,jv.slug vacancy_slug,jv.pipeline_json,jv.company_id $base WHERE $where ORDER BY ja.created_at DESC LIMIT ".(int)$per.' OFFSET '.(int)(($page-1)*$per),$p);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    /** KPIs del panel (organización completa), estilo panel Moderna. */
    public function recruitingKpis(array $companyIds){
        if(!$companyIds)return ['total'=>0,'nuevos'=>0,'entrevista'=>0,'contratados'=>0,'abiertas'=>0];
        $in=implode(',',array_map('intval',$companyIds));
        $r=$this->one("SELECT COUNT(*) total,
            SUM(ja.current_stage IN ('nuevo','received')) nuevos,
            SUM(ja.current_stage IN ('entrevista','interview')) entrevista,
            SUM(ja.status='hired') contratados
            FROM job_applications ja JOIN job_vacancies jv ON jv.id=ja.vacancy_id WHERE jv.company_id IN ($in)");
        $a=$this->one("SELECT COUNT(*) n FROM job_vacancies WHERE company_id IN ($in) AND status='published' AND slug NOT LIKE 'espontanea-%'");
        return ['total'=>(int)($r->total??0),'nuevos'=>(int)($r->nuevos??0),'entrevista'=>(int)($r->entrevista??0),'contratados'=>(int)($r->contratados??0),'abiertas'=>(int)($a->n??0)];
    }
    public function vacancyBySlug($slug,$anyStatus=false){return $this->one("SELECT jv.*,c.name company_name,c.organization_group org_group,b.name branch_name FROM job_vacancies jv JOIN companies c ON c.id=jv.company_id LEFT JOIN company_branches b ON b.id=jv.branch_id WHERE jv.slug=?".($anyStatus?'':" AND jv.status='published' AND (jv.closes_at IS NULL OR jv.closes_at>=CURDATE())"),[$slug]);}
    public function vacancyById($id,$companyId){return $this->one('SELECT jv.* FROM job_vacancies jv WHERE jv.id=? AND jv.company_id=?',[(int)$id,(int)$companyId]);}
    public function vacancyByIdIn($id,array $companyIds){if(!$companyIds)return null;$in=implode(',',array_map('intval',$companyIds));return $this->one("SELECT jv.*,c.name company_name FROM job_vacancies jv JOIN companies c ON c.id=jv.company_id WHERE jv.id=? AND jv.company_id IN ($in)",[(int)$id]);}
    /**
     * Postulaciones con filtros server-side y paginación (port del panel del
     * Flask viejo). $f: q (nombre/email), stage, status. Devuelve
     * ['rows'=>..., 'total'=>n, 'page'=>p, 'pages'=>n].
     */
    public function applications($vacancyId,array $f=[],$page=1,$per=25){
        $where='ja.vacancy_id=?';$p=[(int)$vacancyId];
        if(($f['q']??'')!==''){$where.=' AND (c.full_name LIKE ? OR c.email LIKE ?)';$like='%'.$f['q'].'%';$p[]=$like;$p[]=$like;}
        if(($f['stage']??'')!==''){$where.=' AND ja.current_stage=?';$p[]=$f['stage'];}
        if(($f['status']??'')!==''){$where.=' AND ja.status=?';$p[]=$f['status'];}
        $total=(int)($this->one("SELECT COUNT(*) n FROM job_applications ja JOIN candidates c ON c.id=ja.candidate_id WHERE $where",$p)->n??0);
        $pages=max(1,(int)ceil($total/$per));$page=min(max(1,(int)$page),$pages);
        $rows=$this->query("SELECT ja.*,c.full_name,c.email,c.phone FROM job_applications ja JOIN candidates c ON c.id=ja.candidate_id WHERE $where ORDER BY ja.ai_score DESC,ja.created_at DESC LIMIT ".(int)$per.' OFFSET '.(int)(($page-1)*$per),$p);
        return ['rows'=>$rows,'total'=>$total,'page'=>$page,'pages'=>$pages];
    }
    public function positionsForCompany($companyId){return $this->query('SELECT id,name FROM job_positions WHERE company_id=? ORDER BY name',[(int)$companyId]);}
    public function branchesForCompany($companyId){return $this->query('SELECT id,name FROM company_branches WHERE company_id=? AND is_active=1 ORDER BY name',[(int)$companyId]);}
    public function performanceCycles($companyId){return $this->query('SELECT pc.*,pt.name template_name,(SELECT COUNT(*) FROM performance_reviews pr WHERE pr.cycle_id=pc.id) reviews,(SELECT COUNT(*) FROM performance_reviews pr WHERE pr.cycle_id=pc.id AND pr.status<>\'pending\') submitted FROM performance_cycles pc JOIN performance_templates pt ON pt.id=pc.template_id WHERE pc.company_id=? ORDER BY pc.starts_on DESC',[$companyId]);}
    public function audit(){return $this->audit;}
}
