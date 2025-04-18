<?php
include_once "letter.class.php";
/**
 * Utility for setting headers for a letter
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
 * @version 0.4
 */
class Envelope extends Letter
{
	var $to       = array();
	var $subject  = '';
	var $from     = '';

	var $reply_to = '';
	var $cc       = array();
	var $bcc      = array();

	var $_rcpt_to=array();
	var $_placeholder;

	/**
	 * Set letter message and headers charset, set default headers placeholder
	 *
	 * @param string $charset
	 * @return Envelope
	 */
	function Envelope($charset='UTF-8')
	{
		$this->charset=$charset;
		$this->_placeholder='#?#';
	}

	function add_to($email_address, $name=null)
	{
		return $this->_set_attr(true, 'to', $email_address, $name);
	}

	function set_subject($subject)
	{
		$subject=@trim($subject);
		if (empty($subject))
			return false;
		$this->subject=$subject;
		return true;
	}

	function set_from($email_address, $name=null)
	{
		return $this->_set_attr(false, 'from', $email_address, $name);
	}

	function set_reply_to($email_address, $name=null)
	{
		return $this->_set_attr(false, 'reply_to', $email_address, $name);
	}

	function add_cc($email_address, $name=null)
	{
		return $this->_set_attr(true, 'cc', $email_address, $name);
	}

	function add_bcc($email_address, $name=null)
	{
		return $this->_set_attr(true, 'bcc', $email_address, $name);
	}

	/**
	 * Analyze all attributes and populate respective recipients and headers
	 *
	 * @return bool
	 */
	function build()
	{
		// obtain all recipients from attributes
		foreach (array('to', 'cc', 'bcc') as $attribute)
			if (!empty($this->$attribute))
				foreach ($this->$attribute as $rcpt)
					if (!empty($rcpt[0]))
						$this->_rcpt_to[]=$rcpt[0];
		// there must be sender and at least 1 recipient
		if (empty($this->from) || empty($this->_rcpt_to))
			return false;
		$this->_rcpt_to=array_unique($this->_rcpt_to);
		// add headers
		foreach (array(
			'to'        =>'To: '
			,'subject'  =>'Subject: '
			,'from'     =>'From: '
			,'reply_to' =>'Reply-To: '
			,'cc'       =>'Cc: '
//			,'bcc'      =>'BCc: ' "blind" copy :)
			)
			as $attribute=>$hdr)
			if (!empty($this->$attribute))
			{
				$attribute=$this->$attribute;
				if (!is_array($attribute))
				{
					// single attr
					$this->add_header($hdr.$this->base64_placeholders($this->_placeholder, $attribute));
				}
				else
				{
					if (!is_array($attribute[0]))
					{
						// composite attr
						if (isset($attribute[1]))
							$encoded=$this->base64_placeholders("\"$this->_placeholder\" <".$attribute[0].">", $attribute[1]);
						else
							$encoded=$attribute[0];
						$this->add_header($hdr.$encoded);
					}
					elseif (is_array($attribute[0]))
					{
						// multiple composite attr
						$encoded=array();
						foreach ($attribute as $attr)
						{
							if (isset($attr[1]))
								$encoded[]=$this->base64_placeholders("\"$this->_placeholder\" <".$attr[0].">", $attr[1]);
							else
								$encoded[]=$attr[0];
						}
						$this->add_header($hdr.implode(', ', $encoded));
					}
				}
			}
		// build letter
		return Letter::build();
	}

	/**
	 * Just obtain current letter recipients list
	 *
	 * @return array
	 */
	function get_recipients()
	{
		return (empty($this->_rcpt_to) ? array() : $this->_rcpt_to);
	}

	/**
	 * Parse placeholders in the string, replacing them with respective encoded base64 values
	 *
	 *
	 * @param string placeholdered haystack
	 * @param string [placeholders values, ...]
	 * @return string
	 */
	function base64_placeholders()
	{
		$args=func_get_args();
		$str=@array_shift($args);
		if (empty($str))
			return false;
		$str=explode($this->_placeholder, $str);
		foreach (array_keys($str) as $k)
			if ((isset($str[$k+1])) && (isset($args[$k])))
				$str[$k].='=?'.$this->charset.'?B?'.base64_encode($args[$k]).'?=';
		return implode($str);
	}

	/**
	 * Prepare email/name pair to attributes
	 *
	 * @param string $email_address
	 * @param string [$name]
	 * @return array
	 */
	function parse_email_name($email_address, $name=null)
	{
		$email_address =@trim($email_address);
		$name          =@trim($name);
		if (empty($email_address))
			return false;
		if (empty($name))
			return array($email_address);
		else
			return array($email_address, $name);
	}

	/**
	 * Set specified attribute value with specified email/name
	 *
	 * @param bool $is_arr
	 * @param string $attr
	 * @param string $email_address
	 * @param string $name
	 * @return bool
	 */
	function _set_attr($is_arr, $attr, $email_address, $name=null)
	{
		$result=$this->parse_email_name($email_address, $name);
		if (false===$result)
			return false;
		if ($is_arr)
			array_push($this->$attr, $result);
		else
			$this->$attr=$result;
		return true;
	}
}
?>