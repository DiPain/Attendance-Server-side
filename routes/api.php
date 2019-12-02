<?php

use Illuminate\Http\Request;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Here is where you can register API routes for your application. These
| routes are loaded by the RouteServiceProvider within a group which
| is assigned the "api" middleware group. Enjoy building your API!
|
*/
Route::post('logs', 'logController@login');
Route::post('logout', 'logController@logout');
Route::post('polcy', 'logController@polcySearch');
Route::post('events', 'logController@getNextEve');
Route::post('leaveCat', 'logController@leaveCat');
Route::post('getStats', 'logController@getStats');
Route::post('getBeeps', 'logController@getBeeps');
Route::post('leaveReq', 'logController@leaveReq');
Route::post('getLeaves', 'logController@getLeaves');
Route::post('getAbsence', 'logController@getAbsence');
Route::post('changePass', 'logController@changePass');
Route::post('getprofile', 'logController@getProfile');
Route::post('getPolicies', 'logController@getPolicies');
Route::post('checkExists', 'logController@checkExists');
Route::post('getAttendance', 'logController@getAtt');
Route::post('uploadImage', 'logController@uploadImage');
Route::post('getDayEvents', 'logController@getDayEvents');
Route::post('pushPlayId', 'logController@setPlayId');
Route::post('checkNotification', 'logController@checkNotification');
Route::post('bpdTday', 'logController@beepedToday');
Route::post('getTotalStats', 'logController@getTotalStats');
Route::post('q', 'logController@leaveCat');
Route::post('w', 'logController@passReset');





Route::middleware('auth:api')->post('/user', function (Request $request) {
    return $request->user();
});
