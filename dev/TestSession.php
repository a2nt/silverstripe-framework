<?php
/**
 * Represents a test usage session of a web-app
 * It will maintain session-state from request to request
 * @package sapphire
 * @subpackage testing
 */
class TestSession {
	private $session;
	private $lastResponse;
	
	/**
	 * @var string $lastUrl Fake HTTP Referer Tracking, set in {@link get()} and {@link post()}.
	 */
	private $lastUrl;

	function __construct() {
		$this->session = new Session(array());
	}
	
	/**
	 * Submit a get request
	 * @uses Director::test()
	 */
	function get($url) {
		$headers = ($this->lastUrl) ? array('Referer'=>$this->lastUrl) : null;
		$this->lastResponse = Director::test($url, null, $this->session, null, null, $headers);
		$this->lastUrl = $url;
		if(!$this->lastResponse) user_error("Director::test($url) returned null", E_USER_WARNING);
		return $this->lastResponse;
	}

	/**
	 * Submit a post request
	 * @uses Director::test()
	 */
	function post($url, $data, $headers = null) {
		$headers = ($this->lastUrl) ? array('Referer'=>$this->lastUrl) : null;
		$this->lastResponse = Director::test($url, $data, $this->session, null, null, $headers);
		$this->lastUrl = $url;
		if(!$this->lastResponse) user_error("Director::test($url) returned null", E_USER_WARNING);
		return $this->lastResponse;
	}
	
	/**
	 * Submit the form with the given HTML ID, filling it out with the given data.
	 * Acts on the most recent response
	 */
	function submitForm($formID, $button = null, $data = array()) {
		$page = $this->lastPage();
		if($page) {
			$form = $page->getFormById($formID);

			foreach($data as $k => $v) {
				$form->setField(new SimpleByName($k), $v);
			}

			if($button) $submission = $form->submitButton(new SimpleByName($button));
			else $submission = $form->submit();

			$url = Director::makeRelative($form->getAction()->asString());

			$postVars = array();
			parse_str($submission->_encode(), $postVars);
			return $this->post($url, $postVars);
			
		} else {
			user_error("TestSession::submitForm called when there is no form loaded.  Visit the page with the form first", E_USER_WARNING);
		}
	}
	
	/**
	 * If the last request was a 3xx response, then follow the redirection
	 */
	function followRedirection() {
		if($this->lastResponse->getHeader('Location')) {
			$url = Director::makeRelative($this->lastResponse->getHeader('Location'));
			$url = strtok($url, '#');
			return $this->get($url);
		}
	}
	
	/**
	 * Returns true if the last response was a 3xx redirection
	 */
	function wasRedirected() {
		$code = $this->lastResponse->getStatusCode();
		return $code >= 300 && $code < 400;
	}
	
	/**
	 * Get the most recent response, as an HTTPResponse object
	 */
	function lastResponse() {
		return $this->lastResponse;
	}
	
	/**
	 * Get the most recent response's content
	 */
	function lastContent() {
		if(is_string($this->lastResponse)) return $this->lastResponse;
		else return $this->lastResponse->getBody();
	}
	
	function cssParser() {
		return new CSSContentParser($this->lastContent());
	}

	
	/**
	 * Get the last response as a SimplePage object
	 */
	function lastPage() {
		require_once("thirdparty/simpletest/http.php");
		require_once("thirdparty/simpletest/page.php");
		require_once("thirdparty/simpletest/form.php");

		$builder = &new SimplePageBuilder();
		if($this->lastResponse) {
			$page = &$builder->parse(new TestSession_STResponseWrapper($this->lastResponse));
			$builder->free();
			unset($builder);
		
			return $page;
		}
	}
	
	/**
	 * Get the current session, as a Session object
	 */
	function session() {
		return $this->session;
	}
}

/**
 * Wrapper around HTTPResponse to make it look like a SimpleHTTPResposne
 */
class TestSession_STResponseWrapper {
	private $response;

	function __construct(HTTPResponse $response) {
		$this->response = $response;
	}
	
	function getContent() {
		return $this->response->getBody();
	}
	
	function getError() {
		return "";
	}
	
	function getSent() {
		return null;
	}
	
	function getHeaders() {
		return "";
	}
	
	function getMethod() {
		return "GET";
	}
	
	function getUrl() {
		return "";
	}
	
	function getRequestData() {
		return null;
	}
}