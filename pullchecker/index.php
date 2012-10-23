<?php

// bootstrap
$root = dirname(__FILE__) . '/';
// path to CodeSniffer class is required
require_once("{$root}config.php");
define('CODESNIFFER_CLASS_DIRECTORY', $config['cs']['classes_directory']);
require_once("{$root}include/cs/CsWrapper.php");
require_once("{$root}include/cs/CsHtmlReport.php");
require_once("{$root}include/githubapi/GithubApi.php");
//

// is request allowed?
if(!empty($config['enabled_for_hosts']) && !in_array($_SERVER['HTTP_HOST'], $config['enabled_for_hosts'])) {
    die('External access not allowed');
}
//

// prepare request
$pull = isset($_GET['pull']) ? intval($_GET['pull']) : null;
$rep = isset($_GET['rep']) ? $_GET['rep'] : null;
$is_auth_request = isset($_GET['auth']);

$r_parts = explode('/', $_SERVER['QUERY_STRING']);
if (sizeof($r_parts) == 4
    && !empty($r_parts[0]) && !empty($r_parts[1]) && !empty($r_parts[3]) && $r_parts[2] == 'pull'
) {
    $pull = intval($r_parts[3]);
    $rep = "{$r_parts[0]}/{$r_parts[1]}";
}
//

// main part
$res = array();
try {
    if ($is_auth_request) {
        $github_api = new GithubApi($config['github']);
        $github_api->auth();
    }

    if ($pull && $rep) {
        $config['github']['variables']['token'] = @file_get_contents($config['github']['token_file']);
        if ($config['github']['variables']['token']) {
            $config['github']['variables']['pull'] = $pull;
            $config['github']['variables']['repo'] = $rep;

            // Retrieve required data from GitHub
            $github_api = new GithubApi($config['github']);
            $pull_request = $github_api->getPullRequest();
            $diff = $github_api->getPullRequestDiff();
            $prepared_diff = $github_api->getFilesRawContent($pull_request, $diff);

            // Transfer data to CsWrapper
            $csWrapper = new CsWrapper();
            $csWrapper->cswSetStandard($config['cs']['standard']);
            foreach ($prepared_diff as $file => $data) {
                $csWrapper->cswAddCode($file, $data['content'], array_keys($data['lines']));
            }

            // Execute checks
            $data = $csWrapper->cswExecute();

            // Generate full HTML report for changed lines
            $html_report_object = new CsHtmlReport($data, false);
            $res['content'] = $html_report_object->generateFullPageHTML(
                'Code Style Report for pull request '.$pull.' in '.$rep
            );
        } else {
            throw new Exception('Application is not authorized');
        }
    } else {
        throw new Exception('Invalid request');
    }
} catch (Exception $e) {
    $res = array('error' => 'Fatal error. ' . $e->getMessage());
}


// final output
if (isset($res['error'])) {
    echo $res['error'];
} elseif (isset($res['content'])) {
    echo $res['content'];
}

