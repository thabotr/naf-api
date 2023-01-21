from assertpy import assert_that
from routes import TestCaseWithHTTP, Routes, getAuthHeaders
from http import client
import json
import requests
from datetime import datetime

class GETNotifications(TestCaseWithHTTP):
  notificationsURL = f"{Routes.BASE_PATH}/notifications"

  def getNotifications(self, headers: dict[str, str]):
    self.conn.request('GET', self.notificationsURL, headers=self.authed_headers | headers)
    response = self.conn.getresponse()
    status, body = response.status, response.read().decode("utf-8")
    return (status, body)

  def testBadRequestOnMissingMessagesSince(self):
    """given a request without the 'messagessince' header instruction it returns status 'Bad Request'"""
    missing_messages_since = {}
    status, body = self.getNotifications(missing_messages_since)    
    assert_that(status).is_equal_to(client.BAD_REQUEST)
    assert_that(body).is_equal_to("missing header 'messagessince'")
  
  def testBadRequestOnMessagesSinceInvalidDateTime(self):
    """given a request with an invalid 'messagessince' header time stamp it returns status 'Bad Request'"""
    bad_messages_since_timestamp = {
      'messagessince': '2023/a1/1'
    }
    status, body = self.getNotifications(bad_messages_since_timestamp)
    assert_that(status).is_equal_to(client.BAD_REQUEST)
    assert_that(body).is_equal_to("instruction header 'messagessince' should a UTC time of format '%Y-%m-%d %H:%M:%S'")
  
  def testIdleEventCodeOnNoNotifications(self):
    """given a request with a valid 'messagessince' after which no new messages 
    have been sent to the user then it returns 'OK' and IDLE event code in the body"""
    before_message = {
      'messagessince' : str(datetime.utcnow())
    }
    status, body = self.getNotifications(before_message)
    assert_that(status).is_equal_to(client.OK)
    idle_event_code = '0'
    assert_that(body).is_equal_to(idle_event_code)

  def sendMessageFromOtherUser(self):
    message = json.dumps({
      'text': 'Test.POSTNofitications.testNewMessageEventCodeOnNewMessage',
      'toHandle': self.handle,
    })
    auth = getAuthHeaders('w/testHandle2', 'testToken2')
    self.conn.request('POST', f"{Routes.BASE_PATH}/messages", message, auth)
    status = self.conn.getresponse().status
    assert_that(status).is_equal_to(client.CREATED)

  def testNewMessageEventCodeOnNewMessage(self):
    """given a request with a valid 'messagessince' header it should return status 
    'OK' and NEW_MESSAGE eventy code when the user has been sent a message after the
    timestamp 'messagessince' 
    """
    since = {
      'messagessince': str(datetime.utcnow())
    }
    self.sendMessageFromOtherUser()
    status, body = self.getNotifications(since)
    new_message_event = '1'
    assert_that(status).is_equal_to(client.OK)
    assert_that(body).is_equal_to(new_message_event)


    