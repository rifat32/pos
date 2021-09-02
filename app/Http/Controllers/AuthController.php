<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use App\Models\User;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\DB;



class AuthController extends Controller
{

    public function register(Request $request)
    {

        $validator = Validator::make($request->all(), [
            'surname' => 'required|string|max:100',
            'first_name'=>'required|string|max:100',
            'username'=>'required|string|unique:users,username',
            'password' => 'required|confirmed|string|min:6',
        ]);
        if ($validator->fails()) {

            return response(['errors' => $validator->errors()->all()], 422);
        }
        $request['password'] = Hash::make($request['password']);
        $request['remember_token'] = Str::random(10);
        // return response()->json([
        //     "test" =>   $request->all()
        // ]);
        $user =  User::create($request->all());
        $token = $user->createToken('client')->plainTextToken;

        return response(["ok" => true, "message" => "You have successfully registered", "user" => $user, "token" => $token], 200);
    }
    public function login(Request $request)
    {

        $loginData = $request->validate([
            'username' => 'required|string',
            'password' => 'required|string'
        ]);

        if (!auth()->attempt($loginData)) {
            return response(['message' => 'Invalid Credentials'], 422);
        }
        // $user = User::find(1);

        // // Creating a token without scopes...
        // $token = $user->createToken('Token Name')->accessToken;


         $accessToken = Auth::user()->createToken('XBtEKCGRRAswsDx0YLrNuo3tiPhnxk5MBrrKCMJw');

        return response()->json(['user' => auth()->user(), 'token' => $accessToken,   "ok" => true], 200);
    }
    public function logout(Request $request)
    {
        $request->user()->currentAccessToken()->delete();
        return response()->json(["ok" => true]);

    }
}
