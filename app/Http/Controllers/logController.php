<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use App\User;
use App\Employee;
use App\Policies;
use App\Notifications;
use App\Department;
use App\Category;
use App\Leaves;
use App\Attendance;
use App\Calendar;
use Carbon\Carbon;
use \DateTime;
use Illuminate\Support\Facades\Hash;

class logController extends Controller
{
    //===function to get first date of given month
    private function getFirst(String $start){
        if($start[7]=='-'){
            $start = substr($start,0,8);
        }else{
            $start = substr($start,0,7);
        }
        $start=$start.'1';
        return $start;
    }

    //===function to get last date of given month
    private function getLast(String $start){
        $end = '';
        foreach(range(0,6) as $j){
            $end = $end.$start[$j];
        }
        if($end[-1]!='-'){
            $end=$end.'-';
        }
        $end = $end.date('t',strtotime($start)).' 23:59:59';
        return $end;
    }

    //==========return a list of absent days ===============
    private function absences($start, $end, $id){
        $workDays = Calendar::select('theDate')->whereRaw(' theDate BETWEEN "'.$start.'" AND "'.$end.'" AND open = 1')->get();
        $Att = Attendance::whereRaw('io_time BETWEEN "'.$start.'" AND "'.$end.'" AND user_id ="'.$id.'"' )->get();
        $res = [];
        foreach($workDays as $workday){
            $add = true;
            for($i=0; $i<count($Att); $i++){
                $idate = Carbon::create($Att[$i]['io_time'])->format('Y M d');
                $workdate = Carbon::create($workday['theDate'])->format('Y M d');
                if($idate==$workdate){
                    $add = false;
                }
            }
            if($add){
                $add=false;
                array_push($res, $workday['theDate']);        
            }
        }
        return $res;
    }

    //======events of each day===============
    public function getDayEvents(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $start = $request->input('ofDate');
                $end = $start.' 23:59:59';

                $Att = Attendance::select('io_time')->whereRaw('io_time BETWEEN "'.$start.'" AND "'.$end.'" AND user_id ="'.$id.'"' )->get();
                $eves = Calendar::select('open','events')->where(['theDate'=>$start])->get();
                $bdays = Employee::select('f_name','l_name','dob' )->get();
                $bds=[];
                foreach($bdays as $bd){
                    if(
                        date('m',strtotime($bd['dob']))==
                        date('m',strtotime($start))
                        and
                        date('d',strtotime($bd['dob']))==
                        date('d',strtotime($start))
                    ){
                        array_push($bds,$bd['f_name'].' '.$bd['l_name'] );
                    }
                }
                $beeps = [];
                foreach($Att as $att){
                    array_push($beeps,$att['io_time']);
                }


                return response()->json([
                    'success'=>'true',
                    'beeps'=>$beeps,
                    'events'=>$eves,
                    'bdays'=>$bds,
                ]);
            }
        }
    }

    //============returns a list of hours per day and total
    private function getHours($start, $end, $id){
        $totalHours = 0;
        $Att = Attendance::select('io_time')->whereRaw('io_time BETWEEN "'.$start.'" AND "'.$end.'" AND user_id ="'.$id.'"' )->orderBy('io_time')->get();
        $ret =  date('Y-m-d H:i:s');
        $perday = [];
        for($at =0; $at < count($Att)-1; $at++){
            $current  = Carbon::create($Att[$at]['io_time']);
            $next  = Carbon::create($Att[$at+1]['io_time']);
            
            if(($current->format('Y M d'))==($next->format('Y M d'))){
                
                $at+=1;
            }else{                
                $next = Carbon::create($Att[$at]['io_time'])->setTime(18, 0, 0);
            }
            $dif = $next->diffInHours($current);
            array_push($perday,[$current->format('Y-m-d'),$dif]);
            $totalHours += $dif;
        }
        $perDay = [];
        for($i=0; $i<count($perday); $i++){
            $brk = false;
            foreach($perDay as $item){
                if($perday[$i][0]==$item[0]){
                    $brk = true;
                    break;
                }
            }
            if($brk){
                continue;
            }
            $count = 0;
            for($j=$i; $j<count($perday); $j++){
                if($perday[$i][0]==$perday[$j][0]){
                    $count+=$perday[$j][1];
                }
            }
            array_push($perDay,[$perday[$i][0],$count]);
        }
        return [$totalHours, $perDay];
    }

    //========Stats Calculator===============================
    public function getStats(Request $request){
        $api_token = $request->input('api_token');
        $start = $this->getFirst($request->input('ofDate'));
        $end = $this->getLast($start);
        
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $absences = $this->absences($start, $end, $id);
                $hours = $this->getHours($start, $end, $id);
                return response()->json([
                    'success' => 'true',
                    'absences' => $absences,
                    'hours' => $hours[0],
                    'perDay'=> $hours[1],
                ]);
            }
        }
    }


    //==========Policy Retriever  ===========================
    private function polcy(){
        $category = Category::all();
        $result  = []; 
        foreach($category as $row){
            $policy = Policies::where('category_id',$row->id)->get();
            array_push($result,[
                'category'=>$row->name,
                'details'=>$policy]);
        }
        return $result; 
    }

    //============Policy Api================================
    public function getPolicies(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::where('api_token',$api_token)->get(); 
            if($emp->isNotEmpty()){
                return $this->polcy();
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'wrong token' 
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'empty token' 
            ]);
        }
    }
    
    //============Leaves Request Api===============================
    public function leaveReq(Request $request){
        $api_token = $request->input('api_token');
        $on= $request->input('leave_on');
        $till= $request->input('leave_till');
        $reason= $request->input('reason');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;

                //==checking Previous Request --------------------
                $prev = Leaves::where(["user_id"=>$id, "approved"=>null ])->get();
                if($prev->isNotEmpty()){
                    return response()->json([
                        'success'=>'false',
                        'error'=>'you already have requests pending' 
                    ]);
                }else{
                    $ll = new Leaves();
                    $ll->user_id=$id;
                    $ll->leave_on=$on; 
                    $ll->leave_till=$till; 
                    $ll->reason=$reason; 
                    $ll->save();
                    // $result = Attendance
                    return response()->json([
                        'success'=>'true',
                        'lol'=> 'sorry, no table yet'
                    ]);
                }
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'invalid token' 
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'blank' 
            ]);
        }
    }
    //==============Leaves retriever==========================
    public function getLeaves(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $result = Leaves::where(['user_id'=>$id])->orderBy('leave_on','DESC')->get();
                if($result->isNotEmpty()){
                    return response()->json([
                        'success'=>'true',
                        'data' => $result,
                    ]);
                }else{
                    return response()->json([
                        'success'=>'false',
                        'error'=> 'no endry in database'
                    ]);    
                }
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'invalid token' 
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'blank' 
            ]);
        }
    }

    public function getAbsence(Request $request){
        $api_token = $request->input('api_token');
        $start = $this->getFirst($request->input('toCheck'));
        $end = $this->getLast($start);
        
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $res = $this->absences($start,$end, $id);
                return response()->json([
                    'success'=>'true',
                    'value'=>$res
                ]);
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'wrong api'
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'empty api'
            ]);
        }
    }

    public function checkNotification(Request $request){
        $api_token = $request->input('api_token');
        $show = $request->input('show');
        if($api_token!=''){
            $emp = Employee::where('api_token',$api_token); 
            if($emp){
                $result = Notification::all()->orderby('id','DESC')->chunk(5)->get(); 
                return response()->json($result);
            }
        }
    }

    public function logout(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $result = Employee::where('api_token',$api_token)->update(array('api_token'=>'')); 
            if($result){
                return response()->json(['success'=>'true' ]);
            }
            else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'wrong token' 
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'blank' 
            ]);
        }
    }

    //================New and Improved Attendance Api
    public function getAtt(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $result = Attendance::where(["user_id"=>$id])->get();
                if($result->isNotEmpty()){
                    return $result;
                }else{
                    return response()->json([
                        'success'=>'false',
                        'error'=> 'sorry, no table yet'
                    ]);
                }
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'invalid token' 
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'blank' 
            ]);
        }
    }

    public function changePass(Request $request){
        $api_token = $request->input('api_token');
        $old = $request->input('old');
        $nuu = $request->input('nuu');
        if($api_token!=''){
            $result = Employee::where(["api_token"=>$api_token ])->get();
            if($result->isNotEmpty()){
                if(Hash::check($old, $result[0]->password)){
                    $result = Employee::where('api_token',$api_token)->update(array('password'=>password_hash($nuu, PASSWORD_DEFAULT))); 
                    if($result){
                        return response()->json([
                            'success'=>'true',
                        ]);
                    }
                }else{
                    return response()->json([
                        'success'=>'false',
                        'error'=>'wrong old password'
                    ]);
                }
                
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'token Mismatch'
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'empty token'
            ]);
        }
    }

    public function getProfile(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $result = Employee::where(["api_token"=>$api_token ])->get();
            if($result->isNotEmpty()){

                $dep = Department::select('name')->where(["id"=>$result[0]->dept_id ])->get();
                if($dep->isNotEmpty()){
                    $result[0]['dept']=$dep[0]->name;
                    $result[0]['success']='true';
                    return $result[0];    
                }else{
                    return response()->json([
                        'success'=>'false',
                        'error'=>'No Dept' 
                    ]);
                }
                
            }else{
                
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'blank' 
            ]);
        }
    }

    public function checkExists(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $result = Employee::where(["api_token"=>$api_token ])->get();
            return $result;
            if($result->isNotEmpty()){
                return 'true';
            }else{
                return 'false';
            }
        }
    }

    public function generateToken(){
        $api_token = Str::random(60);
        $result = Employee::where(["api_token"=>$api_token ])->get();
        if($result->isNotEmpty()){
            $api_token = generateToken();
        }
        return $api_token;
    }
   

    public function login(Request $request)
    {
        $api_token = $this->generateToken();
        $email=$request->input('email');
        $pass=$request->input('password');
        $result = Employee::where(["email"=>$email ])->get();
            if($result->isNotEmpty()){
                $id=$result[0]->id;
                $passwor = $result[0]->password;
                if(Hash::check($pass, $passwor)){
                    $here= Employee::where('id',$id)->update(array('api_token'=>$api_token)); 
                    if($here ==1){
                        return response()->json([
                            'result'=>'success',
                            'id'=>$id,
                            'token' => $api_token
                        ]);
                    }else{
                    return response()->json( ['result'=>'fu'] );   

                    }
                }else{
                    return response()->json( ['result'=>'password'] );   
                }
            }else{
                return response()->json( ['result'=>'email'] );
            }
    }
}
    