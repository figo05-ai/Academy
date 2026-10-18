<?php
namespace App\Http\Controllers;
use Illuminate\Http\Request;
use Illuminate\Routing\Controller;
use Illuminate\Support\Facades\DB;
class SketchupAuthorizationController extends Controller {
    private function code(Request $request): string {
        abort_unless($request->isSecure(),400);
        return $request->validate(['code'=>['required','regex:/\A[A-F0-9]{10}\z/']])['code'];
    }
    public function show(Request $request) {
        $code=$this->code($request);
        abort_unless(DB::table('sketchup_device_requests')->where('code_hash',hash('sha256',$code))->whereNull('user_id')->whereNull('consumed_at')->where('expires_at','>',now())->exists(),410);
        return response()->view('sketchup.authorize',compact('code'))->header('Cache-Control','no-store')->header('Referrer-Policy','no-referrer');
    }
    public function approve(Request $request) {
        $code=$this->code($request);
        abort_unless($request->user()->is_active,403);
        $updated=DB::table('sketchup_device_requests')->where('code_hash',hash('sha256',$code))->whereNull('user_id')->whereNull('consumed_at')->where('expires_at','>',now())->update(['user_id'=>$request->user()->id,'updated_at'=>now()]);
        abort_unless($updated===1,410);
        return response()->view('sketchup.approved')->header('Cache-Control','no-store');
    }
}
