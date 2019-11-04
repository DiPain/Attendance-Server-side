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
Route::post('leaveReq', 'logController@leaveReq');
Route::post('getLeaves', 'logController@getLeaves');
Route::post('Policies', 'logController@getPolicies');
Route::post('getAbsence', 'logController@getAbsence');
Route::post('changePass', 'logController@changePass');
Route::post('getprofile', 'logController@getProfile');
Route::post('checkExists', 'logController@checkExists');
Route::post('getAttendance', 'logController@getAtt');
Route::post('getStats', 'logController@getStats');
Route::post('uploadImage', 'logController@uploadImage');
Route::post('getDayEvents', 'logController@getDayEvents');
Route::post('checkNotification', 'logController@checkNotification');



Route::middleware('auth:api')->post('/user', function (Request $request) {
    return $request->user();
});
