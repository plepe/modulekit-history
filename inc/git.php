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
    adv_exec("git init");
  }

  foreach($changeset->objects as $ob) {
    $ob_path = get_class($ob) . "/" . $ob->id() . '.json';

    @mkdir("{$git_path}/" . get_class($ob));

    if($ob->orig_id !== null) {
      $orig_ob_path = get_class($ob) . "/" . $ob->orig_id . '.json';

      if($orig_ob_path != $ob_path)
        adv_exec("git mv " . shell_escape("{$git_path}/{$orig_ob_path}") . " " . shell_escape("{$git_path}/{$ob_path}"));

    }

    file_put_contents($ob_path, json_readable_encode($ob->data()));
    adv_exec("git add " . shell_escape("{$git_path}/{$ob_path}"));
  }

  foreach($changeset->removed_objects as $ob) {
    $ob_path = get_class($ob) . "/" . $ob->id() . '.json';
    adv_exec("git rm " . shell_escape("{$git_path}/{$ob_path}"));
  }

  global $auth;
  $user = $auth->current_user()->name();
  $email = $auth->current_user()->email();

  if(!$email)
    $email = "unknown@unknown";

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

function git_log_all() {
  global $git;

  if(!isset($git))
    return;

  $cwd = getcwd();

  if(chdir($git['path']) === false) {
    messages_add("Git: cannot chdir to git directory", MSG_ERROR);
    return;
  }

  $ret = array();
  $commit = null;
  $result = adv_exec("git log --stat");
  foreach(explode("\n", $result[1]) as $r) {
    if(preg_match("/^commit (.*)/", $r, $m)) {
      if($commit)
        $ret[] = $commit;

      $commit = array(
        'commit' => $m[1],
        'message' => '',
        'objects' => array(),
      );
    }
    elseif(preg_match("/^Author:\s*(.*) <(.*)>$/", $r, $m)) {
      $commit['author_name'] = $m[1];
      $commit['author_email'] = $m[1];
    }
    elseif(preg_match("/^Date:\s*(.*)$/", $r, $m)) {
      $d = new DateTime($m[1]);
      $commit['date'] = $d->format('c');
    }
    elseif(preg_match("/^    (.*)$/", $r, $m))
      $commit['message'] .= $m[1];
    elseif(preg_match("/^ ([A-Za-z0-9_]*)\/(.*)\.json/", $r, $m))
      $commit['objects'][] = array($m[1], $m[2]);
  }

  if($commit)
    $ret[] = $commit;

  chdir($cwd);

  return $ret;
}

register_hook("changeset_commit", "git_dump");
