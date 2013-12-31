<?php

namespace Destiny\Controllers;

use Destiny\Common\Utils\Date;
use Destiny\Common\Exception;
use Destiny\Common\ViewModel;
use Destiny\Common\Utils\Country;
use Destiny\Common\Annotation\Controller;
use Destiny\Common\Annotation\Route;
use Destiny\Common\Annotation\HttpMethod;
use Destiny\Common\Annotation\Secure;
use Destiny\Common\Annotation\Transactional;
use Destiny\Chat\ChatlogService;
use Destiny\Common\User\UserFeaturesService;
use Destiny\Common\User\UserService;
use Destiny\Common\Authentication\AuthenticationService;
use Destiny\Api\ApiAuthenticationService;
use Destiny\Common\Session;

/**
 * @Controller
 */
class UserAdminController {
	
	/**
	 * @Route ("/admin/user/{id}/edit")
	 * @Secure ({"ADMIN"})
	 * @HttpMethod ({"GET"})
	 *
	 * @param array $params        	
	 * @param ViewModel $model        	
	 * @throws Exception
	 * @return string
	 */
	public function adminUserEdit(array $params, ViewModel $model) {
		$model->title = 'User';
		if (! isset ( $params ['id'] ) || empty ( $params ['id'] )) {
			throw new Exception ( 'userId required' );
		}
		$user = UserService::instance ()->getUserById ( $params ['id'] );
		if (empty ( $user )) {
			throw new Exception ( 'User was not found' );
		}
		
		$userService = UserService::instance ();
		$userFeaturesService = UserFeaturesService::instance ();
		$apiAuthenticationService = ApiAuthenticationService::instance ();
		$chatlogService = ChatlogService::instance ();
		
		$user ['roles'] = $userService->getUserRolesByUserId ( $user ['userId'] );
		$user ['features'] = $userFeaturesService->getUserFeatures ( $user ['userId'] );
		$model->user = $user;
		$model->features = $userFeaturesService->getDetailedFeatures ();
		$ban = $userService->getUserActiveBan ( $user ['userId'] );
		$banContext = array ();
		if (! empty ( $ban )) {
			$banContext = $chatlogService->getChatLogBanContext ( $user ['userId'], Date::getDateTime ( $ban ['starttimestamp'] ), 18 );
		}
		$model->banContext = $banContext;
		$model->ban = $ban;
		$model->authSessions = $apiAuthenticationService->getAuthSessionsByUserId ( $user ['userId'] );
		$model->address = $userService->getAddressByUserId ( $user ['userId'] );
		
		if (Session::get ( 'modelSuccess' )) {
			$model->success = Session::get ( 'modelSuccess' );
			Session::set ( 'modelSuccess' );
		}
		
		return 'admin/user';
	}
	
	/**
	 * @Route ("/admin/user/{id}/edit")
	 * @Secure ({"ADMIN"})
	 * @HttpMethod ({"POST"})
	 * @Transactional
	 *
	 * @param array $params        	
	 * @param ViewModel $model        	
	 * @throws Exception
	 * @return string
	 */
	public function adminUserEditProcess(array $params, ViewModel $model) {
		$model->title = 'User';
		if (! isset ( $params ['id'] ) || empty ( $params ['id'] )) {
			throw new Exception ( 'userId required' );
		}
		
		$authService = AuthenticationService::instance ();
		$userService = UserService::instance ();
		$userFeatureService = UserFeaturesService::instance ();
		
		$user = $userService->getUserById ( $params ['id'] );
		if (empty ( $user )) {
			throw new Exception ( 'User was not found' );
		}
		
		$username = (isset ( $params ['username'] ) && ! empty ( $params ['username'] )) ? $params ['username'] : $user ['username'];
		$email = (isset ( $params ['email'] ) && ! empty ( $params ['email'] )) ? $params ['email'] : $user ['email'];
		$country = (isset ( $params ['country'] ) && ! empty ( $params ['country'] )) ? $params ['country'] : $user ['country'];
		
		$authService->validateUsername ( $username, $user );
		$authService->validateEmail ( $email, $user );
		if (! empty ( $country )) {
			$countryArr = Country::getCountryByCode ( $country );
			if (empty ( $countryArr )) {
				throw new Exception ( 'Invalid country' );
			}
			$country = $countryArr ['alpha-2'];
		}
		
		// Data for update
		$userData = array (
				'username' => $username,
				'country' => $country,
				'email' => $email 
		);
		$userService->updateUser ( $user ['userId'], $userData );
		$user = $userService->getUserById ( $params ['id'] );
		
		// Features
		if (! isset ( $params ['features'] ))
			$params ['features'] = array ();
			
			// Roles
		if (! isset ( $params ['roles'] ))
			$params ['roles'] = array ();
		
		$userFeatureService->setUserFeatures ( $user ['userId'], $params ['features'] );
		$userService->setUserRoles ( $user ['userId'], $params ['roles'] );
		$authService->flagUserForUpdate ( $user ['userId'] );
		
		Session::set ( 'modelSuccess', 'User profile updated' );
		
		return 'redirect: /admin/user/'.$user ['userId'].'/edit';
	}
}
