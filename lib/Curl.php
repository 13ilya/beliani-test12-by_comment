<?php

class Curl {

  private $_ch;
  private $_is_post = false;
  public $DEBUG = false;

  public function __construct($url = null, $options = array()) {
    $this->_ch = curl_init($url);
    $this->initialize($options);
  }

  public function __destruct() {
    curl_close($this->_ch);
    unset($this->_ch);
  }

  public function initialize($options = array()) {
    foreach ($options as $key => $value) {
      curl_setopt($this->_ch, $key, $value);
    }

    return true;
  }

  public function set_headers($options = null) {
    curl_setopt_array($this->_ch, $options);
  }

  public function set_url($url, $customrequest = false) {
    if (!filter_var($url, FILTER_VALIDATE_URL)) {
      throw new Exception('Invalid URL');
    }

    curl_setopt($this->_ch, CURLOPT_URL, $url);
    curl_setopt($this->_ch, CURLOPT_POST, $this->_is_post);
    if ($customrequest)
    {
        curl_setopt($this->_ch, CURLOPT_CUSTOMREQUEST, $customrequest);
    }
  }
  
  public function set_post($data, $url_form_encoded = true) {
    $this->_is_post = true;
    curl_setopt($this->_ch, CURLOPT_POST, true);
    if ($url_form_encoded) {
      $str = array();
      foreach ($data as $key => $value) {
        if ($value) {
          $str[] = urlencode($key) . '=' . urlencode($value);
        }
        else {
          $str[] = $key;
        }
      }
      $data = implode('&', $str);
    }
    curl_setopt($this->_ch, CURLOPT_POSTFIELDS, $data);
  }

  public function exec() {
    if ($this->DEBUG) {
      curl_setopt($this->_ch, CURLINFO_HEADER_OUT, true);
    }

    $result = curl_exec($this->_ch);
    $this->_is_post = false;
    if (curl_errno($this->_ch) && $this->DEBUG) {
      throw new Exception(curl_error($this->_ch));
    }

    if ($this->DEBUG) {
      $result = curl_getinfo($this->_ch, CURLINFO_HEADER_OUT) . $result;
    }

    return $result;
  }

  public function get_info($option = 0) {
    return curl_getinfo($this->_ch, $option);
  }

  public function set_referer($referer) {
    curl_setopt($this->_ch, CURLOPT_REFERER, $referer);
  }

  public function set_cookie($cookie = array()) {
    $_cookie = array();
    foreach ($cookie as $key => $value) {
      $_cookie[] = "$key=$value";
    }
    $_cookie = implode('; ', $_cookie);

    curl_setopt($this->_ch, CURLOPT_COOKIE, $_cookie);
    return true;
  }

  public function get_cookies($header) {
    $cookies = array();
    $rows = explode("\n", $header);

    for ($i = 0; $i < count($rows); $i++) {
      $header = $rows[$i];
      if (substr($header, 0, 11) == 'Set-Cookie:') {
        $pos = strpos($header, ';');
        if ($pos) {
          $header = substr($header, 12, $pos - 12);
        }
        else {
          $header = substr($header, 12, strlen($header) - 13);
        }

        $header = explode('=', $header);
        if (count($header) == 2) {
          $cookies[$header[0]] = $header[1];
        }
      }
    }
    return $cookies;
  }

}
