<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Patrono;
use App\Models\Vacacion;

echo "=== DB STATE ===\n";
$patrons = Patrono::all();
foreach ($patrons as $pp) {
    echo 'patrono id='.$pp->id.' rif='.$pp->rif.' estado='.$pp->estado."\n";
}
$vacs = Vacacion::all();
foreach ($vacs as $v) {
    echo 'v'.$v->id.' patrono='.$v->patrono_id.' empleado='.$v->empleado_id.' c='.$v->estatus_calculo.' v='.$v->estatus_vacacion."\n";
}
echo "Vacaciones count by patrono: ".Vacacion::selectRaw('patrono_id,count(*) c')->groupBy('patrono_id')->pluck('c','patrono_id')->toJson()."\n";
echo "Active patronos: ".Patrono::where('estado','Activo')->count()."\n";

$base='http://localhost/scl';
$cj=__DIR__.'/stubs_cookie.txt'; @unlink($cj);
function req($ch,$url,$post=null){
    curl_setopt($ch,CURLOPT_URL,$url);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);curl_setopt($ch,CURLOPT_MAXREDIRS,6);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);curl_setopt($ch,CURLOPT_HEADER,false);
    if($post){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$post);}
    else{curl_setopt($ch,CURLOPT_HTTPGET,true);curl_setopt($ch,CURLOPT_POST,false);}
    return curl_exec($ch);
}
$ch=curl_init();curl_setopt($ch,CURLOPT_COOKIEJAR,$cj);curl_setopt($ch,CURLOPT_COOKIEFILE,$cj);curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);

$lg=req($ch,$base.'/login');
preg_match('/name="_token" value="([^"]+)"/',$lg,$t);$csrf=$t[1]??'';
echo "csrf=".($csrf?'OK':'NONE')."\n";
req($ch,$base.'/login',['_token'=>$csrf,'email'=>'admin@scl.local','password'=>'admin123']);

$pid = $patrons->where('rif','J-12345678-9')->first()?->id ?? 5;
echo "selected patrono id=".$pid."\n";

// seleccionar GET sets session
$sel=req($ch,$base.'/entorno/seleccionar?patron='.$pid);
preg_match('/name="_token" value="([^"]+)"/',$sel,$t);$csrf2=$t[1]??'';
echo "seleccion code(200=ok)\n";

// guardar POST (capture headers to see redirect + set-cookie)
$ch2=curl_init();curl_setopt($ch2,CURLOPT_COOKIEJAR,$cj);curl_setopt($ch2,CURLOPT_COOKIEFILE,$cj);
curl_setopt($ch2,CURLOPT_URL,$base.'/entorno/guardar');curl_setopt($ch2,CURLOPT_POST,true);curl_setopt($ch2,CURLOPT_POSTFIELDS,['_token'=>$csrf2,'patron_id'=>(string)$pid]);
curl_setopt($ch2,CURLOPT_RETURNTRANSFER,true);curl_setopt($ch2,CURLOPT_FOLLOWLOCATION,false);curl_setopt($ch2,CURLOPT_HEADER,true);curl_setopt($ch2,CURLOPT_SSL_VERIFYPEER,false);
$raw=cur2($ch2);
$hs=curl_getinfo($ch2,CURLINFO_HEADER_SIZE);
echo "guardar raw header:\n".substr($raw,0,$hs)."\n";
curl_close($ch2);

// vacaciones index
$body=req($ch,$base.'/vacaciones');
echo "vacaciones len=".strlen($body)."\n";
$i=stripos($body,'registros');
echo 'registros at '.($i===false?'NONE':$i)."\n";
if($i!==false) echo '  snip: '.substr($body,$i-120,300)."\n";
$nh=stripos($body,'No hay periodos');
echo 'empty-state at '.($nh===false?'NONE':$nh)."\n";
if($nh===false){ preg_match_all('#/vacaciones/(\d+)#',$body,$m); echo 'href ids: '.implode(',',$m[1])."\n"; }
curl_close($ch);
function cur2($ch){return curl_exec($ch);}
echo "=== END ===\n";
