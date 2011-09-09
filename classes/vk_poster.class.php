<?

class vk_auth
{
	
	private $email = '';
	private $pwd = '';
	private $sleeptime = 1;
	private $minicurl;

	
	function __construct()
	{
		$this->email = VKEMAIL;
		$this->pwd = VKPWD;
		$this->sleeptime = SLEEPTIME;
		$this->minicurl = new minicurl(TRUE, COOKIES_FILE, 'Mozilla/5.0 (Windows NT 6.1; rv:2.0.1) Gecko/20100101 Firefox/4.0.1');
	}

	public function check_auth()
	{
		if($this->need_auth())
		{
			if(!$this->auth())
			{
				$this->put_error_in_logfile('Not authorised!');
				return FALSE;
			}
		}

		return TRUE;
	}

	public function post_to_user($user_id, $message, $friends_only = '')
	{
		// check_auth() - так ли тут нужно? по-моему, нет
		// act=post&al=1&attach1=1_1&attach1_type=photo&facebook_export=&friends_only=&hash=1&message=test&note_title=&official=&status_export=&to_id=1&type=all

		if (!is_numeric($user_id))
		{
			$this->put_error_in_logfile('$user_id - only numbers!');
			return FALSE;
		}

		$hash = $this->get_hash('id' . $user_id);
		if (empty($hash))
		{
			$this->put_error_in_logfile('JS-Field "post_hash" not found!');
			return FALSE;
		}

		$post = array(
			'act' => 'post',
			'al' => '1',
			'facebook_export' => '',
			'friends_only' => $friends_only,
			'hash' => $hash,
			'message' => $message,
			'note_title' => '',
			'official' => '',
			'status_export' => '',
			'to_id' => $user_id,
			'type' => 'feed',
		);

		if(!$this->post_to_wall_query($post))
		{
			$this->put_error_in_logfile('Message not posted!');
			return FALSE;
		}

		return TRUE;
	}

	public function post_to_group($group_id, $message, $official = '')
	{
// act=post&al=1&facebook_export=&friends_only=&hash=e7c66a3e49eb5d5744&message=test&note_title=&official=&status_export=&to_id=-16153068&type=all
// club16153068
// admin act=post&al=1&facebook_export=&friends_only=&hash=00cbb6eaa0b44a3843&message=test&note_title=&official=1&status_export=&to_id=-15014694&type=all
	}

	public function post_to_public_page($page_id, $message)
	{
		$post = array(
			'act' => 'post',
			'al' => '1',
			'facebook_export' => '',
			'friends_only' => '',
			'hash' => $hash,
			'message' => $message,
			'note_title' => '',
			'official' => '',
			'status_export' => '',
			'to_id' => '-' . $page_id, //!!!!!!
			'type' => 'all', // own |
		);
	}

	private function need_auth()
	{
		$result = $this->minicurl->get_file('http://vkontakte.ru/settings');
		$this->sleep();
		return strpos($result, 'HTTP/1.1 302 Found') !==FALSE;
	}

	private function auth()
	{
		$this->minicurl->clear_cookies();

		$location = $this->get_auth_location();
		if($location === FALSE){
			$this->put_error_in_logfile('Not recieved Location!');
			return FALSE;
		}

		$sid = $this->get_auth_cookies($location);
		if(!$sid){
			$this->put_error_in_logfile('Not received cookies!');
			return FALSE;
		}

		$this->minicurl->set_cookies('remixsid=' . $sid . '; path=/; domain=.vkontakte.ru');

		return TRUE;
	}

	private function get_hash($page_id)
	{
		$result = $this->minicurl->get_file('http://vkontakte.ru/' . $page_id);

		preg_match('#"post_hash":"([^"]+)"#isU', $result, $match);

		if (strpos($result, 'action="https://login.vk.com/?act=login'))
		{
			unset($match[1]);
		}

		$this->sleep();
		return ((isset($match[1])) ? $match[1] : '');
	}

	private function get_auth_location()
	{
		$html = $this->minicurl->get_file('http://vkontakte.ru/');
		preg_match('#<input type="hidden" name="ip_h" value="([a-z0-9]*?)" \/>#isU', $html, $matches);

		$post = array(
			'act' => 'login',
			'al_frame' => '1',
			'captcha_key' => '',
			'captcha_sid' => '',
			'email' => $this->email,
			'expire' => '',
			'from_host' => 'vkontakte.ru',
			'ip_h' => (isset($matches[1]) ? $matches[1]: ''),
			'pass' => $this->pwd,
			'q' => '1',
		);

		$auth = $this->minicurl->get_file('http://login.vk.com/?act=login', $post, 'http://vkontakte.ru/');
		preg_match('#Location\: ([^\r\n]+)#is', $auth, $match);

		$this->sleep();
		return ((isset($match[1])) ? $match[1] : FALSE);
	}

	private function get_auth_cookies($location)
	{
		$result = $this->minicurl->get_file($location);

		$this->sleep();
		return ((strpos($result, "setCookieEx('sid', ") === FALSE) ? FALSE :
				substr($result, strpos($result, "setCookieEx('sid', '") + 20, 60));
	}


	private function post_to_wall_query($post)
	{
		$result = $this->minicurl->get_file('http://vkontakte.ru/al_wall.php', $post);

		$this->sleep();
		preg_match('#>\d<!>\d+<!>([\d]+)<!>#isU', $result, $match);

		return (isset($match[1]) AND ($match[1] == '0'));
	}

	private function sleep()
	{
		if ($this->sleeptime)
		{
			sleep($this->sleeptime + rand(1, 4));
		}
	}

	public function print_last_error()
	{
		$errors = array_reverse(file(LOG_FILE));
		return '<b>Error!</b><br>' . $errors[0];
	}

	private function put_error_in_logfile($msg)
	{
		$msg = '[' . date('Y.m.d H:i:s') . ']: ' . $msg . "\n";
		$fp = fopen(LOG_FILE, 'a');
		fwrite($fp, $msg);
		fclose($fp);
	}
}

?>