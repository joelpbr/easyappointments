<?php
$_SERVER['argv']=['index.php','console','migrate'];chdir(dirname(__DIR__,2));require 'index.php';
require_once APPPATH.'libraries/Podologo_clinical_repair.php';
$ci=&get_instance();$db=$ci->db->conn_id;$p=$ci->db->dbprefix;
$missing=[];
$r=$db->query("SELECT l.source_id,a.id FROM {$p}podologo_import_links l JOIN {$p}appointments a ON a.id=l.target_id WHERE l.source_table='VISITA' AND a.id_users_provider IS NULL");
while($row=$r->fetch_assoc())$missing[(int)$row['source_id']]=true;
$groups=[];$fields=[];$timed=[];$examples=[];$codes=[];
foreach((new Podologo_clinical_repair())->rows('VISITA') as [,$row]){
    if(!isset($missing[$row['ID']]))continue;
    $ids=[];
    foreach($row as $field=>$value)if(preg_match('/ESPECIALISTA|PERSONAL/',$field)){$ids[$field]=$value;$fields[$field]=true;}
    $key=json_encode($ids);$groups[$key]=($groups[$key]??0)+1;
    if(substr($row['FECHAVISITA'],11,8)!=='00:00:00'){
        $text=implode("\n",array_map(static fn($k)=>Podologo_clinical::text($row[$k]??null),['DESCRIPCION','QUIROPODIARECOMENDACION','MOTIVOVISITAQUIRO','EVOLUCIONQUIRO','BIOMECANICARECOMENDACION','CIRUGIARECOMENDACION']));
        $code=preg_match('/\bQUIRO\.?\s+S\b/u',$text)?'QUIRO S':(preg_match('/(?<![\p{L}\p{N}_])T(?![\p{L}\p{N}_])/u',$text)?'T':(preg_match('/\bQUIRO\b/u',$text)?'QUIRO without S/T':'no QUIRO code'));
        $codes[$code]=($codes[$code]??0)+1;
        $timed[$key]=($timed[$key]??0)+1;
        if(count($examples)<8)$examples[]=['id'=>$row['ID'],'date'=>$row['FECHAVISITA'],'providers'=>$ids,
            'types'=>array_intersect_key($row,array_flip(['QUIROPODIA','CIRUGIA','BIOMECANICA','PIERIESGO']))];
    }
}
arsort($groups);arsort($timed);
echo json_encode(['missing'=>count($missing),'groups'=>$groups,'timed'=>$timed,'fields'=>array_keys($fields),'examples'=>$examples,'timed_comment_codes'=>$codes],JSON_UNESCAPED_UNICODE).PHP_EOL;
