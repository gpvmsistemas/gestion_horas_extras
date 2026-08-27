<?php
chdir(__DIR__.'/../public'); require '../app/bootstrap.php';
$db=new Database();$db->query('SELECT * FROM audit_events ORDER BY id');$previous=null;$count=0;
foreach($db->resultSet() as $r){$payload=implode('|',[$previous,(string)$r->occurred_at,(string)$r->actor_user_id,$r->action_key,$r->entity_type,(string)$r->entity_id,(string)$r->before_json,(string)$r->after_json,(string)$r->reason,$r->correlation_id]);$expected=hash('sha256',$payload);if(!hash_equals($expected,$r->event_hash)){fwrite(STDERR,"Cadena inválida en evento {$r->id}\n");exit(1);}$previous=$r->event_hash;$count++;}
echo "Cadena válida: $count eventos.\n";
