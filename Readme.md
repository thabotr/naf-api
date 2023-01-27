# NAF API
Provides an interface for registering, logging in, connecting with other users, and messaging with NAF.
# Glossary
- 🔐 endpoint requires basic authentication. To use username `testHandle` and password `testToken` by default as basic auth credentials
- 🔒 endpoint passes credentials through basic authentication
- **&lt;timestamp>** is a datetime string of format 'Y-m-d H-M-S'
- **&lt;validHandle>** is a string which matches regexp 'w/[a-zA-Z0-9-_]+'
- ❌❌ endpoint unvalidated
- ✅❌ endpoint DEV validated
- ✅✅ endpoint PROD validated

# Endpoints
## ✅✅ GET /ping
use for server liveness probe

Returns:

    Status: 200 OK

## ✅✅ 🔐 GET /profiles/connected-users
the profiles for all connected users

Returns:

    Status: 200 OK

Body:

  ```json
  [
    {"handle": "<handle_1>"}, 
    {"handle": "<handle_2>"},
    ...
  ]
  ```
## ✅✅ 🔐 GET /profiles/my-profile
Returns:

    Status: 200 OK

Body:

  ```json
  {"handle" : "<user_handle>"}
  ```

## ✅✅ 🔒 POST /profiles/my-profile
register a new user

**&lt;username of basic auth> is a &lt;validHandle> which is not yet registered AND &lt;password of basic auth> is a string of length 8 or greater**

Example:

    basic authentication username: w/testHandle6
    basic authentication password: testToken

Returns:

    Status: 201 Created

**&lt;username of basic auth> is not a &lt;validHandle> OR &lt;password of basic auth> is not a string of length less than 8**

Examples:

    basic authentication username: w/test=Handle6
    basic authentication password: testToken

    basic authentication username: w/testHandle6
    basic authentication password: shortPw


Returns:

    Status: 400 Bad Request

**&lt;username of basic auth> is a &lt;validHandle> AND &lt;password of basic auth> is string of length 8 or greater BUT &lt;username of basic auth> refers to an already registered user**

Example:

An already registered user handle

    basic authentication username: w/testHandle
    basic authentication password: testTokens


Returns:

    Status: 409 Conflict

## ✅✅ 🔐 DELETE /profiles/my-profile
deregister user

Returns:

    Status: 200 OK

## ✅✅ 🔐 GET /connections/pending
all connection pending connection requests sent by user

Returns:

    Status: 200 OK

Body:

  ```json
  [
    { "handle": "handle_1", "timestamp" : "<timestamp>"},
    { "handle": "handle_2", "timestamp" : "<timestamp>"},
    ...
  ]
  ```

## ✅✅ 🔐 POST /connections

Creates a request to connect to another user. If two users post these to each other then they will be connected.

Request body:

    <toHandle>

**&lt;toHandle> is a &lt;validHandle>**

  Example:

    w/testHandle2

  Returns:
  
    Status: 200 OK

  Body:

  timestamp indicating the first time that this connection request was sent or the time at which the users were connected
  ```json
  {"timestamp" : "<timestamp>"}
  ```

**&lt;toHandle> missing or is not a &lt;validHandle>**

  Examples:

    w/
    w/!testHandle2
  Returns:

    Status: 400 Bad Request

**&lt;toHandle> is a &lt;validHandle> but refers to an unregistered user**

  Example:

  any valid handle which has not yet been registered

    w/imNotRegistered
  Returns:

      Status: 404 Not Found

## ✅✅ 🔐 DELETE /connections?toHandle=&lt;validHandle>

disconnects from a user or deletes a request to connect to a user

**&lt;toHandle> is a &lt;validHandle>**

  Example:

    w/testHandle2

  Returns:
  
    Status: 200 OK


**&lt;toHandle> missing or is not a &lt;validHandle>**

  Examples:

    w/
    w/!testHandle2
  Returns:

    Status: 400 Bad Request

## ✅✅ 🔐 GET /messages?after=&lt;timestamp>&toMe=&lt;true if 1 otherwise false>
all messsages sent to and from this user

**url parameter &lt;after> is a valid**

Returns:

messages sent after &lt;after>

    Status: 200 OK

**url parameter &lt;toMe> is valid**

Returns:

only messages sent to user

    Status: 200 OK

**if any of the url parameters are omitted**

Returns:

messages without the application of the omitted filter

    Status: 200 OK

Body:

for all valid requests

  ```json
  [
    {
      "text" : "<string>", 
      "fromHandle" : "<handle_a>", 
      "toHandle" : "<handle_b>", 
      "timestamp" : "<timestamp>"
    },
    ...
  ]
  ```

**if any of the url parameters are invalid**

Returns:

    Status: 400 Bad Request


## ✅✅ 🔐 POST /messages
sends a message to another user

Request body:

  ```json
  {"text" : "<message text>", "toHandle": "<other_user_handle>"}
  ```

**valid message in request body**

Returns:

    Status: 201 Created

**&lt;other_user_handle> is missing or not a &lt;validHandle>**

Returns:

    Status: 400 Bad Request

**&lt;message text> is missing**

Returns:

    Status: 400 Bad Request

**&lt;toHandle> refers to a user not connected to us**

Returns:

    Status: 404 Not Found