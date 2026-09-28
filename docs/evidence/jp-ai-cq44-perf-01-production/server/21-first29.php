<?php
function nearest_rank(array $a, float $p): ?int {
  if ($a === []) return null;
  sort($a); $n=count($a); $rank=max(1,min($n,(int)ceil(($p/100)*$n))); return $a[$rank-1];
}
foreach ([
  'docs/evidence/jp-ai-cq43-final-resoak/out/sessions/01-primary-long-session.json',
  'docs/evidence/jp-ai-cq44-perf-01-production/out/sessions/05-real-qwen-session.json',
] as $path) {
  $turns=json_decode(file_get_contents($path),true)['turns'];
  $user=[];
  foreach ($turns as $t) { if (empty($t['resume_ai'])) $user[]=$t; }
  $first29=array_slice($user,0,29);
  $tot=array_map(fn($t)=>(int)round((float)($t['latency_ms']??0)), $first29);
  $q=0; foreach($first29 as $t){ $m=$t['meta']??[]; $q+=(int)($m['MODEL_CALLS']??$m['GENERAL_MODEL_CALLS']??0);} 
  echo basename(dirname($path)).'/'.basename($path)." first29_qwen=$q p50=".nearest_rank($tot,50)." p95=".nearest_rank($tot,95)." max=".max($tot)."\n";
}
