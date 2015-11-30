<?php
class Changeset {
  function __construct($message) {
    global $db;

    $this->status = false;
    $this->message = $message;

    $db->beginTransaction();
  }

  function disableForeignKeyChecks() {
    global $db;

    $db->disableForeignKeyChecks();
    $this->foreign_key_checks_disabled = true;
  }

  function enableForeignKeyChecks() {
    global $db;

    $db->enableForeignKeyChecks();
    unset($this->foreign_key_checks_disabled);
  }

  function add($object) {
    if($this->status === false) {
      $this->open();
      $this->commit();
    }
  }

  function open() {
    $this->status = true;

    call_hooks("changeset_open", $this);
  }

  function rollBack() {
    global $db;
    call_hooks("changeset_rollback", $this);

    if(isset($this->foreign_key_checks_disabled))
      $db->enableForeignKeyChecks();

    $this->status = false;
    $db->rollBack();
  }

  function commit() {
    global $db;

    if(isset($this->foreign_key_checks_disabled))
      $db->enableForeignKeyChecks();

    $this->status = false;
    $db->commit();

    call_hooks("changeset_commit", $this);
  }

  function is_open() {
    return $this->status;
  }
}
