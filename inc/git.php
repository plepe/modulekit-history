<?php
/**
 * git_dump() - save all data to the git repository
 * parameter: $changeset - see class Changeset
 */
function git_dump($changeset) {
  global $git;
  global $system;

  $message = $changeset->message;

  if(!isset($git))
    return;

  $cwd = getcwd();

  $git_path = $git['path'];
  if(!file_exists($git['path'])) {
    mkdir($git['path']);
  }

  if(chdir($git['path']) === false) {
    messages_add("Git: cannot chdir to git directory", MSG_ERROR);
    return;
  }

  if(!is_dir(".git")) {
    system("git init");
  }

  foreach($changeset->objects as $ob) {
    $ob_path = get_class($ob) . "/" . $ob->id() . '.json';

    @mkdir("{$git_path}/" . get_class($ob));

    if($ob->orig_id !== null) {
      $orig_ob_path = get_class($ob) . "/" . $ob->orig_id . '.json';

      if($orig_ob_path != $ob_path)
        system("git mv " . shell_escape("{$git_path}/{$orig_ob_path}") . " " . shell_escape("{$git_path}/{$ob_path}"));

    }

    file_put_contents($ob_path, json_readable_encode($ob->data()));
    system("git add " . shell_escape("{$git_path}/{$ob_path}"));
  }

  foreach($changeset->removed_objects as $ob) {
    $ob_path = get_class($ob) . "/" . $ob->id() . '.json';
    system("git rm " . shell_escape("{$git_path}/{$ob_path}"));
  }

  global $auth;
  $user = $auth->current_user()->name();
  $email = $auth->current_user()->email();

  if(!$email)
    $email = "unknown@unknown";

  system("git add .");
  $result = adv_exec("git " .
           "-c user.name=" . shell_escape($user) . " " .
           "-c user.email=" . shell_escape($email) . " " .
           "commit " .
           "-a -m " . shell_escape($message) . " " .
           "--allow-empty-message ".
           "--author=" . shell_escape("{$user} <{$email}>")
        );

  if(!in_array($result[0], array(0, 1))) {
    messages_add("<pre>Git commit failed:\n" . htmlspecialchars($result[1]) . "</pre>\n", MSG_ERROR);
  }

  chdir($cwd);
}

register_hook("changeset_commit", "git_dump");
