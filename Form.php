<?php

/**
 * Form.php
 *
 * @author ykiwng
 * @see https://github.com/creasty/form-php
 */

mb_language('ja');
session_start();

if (!defined('CAPTCHA_SCRIPT'))
	define('CAPTCHA_SCRIPT', '/lib/captcha/get.php');

/**
 * Form
 */
class Form {
	public function __construct($config = array()) {
		$config += array(
			'prefix'      => 'form',
			'ajax'        => true,
			'mail'        => false,
			'date_format' => 'Y/m/d',
			'time_format' => 'H:i:s',
			'nonce'       => '',
		);

		$this->last_status = array(
			'processed'     => false,
			'error_message' => array(),
			'error_count'   => 0,
		);

		$this->config     = $config;
		$this->validators = array();
		$this->html       = new Form_Html($this);
		$this->called     = false;

		if ('POST' != $_SERVER['REQUEST_METHOD'])
			return;

		if ($config['ajax'] && !isset($_POST['_ajax_call']))
			return;

		if ($config['nonce'] && !$this->check_nonce())
			if ($config['ajax'])
				exit;
			else
				return;

		$this->called = true;
	}

	public function submit($arg = null) {
		if (!$this->called)
			return;

		$error = array();
		$processed = true;
		$error_count = 0;

		foreach ($this->validators as $name => $validator) {
			if (!$validator->is_valid()) {
				$error[$name] = $validator->error;
				$error_count++;
			} elseif ($validator->meta['required']) {
				$error[$name] = 'valid';
			}
		}

		if ($error_count > 0)
			$processed = false;
		else
			$processed &= $this->save($arg);

		if ($processed && $this->config['mail'])
			$processed &= $this->send($arg);

		$this->last_status = array(
			'processed' => $processed,
			'error_message' => $error,
			'error_count' => $error_count,
		);

		$this->post_process();
	}

	public function ajax() {
		if (!$this->called)
			return;

		header('Content-type: application/json', true);

		echo json_encode($this->last_status);

		exit;
	}

	public function get_data($flatten = false) {
		if ($this->last_data)
			return $this->last_data;

		$data = array();

		foreach ($this->validators as $name => $validator) {
			if (!isset($validator->value))
				continue;

			if ($validator->meta['multi']) {
				$res = array();

				foreach ($validator->value as $val) {
					$res[] = $validator->meta['option'][$val - 1];
				}

				if ($flatten)
					$res = implode("\n", $res);

				$data[$name] = $res;
			} elseif (isset($validator->meta['option'])) {
				$data[$name] = $validator->meta['option'][$validator->value - 1];
			} else {
				$data[$name] = $validator->value;
			}
		}

		return $this->last_data = $data;
	}

	public function save($arg = null) {
		return true;
	}

	public function send($arg = null) {
		if (!$arg || !isset($arg['from'], $arg['subject'], $arg['body']))
			return;

		$mail_from = $arg['from'];

		if (preg_match('|^(.+?) <(.+?)>$|u', $mail_from, $m)) {
			$m[1] = mb_encode_mimeheader($m[1]);
			$arg['from'] = $m[2];
			$mail_from = "{$m[1]} <{$m[2]}>";
		}

		$data = $this->get_data(true);
		$data['DATE'] = date($this->config['date_format']);
		$data['TIME'] = date($this->config['time_format']);

		if (!$arg)
			return false;

		$body = $arg['body'];

		foreach ($data as $key => $value) {
			$body = str_replace('{{' . $key . '}}', $value, $body);
		}

		$body = preg_replace('|{{[\w\-\+\&]+}}|u', '', $body);
		$body = trim($body);
		$body = strip_tags($body);
		$body = wordwrap($body, 70);

		$header = array();
		$header[] = 'From: ' . $mail_from;

		if ($arg['cc'])
			$header[] = 'Cc: ' . $arg['cc'];

		if ($arg['bcc'])
			$header[] = 'Bcc: ' . $arg['bcc'];

		if ($arg['reply'])
			$header[] = 'Reply-To: ' . $arg['reply'];

		$header = array_map(array(&$this, 'remove_line_feeds'), $header);
		$header = implode("\n", $header);

		if (!$arg['to'])
			$mail_to = $arg['from'];
		elseif (strpos($arg['to'], '@'))
			$mail_to = $arg['to'];
		else
			$mail_to = $data[$arg['to']];

		return @mb_send_mail($mail_to, $subject, $body, $header);
	}

	private function remove_line_feeds($str) {
		return str_replace(array("\r\n","\r","\n"), '', $str);
	}

	public function post_process() {
		if ($this->config['ajax'])
			$this->ajax();
	}

	private function check_nonce() {
		$name = $this->get_name('nonce');
		$post = &$_POST[$name];
		$session = &$_SESSION[$name];

		return isset($post, $session) && !empty($post) && $post == $session;
	}

	public function get_name($name) {
		$name = preg_replace('|[^\w\-]|', '', $name);
		return $this->config['prefix'] . '-' . $name;
	}

	public function add($name, $requried = false) {
		if (!$name || empty($name))
			return '';

		$validator = new Form_Validator($this, $name);
		$validator->required($requried);
		return $this->validators[$name] = $validator;
	}

	public function add_captcha() {
		return $this->add('captcha', true)->check_captcha();
	}
}


/**
 * Validator
 */
class Form_Validator {
	public static $pattern = array(
		'email'  => '|^[a-zA-Z][\w\+\.\-]{3,}\@[a-z\.]+?\.[a-z]{2,4}$|u',
		'tel'    => '%^(0\d{9}|0[5789]0\d{8})$%u',
		'credit' => '/^(?:4[0-9]{12}(?:[0-9]{3})?|5[1-5][0-9]{14}|6011[0-9]{12}|3(?:0[0-5]|[68][0-9])[0-9]{11}|3[47][0-9]{13})$/',
		'url'    => '/^(http|https|ftp):\/\/([A-Z0-9][A-Z0-9_-]*(?:\.[A-Z0-9][A-Z0-9_-]*)+):?(\d+)?\/?/i',
	);

	public function __construct($form, $name) {
		preg_match('|^([\w\-]+)([\+])?(&)?$|', $name, $m);

		$name = $form->get_name($name);

		$val = $_POST[$name];
		$val = is_array($val)
			? array_map(array(&$this, 'sanitize'), $val)
			: $this->sanitize($val);

		$this->form   = $form;

		$this->interr = false;
		$this->called = $form->called;

		$this->name   = $name;
		$this->value  = $val;
		$this->meta   = array();
		$this->valid  = true;
		$this->error  = 'none';

		/*	Meta
		-----------------------------------------------*/
		$this->meta['name'] = $this->meta['id'] = $form->get_name($m[1]);

		if ($m[2]) {
			$this->meta['multi'] = true;
			$this->meta['name'] .= '[]';
		}

		if ($m[3])
			$this->meta['option'] = array();

		return $this;
	}

	public function is_valid() {
		return $this->called && $this->valid;
	}

	/*	Util
	-----------------------------------------------*/
	private function sanitize($val) {
		if (!isset($val))
			return '';

		$val = str_replace("\0", '', $val);
		$val = trim($val);

		if ('0' === $val)
			$val = 0;
		elseif (preg_match('|^[\+\-]?[1-9]\d*$|u', $val))
			$val = (int) $val;
		elseif (preg_match('|^[\+\-]?\d+?\.\d+$|u', $val))
			$val = (float) $val;

		return $val;
	}
	private function value_size() {
		$val = $this->value;

		if (!isset($val) || !is_numeric($val) && empty($val))
			return 0;
		elseif (is_array($val))
			return sizeof($val);
		else
			return 1;
	}

	private function reject($message = 'unknown') {
		$this->valid = false;
		$this->error = $message;
	}

	private function interrupt() {
		return $this->interr || !$this->valid || !$this->called;
	}

	/*	Data provide
	-----------------------------------------------*/
	public function set_option($option) {
		if (!is_array($option)) {
			$this->reject('option_not_array');
			return $this;
		}

		$this->meta['option'] = $option;
		$this->meta['type'] = $this->meta['multi'] ? 'checkbox' : 'radio';

		if ($this->interrupt())
			return $this;

		if (0 === $this->value) {
			if ($this->meta['required'])
				$this->reject('required');
			else
				$this->interr = true;

			return $this;
		}

		$values = is_array($this->value) ? $this->value : array($this->value);
		$size = sizeof($option);

		foreach ($values as $val) {
			if (!is_numeric($val) || $val < 0 || $val > $size) {
				$this->reject('unexpected');
				break;
			}
		}

		return $this;
	}
	public function set_val($text) {
		if (!$this->meta['option'])
			$val = array_search($text, $this->meta['option']);
		else if (0 !== $this->value && empty($this->value))
			$this->value = ($val = $text);

		if (isset($val))
			$this->meta['default'] = $val;

		return $this;
	}

	/*	Low level rules
	-----------------------------------------------*/
	public function required($bool) {
		if ($this->interrupt())
			return $this;

		$this->meta['required'] = $bool;

		if (!is_numeric($this->value) && empty($this->value)) {
			if ($bool)
				$this->reject('required');
			else
				$this->interr = true;
		}

		return $this;
	}
	public function minimum($min) {
		if ($this->interrupt())
			return $this;

		if ($this->value < $min)
			$this->reject(array('minimum', $min));

		return $this;
	}
	public function maximum($max) {
		if ($this->interrupt())
			return $this;

		if ($this->value > $max)
			$this->reject(array('maximum', $max));

		return $this;
	}
	public function minlen($min) {
		if ($this->interrupt())
			return $this;

		if (mb_strlen((string) $this->value) < $min)
			$this->reject(array('minlen', $min));

		return $this;
	}
	public function maxlen($max) {
		if ($this->interrupt())
			return $this;

		if (mb_strlen((string) $this->value) > $max)
			$this->reject(array('maxlen', $max));

		return $this;
	}
	public function minselect($min) {
		if ($this->interrupt())
			return $this;


		if ($this->value_size() < $min)
			$this->reject(array('minselect', $min));

		return $this;
	}
	public function maxselect($max) {
		if ($this->interrupt())
			return $this;

		if ($this->value_size() > $max)
			$this->reject(array('maxselect', $max));

		return $this;
	}
	public function select($num) {
		if ($this->interrupt())
			return $this;

		if ($this->value_size() !== $num)
			$this->reject(array('select', $max));

		return $this;
	}

	/*	Rule & Filter
	-----------------------------------------------*/
	private function _func_call($call, $filter, $arg = array()) {
		$filter = "validation_filter_$filter";
		array_shift($arg);
		array_unshift($arg, $call, $this->value);

		if (function_exists($filter)) {
			$res = call_user_func_array($filter, $arg);

			if ($change)
				$this->value = $res;

			return $res;
		}

		return null;
	}
	public function format($filter) {
		$arg = func_get_args();

		$this->_func_call('format', $filter, $arg);

		return $this;
	}
	public function filter($rule, $arg = null) {
		if ($this->interrupt())
			return $this;

		if (preg_match('|^[a-z_]\w+$|', $rule)) {
			if (self::$pattern[$rule]) {
				if (!preg_match(self::$pattern[$rule], $this->value))
					$this->reject('invalid');
			} else {
				$_arg = isset($arg) ? $arg : func_get_args();

				if (false === $this->_filter('filter', $rule, $_arg))
					$this->reject('invalid');
			}
		} else { // regexp
			if (!preg_match($rule, $this->value)) {
				$this->reject('invalid');
			}
		}

		return $this;
	}
	public function type($type) {
		$arg = func_get_args();

		$this->meta['type'] = $type;
		$this->filter($type, $arg);

		return $this;
	}

	/*	Conditional
	-----------------------------------------------*/
	public function when($case, $func) {
		if (!$this->called && is_callable($func)) {
			call_user_func($func, $this->form);
			return $this;
		}

		if ($this->interrupt())
			return $this;

		if ($case == $this->value) {
			call_user_func($func, $this->form);
		}

		return $this;
	}

	/*	Captcha
	-----------------------------------------------*/
	public function check_captcha() {
		if ($this->interrupt())
			return $this;

		$value = strtolower($this->value);

		$captcha = &$_SESSION['captcha'];

		if (!isset($captcha) || $value != $captcha)
			$this->reject('invalid');

		return $this;
	}
}


/**
 * Form Html Helper
 */
class Form_Html {
	public function __construct($Form) {
		$this->form = $Form;
	}

	private function builder($tag, $attrs = array(), $content = null) {
		$att = '';

		foreach ($attrs as $key => $val) {
			if (is_bool($val)) {
				if ($val)
					$att .= " $key";
			} else {
				$att .= " $key=\"$val\"";
			}
		}

		return isset($content) ? "<$tag$att>$content</$tag>" : "<$tag$att />";
	}

	private function get_info($name) {
		$validator = $this->form->validators[$name];

		return $validator->meta;
	}

	public function text($name, $attrs = array()) {
		$info = $this->get_info($name);

		echo $this->builder('input', array(
			'type' => $info['type'] ? $info['type'] : 'text',
			'name' => $info['name'],
			'id'   => $info['id'],
		) + $attrs);
	}
	public function textarea($name, $attrs = array()) {
		$info = $this->get_info($name);

		echo $this->builder('textarea', array(
			'name' => $info['name'],
			'id'   => $info['id'],
		) + $attrs, '');
	}

	public function option($name, $attrs = array()) {
		$info = $this->get_info($name);

		for ($i = 0, $len = sizeof($info['option']); $i < $len; $i++) {
			echo '<li><label>';
			echo $this->builder('input', array(
				'type'  => $info['type'],
				'name'  => $info['name'],
				'value' => $i
			) + $attrs);
			echo ' ', $info['option'][$i];
			echo '</label></li>';
		}
	}
	public function select($name, $attrs = array()) {
		$info = $this->get_info($name);

		$option = $this->builder('option', array(
			'value' => 0,
			'disabled' => true,
			'selected' => !isset($info['default']),
		), $this->form->config['text_selectbox']);

		for ($i = 0, $len = sizeof($info['option']); $i < $len; $i++) {
			$option .= $this->builder('option', array(
				'value' => $i + 1,
				'selected' => (isset($info['default']) && $i === $info['default']),
			), $info['option'][$i]);
		}

		echo $this->builder('select', array(
			'name'     => $info['name'],
			'id'       => $info['id'],
			'multiple' => !!$info['multi'],
		) + $attrs, $option);
	}
	public function hidden($name, $value) {
		echo $this->builder('input', array(
			'type' => 'hidden',
			'name' => $this->form->get_name($name),
			'value' => $value,
		));
	}

	public function captcha($attrs = array()) {
		$this->text('captcha', $attrs);
	}
	public function captcha_image($attrs = array()) {
		echo $this->builder('img', array(
			'src' => CAPTCHA_SCRIPT,
			'id'  => 'captcha-image',
			'alt' => ''
		) + $attrs);
	}

	public function nonce() {
		$prefix = $this->form->config['prefix'];
		$key = $this->form->config['nonce'];
		$key = $nonce . hash_hmac('ripemd160', substr($nonce, 0, 8), $prefix . mt_rand());
		$nonce = hash_hmac('ripemd160', mt_rand(), $key);

		$_SESSION[$this->form->get_name('nonce')] = $nonce;

		$this->hidden('nonce', $nonce);
		echo "\n";
	}

}


/*=== Default Filters
==============================================================================================*/
function validation_filter_kana($call, $val, $option = '') {
	if ('format' == $call)
		return mb_convert_kana($val, $option);

	return true;
}

function validation_filter_hiragana($call, $val) {
	if ('filter' == $call)
		return !!preg_match('/^[ぁ-んー　\s]+$/u', $val);

	return true;
}

function validation_filter_katakana($call, $val) {
	if ('filter' == $call)
		return !!preg_match('/^[ァ-ヶー　\s]+$/u', $val);

	return true;
}

function validation_filter_datetime($call, $val, $format = '') {
	$p = date_parse_from_format($format, $val);

	if ('filter' == $call)
		return $p['error_count'] === 0;

	if ('format' == $call)
		return $p;
}



