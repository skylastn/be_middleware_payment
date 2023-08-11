<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;
use App\Models\Project;

class Encryption extends Model
{
    use HasFactory;
    private string $encryptMethod = 'AES-256-CBC';
    // private string $key;
    // private string $iv;

    // public function __construct()
    // {
    //     $mykey = 'ThisIsASecuredKey';
    //     $myiv = 'ThisIsASecuredBlock';
    //     $this->key = substr(hash('sha256', $mykey), 0, 32);
    //     $this->iv = substr(hash('sha256', $myiv), 0, 16);
    // }

    public function encrypt(string $data, string $value): string
    {
        $project = Project::where("value", $value)->first();
        if(!isset($project)){
            return "";
        }
        $mykey = $project->key;
        $myiv = $project->secure;
        $key = substr(hash('sha256', $mykey), 0, 32);
        $iv = substr(hash('sha256', $myiv), 0, 16);
        return openssl_encrypt($data, $this->encryptMethod, $key, 0, $iv);
    }

    public function decrypt(string $base64Value, $value): string
    {

        $project = Project::where("value", $value)->first();
        if(!isset($project)){
            return "";
        }
        $mykey = $project->key;
        $myiv = $project->secure;
        $key = substr(hash('sha256', $mykey), 0, 32);
        $iv = substr(hash('sha256', $myiv), 0, 16);
        return openssl_decrypt($base64Value, $this->encryptMethod, $key, 0, $iv);
    }
}
