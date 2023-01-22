# NAF API

# Glossary
- 🔐 endpoint requires basic authentication
- 🔒 endpoint passes credentials through basic authentication
- **&lt;timestamp>** is a datetime string of format 'Y-m-d H-M-S'
- **&lt;validHandle>** is a string which matches regexp 'w/[a-zA-Z0-9-_]+'

# Endpoints
## ❌❌ 🔒 POST /profiles
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

## ❌❌ 🔐 GET /connections
all user connections seperated by 'requested' and 'connected' states

Returns:

    Status: 200 OK

Body:

  ```json
  {
    "requested" : ["<handle_a>", "<handle_b>", ...],
    "connected" : ["handle_1", "<handle_2>", ...]
  }
  ```

## ✅❌ 🔐 POST /connections/&lt;toHandle>

Creates a request to connect to another user. If two users post these to each other then they will be connected.

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

  