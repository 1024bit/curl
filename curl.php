<?php
/** 
 * Emulate the browser's behavior to request url
 *   
 * @param url           {String}              target url
 * @param data          {Array/String}        post data
 * @param headers       {Array}               set http request header
 * @param setheader     {Boolean}             set http response header or not
 * @param setcookie     {Boolean}             set http response cookies or not
 * @param withcookie    {Boolean}             request with cookie or not, if true, then setcookie must be true
 * @param cookiedomain  {String}              change the cookie's domain manually
 *
 * @return {Array}
 **/
function curl($url, $data = null, $headers = array(), $setheader = FALSE, $setcookie = FALSE, $withcookie = TRUE, $cookiedomain) {
    $ch = curl_init();
    curl_setopt($ch, CURLOPT_URL, $url);
        
    curl_setopt($ch, CURLOPT_HEADER, TRUE);
    curl_setopt($ch, CURLOPT_FOLLOWLOCATION, FALSE);

    // curl_setopt($ch, CURLOPT_VERBOSE, TRUE);
    // $curl_debug_file = __DIR__.DIRECTORY_SEPARATOR.'curl.log';
    // $fh = fopen($curl_debug_file, "w+");
    // curl_setopt($ch, CURLOPT_STDERR, $fh);    
    
    if ($withcookie) {
        array_push($headers, 'Cookie: '.str_cookie());
    }
    // Resolve two http response status code problem
    array_push($headers, 'Expect:');
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headers);
    
    if (!empty($data)){
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
    }
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE);
    // Timeout (seconds)
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 3);
    $out = curl_exec($ch);

    $status_code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    list($headers, $body) = explode("\r\n\r\n", $out, 2);

    preg_match('|^HTTP\/1.+\\n|', $headers, $status_text);
    $status_text = $status_text[0];
    preg_match_all('|(.+?): (.+?)\\n|', $out, $matches);  
    $headers = array();
    $headers['Set-Cookie'] = array();
    
    // Extract cookies from the headers
    foreach ($matches[1] as $key => $val) {
        if ($val !== 'Set-Cookie') {
            $headers[$val] = $matches[2][$key];
        } else {
            array_push($headers['Set-Cookie'], $matches[2][$key]);
        }
    }
    
    curl_close($ch);
    
    // HTTP status
    header($status_text);
    
    if ($setcookie) {
        foreach ($headers['Set-Cookie'] as $cookie) {
            $tmp = explode('; ', $cookie, 2);
            $keyval = explode('=', $tmp[0]);
            preg_match('|(expires=(.+?); )?(path=(.+?); )?(domain=(.+?); )?(secure; )?(httponly)?|', $tmp[1], $matches);
            
            $expires =  !empty($matches[1]) ? strtotime($matches[2]) : 0;
            $path =  !empty($matches[3]) ? $matches[4] : '/';
            $domain =  !empty($matches[5]) ? $matches[6] : '';
            $secure =  !empty($matches[7]) ? $matches[7] : '';
            $httponly =  !empty($matches[8]) ? $matches[8] : '';
            // Don't override the domain is defined explictly 
            if ($cookiedomain && ($domain == '')) {
                $domain = $cookiedomain;
            }

            setcookie($keyval[0], $keyval[1], $expires, $path, $domain, $secure, $httponly);
            $_COOKIE[$keyval[0]] = $keyval[1];
        }
    }
    
    if ($setheader) {
        unset($headers['Set-Cookie']);
        foreach ($headers as $key => $header) {  
            // We need a complete response, Content-Length will truncate the response
            if ($key !== 'Content-Length') {
                header($key.': '.$header);
            }        
        }        
    }
        
    return array('cookies' => $_COOKIE, 'status' => array('status_code' => $status_code, 'status_text' => $status_text), 'headers' => $headers, 'body' => $body, 'response' => $out);
}
// String $_COOKIE
function str_cookie() {
    $ckstr = '';
    foreach ($_COOKIE as $key => $val) {
        $ckstr .= $key.'='.urlencode($val).'; ';
    }
    return substr($ckstr, 0, -2);
}
?>
