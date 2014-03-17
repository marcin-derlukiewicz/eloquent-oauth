<?php namespace AdamWathan\EloquentOAuth\Providers;

use AdamWathan\EloquentOAuth\ProviderUserDetails as UserDetails;
use AdamWathan\EloquentOAuth\ApplicationRejectedException;

abstract class Provider implements ProviderInterface
{
	protected $clientId;
	protected $clientSecret;
	protected $redirectUri;
	protected $scope;

	protected $headers = array(
		'authorize' => array(),
		'access_token' => array(),
		'user_details' => array(),
		);

	protected $accessToken;
	protected $providerUserData;

	public function __construct($config, $httpClient)
	{
		$this->httpClient = $httpClient;
		$this->clientId = $config['id'];
		$this->clientSecret = $config['secret'];
		$this->redirectUri = $config['redirect'];
		$this->scope = $config['scope'];
	}

	public function redirectUri()
	{
		return $this->redirectUri;
	}

	public function authorizeUrl()
	{
		$url = $this->getAuthorizeUrl();
		$url .= '?' . $this->buildAuthorizeQueryString();
		return $url;
	}

	protected function buildAuthorizeQueryString()
	{
		$queryString = "client_id=".$this->clientId;
		$queryString .= "&scope=".urlencode($this->compileScopes());
		$queryString .= "&redirect_uri=".$this->redirectUri;
		$queryString .= "&response_type=code";
		$queryString .= "&state=abc123";
		return $queryString;
	}

	protected function compileScopes()
	{
		return implode(',', $this->scope);
	}

	public function getUserDetails()
	{
		$this->accessToken = $this->requestAccessToken();
		$this->providerUserData = $this->requestUserData();
		return new UserDetails(array(
			'accessToken' => $this->accessToken,
			'userId' => $this->userId(),
			'nickname' => $this->nickname(),
			'firstName' => $this->firstName(),
			'lastName' => $this->lastName(),
			'email' => $this->email(),
			'imageUrl' => $this->imageUrl(),
			));
	}

	protected function getProviderUserData($key)
	{
		if (! isset($this->providerUserData[$key])) {
			return null;
		}
		return $this->providerUserData[$key];
	}

	protected function requestAccessToken()
	{
		$url = $this->getAccessTokenBaseUrl();
		$request = $this->httpClient->post($url, $this->headers['access_token'], $this->buildAccessTokenPostBody());
		$response = $request->send();
		return $this->parseTokenResponse((string) $response->getBody());
	}

	protected function requestUserData()
	{
		$url = $this->buildUserDataUrl();
		$request = $this->httpClient->get($url, $this->headers['user_details']);
		$response = $request->send();
		return $this->parseUserDataResponse((string) $response->getBody());
	}

	protected function buildAccessTokenPostBody()
	{
		$body = "code=".$this->getAuthorizationCode();
		$body .= "&client_id=".$this->clientId;
		$body .= "&client_secret=".$this->clientSecret;
		$body .= "&redirect_uri=".$this->redirectUri();
		$body .= "&grant_type=authorization_code";
		return $body;
	}

	protected function buildUserDataUrl()
	{
		$url = $this->getUserDataUrl();
		$url .= "?access_token=".$this->accessToken;
		return $url;
	}

	protected function getAuthorizationCode()
	{
		if (! isset($_GET['code'])) {
			throw new ApplicationRejectedException;
		}
		return $_GET['code'];
	}

	abstract protected function getAuthorizeUrl();
	abstract protected function getAccessTokenBaseUrl();

	abstract protected function parseTokenResponse($response);
	abstract protected function parseUserDataResponse($response);

	abstract protected function userId();
	abstract protected function nickname();
	abstract protected function firstName();
	abstract protected function lastName();
	abstract protected function email();
	abstract protected function imageUrl();
}
