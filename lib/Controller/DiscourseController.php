<?php
namespace OCA\DiscourseSSO\Controller;

use OCP\IRequest;
use OCP\IConfig;
use OCP\AppFramework\Http\TemplateResponse;
use OCP\AppFramework\Http\DataResponse;
use OCP\AppFramework\Http\RedirectResponse;
use OCP\AppFramework\Controller;
use OCP\IUserManager;
use OCP\IGroupManager;
use OCP\ILogger;
use OCP\IUserSession;
use Cviebrock\DiscoursePHP\SSOHelper;

class DiscourseController extends Controller {
	private $userId;
	private $config;
	private $logger;
	private $userManager;
	private $userSession;
	private $groupManager;

	public function __construct($AppName, IRequest $request, IConfig $config, IUserManager $userManager, IGroupManager $groupManager, ILogger $logger, IUserSession $userSession, $UserId){
		parent::__construct($AppName, $request);
		$this->userId = $UserId;
		$this->config = $config;
		$this->logger = $logger;
		$this->userManager = $userManager;
		$this->userSession = $userSession;
		$this->groupManager = $groupManager;
	}

	private function replaceWhitespaces($string) {
		$replaceString = $this->config->getAppValue($this->appName, 'replace_whitespaces', '');
		if ($replaceString !== '') {
				$string = preg_replace('/\s+/', $replaceString, $string);	
				return $string;
		} else {
				return $string;
		}
}


	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function sso($sso, $sig) {
		$ssoHelper = new SSOHelper();

		// this should be the same in your code and in your Discourse settings:
		$secret = $this->config->getAppValue($this->appName, 'clientsecret', '');
		$this->logger->error('secret: '.$secret, array('app' => 'discoursesso'));
		$ssoHelper->setSecret( $secret );

		// load the payload passed in by Discourse
		$payload = $sso;
		$signature = $sig;

		// validate the payload
		if (!($ssoHelper->validatePayload($payload,$signature))) {
		    // invaild, deny
		    header("HTTP/1.1 403 Forbidden");
		    echo("Bad SSO request");
		    die();
		}

		$nonce = $ssoHelper->getNonce($payload);

		$user = $this->userManager->get($this->userId);
		
                $add_groups = '';
                $remove_groups = '';
                $allGroups = $this->groupManager->search(null, null, null);
                foreach($allGroups as $group) {
                        if (!($this->groupManager->isInGroup($this->userId, $group->getGID()))) {
                          $remove_groups = $remove_groups.$group->getGID().',';
                        } else {
                          $add_groups = $add_groups.$group->getGID().',';
                        }
                }

                $userId = $this->userId;
                $userEmail = $user->getEMailAddress();

                $extraParameters = array(
					 //'username' => $this->replaceWhitespaces($userId),                  # 20190109 NextCloud Displayname as Username in Discourse
					 'username' => $this->replaceWhitespaces($user->getDisplayName()),  
                     'name'     => $user->getDisplayName(),
                     'add_groups' => $this->replaceWhitespaces($add_groups),
                     'remove_groups' => $this->replaceWhitespaces($remove_groups),
                     'groups' => $this->replaceWhitespaces($add_groups)
                );

		// build query string and redirect back to the Discourse site
		//$query = $ssoHelper->getSignInString($nonce, $this->replaceWhitespaces($userId), $userEmail, $extraParameters);
		$query = $ssoHelper->getSignInString($nonce, $this->replaceWhitespaces($user->getDisplayName()), $userEmail, $extraParameters); // # 20190109 NextCloud Displayname as Username in Discourse
		$url = $this->config->getAppValue($this->appName, 'clienturl', '');
		$this->logger->error('url: '.$url, array('app' => 'discoursesso'));

		return new RedirectResponse($url . '/session/sso_login?' . $query);
	}

	/**
	 * CAUTION: the @Stuff turns off security checks; for this page no admin is
	 *          required and no CSRF check. If you don't know what CSRF is, read
	 *          it up in the docs or you might create a security hole. This is
	 *          basically the only required method to add this exemption, don't
	 *          add it to any other method if you don't exactly know what it does
	 *
	 * @NoAdminRequired
	 * @NoCSRFRequired
	 */
	public function logout() {
		$this->userSession->logout();
		$url = $this->config->getAppValue($this->appName, 'clienturl', '');
		return new RedirectResponse($url);
	}

}
