<?php
/**
 * Utility for creating, queuing and sending letters
 * Copyright (C) 2006  Anton Makarenko
 *
 * This library is free software; you can redistribute it and/or
 * modify it under the terms of the GNU Lesser General Public
 * License as published by the Free Software Foundation; either
 * version 2.1 of the License, or (at your option) any later version.
 *
 * This library is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the GNU
 * Lesser General Public License for more details.
 *
 * You should have received a copy of the GNU Lesser General Public
 * License along with this library; if not, write to the Free Software
 * Foundation, Inc., 51 Franklin Street, Fifth Floor, Boston, MA  02110-1301  USA
 *
 * @author  Anton Makarenko <php at ripfolio dot com>
 * @package Mailman
 * @version 0.7
 */
include_once "envelope.class.php";
include_once "smtp_socket.class.php";
class Mailman
{
	var $letter;
	var $charset;
	var $verbose=false;
	var $letters_queue=array();

	var $_log;

	/**
	 * Set charset for all letters, ceate new letter
	 *
	 * @param string $charset
	 * @return Mailman
	 */
	function Mailman($charset='UTF-8')
	{
		$this->charset=$charset;
		$this->queue_letter();
	}

	/**
	 * Build/validate current letter, copy it to queue, replace it with new empty
	 *
	 * Bad letters will not be queued. true will be returned if the letter will be queued
	 * New letter will be created in any case
	 *
	 * @return bool
	 */
	function queue_letter()
	{
		$result=false;
		if ((!empty($this->letter)) && (is_object($this->letter))) {
			if ($this->letter->build())
			{
				$result=true;
				$this->letters_queue[]=$this->letter;
			}
		}	
		$this->letter=&new Envelope($this->charset);
		return $result;
	}

	/**
	 * Queue current letter and try to send all queued letters via SMTP server
	 *
	 * If login & password are specified, function will try to authenticate
	 * in next sequence:
	 * AUTH CRAM-MD5
	 * AUTH LOGIN
	 *
	 * Commands executing will be stopped on any socket unexpected behaviour.
	 *
	 * Will return false, if letters sending sequence has not been started.
	 * Or will return quantity (int) of successfully sent queued letters.
	 *
	 * Sent letters are removed from queue.
	 *
	 * @param string $host
	 * @param string $smtp_login
	 * @param string $smtp_password
	 * @param int $port
	 * @param int $timeout
	 * @return false|int
	 */
	function send_via_smtp($host, $smtp_login='', $smtp_password='', $port=25, $timeout=15)
	{
		// there should be at least 1 queued letter
		$this->queue_letter();
		if (empty($this->letters_queue)) {
			echo '<br>NO QUEUE<br>';
			return false;
		}	

		// connect and try to authenticate if required
		$socket=new Smtp_socket(1/*$this->verbose*/);
		$this->_log=&$socket->log;
		if (!$socket->connect($host, $port, $timeout))
			return false;
		if ((!empty($smtp_login)) && (!empty($smtp_password)))
		{
			// look for authentication options
			$auth_options=$socket->execute('EHLO '.$_SERVER['HTTP_HOST'], 250);
			$is_authenticated=false;
			if (empty($auth_options))
			{
				$socket->disconnect();
				return false;
			}
			// AUTH CRAM-MD5
			if (preg_match('/AUTH.*?CRAM\-MD5/is', $auth_options))
			{
				$cram_334_request=$socket->execute('AUTH CRAM-MD5', 334);
				if ($cram_334_request)
					if (false!==$socket->execute(
							$this->generate_cram_md5(
								preg_replace('/^334\s+(.*?)\s*$/is', '\\1', $cram_334_request)
								,$smtp_login
								,$smtp_password)
							,235))
						$is_authenticated=true;
			}
			// AUTH LOGIN
			if ((!$is_authenticated) && (preg_match('/AUTH.*?LOGIN/is', $auth_options)))
				$is_authenticated=
					(bool)$socket->execute('RSET', 250)
					&&
					(bool)$socket->execute('AUTH LOGIN', 334)
					&&
					(bool)$socket->execute(base64_encode($smtp_login), 334)
					&&
					(bool)$socket->execute(base64_encode($smtp_password), 235);
			if (!$is_authenticated)
			{
				$socket->disconnect();
				return false;
			}
		}
		// dont authenticate
		elseif (!$socket->execute('HELO '.$_SERVER['HTTP_HOST'], 250))
		{
			$socket->disconnect();
			return false;
		}

		// walk through queued letters and try to send them
		$sent_letters_counter=0;
		foreach ($this->letters_queue as $k=>$l)
//		$l = $this->letter; 
		{
			$commands=array();
			$commands[]=array('RSET',                            250);
			$commands[]=array('MAIL FROM:<'.$l->from[0].'>',     250);
			foreach ($l->get_recipients() as $email_address)
				$commands[]=array('RCPT TO:<'.$email_address.'>', 250, 251);
			$commands[]=array('DATA',                            354);
			$commands[]=array($l->fetch()."\r\n.",               250);
			// push all commands to SMTP socket
			$is_letter_sent=true;
			foreach ($commands as $command)
				if (false===call_user_func_array(array(&$socket, 'execute'), $command)) {
//				        print_r($socket->log);
					$is_letter_sent=false;
				}	
			if ($is_letter_sent)
			{
//				unset($this->letters_queue[$k]);
				$sent_letters_counter+=1;
			}
		}
		$socket->execute('QUIT', 221);
		$socket->disconnect();
		return $sent_letters_counter;
	}

	function send_via_mail()
	{
		trigger_error(__CLASS__.'::'.__FUNCTION__.'() not implemented yet', E_USER_ERROR);
	}

	/**
	 * Obtain log contents in convenient format
	 *
	 * @param bool $pre
	 * @return string
	 */
	function get_log($pre=false)
	{
		if (empty($this->_log) || !$this->verbose)
			return '';
		$log=implode("\r\n", $this->_log);
		if ($pre)
			$log='<pre>'.htmlspecialchars($log, ENT_QUOTES).'</pre>';
		return $log;
	}

	/**
	 * Create response for CRAM-MD5 authentication request using login & password
	 *
	 * CRAM-MD5 authentication request is a base64-encoded string
	 * that means something like <timestamp@host>
	 * For example
	 * PDI4MjE5NzQwMDAuMjU1NDM5OUBkb21haW4uY29tPg==
	 * <2821974000.2554399@domain.com>
	 *
	 * This is an elaborated algorithm, taken from
	 * http://pear.php.net/package/Auth_SASL/
	 *
	 * @param string $base64_cram_request
	 * @param string $login
	 * @param string $password
	 * @return string
	 */
	function generate_cram_md5($base64_cram_request, $login, $password)
	{
		$decoded_request=base64_decode($base64_cram_request);
		// make password 64-byte binary string (rfc2104)
		if (strlen($password)<64)
			$key=str_pad($password, 64, chr(0));
		else
			$key=pack('H32', md5($password));
		$key=substr($key, 0, 64);
		$ipad=$key ^ str_repeat(chr(0x36), 64);
		$opad=$key ^ str_repeat(chr(0x5C), 64);
		// create digest and encode it with login (rfc2195)
		$digest=md5($opad.pack('H32', md5($ipad.$decoded_request)));
		return base64_encode($login.' '.$digest);
	}
}
?>