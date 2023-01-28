<?php

class DateTimePerRelation
{
  public $messagesAfter;
  public $connectionsAfter;

  public function __construct(string $messagesAfter, string $connectionsAfter)
  {
    $this->messagesAfter = $messagesAfter;
    $this->connectionsAfter = $connectionsAfter;
  }
}

class CountPerRelation
{
  public $messageCount;
  public $connectionsCount;

  public function __construct(int $messageCount, int $connectionsCount)
  {
    $this->messageCount = $messageCount;
    $this->connectionsCount = $connectionsCount;
  }
}