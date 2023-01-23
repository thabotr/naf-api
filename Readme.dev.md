### Back-end unit testing
  Download [PHPUnit](https://phpunit.de/index.html) phar and place it in project root
    
  Run 
    
    php .\phpunit-6.5.14.phar --verbose .\<path\to\test\file>.php
  
  from the php tests directory
### Back-end contract/integration testing
These ensure that the back-end's endpoints are working as expected

    python .\int_tests\resource\__main___test.py