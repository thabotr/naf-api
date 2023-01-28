<?php
class Profile
{
  public int $id;
  public string $token;
  public string $handle;

  public function __construct(int $id, string $handle, string $token)
  {
    $this->id = $id;
    $this->handle = $handle;
    $this->token = $token;
  }
}