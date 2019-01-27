<?php

function echox($msg, $code = 0) { echo $msg . PHP_EOL; exit($code); }

if(exec('whoami') !== 'root') echox('you have to be root, exitting', 1);

if(!file_exists('config.json')) echox("config file not found, exiting", 2);
$config = json_decode(file_get_contents('config.json'), true);
if(json_last_error() !== JSON_ERROR_NONE) echox("couldn't read the config file - check format, exiting", 3);
if(!isset($config['group']) || empty($config['group'])) echox("missing or invalid unix group, exitting", 4);
if(!isset($config['users'])) $config['users'] = [];
function config_save(){ global $config; file_put_contents('config.json', json_encode($config)); }

$users = json_decode(@file_get_contents($config['api_url'], false, stream_context_create(['http' => $config['api_http_options']])), true);
if(strpos($http_response_header[0], '200') === false) echox("invalid response from api: ({$http_response_header[0]}); exiting", 5);
$list = []; foreach($users as $user) $list[$user['id']] = $user['login'];

if($config['users'] == $list) echox("no changes detected, exiting");

$counter = ['changed' => 0, 'added' => 0, 'deleted' => 0];

foreach($list as $id => $login){
  if(isset($config['users'][$id])){
    if($config['users'][$id] === $login) continue;

    $login_old = $config['users'][$id];
    $config['users'][$id] = $login;
    $counter['changed']++;

    if(!empty(exec("grep '^{$login_old}:' /etc/passwd")) && empty(exec("grep '^{$login}:' /etc/passwd"))){
      $new_home = str_replace($login_old, $login, exec("echo ~{$login_old}"));
      exec("pkill -9 -u {$login_old}");
      exec("usermod -l {$login} {$login_old}");
      exec("usermod -d {$new_home} -m {$login}");
    }
  }else{
    $config['config'][$id] = $login;
    $counter['added']++;

    if(empty(exec("grep '^{$login}:' /etc/passwd")))
      exec("useradd -mg {$config['group']} {$login}");
  }
}

foreach($config['users'] as $id => $login){
  if(isset($list[$id])) continue;

  unset($config['users'][$id]);
  $counter['deleted']++;

  if(!empty(exec("grep '^{$login}:' /etc/passwd")))
      exec("userdel -fr {$login}");
}

echo "added {$counter['added']} users, changed {$counter['changed']} users and deleted {$counter['deleted']} users";
