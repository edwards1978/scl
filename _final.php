<?php
require __DIR__.'/vendor/autoload.php';
$app = require __DIR__.'/bootstrap/app.php';
$app->make(Illuminate\Contracts\Console\Kernel::class)->bootstrap();

use App\Models\Patrono;
use App\Models\Empleado;
use App\Models\Vacacion;
use App\Models\VacacionesRecibo;
use App\Services\VacacionesService;
use Carbon\Carbon;

echo "=== SEED ===\n";
$patrono = Patrono::updateOrCreate(
    ['rif' => 'J-12345678-9'],
    ['razon_social'=>'Industrias Test CA','nombre_comercial'=>'Test','registro_mercado'=>'123','direccion_fiscal'=>'Av. Test, Caracas','representante_legal'=>'Admin Test','telefono'=>'021-5551212','correo'=>'test@test.com','estado'=>'Activo','descuenta_ivss'=>true,'descuenta_paro_fijo'=>true,'descuenta_faov'=>true]
);
Empleado::where('patrono_id',$patrono->id)->delete();
VacacionesRecibo::whereHas('vacacion',fn($q)=>$q->where('patrono_id',$patrono->id))->delete();
Vacacion::where('patrono_id',$patrono->id)->delete();
$e1 = Empleado::create(['patrono_id'=>$patrono->id,'nombres'=>'Ana María','apellidos'=>'Gómez Pérez','cedula'=>'12.345.678','fecha_nacimiento'=>Carbon::now()->subYears(30)->toDateString(),'genero'=>'F','fecha_ingreso'=>Carbon::now()->subYears(2)->toDateString(),'cargo'=>'Operaria','tipo_contrato'=>'Indeterminado','tipo_salario'=>'Fijo','jornada_laboral'=>'L-V','salario_base'=>30000.00,'moneda'=>'USD','dias_descanso'=>'6,7','estatus'=>'Activo']);
echo "e1={$e1->id} ingreso={$e1->fecha_ingreso->format('Y-m-d')}\n";
$s = app(VacacionesService::class);
function makeVac(Empleado $emp, VacacionesService $s, string $vac){
    $c=$s->calcular($emp,0);
    $v=Vacacion::create(['patrono_id'=>$emp->patrono_id,'empleado_id'=>$emp->id,'fecha_calculo'=>now()->toDateString(),'fecha_inicio'=>$c['fecha_inicio_user'],'fecha_inicio_real'=>$c['fecha_inicio_real'],'fecha_fin_real'=>$c['fecha_fin_real'],'fecha_reintegro'=>$c['fecha_reintegro'],'nro_vacacion'=>$c['anio_servicio'],'periodo_desde'=>$c['periodo_desde'],'periodo_hasta'=>$c['periodo_hasta'],'dias_vacaciones'=>$c['dias_vacaciones_base'],'dias_adicionales'=>$c['dias_vacaciones_adic'],'dias_bono'=>$c['dias_bono_base'],'dias_bono_adicionales'=>$c['dias_bono_adic'],'feriados_descanso_en_periodo'=>$c['feriados_en_periodo'],'salario_normal'=>$c['salario_normal'],'moneda'=>$c['moneda'],'estatus_calculo'=>'por_aprobar','estatus_vacacion'=>$vac]);
    if ($vac==='disfrutada') { $v->update(['estatus_calculo'=>'aprobada']); $r=$s->generarRecibo($v); $r->update(['fecha_inicio'=>$v->fecha_inicio_real,'fecha_retiro'=>$v->fecha_reintegro]); $v->update(['estatus_vacacion'=>'disfrutada']); }
    return $v;
}
$vPend = makeVac($e1,$s,'pendiente');
$vPag  = makeVac($e1,$s,'disfrutada');
echo "vPend={$vPend->id}(por_aprobar/pendiente) vPag={$vPag->id}(pagada/disfrutada)\n";
echo "vPend neto esperado 28350.00; vPag neto recibo=".($vPag->recibos()->first()?->neto ?? 'NONE')."\n";

echo "=== HTTP ===\n";
$base='http://localhost/scl';
$cj=__DIR__.'/stubs_cookie.txt'; @unlink($cj);
function req($ch,$url,$post=null,$follow=true){
    curl_setopt($ch,CURLOPT_URL,$url);curl_setopt($ch,CURLOPT_RETURNTRANSFER,true);
    curl_setopt($ch,CURLOPT_FOLLOWLOCATION,$follow);curl_setopt($ch,CURLOPT_MAXREDIRS,6);
    curl_setopt($ch,CURLOPT_SSL_VERIFYPEER,false);curl_setopt($ch,CURLOPT_HEADER,false);
    if($post){curl_setopt($ch,CURLOPT_POST,true);curl_setopt($ch,CURLOPT_POSTFIELDS,$post);}else{curl_setopt($ch,CURLOPT_HTTPGET,true);curl_setopt($ch,CURLOPT_POST,false);}
    $b=cur($ch);return ['code'=>curl_getinfo($ch,CURLINFO_HTTP_CODE),'ctype'=>curl_getinfo($ch,CURLINFO_CONTENT_TYPE),'eff'=>curl_getinfo($ch,CURLINFO_EFFECTIVE_URL),'redirect'=>curl_getinfo($ch,CURLINFO_REDIRECT_URL),'len'=>strlen($b),'body'=>$b];
}
function cur($c){return curl_exec($c);}
function has($b,$n){return strpos($b,$n)!==false?'YES':'NO';}
$ch=curl_init();curl_setopt($ch,CURLOPT_COOKIEJAR,$cj);curl_setopt($ch,CURLOPT_COOKIEFILE,$cj);

$lg=req($ch,$base.'/login');
preg_match('/name="_token" value="([^"]+)"/',$lg['body'],$t);$csrf=$t[1]??'';
echo "login GET ".$lg['code']." csrf=".($csrf?'OK':'NONE')."\n";
req($ch,$base.'/login',['_token'=>$csrf,'email'=>'admin@scl.local','password'=>'admin123']);
$sel=req($ch,$base.'/entorno/seleccionar?patron='.$patrono->id);
preg_match('/name="_token" value="([^"]+)"/',$sel['body'],$t);$csrf2=$t[1]??'';
echo "seleccionar code=".$sel['code']." (contexto set here)\n";
req($ch,$base.'/entorno/guardar',['_token'=>$csrf2,'patron_id'=>(string)$patrono->id],false);

$v=req($ch,$base.'/vacaciones');
echo "index code=".$v['code'].' ctype='.$v['ctype']." len=".$v['len']."\n";
echo "  + Nueva Vacacion: ".has($v['body'],'+ Nueva Vacación')."\n";
echo "  store.open trigger: ".has($v['body'],'$store.vacaciones.open = true')."\n";
echo "  row vPend {$vPend->id}: ".has($v['body'],'/vacaciones/'.$vPend->id)."\n";
echo "  pdf link vPag {$vPag->id}: ".has($v['body'], '/vacaciones/'.$vPag->id.'/pdf')."\n";

$d=req($ch,$base.'/dashboard');echo "dashboard ".$d['code']." mount: ".has($d['body'],'wire:')."\n";
$e=req($ch,$base.'/empleados');echo "empleados ".$e['code']." mount: ".has($e['body'],'wire:')."  +shortcut: ".has($e['body'],'title="Nueva Vacación')."\n";

$sh=req($ch,$base.'/vacaciones/'.$vPend->id);
preg_match('/name="_token" value="([^"]+)"/',$sh['body'],$t);$csrf3=$t[1]??'';
echo "show {$vPend->id} code=".$sh['code']." csrf=".($csrf3?'OK':'NONE')."\n";

$ap=req($ch,$base.'/vacaciones/'.$vPend->id.'/estatus',['_token'=>$csrf3,'_method'=>'PUT','accion'=>'aprobar'],false);
echo "aprobar code=".$ap['code']." redirect=".($ap['redirect']??'-')."\n";
$pg=req($ch,$base.'/vacaciones/'.$vPend->id.'/estatus',['_token'=>$csrf3,'_method'=>'PUT','accion'=>'pagar'],false);
echo "pagar code=".$pg['code']." redirect=".($pg['redirect']??'-')."\n";

$pdf=req($ch,$base.'/vacaciones/'.$vPend->id.'/pdf');
echo "pdf vPend code=".$pdf['code']." ctype=".$pdf['ctype']." len=".$pdf['len']."\n";
echo "  %PDF: ".(substr($pdf['body'],0,4)==='%PDF'?'YES':'NO')."\n";
echo "  Neto a pagar: ".has($pdf['body'],'Neto a pagar')."\n";
echo "  IVSS: ".has($pdf['body'],'IVSS')."\n";
echo "  SCL: ".has($pdf['body'],'SCL')."\n";
echo "  patrono: ".has($pdf['body'],'Industrias Test CA')."\n";

$pdf2=req($ch,$base.'/vacaciones/'.$vPag->id.'/pdf');
echo "pdf vPag code=".$pdf2['code']." ctype=".$pdf2['ctype']." %PDF: ".(substr($pdf2['body'],0,4)==='%PDF'?'YES':'NO')."\n";

curl_close($ch);
echo "=== END ===\n";
