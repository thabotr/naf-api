<?php
namespace resource;

require_once(realpath(dirname(__FILE__) . '/../../../vendor/autoload.php'));
require_once(realpath(dirname(__FILE__) . '/common.php'));

use middleware\rules\UserNotFoundException;
use resource\HTTPResourceTests;

class ProfilesEndpointTests extends HTTPResourceTests
{

  public function testGetProfilesConnectedUsersReturnsProfilesForConnectedUsers(): void
  {
    $likeProfile = function ($user) {
      return array("handle" => $user->handle);
    };

    $this->setUserConnections();
    $response = $this->client->get(
      '/profiles/connected-users',
      ['auth' => [$this->me->handle, $this->me->token, 'basic']]
    );
    $this->assertEquals(200, $response->getStatusCode());
    $profiles = array_map($likeProfile, json_decode($response->getBody()));
    $expectedProfiles = array_map($likeProfile, $this->others);
    $this->assertEquals($expectedProfiles, $profiles);
    $this->clearUserConnections();
  }

  public function testGetMyProfileReturnsProfile(): void
  {
    $this->repo->add_user(array(
      "handle" => $this->me->handle,
      "token" => $this->me->token
    ));
    $response = $this->client->get(
      $this->myProfileURL,
      ['auth' => [$this->me->handle, $this->me->token, 'basic']]
    );
    $this->assertEquals(200, $response->getStatusCode());
    $profile = json_decode($response->getBody(), true);
    $this->assertEquals($this->me->handle, $profile['handle']);
  }

  public function testPOSTMyProfileCreatesProfileOnValidCredentials(): void
  {
    $user = new Profile(-1, "w/aHandle", "ATokenThatsValid");
    $this->assertUserNotRegistered($user);
    $response = $this->client->post(
      $this->myProfileURL,
      ["auth" => [$user->handle, $user->token, 'basic']]
    );
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertUserRegistered($user);
    $this->clearUser($user);
  }

  public function testPOSTMyProfileReturnsBadRequestOnBadCredentials(): void
  {
    $badHandle = "w/test=Handle6";
    $token = "testToken";
    $response = $this->client->post(
      $this->myProfileURL,
      ['auth' => [$badHandle, $token, 'basic'], 'http_errors' => false]
    );
    $this->assertEquals(400, $response->getStatusCode());
    
    $handle = "w/testHandle6";
    $badToken = "shortPw";
    $response2 = $this->client->post(
      $this->myProfileURL,
      ['auth' => [$handle, $badToken, 'basic'], 'http_errors' => false]
    );
    $this->assertEquals(400, $response2->getStatusCode());
  }

  public function testPOSTMyProfileReturnsConflictOnRegisteredUser(): void
  {
    $response = $this->client->post(
      $this->myProfileURL,
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'], 
        'http_errors' => false
      ]
    );
    $this->assertEquals(409, $response->getStatusCode());
  }

  public function testDeleteMyProfileRemovesAccount(): void
  {
    $user = new Profile(-1, "w/aHandle", "ATokenThatsValid");
    $this->setUser($user);
    $response = $this->client->delete(
      $this->myProfileURL,
      ["auth" => [$user->handle, $user->token, 'basic']]
    );
    $this->assertEquals(200, $response->getStatusCode());
    $this->assertUserNotRegistered($user);
    $this->clearUser($user);
  }

  private function assertUserNotRegistered(Profile $user): void
  {
    try {
      $this->repo->get_user_id_and_profile($user->handle, $user->token);
      $this->assertFalse(true); // fail since user already registered
    } catch (UserNotFoundException $_) {
    }
  }

  private function assertUserRegistered(Profile $user): void
  {
    [$id, $profile] = $this->repo->get_user_id_and_profile(
      $user->handle,
      $user->token
    );
    $user->id = $id;
    $this->assertEquals($user->handle, $profile["handle"]);
  }

  protected $myProfileURL = "/profiles/my-profile";
}