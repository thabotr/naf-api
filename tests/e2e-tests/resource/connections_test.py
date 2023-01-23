from assertpy import assert_that
from routes import TestCaseWithHTTP, Routes, getAuthHeaders
from http import client
import json

connections_URL = f"{Routes.BASE_PATH}/connections"


class DELETEConnections(TestCaseWithHTTP):

    def deleteConnection(self, handle=None):
        handle_uri = f"/{handle}" if handle else ""
        url = f"{connections_URL}{handle_uri}"
        self.conn.request('DELETE', url, headers=self.authed_headers)
        response = self.conn.getresponse()
        status = response.status
        body = response.read().decode("utf-8")
        return status, body

    def testBadRequestOnMissingHandleInURL(self):
        """given a url without the handle it returns 'Bad Request'"""
        status, body = self.deleteConnection()
        assert_that(status).is_equal_to(client.BAD_REQUEST)
        assert_that(body).is_equal_to("missing handle in url")

    def testBadRequestOnMalformedHandleInURL(self):
        """given a url with a malformed handle it returns 'Bad request'"""
        malformedHandleWoutSlash = "wtestOneTwo3"
        status, body = self.deleteConnection(malformedHandleWoutSlash)
        assert_that(status).is_equal_to(client.BAD_REQUEST)
        assert_that(body).is_equal_to("missing handle in url")

    def testOKOnConnectedUserHandleInURL(self):
        """given a url with the handle of a connected user it returns 'OK'"""
        status, body = self.deleteConnection(self.secondConnectedUser)
        assert_that(status).is_equal_to(client.OK)
        assert_that(body).is_equal_to(
            f"disconnected from {self.secondConnectedUser}")

    def testOKOnNonConnectedUserInURL(self):
        """gievn a url with the handle of a non-connected user it returns 'OK'"""
        unconnectedUser = "w/someOtherHandle"
        status, body = self.deleteConnection(unconnectedUser)
        assert_that(status).is_equal_to(client.OK)
        assert_that(body).is_equal_to(f"disconnected from {unconnectedUser}")


class POSTConnections(TestCaseWithHTTP):
    def postConnection(self, url, to_json=False):
        self.conn.request('POST', url, headers=self.authed_headers)
        response = self.conn.getresponse()
        status, body = response.status, response.read().decode('utf-8')
        if to_json:
            body = json.loads(body)
        return status, body

    def testBadRequestOnMissingOrBadHandleInURL(self):
        """given a url with a missing or malformed handle it returns 'Bad Request'"""
        missing_handle_URL = connections_URL
        status, body = self.postConnection(missing_handle_URL)
        assert_that(status).is_equal_to(client.BAD_REQUEST)
        assert_that(body).is_equal_to(
            "invalid or missing handle in url. Expected handle matching 'w/[a-zA-Z0-9-_]+'"
        )

        malformed_handle_URL = f"{connections_URL}/w/*badHandle"
        murl_status, m_urlbody = self.postConnection(malformed_handle_URL)
        assert_that(murl_status).is_equal_to(client.BAD_REQUEST)
        assert_that(m_urlbody).is_equal_to(
            "invalid or missing handle in url. Expected handle matching 'w/[a-zA-Z0-9-_]+'"
        )

    def testOkOnValidURLHandleForNonConnectedUser(self):
        """given a url with a valid handle who's user we are not already connected to,
        it returns 'OK' and the timestamp for the connection request
        """
        unconnected_handle = "w/testHandlle4"
        unconnected_handle_URL = f"{connections_URL}/{unconnected_handle}"
        status, body = self.postConnection(
            unconnected_handle_URL, to_json=True)
        assert_that(status).is_equal_to(client.OK)
        assert_that(body).contains_key('timestamp')

    def testNotFoundOnURLHandleForNonExistentUser(self):
        """given a handle for an unregistered user in the URL it should return 'Not Found'"""
        unregistered_user_handle = "w/someRandomUregisteredUser"
        unreged_user_handle_URL = f"{connections_URL}/{unregistered_user_handle}"
        status, body = self.postConnection(unreged_user_handle_URL)
        assert_that(status).is_equal_to(client.NOT_FOUND)
        assert_that(body).is_equal_to(
            f"user {unregistered_user_handle} not found")

    def testOkAndConnectionTimestampOnAlreadyConnectedUserHandle(self):
        """given a url with the handle of an already connected user it returns 'OK'
        and the connection timestamp
        """
        connected_user_handle = "w/testHandle2"
        connected_user_handle_url = f"{connections_URL}/{connected_user_handle}"
        status, body = self.postConnection(
            connected_user_handle_url, to_json=True)
        assert_that(status).is_equal_to(client.OK)
        assert_that(body).contains_key('timestamp')

    def testOkAnd1stRequestTimestampOnDuplicateCalls(self):
        """given mutliple requests to connect to the same user it returns 'OK' and the
        timestamp of the first connection request
        """
        to_user_handle = "w/testHandle3"
        to_user_handle_url = f"{connections_URL}/{to_user_handle}"
        first_status, first_body = self.postConnection(
            to_user_handle_url, to_json=True)
        assert_that(first_status).is_equal_to(client.OK)
        assert_that(first_body).contains_key('timestamp')
        second_status, second_body = self.postConnection(
            to_user_handle_url, to_json=True)
        assert_that(second_status).is_equal_to(client.OK)
        assert_that(second_body).contains_key('timestamp')
        assert_that(first_body).is_equal_to(second_body)

    def send_connection_request_to_us_as(self, another_user):
        another_user_auth = getAuthHeaders(
            another_user['handle'], another_user['token'])
        self.conn.request(
            "POST", f"{connections_URL}/{self.handle}", headers=another_user_auth)
        resp = self.conn.getresponse()
        assert_that(resp.status).is_equal_to(client.OK)

    def get_our_chats(self):
        self.conn.request(
            "GET", f"{Routes.BASE_PATH}/chats", headers=self.authed_headers)
        our_chats_response = self.conn.getresponse()
        assert_that(our_chats_response.status).is_equal_to(client.OK)
        our_chats = json.loads(our_chats_response.read())
        return our_chats

    def disconnect_from(self, user_handle):
        self.conn.request(
            "DELETE", f"{connections_URL}/{user_handle}", headers=self.authed_headers)
        resp = self.conn.getresponse()
        assert_that(resp.status).is_equal_to(client.OK)

    def testAddsUserToOurChatsIfTheyAlsoRequestedConnectionToUs(self):
        user_5 = {
            "handle": 'w/testHandle5',
            "token": "testToken5",
        }
        self.disconnect_from(user_5["handle"])
        self.send_connection_request_to_us_as(user_5)
        user_5_as_chat = {
            "user": {"handle": user_5["handle"]}
        }
        our_chats = self.get_our_chats()
        assert_that(our_chats).does_not_contain(user_5_as_chat)

        to_chat_5_url = f"{connections_URL}/{user_5['handle']}"
        status, _ = self.postConnection(to_chat_5_url)
        assert_that(status).is_equal_to(client.OK)
        our_recent_chats = self.get_our_chats()
        assert_that(our_recent_chats).contains(user_5_as_chat)
