<?php
class Changeset {
  function __construct($message) {
    global $db;

    $this->status = false;
    $this->message = $message;
    $this->objects = array();
    $this->removed_objects = array();

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

  function set_message($text) {
    $this->message = $text;
  }

  function add($object) {
    if (!in_array($object, $this->objects)) {
      $this->objects[] = $object;
    }

    if($this->status === false) {
      $this->open();
      $this->commit();
    }
  }

  function remove($object) {
    if (!in_array($object, $this->removed_objects)) {
      $this->removed_objects[] = $object;
    }

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

    foreach ($this->objects as $ob) {
      if (method_exists($ob, 'on_commit')) {
        $ob->on_commit($this);
      }
    }

    call_hooks("changeset_commit", $this);
  }

  function is_open() {
    return $this->status;
  }
}
