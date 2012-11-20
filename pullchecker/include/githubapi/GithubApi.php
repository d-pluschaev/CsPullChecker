<?php

require_once(dirname(__FILE__) . '/HttpRequestClass.php');

class GithubApi extends HTTPRequestClass
{
    protected $cfg;

    public function __construct(array $config)
    {
        if (!isset($config['api']) || !isset($config['variables'])) {
            throw new Exception('GithubApi: invalid argument $config passed in constructor');
        }
        $this->cfg = $config;
        $this->initGitHubApi();
    }

    public function auth()
    {
        $api = $this->github_api['authenticate'];
//        $data = $this->performRequest(
//            $api['url'],
//            array(
//                'method'=>'GET',
//                'content'=>$api['content'],
//                'header'=>array(
//                    'Referer: '.$this->cfg['application_url'],
//                    'Host: '.parse_url($this->cfg['application_url'],PHP_URL_HOST),
//                ),
//                'max_redirects'=>5,
//            ),
//            true
//        );

        header('Location: ' . $api['url'] . '?' . http_build_query($api['content']));
        exit;
    }

    public function authGetToken()
    {
        $api = $this->github_api['authenticate_get_token'];
        $data = $this->performRequest($api['url'], $api);
        if (!isset($data['data']['content']['access_token'])) {
            if (isset($data['data']['content']['error'])) {
                throw new Exception('GithubApi::authGetToken: ' . $data['data']['content']['error']);
            }
            throw new Exception('GithubApi::authGetToken: unable to retrieve token');
        } else {
            return $data['data']['content']['access_token'];
        }
    }

    public function getPullRequest()
    {
        $api = $this->github_api['get_pull_request'];
        $data = $this->performRequest($api['url'], $api);

        if (empty($data['data']['content'])) {
            if (isset($data['data']['content']['error'])) {
                throw new Exception('GithubApi::getPullRequest: ' . $data['data']['content']['error']);
            }
            throw new Exception('GithubApi::getPullRequest: unable to retrieve data');
        } else {
            if (!isset($data['data']['content']['user'])) {
                throw new Exception('GithubApi::getPullRequest: request is not found');
            }
            return $data['data']['content'];
        }
    }

    public function getPullRequestDiff()
    {
        $api = $this->github_api['get_pull_request_diff'];
        $data = $this->performRequest($api['url'], $api);
        if (empty($data['body'])) {
            throw new Exception('GithubApi::getPullRequestDiff: diff is not found');
        }
        return $this->analyzeDiff($data['body']);
    }

    public function getFilesRawContent(array $pull, array $diff)
    {
        foreach ($diff as $file => $data) {
            if (!$data['is_new']) {
                $diff[$file]['content'] = $this->getGitHubFile($pull['head']['sha'], $file);
            } else {
                $diff[$file]['content'] = implode("\n", $data['lines']);
            }
        }
        return $diff;
    }

    protected function getGitHubFile($sha, $path)
    {
        $this->cfg['variables']['file_sha'] = $sha;
        $this->cfg['variables']['file_path'] = $path;
        $this->initGitHubApi('get_file_by_sha_and_path');

        $api = $this->github_api['get_file_by_sha_and_path'];
        $data = $this->performRequest($api['url'], $api);
        return $data['body'];

    }

    protected function initGitHubApi($only_for_key = '')
    {
        $this->github_api = array();
        foreach ($this->cfg['api'] as $key => $value) {
            if (!$only_for_key || $only_for_key == $key) {
                $this->github_api[$key] = $value;
                foreach ($this->cfg['variables'] as $v_key => $v_value) {
                    foreach ($this->github_api[$key] as $k => $v) {
                        $this->github_api[$key][$k] = str_ireplace("{{$v_key}}", $v_value, $this->github_api[$key][$k]);
                    }
                }
            }
        }
    }

    protected function analyzeDiff($diff, $rep_dir = '/')
    {
        $diff = preg_split("/((\r?\n)|(\r\n?))/", $diff);
        $out = array();
        $line = $is_new = 0;
        foreach ($diff as $row) {
            if (preg_match("~^---\s(.*)~", $row, $matches)) {
                $path_minus = substr($matches[1], strpos($matches[1], $rep_dir) + 1);
                continue;
            } elseif (preg_match("~^\+\+\+\s(.*)~", $row, $matches)) {
                $path = substr($matches[1], strpos($matches[1], $rep_dir) + 1);
                $out[$path] = isset($out[$path]) ? $out[$path] : array('lines' => array());
                $out[$path]['is_new'] = $path_minus != $path;
            } elseif (preg_match("~^@@\ -[0-9]+(,[0-9]+)?\ \+([0-9]+)(,[0-9]+)?\ @@.*$~", $row, $matches)) {
                $line = $matches[2];
            } elseif (preg_match("~^\+(.*)~", $row, $matches)) {
                $out[$path]['lines'][$line] = $matches[1];
                $line++;
            } elseif (!preg_match("~^-+.*~", $row, $matches)) {
                $line++;
            }
        }
        return $out;
    }
}
