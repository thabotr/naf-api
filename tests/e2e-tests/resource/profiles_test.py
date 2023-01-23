from assertpy import assert_that
from routes import TestCaseWithHTTP, Routes, getAuthHeaders
from http import client
import json

profilesURL = f"{Routes.BASE_PATH}/profiles"


class GETProfiles(TestCaseWithHTTP):
    def testOKOnAuthedUser(self):
        """given a request by an authed user it should return their profile and status 'OK'"""
        expectedProfile = {
            "handle": self.handle,
        }
        self.conn.request('GET', profilesURL, headers=self.authed_headers)
        response = self.conn.getresponse()
        status = response.status
        body = response.read().decode("utf-8")
        assert_that(status).is_equal_to(client.OK)
        profile = json.loads(body)
        assert_that(profile).is_equal_to(expectedProfile)


class POSTProfiles(TestCaseWithHTTP):
    def postProfile(self, handle, token):
        auth_headers = getAuthHeaders(handle, token)
        self.conn.request('POST', profilesURL, headers=auth_headers)
        response = self.conn.getresponse()
        status = response.status
        body = response.read().decode("utf-8")
        return status, body

    valid_handle = "w/unknownHadle"
    valid_token = "an8token"
    weak_token_error = "token too weak. Must be atleast 8 characters"

    def get_invalid_handle_error(self, handle):
        return f"invalid handle '{handle}'. Valid handle matches regexp 'w/[a-zA-Z0-9-_]+'"

    def testBadRequestOnWeakToken(self):
        """given a request with a weak password it should return status 'Bad Request'"""
        empty_pw = ''
        status_4_token, body_4_token = self.postProfile(
            self.valid_handle, empty_pw)
        assert_that(status_4_token).is_equal_to(client.BAD_REQUEST)
        assert_that(body_4_token).is_equal_to(self.weak_token_error)

        not_gt_eight_chars_pw = 'pass'
        status_4_stoken, body_4_stoken = self.postProfile(
            self.valid_handle, not_gt_eight_chars_pw)
        assert_that(status_4_stoken).is_equal_to(client.BAD_REQUEST)
        assert_that(body_4_stoken).is_equal_to(self.weak_token_error)

    def testBadRequestOnInvalidHandle(self):
        """given a request with a malformed handle it should return status 'Bad Request'"""
        bad_handle = 'wsomebody'
        status_4_bhandle, body_4_bhandle = self.postProfile(
            bad_handle, self.valid_token)
        assert_that(status_4_bhandle).is_equal_to(client.BAD_REQUEST)
        assert_that(body_4_bhandle).is_equal_to(
            self.get_invalid_handle_error(bad_handle))

        incomplete_handle = 'w/'
        status_4_inchandle, body_4_inchandle = self.postProfile(
            incomplete_handle, self.valid_token)
        assert_that(status_4_inchandle).is_equal_to(client.BAD_REQUEST)
        assert_that(body_4_inchandle).is_equal_to(
            self.get_invalid_handle_error(incomplete_handle))

    def testConflictOnTakenHandle(self):
        """given a request with a handle that is already registered it should return status 'Conflict'"""
        already_registered_handle = self.handle
        status, body = self.postProfile(
            already_registered_handle, self.valid_token)
        assert_that(status).is_equal_to(client.CONFLICT)
        assert_that(body).is_equal_to(
            f"We already know someone by the handle '{already_registered_handle}'")

    def testCreatedOnNewValidUser(self):
        """given a request with valid credentials of a non-taken user it should return status 'Created'"""
        new_valid_handle = "w/newValidTestHandle"
        status, body = self.postProfile(new_valid_handle, self.valid_token)
        assert_that(status).is_equal_to(client.CREATED)
        assert_that(body).is_equal_to(f"Notifications Are Free {new_valid_handle}!")

