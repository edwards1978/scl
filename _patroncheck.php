<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();
use App\Models\Patrono;

$p4 = Patrono::find(4); $p5 = Patrono::find(5);
echo "p4=".($p4?->razon_social ?? 'NONE')." (rif ".$p4?->rif.")\n";
echo "p5=".($p5?->razon_social ?? 'NONE')." (rif ".$p5?->rif.")\n";

// confirm param name in controller + blade
$ctrl = file_get_contents(__DIR__.'/app/Http/Controllers/Web/EntornoController.php');
$blade = file_get_contents(__DIR__.'/resources/views/entorno/seleccionar.blade.php');
echo "controller get('patrono')? ".(strpos($ctrl,"get('patrono'")!==false?'YES':'NO')."\n";
echo "controller get('patron')? ".(strpos($ctrl,"get('patron'")!==false?'YES':'NO')."\n";
echo "blade builds '?patron='? ".(strpos($blade,'?patron=')!==false?'YES':'NO')."\n";
echo "blade builds '?patrono='? ".(strpos($blade,'?patrono=')!==false?'YES':'NO')."\n";

$base='http://localhost/scl';
$cj=__DIR__.'/stubs_cookie.txt'; @unlink($cj);
function req($ch,$url,$post=null){
    curl_setopt($ch,CURLOPT_URL,$url);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,true);curl_setopt($ch,CURLOPT_MAXREDIRS,6);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);curl_setopt($ch,CURLOPT_HEADER,false);
    if($post){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$post);}else{curl_setopt($ch,CURLOPT_HTTPGET,true);curl_setopt($ch,CURLOPT_POST,false);}
    return curl_exec($ch);
}
$ch=curl_init();curl_setopt($ch,CURLOPT_COOKIEJAR,$cj);curl_setopt($ch,CURLOPT_COOKIEFILE,$cj);
$lg=req($ch,$base.'/login');
preg_match('/name="_token" value="([^"]+)"/',$lg,$t);$csrf=$t[1]??'';
req($ch,$base.'/login',['_token'=>$csrf,'email'=>'admin@scl.local','password'=>'admin123']);
req($ch,$base.'/entorno/seleccionar?patron=5');
$body=req($ch,$base.'/vacaciones');
echo "active patrono on /vacaciones header:\n";
if (strpos($body,$p5->razon_social)!==false) echo "  -> patrono 5 (".$p5->razon_social.") YES\n";
if (strpos($body,$p4->razon_social)!==false) echo "  -> patrono 4 (".$p4->razon_social.") YES\n";
// dump the header patrono area
$i = strpos($body, 'nombre_patrono');
echo "session('nombre_patrono') ref at ".($i===false?'NONE':$i)."\n";
// find a span with razon
foreach ([$p4->razon_social,$p5->razon_social] as $n) {
    $pos = strpos($body,$n);
    echo "  '$n' found at ".($pos===false?'NONE':$pos)."\n";
}
curl_close($ch);
