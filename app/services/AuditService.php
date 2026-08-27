<?php
class AuditService {
    private $db;
    public function __construct($db=null){$this->db=$db instanceof Database?$db:new Database();}
    public function ready(){try{$this->db->query("SHOW TABLES LIKE 'audit_events'");return(bool)$this->db->single();}catch(Throwable $e){return false;}}
    public function record($action,$entityType,$entityId,$before=null,$after=null,$reason='',$companyId=0,$branchId=0){
        if(!$this->ready())return true;
        $actor=(int)($_SESSION['user_id']??0)?:null;$occurred=(new DateTimeImmutable())->format('Y-m-d H:i:s.u');$beforeJson=$before===null?null:json_encode($before,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$afterJson=$after===null?null:json_encode($after,JSON_UNESCAPED_UNICODE|JSON_UNESCAPED_SLASHES);$correlation=$this->uuid();
        $this->db->query("SELECT GET_LOCK('audit_events_chain',5) acquired");$lock=$this->db->single();if(!$lock||(int)$lock->acquired!==1)throw new RuntimeException('No se pudo bloquear la cadena de auditoría.');
        try{$this->db->query('SELECT event_hash FROM audit_events ORDER BY id DESC LIMIT 1');$last=$this->db->single();$previous=$last->event_hash??null;$payload=implode('|',[$previous,$occurred,(string)$actor,$action,$entityType,(string)$entityId,(string)$beforeJson,(string)$afterJson,(string)$reason,$correlation]);$hash=hash('sha256',$payload);$this->db->query('INSERT INTO audit_events(occurred_at,actor_user_id,company_id,branch_id,action_key,entity_type,entity_id,reason,before_json,after_json,request_ip,correlation_id,previous_hash,event_hash) VALUES(?,?,?,?,?,?,?,?,?,?,?,?,?,?)');return $this->db->execute([$occurred,$actor,$companyId?:null,$branchId?:null,$action,$entityType,(string)$entityId,$reason?:null,$beforeJson,$afterJson,$_SERVER['REMOTE_ADDR']??null,$correlation,$previous,$hash]);}
        finally{$this->db->query("SELECT RELEASE_LOCK('audit_events_chain')");$this->db->single();}
    }
    private function uuid(){return sprintf('%04x%04x-%04x-%04x-%04x-%04x%04x%04x',random_int(0,65535),random_int(0,65535),random_int(0,65535),random_int(16384,20479),random_int(32768,49151),random_int(0,65535),random_int(0,65535),random_int(0,65535));}
}
