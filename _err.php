<?php
$base = 'http://localhost/scl';
$cj = __DIR__.'/stubs_cookie.txt';
@unlink($cj);

function r($ch, $url, $post = null) {
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    else       { curl_setopt($ch, CURLOPT_HTTPGET, true); }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    return ['code'=>curl_getinfo($ch, CURLINFO_HTTP_CODE), 'body'=>curl_exec($ch)];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_COOKIEJAR, $cj);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cj);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, false);
curl_setopt($ch, CURLOPT_HEADER, false);

$lg = r($ch, $base.'/login');
$csrf = '';
if (preg_match('/name="_token" value="([^"]+)"/', $lg['body'], $t)) $csrf = $t[1];
if (!$csrf) preg_match('/csrf_token" content="([^"]+)"/', $lg['body'], $t) && $csrf = $t[1];
echo "csrf=".($csrf?'OK':'NONE')."\n";
r($ch, $base.'/login', ['_token'=>$csrf, 'email'=>'admin@scl.local', 'password'=>'admin123']);
$s = r($ch, $base.'/entorno/seleccionar');
echo "SELECCIONAR code=".$s['code']."\n";
$b = $s['body'];
echo "len=".strlen($b)."\n";
foreach (['Whoops','Exception:','message','Class ','Livewire','CrearVacacion','views/','app/','sql','SQLSTATE','patronos','empleado'] as $needle) {
    $pos = stripos($b, $needle);
    if ($pos !== false) {
        echo "FOUND '$needle' at $pos: ".trim(substr($b, max(0,$pos-60), 160))."\n";
    }
}
// dump a slice around <body
$bp = stripos($b, '<body');
echo "\n--- BODY SNIPPET ---\n";
echo substr($b, $bp, 800);
