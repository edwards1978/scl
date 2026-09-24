<?php
$base = 'http://localhost/scl';
$cj = __DIR__.'/stubs_cookie.txt';
@unlink($cj);

function r($ch, $url, $post = null) {
    curl_setopt($ch, CURLOPT_URL, $url);
    if ($post) { curl_setopt($ch, CURLOPT_POST, true); curl_setopt($ch, CURLOPT_POSTFIELDS, $post); }
    else       { curl_setopt($ch, CURLOPT_HTTPGET, true); }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $body = curl_exec($ch);
    return ['code'=>curl_getinfo($ch, CURLINFO_HTTP_CODE), 'url'=>curl_getinfo($ch, CURLINFO_EFFECTIVE_URL), 'body'=>$body];
}

$ch = curl_init();
curl_setopt($ch, CURLOPT_COOKIEJAR, $cj);
curl_setopt($ch, CURLOPT_COOKIEFILE, $cj);
curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
curl_setopt($ch, CURLOPT_MAXREDIRS, 5);
curl_setopt($ch, CURLOPT_HEADER, false);

// 1) GET login
$login = r($ch, $base.'/login');
echo "LOGIN GET code={$login['code']} url={$login['url']}\n";

$csrf = '';
if (preg_match('/name="csrf_token" content="([^"]+)"/', $login['body'], $t)) $csrf = $t[1];
elseif (preg_match('/name="_token" value="([^"]+)"/', $login['body'], $t)) $csrf = $t[1];
echo "csrf=".($csrf ? 'OK('.substr($csrf,0,8).')' : 'NONE')."\n";

// 2) POST login
$in = r($ch, $base.'/login', ['_token'=>$csrf,'email'=>'admin@scl.local','password'=>'admin123']);
echo "LOGIN POST code={$in['code']} url={$in['url']}\n";

// 3) GET /vacaciones
$v = r($ch, $base.'/vacaciones');
echo "VACACIONES code={$v['code']} url={$v['url']}\n";
echo "  + Nueva Vacacion button: ".(strpos($v['body'], '+ Nueva Vacaci')!==false ? 'YES' : 'NO')."\n";
echo "  store.open trigger: ".(strpos($v['body'], '$store.vacaciones.open')!==false ? 'YES' : 'NO')."\n";
echo "  header trigger @click: ".(strpos($v['body'], "@click=\"\$store.vacaciones.open = true\"")!==false ? 'YES' : 'NO')."\n";
echo "  has wire: attr: ".(strpos($v['body'], 'wire:')!==false ? 'YES' : 'NO')."\n";

// 4) extract built JS asset & check it
preg_match_all('#/build/[A-Za-z0-9_./-]+\.js#', $v['body'], $js);
$jsurl = $js[0][0] ?? '';
echo "  JS asset: $jsurl\n";
if ($jsurl) {
    $j = r($ch, (str_starts_with($jsurl,'http') ? $jsurl : $base.$jsurl));
    echo "  JS asset GET code={$j['code']} len=".strlen($j['body'])."\n";
    echo "  JS has store(vacaciones): ".(strpos($j['body'], 'store("vacaciones"')!==false ? 'YES' : 'NO')."\n";
}

// 5) GET /empleados (modal should be mounted here too)
$e = r($ch, $base.'/empleados');
echo "EMPLEADOS code={$e['code']}\n";
echo "  has wire: (modal mount): ".(strpos($e['body'], 'wire:')!==false ? 'YES' : 'NO')."\n";

// 6) confirm sidebar + shortcut present on /vacaciones
echo "  sidebar + shortcut: ".(strpos($v['body'], 'title=\"Nueva Vacaci')!==false ? 'YES' : 'NO')."\n";
curl_close($ch);
