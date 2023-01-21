from assertpy import assert_that
from routes import TestCaseWithHTTP, Routes
from http import client
import json

class GETChats(TestCaseWithHTTP):
  chatsURL = f"{Routes.BASE_PATH}/chats"

  def testOKOnAuthedUser(self):
    """given a request by an authed user it should return their connected  chats and status 'OK'"""
    connectedUserProfile = {
        "handle" : self.connectedUser,
      }
    expectedChats = [{
      "user": connectedUserProfile,
    }]
    self.conn.request('GET', self.chatsURL, headers=self.authed_headers)
    response = self.conn.getresponse()
    status = response.status
    body = response.read().decode("utf-8")
    assert_that(status).is_equal_to(client.OK)
    chats = json.loads(body)
    assert_that(chats).is_equal_to(expectedChats)