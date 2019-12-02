<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Str;
use DB;
use App\User;
use App\Employee;
use App\Policy;
use App\Notifications;
use App\Department;
use App\Category;
use App\Leave;
use App\Attendance;
use App\Calendar;
use App\CatLeave;
use Carbon\Carbon;
use DateTime;
use DatePeriod;
use DateInterval;
use Illuminate\Support\Facades\Hash;
use App\Notification;
use App\Event;

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

    private function getTilToday(String $start){
        if (substr($start,0,7)== Date('Y-m')){
            return substr(date('Y-m-d'),0,10);
        }else{
            return $this->getLast($start);
        }
    }

    public function workDaysBetween($start, $end){
        // $start = $r['start']; $end=$r['end'];
        $wDays = [];
        $interval = DateInterval::createFromDateString('1 day');
        $period = new DatePeriod(new DateTime($start), $interval, new DateTime($end));
        $closedDays = Calendar::select('task_date')->whereRaw(' task_date BETWEEN "'.$start.'" AND "'.$end.'" AND open = 0')->get();
       
        foreach ($period as $dt) {
            array_push( $wDays,  $dt->format("Y-m-d"));
        }
        
        foreach ($closedDays as $dt) {
            if(in_array(substr($dt['task_date'],0,10), $wDays)){
                \array_splice($wDays, array_search(substr($dt['task_date'],0,10), $wDays),1);
            } 
        }
        return $wDays;

    }

    //==========return a list of absent days ===============
    private function absences($start, $end, $id){
        $workDays = $this->workDaysBetween($start, $end);
        $Att = Attendance::whereRaw('io_time BETWEEN "'.$start.'" AND "'.$end.'" AND user_id ="'.$id.'"' )->get();
        $absences = [];
        foreach($Att as $day){
            $idate = Carbon::create($day['io_time'])->format('Y-m-d');
            if(in_array($idate, $workDays)){
                array_splice($workDays,array_search($idate, $workDays),1);        
            }
        }
        return $workDays;
    }

    public function uploadImage(Request $request){
        $api_token = $request->api_token;
        if($api_token!=''){
            $emp = Employee::where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $name=$emp[0]->f_name;
                $old_filename=$emp[0]->image;
                $byteimage = $request->image;
                $filename =$name.'_'.date('Y_m_d_H_i_s').'.png';
                $decoded = base64_decode($byteimage);
                $im = imageCreateFromString($decoded);
                if (!$im) {
                    die('Base64 value is not a valid image');
                }
                $img_file = 'profiles/'.$filename;
                imagepng($im, $img_file, 0);
                DB::table('employees')->where('id', $id)->update(['image' => $filename]);
                unlink('profiles/'.$old_filename);
                return 'done';
            }
        }
    }

    private function beeps($start, $end, $id){
        $Att = Attendance::select('io_time')->whereRaw('io_time BETWEEN "'.$start.'" AND "'.$end.'" AND user_id ="'.$id.'"' )->orderBy('io_time')->get();
        $beeps = [];    
        
        foreach($Att as $att){
            array_push($beeps,$att['io_time']);
        }
        return $beeps;
    }

    public function beepedToday(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $start = date('Y-m-d');
                $end = $start.' 23:59:59';
                $unformatted = $this->beeps($start, $end, $id);
                return  response()->json([
                    'success'=>'true',
                    'data'=>$unformatted,
                ]);
                
            }else{
                return  response()->json([
                    'success'=>'false',
                    'error'=>'wrong token',
                ]);
            }
        }else{
            return  response()->json([
                'success'=>'false',
                'error'=>'empti token',
            ]);
        }
    }

    public function getBeeps(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                $start = $this->getFirst($request->input('ofDate'));
                $end = $this->getTilToday($start);
                $unformatted = $this->beeps($start, $end, $id);
                $current='';
                $f = '';
                $l = '';
                $beeps = [];
                for($i = 0; $i < sizeof($unformatted); $i++){
                    if($i == sizeof($unformatted)-1){
                        $l = substr($unformatted[$i], 11,8);
                        array_push($beeps,[$current,Carbon::create($l)->diffInHours(Carbon::create($f)),$f,$l] );
                    }else if($i > 0 ){
                        if($current != substr($unformatted[$i], 0,10)){
                            array_push($beeps,[$current,Carbon::create($l)->diffInHours(Carbon::create($f)),$f,$l] );
                            $current = substr($unformatted[$i], 0,10);
                            $f = substr($unformatted[$i], 11,8);
                        }
                    }else{
                        $current = substr($unformatted[$i], 0,10);
                        $f = substr($unformatted[$i], 11,8);
                    }
                    $l = substr($unformatted[$i], 11,8);
                }
                return response()->json([
                    'success'=>'true',
                    'top'=>'09:00',
                    'bottom'=>'18:00',
                    'data'=>$beeps,
                ]);
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'wrong token'
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'blank token'
            ]);
        }
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
                $eves = Calendar::where(['task_date'=>$start])->get();
                $bdays = Employee::select('f_name','l_name','dob' )->get();
                $leaves = Leave::where('user_id',$id)->where('leave_on', $start )->get();
                $bds=[];
                $leas=[];
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
                
                foreach($leaves as $leave){
                    $leas = [$leave['reason'],$leave['leave_on'],$leave['leave_till']];
                break;
                }
                return response()->json([
                    'success'=>'true',
                    'beeps'=>$this->beeps($start,$end,$id),
                    'events'=>$eves,
                    'bdays'=>$bds,
                    'leaves'=>$leas,
                ]);
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'wrong token',
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'empty token',
            ]);
        }
    }

    // get next and current events
    public function getNextEve(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $now = date('Y-m-d');
                $to = date('Y-m-d', strtotime($now. ' + 1 days'));
                $res = Event::whereBetween('onDate', [$now, $to])->get();
                $ret =[];
                if(count($res)<3){
                    $next = Event::where('onDate','>', $to)->orderBy('onDate')->take(2)->get();
                    foreach($res as $r){
                        array_push($ret,$r);
                    }
                    foreach ($next as $key) {
                        array_push($ret, $key);
                    }
                }
                return response()->json([
                    'success'=>'true',
                    'data'=>$ret,
                ]);
            }else{
                return response()->json([
                    'success'=>'false',
                    'error'=>'wrong api',
                ]);
            }
        }else{
            return response()->json([
                'success'=>'false',
                'error'=>'wrong api',
            ]);
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

    private function stats($start, $end, $id){
        $absences = $this->absences($start, $end, $id);
        $hours = $this->getHours($start, $end, $id);
        $leaves = CatLeave::selectRaw('sum(leaves) as leaves')->get();
        return response()->json([
            'success' => 'true',
            'absences' => $absences,
            'hours' => $hours[0],
            'perDay'=> $hours[1],
            'leaves'=>$leaves[0]['leaves'],
        ]);
    }


    //========Monthly Stats Calculator===============================
    public function getStats(Request $request){
        $api_token = $request->input('api_token');
        $start = $this->getFirst($request->input('ofDate'));
        $end = $this->getTilToday($start);
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;
                return $this->stats($start,$end,$id);
            }else{
                return response()->json([
                    'success' => 'false',
                    'error' => 'token expired',
                ]);
            }
        }
    }

    // total stats
    public function getTotalStats(Request $request){
        $api_token = $request->input('api_token');
        if($api_token!=''){
            $emp = Employee::where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $start = $emp[0]['joined_date'];
                if($start < date('Y-01-01')){
                    $start = date('Y-01-01');
                }
                $end = date('Y-m-d H:i:s');
                $id=$emp[0]->id;
                return $this->stats($start,$end,$id);
            }else{
                return response()->json([
                    'success' => 'false',
                    'error' => 'token expired',
                ]);
            }
        }
    }

    //==========Policy Retriever  ===========================
    private function polcy($toSearch){
        $category = Category::query()->where('name', 'like', "%$toSearch%")->get();
        $result  = [];
        foreach($category as $row){
            $policy = Policy::where('category_id',$row->id)->get();
            array_push($result,[
                'category'=>$row->name,
                'details'=>$policy]);
        }
        // $policy = Policy::where('details', 'like', "%$toSearch%")->get();
        // foreach($policy as $popo){
        //     $category = Category::where('id', $popo->category_id)->first();
        //     $cat_inside = false;  # boolean to check if category inside result
        //     foreach( $result as $res){
        //         if($category->name == $res['category']){
        //             $cat_inside = true;
        //             $pol_inside = false;
        //             foreach($res['details'] as $pol){
        //                 if($pol['id']==$popo->id){
        //                     $pol_inside = true;
        //                 }
        //             }
        //             if(!$pol_inside){
        //                 $new_arr = $res['details'];
        //                 array_push($new_arr, $popo);
        //                 unset($result[$popo]);
        //                 // for(range(count($result['details'])) as $i){
        //                 //     if($result[$i]['category']==$category->name){
        //                 //         unset()
        //                 //     }
        //                 // }
        //             }
        //         }    
        //     }
        //     echo('here');
        //     if(!$cat_inside){
        //         array_push($result,[
        //             'category'=>$category->name,
        //             'details'=>[$popo]
        //         ]);
        //     }
        // }
        return $result;
    }
    //============Policy Api================================
    public function getPolicies(Request $request){
        $api_token = $request->input('api_token');
        
        if($api_token!=''){
            $emp = Employee::where('api_token',$api_token)->get();
            if($emp->isNotEmpty()){

                    return response()->json(['success' =>'true',
                        'data'=>$this->polcy($request->Search)]);
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
        $category= $request->input('category');
        if($api_token!=''){
            $emp = Employee::select('id')->where(["api_token"=>$api_token ])->get();
            if($emp->isNotEmpty()){
                $id=$emp[0]->id;



                //==checking Previous Request --------------------
                $prev = Leave::where(["user_id"=>$id, "approved"=>null,  ])->get();
                if($prev->isNotEmpty()){
                    return response()->json([
                        'success'=>'false',
                        'error'=>'you already have requests pending' 
                    ]);
                }else{
                    $limited = Leave::selectRaw('sum(leave_till-leave_on+1) as days ')->where( 'user_id', $id)-> where( 'category', $category)->where('approved','approved')->first();
                    $limit = CatLeave::where('id',$category )->first();
                    if($limited['days']>=$limit['leaves']){
                        return response()->json([
                            'success'=>'false',
                            'error'=> 'sorry, leave allowance limit exceeded'
                        ]);
                    }
                    $ll = new Leave();
                    $ll->user_id=$id;
                    $ll->leave_on=$on; 
                    $ll->leave_till=$till; 
                    $ll->reason=$reason; 
                    $ll->category=$category; 
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
                'error'=>'blank token' 
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
                $result = Leave::where(['user_id'=>$id])->orderBy('leave_on','DESC')->get();
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
        $end = $this->getTilToday($start);
     
        if($api_token!=''){
            $emp = Employee::where(["api_token"=>$api_token ])->get();
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
        if($api_token!=''){
            $emp = Employee::where(["api_token"=>$api_token ])->get();
            $id = $emp[0]->id;
            if($emp->isNotEmpty()){
                $result = Notification::where('user_id',$id)->orWhere('user_id',null)->orderBy('created_at', 'DESC')->limit(5)->get(); 
                return response()->json(
                    [
                        'success'=>'true',
                        'data'=>$result]);
            }else{
                return response()->json([
                    'success'=>'false',
                    'error' => 'expired token'
                ]);
            }
            }else{
            return response()->json([
                'success'=>'false',
                'error' => 'empty token'
            ]);
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

    public function leaveCat(Request $request){
        $token=$request->input('api_token');
        $result = Employee::where(["api_token"=>$token])->get();
        if($result->isNotEmpty()){
            $re = CatLeave::all();
            $ret = [];
            $id = $result[0]['id'];
            foreach ($re as $key) {
                $limited = Leave::selectRaw('sum(leave_till-leave_on+1) as days ')->where( 'user_id', $id)-> where( 'category', $key['id'])->where('approved','approved')->first();
                $days = 0;
                if($limited['days']!=null){
                    $days = $limited['days'];
                }
                $ret [$key['name']]=[$key['need_reason'],$key['id'],$key['leaves'],$days ];
            }

            return response()->json([
                'success'=>'true',
                'data'=>$ret
            ]);
        }
    }

    private function notifyUsers($title, $message, $image, bool $isTest = False, $players)
    {
        $app_id = "08725583-aaf5-4dfd-8d6d-6abc738f5539";
        $rest_api_key = "ODY4ZTE0ZmQtODQ5ZS00MDE5LThhMjYtYTI0MmM4ZjUwOTUw";
        $heading = array(
        "en" => $title
        );
        $content = array(
        "en" => $message
        );
        $fields = array(
        'app_id' => $app_id,
        'data' => array('just'=>'because'),
        'contents' => $content,
        'headings' => $heading,
        'large_icon' => $image,
        );
         
        if ($isTest) {
            $fields['included_segments'] = array("Test Users");
        } else {
            $fields['included_segments'] = array("Active Users", "Inactive Users");
        }

        if($players->isNotEmpty()){
            $fields['include_player_ids'] = $players;
        }

        $fields = json_encode($fields);
        print("\nJSON sent:\n");
        print($fields);
         
        $ch = curl_init();
        curl_setopt($ch, CURLOPT_URL, "https://onesignal.com/api/v1/notifications");
        curl_setopt($ch, CURLOPT_HTTPHEADER, array(
        'Content-Type: application/json; charset=utf-8',
        'Authorization: Basic ' . $rest_api_key
        ));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST, true);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $fields);
        curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
         
        $response = curl_exec($ch);
        curl_close($ch);
         
        $return["allresponses"] = $response;
        $return = json_encode($return);
         
        print("\n\nJSON received:\n");
        print($return);
        print("\n");
    }

    public function send(Request $request){
        $title = 'aloha';
        $bod = $request->bod;
        $this->notifyUsers($title, $bod, 'http://simpleicon.com/wp-content/uploads/cute.png', FALSE, array());
    }

    public function setPlayId(Request $request){
        Employee::where('api_token', $request->api_token)->update(['player_id'=>$request->playerId]);
    }
    public function passReset(Request $request){
        $headers = "From: ash.rbd@gmail.com" ;
        mail('ndpstha@gmail.com', 'feather webs password reset', 'yololololo',$headers);
    }
}
    