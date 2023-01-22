# NAF API

# Glossary
- 🔐 endpoint requires basic authentication
- **&lt;timestamp>** is a datetime string of format 'Y-m-d H-M-S'

# Endpoints
## 🔐 POST /connections/&lt;toHandle>

Creates a request to connect to another user. If two users post these to each other then they will be connected.

**&lt;toHandle> matches regexp 'w/[a-zA-Z0-9-_]+'**

  Example:

    w/testHandle2

  Returns:
  
    Status: 200 OK

  Body:

  timestamp indicating the first time that this connection request was sent or the time at which the users were connected
  ```json
      {"timestamp" : "<timestamp>"}
  ```

**&lt;toHandle> missing or does not match regexp 'w/[a-zA-Z0-9-_]+'**

  Examples:

    w/
    w/!testHandle2
  Returns:

    Status: 400 Bad Request

**&lt;toHandle> refers to an unregistered user**

  Example:

  any valid handle which has not yet been registered

    w/imNotRegistered
  Returns:

      Status: 404 Not Found

  