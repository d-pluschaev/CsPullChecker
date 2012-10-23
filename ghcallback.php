<?php

ini_set('display_errors', 'on');
ini_set('errors_reporting', ~E_ALL);

$res = array();

if (isset($_REQUEST['code'])) {
    $code = filterCode($_REQUEST['code']);

    if ($code) {

        $root = dirname(__FILE__) . '/';
        require_once("{$root}include/githubapi/GithubApi.php");
        require_once("{$root}config.php");
        $config['github']['variables']['code'] = $code;

        @unlink($config['github']['token_file']);

        $res = array('result' => 'ok');
        try {
            $github_api = new GithubApi($config['github']);
            $token = array('token' => $github_api->authGetToken());
            file_put_contents($config['github']['token_file'], $token);

        } catch (Exception $e) {
            $res = array('error' => 'Fatal error. ' . $e->getMessage());
        }
    } else {
        $res = array('error' => 'Invalid request');
    }
}

header('Content-Type: application/json');
echo json_encode($res);
exit;


function filterCode($code)
{
    return preg_replace('~[^A-Za-z0-9]~', '', substr(trim($code), 0, 32));
}
