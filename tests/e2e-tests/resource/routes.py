import unittest
import http.client
import base64
from assertpy import assert_that


class Routes:
    HOST = "localhost"
    PORT = 8000
    BASE_PATH = "/naf/api"
    PROD_SERVER = "www.thaborlabs.com"
    RUN_AGAINST_PROD = False


def encodeAuthCredentials(username: str, password: str) -> str:
    credentials: str = f"{username}:{password}"
    credentials_bytes = credentials.encode('utf-8')
    base64_bytes = base64.b64encode(credentials_bytes)
    credentials_b64 = base64_bytes.decode('utf-8')
    return credentials_b64


def getAuthHeaders(handle, token):
    return {
        "Authorization": f"Basic {encodeAuthCredentials(handle, token)}",
    }


class TestCaseWithHTTP(unittest.TestCase):
    handle = 'w/testHandle'
    token = 'testToken'
    connectedUser = 'w/testHandle2'
    secondConnectedUser = "w/testHandle3"
    authed_headers = {
        "Authorization": f"Basic {encodeAuthCredentials(handle, token)}",
    }

    def setUp(self) -> None:
        if Routes.RUN_AGAINST_PROD:
            self.conn = http.client.HTTPSConnection(Routes.PROD_SERVER)
        else:
            self.conn = http.client.HTTPConnection(Routes.HOST, Routes.PORT)
        return super().setUp()

    def tearDown(self) -> None:
        self.conn.close()
        return super().tearDown()

    def testUnauthOnBadCredentials(self):
        """given unregistered user authorization credentials it returns 'Unauthorized'"""
        unregisteredHandle = 'w/someUnregisteredHandle'
        unregisteredToken = 'someUnregisteredTestToken'
        encoded_bad_credentials = encodeAuthCredentials(
            unregisteredHandle, unregisteredToken)
        badAuthheaders = {
            "Authorization": f"Basic {encoded_bad_credentials}",
        }

        self.conn.request('GET', Routes.BASE_PATH, headers=badAuthheaders)
        response = self.conn.getresponse()
        assert_that(response.status).is_equal_to(http.client.UNAUTHORIZED)
