from http import client
import unittest
import json
from datetime import datetime
from assertpy import assert_that
from routes import Routes, TestCaseWithHTTP

messagesURL = f"{Routes.BASE_PATH}/messages"


class POSTMessages(TestCaseWithHTTP):
    def testBadReqOnMissingRecipient(self):
        """given a message without a 'toHandle' field it returns status 'Bad Request'"""
        messageMissingReceipient = {
            "text": self.messageText,
        }

        status, body = self.postMessage(messageMissingReceipient)
        assert_that(status).is_equal_to(client.BAD_REQUEST)
        assert_that(body).is_equal_to("message missing field 'toHandle'")

    def testBadRequestOnMissingText(self):
        """given a message without a 'text' field it returns status 'Bad Request'"""
        messageMissingText = {
            "toHandle": self.connectedUser,
        }

        status, body = self.postMessage(messageMissingText)
        assert_that(status).is_equal_to(client.BAD_REQUEST)
        assert_that(body).is_equal_to("message missing field 'text'")

    def testNotFoundOnUnconnectedUserHandle(self):
        """given a message with a 'toHandle' for a user we are not connected to it returns status 'Not Found'"""
        unnconnectedUser = "w/someUnconnectedUser"
        message = {
            "text": self.messageText,
            "toHandle": unnconnectedUser,
        }
        status, body = self.postMessage(message, self.authed_headers)
        assert_that(status).is_equal_to(client.NOT_FOUND)
        assert_that(body).is_equal_to(f"user {unnconnectedUser} not found")

    def testCreatedOnValidMessage(self):
        """given a valid message it returns status 'Created' and an object with a valid timestamp"""
        message = {
            "text": self.messageText,
            "toHandle": self.connectedUser,
        }
        status, body = self.postMessage(message)
        assert_that(status).is_equal_to(client.CREATED)
        result: dict = json.loads(body)
        assert_that(result).contains_key("timestamp")
        _ = datetime.strptime(result["timestamp"], '%Y-%m-%d %H:%M:%S')

    def postMessage(self, message: dict, headers: dict = None) -> tuple:
        valid_headers = self.authed_headers if headers == None else headers
        json_message = json.dumps(message)
        self.conn.request('POST', messagesURL, json_message, valid_headers)
        response = self.conn.getresponse()
        status = response.status
        body = response.read().decode("utf-8")
        return status, body

    messageText = "Test.POSTMessages"


class GETMessages(TestCaseWithHTTP):
    def getMessages(self, xtra_headers: dict[str, str] = {}):
        self.conn.request('GET', messagesURL,
                          headers=self.authed_headers | xtra_headers)
        response = self.conn.getresponse()
        status = response.status
        body = response.read().decode("utf-8")
        return status, body

    def testOkOnValidRequest(self):
        """given a request with valid credentials it returns status 'OK' along with the user's messages"""
        status, body = self.getMessages()
        messages = json.loads(body)
        assert_that(status).is_equal_to(client.OK)
        are_only_user_messages = all(
            [msg['fromHandle'] == self.handle or msg['toHandle'] == self.handle for msg in messages])
        assert_that(are_only_user_messages).is_true()

    def testBadRequestWhenSinceIsIvalidDatetime(self):
        """given a request with an invalid datetime header 'since' it returns 'Bad Request'"""
        invalid_msgs_filter_headers = {
            'since': '2013/a1/01'
        }
        status, body = self.getMessages(invalid_msgs_filter_headers)
        assert_that(status).is_equal_to(client.BAD_REQUEST)
        assert_that(body).is_equal_to(
            "filter header 'since' should be a UTC time of format '%Y-%m-%d %H:%M:%S'")

    def testOkWithLatestMessages(self):
        """given a request with valid credentials and a 'since' header UTC timestamp it 
        returns all the messages sent to the user after 'since' and status 'OK'
        """
        since = datetime.utcnow()
        msgs_filter_headers = {
            'since': str(since)
        }
        status, body = self.getMessages(msgs_filter_headers)
        messages = json.loads(body)
        assert_that(status).is_equal_to(client.OK)
        are_only_messages_to_user = all(
            [msg['toHandle'] == self.handle for msg in messages]
        )
        assert_that(are_only_messages_to_user).is_true()
        are_only_messages_after_since = all(
            [self.is_message_at_or_after(msg, since) for msg in messages])
        assert_that(are_only_messages_after_since).is_true()

    def is_message_at_or_after(self, msg: dict[str, str], datetime: datetime):
        msg_datetime = datetime.strptime(
            msg['timestamp'], '%Y-%m-%d %H:%M:%S')
        return msg_datetime >= datetime


if __name__ == "__main__":
    unittest.main()  # run all tests
