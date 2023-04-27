<?php
$id = 'modulekit-history';

$depend = array(
  'adv_exec',
  'hooks',
  'PDOext',
  'messages',
  'shell_escape',
);

$include = array(
  'php' => array(
    'inc/git.php',
    'inc/Changeset.php',
  ),
);
