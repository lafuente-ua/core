<?
namespace core;

class curl extends \core\core
/** Curl API */
{
	public const CURL_TIMEOUT = 500;
	protected $_url = null;
	protected $_curl = null;
	protected $_post = false;
	protected $_headers = [];

	public function _construct($arg = null)
	{
		$this->_url = $arg;
		parent::_construct($arg);
	}
	public function jsonGet()
	{
		if($this->_url)
		{
			$this->_post = false;
			$this->_headers = ['Accept:application/json'];
			$this->_init();
			if($result = curl_exec($this->_curl))
			{
				if(($data = json_decode($result))
					&& (json_last_error() == JSON_ERROR_NONE))
					return $data;
				else
					return (object)['error'=>true,'raw'=>$result];
			}
		}
		return null;
	}
	public function jsonPost($data)
	{
		if($this->_url && is_array($data))
		{
			$this->_post = true;
			$this->_headers = [
				'Content-type:application/x-www-form-urlencoded',
				'Accept:application/json'];
			$this->_init(http_build_query($data));
			if($result = curl_exec($this->_curl))
			{
				if(($data = json_decode($result))
					&& (json_last_error() == JSON_ERROR_NONE))
					return $data;
				else
					return (object)['error'=>true,'raw'=>$result];
			}
		}
		return null;
	}
	public function jsonJson($data)
	{
		if($this->_url && 
			(is_array($data) || is_object($data)))
		{
			$this->_post = true;
			$this->_headers = [
				'Content-type:application/json',
				'Accept:application/json'];
			$this->_init(json_encode($data, JSON_UNESCAPED_UNICODE));
			if($result = curl_exec($this->_curl))
			{
				if(($data = json_decode($result))
					&& (json_last_error() == JSON_ERROR_NONE))
					return $data;
				else
					return (object)['error'=>true,'raw'=>$result];
			}
		}
		return null;
	}
	protected function _init($data = null)
	{
		$this->_curl = curl_init($this->_url);
		curl_setopt_array($this->_curl,
			[CURLOPT_POST => $this->_post,
			CURLOPT_POSTFIELDS => $data,
			CURLOPT_HTTPHEADER => $this->_headers,
			CURLOPT_FOLLOWLOCATION => true,
			CURLOPT_MAXREDIRS => 2,
			CURLOPT_CONNECTTIMEOUT => self::CURL_TIMEOUT,
			CURLOPT_SSL_VERIFYPEER => false,
			CURLOPT_SSL_VERIFYHOST => false,
			CURLOPT_TIMEOUT => self::CURL_TIMEOUT,
			CURLOPT_REFERER => $this->_url,
			CURLOPT_HEADER => false,
			CURLOPT_RETURNTRANSFER => true]);
		return $this;
	}
}
?>
