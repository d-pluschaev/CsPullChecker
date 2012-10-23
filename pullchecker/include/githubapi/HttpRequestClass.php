<?php


class HTTPRequestClass
{
    public function performRequest($url, array $context_options, $no_http_code_check = false)
    {
        $context = stream_context_create($this->prepareContext($context_options));

        if (!$contents = @file_get_contents($url, false, $context)) {
            // throw exception if headers wasn't retrieved
            if (!$http_response_header) {
                $error = error_get_last();
                throw new HTTPRequestException($error['message'], 1);
            }
        }
        $headers = $this->parseHttpHeaders($http_response_header);
        $data = $this->getRequestData($headers, $contents);

        // is response valid? (Headers are OK and HTTP code == 200)
        if (!$no_http_code_check && $data['has_errors']) {
            throw new HTTPRequestException($data['error'], 2);
        }

        return array(
            'headers' => $headers,
            'body' => $contents,
            'data' => $data,
        );
    }

    protected function prepareContext(array $context_options)
    {
        if (isset($context_options['content']) && is_array($context_options['content'])) {
            $context_options['content'] = http_build_query($context_options['content']);
        }
        return array(
            'http' => $context_options
        );
    }

    protected function getRequestData($headers, $contents)
    {
        $out = array(
            'has_errors' => true,
            'error' => '',
            'content' => array(),
        );
        if (is_array($headers)) {
            if (isset($headers['Status-Line']['Status-Code'])) {
                if ($headers['Status-Line']['Status-Code'] == 200) {
                    if (isset($headers['Content-Type'])) {
                        $out['content'] = $this->convertContentAccordingToContentType(
                            $contents,
                            $headers['Content-Type']
                        );
                    }
                    $out['has_errors'] = false;
                } else {
                    $out['error'] = $headers['Status-Line']['Status-Code']
                        . ': ' . $headers['Status-Line']['Reason-Phrase'];
                }
            } else {
                $out['error'] = 'Headers are invalid or corrupted';
            }
        } else {
            $out['error'] = 'Headers not retrieved';
        }
        return $out;
    }

    protected function convertContentAccordingToContentType($contents, $content_type)
    {
        list($type, $parameter) = explode(';', $content_type);
        $type = trim($type);
        $parameter = trim($parameter);

        $out = array();
        switch ($type) {
            case 'application/json':
                $out = @json_decode($contents, true);
                break;
            case 'application/x-www-form-urlencoded':
                @parse_str($contents, $out);
                break;
        }
        return $out;
    }

    protected function parseHttpHeaders($headers)
    {
        $retVal = array();
        foreach ((array)$headers as $field) {
            if (preg_match('/([^:]+):(.+)/m', $field, $match)) {
                $match[1] = preg_replace(
                    '/(?<=^|[\x09\x20\x2D])./e',
                    'strtoupper("\0")',
                    strtolower(trim($match[1]))
                );
                $match[2] = trim($match[2]);

                if (isset($retVal[$match[1]])) {
                    if (is_array($retVal[$match[1]])) {
                        $retVal[$match[1]][] = $match[2];
                    } else {
                        $retVal[$match[1]] = array($retVal[$match[1]], $match[2]);
                    }
                } else {
                    $retVal[$match[1]] = $match[2];
                }
            } else if (preg_match('/([A-Za-z]+) (.*) HTTP\/([\d.]+)/', $field, $match)) {
                $retVal["Request-Line"] = array(
                    "Method" => $match[1],
                    "Request-URI" => $match[2],
                    "HTTP-Version" => $match[3]
                );
            } else if (preg_match('/HTTP\/([\d.]+) (\d+) (.*)/', $field, $match)) {
                $retVal["Status-Line"] = array(
                    "HTTP-Version" => $match[1],
                    "Status-Code" => $match[2],
                    "Reason-Phrase" => $match[3]
                );
            }
        }
        return $retVal;
    }
}


class HTTPRequestException extends Exception
{
    public function __construct($message, $code = 99)
    {
        parent::__construct(strip_tags($message), $code);
    }
}

