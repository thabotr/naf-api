### Back-end repository integration testing
  Download [PHPUnit](https://phpunit.de/index.html) phar and place it in project root
    
  Run 
    
    php .\phpunit-6.5.14.phar --verbose .\<path\to\test\file>.php
  
  from the php tests directory
### API e2e testing
These ensure that the back-end's endpoints are working as expected

    python .\tests\e2e-tests\resource\__main___test.py [-p to run against PROD]
