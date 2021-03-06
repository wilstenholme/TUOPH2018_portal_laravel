<?php

namespace App\Http\Controllers;

use Exception;
use \Illuminate\Http\Request;
use Illuminate\Support\Facades\Redirect;

class UserController extends Controller
{

    public static function httpGet($url)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $output = curl_exec($ch);

        curl_close($ch);
        return $output;
    }

    public static function httpPost($url, $params)
    {
        $ch = curl_init();

        curl_setopt($ch, CURLOPT_URL, $url);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_HEADER, 'Content-Type: application/json');
        curl_setopt($ch, CURLOPT_POST, TRUE);
        curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($params));

        $output = curl_exec($ch);

        curl_close($ch);
        return $output;
    }

    public function loginFacebook(Request $request){
        $code = $request->get('code');
        $facebookService = \OAuth::consumer('Facebook', url('/login/facebook'));

        if (!is_null($code)) {
            $token = $facebookService->requestAccessToken($code);

            $fb_access_token = $token->getAccessToken();

            $result = self::httpGet('https://openhouse.buffalolarity.com/api/token?type=fb&access_token=' . $fb_access_token);
            $json = json_decode($result, true);
            $access_token = $json['access_token'];

            session()->put('access_token', $access_token);
            session()->save();

            // Previous: '/'
            return redirect('/register');
        }
        else {
            $url = $facebookService->getAuthorizationUri();
            return redirect((string)$url);
        }
    }

    public function loginGoogle(Request $request){
        $code = $request->get('code');
        $googleService = \OAuth::consumer('Google');

        if (!is_null($code)) {
            $token = $googleService->requestAccessToken($code);

            $google_access_token = $token->getAccessToken();

            $result = self::httpGet('https://openhouse.buffalolarity.com/api/token?type=google&access_token=' . $google_access_token);
            $json = json_decode($result, true);
            $access_token = $json['access_token'];

            session()->put('access_token', $access_token);
            session()->save();

            // Previous: '/'
            return redirect('/register');
        }
        else {
            $url = $googleService->getAuthorizationUri(['approval_prompt' => 'force']);
            return redirect((string)$url);
        }
    }

    public static function isLoggedIn(){
        $access_token = session()->get('access_token');
        return !is_null($access_token);
    }

    public static function getUserData(){
        $access_token = session()->get('access_token');

        if (is_null($access_token)) return null;

        $result = self::httpGet('https://openhouse.buffalolarity.com/api/me?access_token=' . $access_token);
        $json = json_decode($result, true);
        return $json;
    }

    public function register(Request $request){
        if(self::isLoggedIn() && self::getUserData()['registered']){
            session()->flash('error', 'คุณได้ลงทะเบียนเรียบร้อยแล้ว ไม่แก้ไขข้อมูลได้ หากมีปัญหาใดๆ โปรดติดต่อที่เฟสบุ๊กเพจ Triam Udom Open House');
            return redirect()->back();
        }

        try {

            $accountType = $request->get('accountType');
            switch ($accountType) {
                case 'student':
                    $this->validate($request, [
                        'prefix' => 'required|in:mr,mrs,miss,master-boy,master-girl,other',
                        'firstName' => 'required|max:255',
                        'lastName' => 'required|max:255',
                        'email' => 'required|email|max:255',
                        'studentYear' => 'required|in:p1-3,p4-6,m1,m2,m3,m4,m5,m6',
                        'schoolName' => 'required|max:255',
                    ]);
                    $result = self::httpPost('https://openhouse.buffalolarity.com/api/register', [
                        'prefix' => $request->get('prefix', 'master-boy'),
                        'firstName' => $request->get('firstName', ''),
                        'lastName' => $request->get('lastName', ''),
                        'email' => $request->get('email', ''),
                        'accountType' => $request->get('accountType'),
                        'studentYear' => $request->get('studentYear', 'm3'),
                        'schoolName' => $request->get('schoolName', ''),
                        'interests' => [],
                        'access_token' => session()->get('access_token'),
                    ]);
                    break;
                case 'teacher':
                    $this->validate($request, [
                        'prefix' => 'required|in:mr,mrs,miss,master-boy,master-girl,other',
                        'firstName' => 'required|max:255',
                        'lastName' => 'required|max:255',
                        'email' => 'required|email|max:255',
                        'schoolName' => 'required|max:255',
                    ]);
                    $result = self::httpPost('https://openhouse.buffalolarity.com/api/register', [
                        'prefix' => $request->get('prefix', 'master-boy'),
                        'firstName' => $request->get('firstName', ''),
                        'lastName' => $request->get('lastName', ''),
                        'email' => $request->get('email', ''),
                        'accountType' => $request->get('accountType'),
                        'schoolName' => $request->get('schoolName', ''),
                        'interests' => [],
                        'access_token' => session()->get('access_token'),
                    ]);
                    break;
                    break;
                case 'student-college':
                case 'guardian':
                    $this->validate($request, [
                        'prefix' => 'required|in:mr,mrs,miss,master-boy,master-girl,other',
                        'firstName' => 'required|max:255',
                        'lastName' => 'required|max:255',
                        'email' => 'required|email|max:255'
                    ]);
                    $result = self::httpPost('https://openhouse.buffalolarity.com/api/register', [
                        'prefix' => $request->get('prefix', 'master-boy'),
                        'firstName' => $request->get('firstName', ''),
                        'lastName' => $request->get('lastName', ''),
                        'email' => $request->get('email', ''),
                        'accountType' => $request->get('accountType'),
                        'interests' => [],
                        'access_token' => session()->get('access_token'),
                    ]);
                    break;
                default:
                    session()->flash('error', 'ประเภทของบัญชีไม่ถูกรูปแบบ');
                    return redirect()->back();
            }

            $json = json_decode($result, true);

            if (array_key_exists('error', $json)) {
                session()->flash('error', 'มีข้อผิดพลาดที่ไม่สามารถระบุได้ กรุณาติดต่อผู้ดูแลระบบ');
                return redirect()->back();
            } else {
                session()->flash('status', 'ลงทะเบียนสำเร็จ รหัสยืนยันการลงทะเบียนของคุณคือ ' . self::getUserData()['id']
                    . ' กรุณาแจ้งรหัสที่ ณ จุดลงทะเบียน เพื่อรับเกียรติบัตรและสูจิบัตรงาน');
                return Redirect::to('/' . "#s-intro");
                // return redirect('/');
            }
        }
        catch(Exception $ex){
            session()->flash('error', 'ข้อมูลไม่ครบถ้วน');
            return redirect()->back();
        }
    }

    public function logout(Request $request){
        session()->flush();
        return redirect('/');
    }
}