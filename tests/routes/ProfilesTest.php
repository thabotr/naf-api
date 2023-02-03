<?php
namespace resource;

require_once(realpath(dirname(__FILE__) . '/../../vendor/autoload.php'));
require_once(realpath(dirname(__FILE__) . '/CommonTest.php'));

use middleware\rules\UserNotFoundException;

class ProfilesTest extends CommonTest
{
  public function testGetProfilesConnectedUsersReturnsProfilesForConnectedUsers(): void
  {
    $likeProfile = function ($user) {
      return array("handle" => $user->handle);
    };

    $this->setUserConnections();
    $response = $this->client->get(
      'profiles/connected-users',
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

  public function testPostMyProfileCreatesProfileOnValidCredentials(): void
  {
    $user = new Profile(-1, "w/aHandle", "ATokenThatsValid");
    $this->assertUserNotRegistered($user);
    $response = $this->client->post(
      $this->myProfileURL,
      ["auth" => [$user->handle, $user->token, 'basic']]
    );
    $this->assertEquals(201, $response->getStatusCode());
    $this->assertEquals(
      "Notifications Are Free $user->handle!",
      $this->responseToString($response),
    );
    $this->assertUserRegistered($user);
    $this->clearUser($user);
  }

  public function testPostMyProfileReturnsBadRequestOnBadCredentials(): void
  {
    $badHandle = "w/test=Handle6";
    $token = "testToken";
    $responseForBadHandle = $this->client->post(
      $this->myProfileURL,
      ['auth' => [$badHandle, $token, 'basic'], 'http_errors' => false]
    );
    $this->assertEquals(400, $responseForBadHandle->getStatusCode());
    $this->assertEquals(
      "invalid handle '$badHandle'. Valid handle matches regexp 'w/[a-zA-Z0-9-_]+'",
      $this->responseToString($responseForBadHandle),
    );

    $handle = "w/testHandle6";
    $badToken = "shortPw";
    $responseForBadToken = $this->client->post(
      $this->myProfileURL,
      ['auth' => [$handle, $badToken, 'basic'], 'http_errors' => false]
    );
    $this->assertEquals(400, $responseForBadToken->getStatusCode());
    $this->assertEquals(
      "token too weak. Must be atleast 8 characters",
      $this->responseToString($responseForBadToken)
    );
  }

  public function testPostMyProfileReturnsConflictOnRegisteredUser(): void
  {
    $response = $this->client->post(
      $this->myProfileURL,
      [
        'auth' => [$this->me->handle, $this->me->token, 'basic'],
        'http_errors' => false
      ]
    );
    $this->assertEquals(409, $response->getStatusCode());
    $this->assertEquals(
      "We already know someone by the handle '" . $this->me->handle . "'",
      $this->responseToString($response),
    );
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
    $this->assertEquals(
      "Notifications Are Free and so are you! Cheers😉",
      $this->responseToString($response),
    );
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

  protected $myProfileURL = "profiles/my-profile";
}