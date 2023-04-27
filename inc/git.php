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
        adv_exec("git mv " . escapeshellarg("{$git_path}/{$orig_ob_path}") . " " . escapeshellarg("{$git_path}/{$ob_path}"));

    }

    file_put_contents($ob_path, json_readable_encode($ob->view()) . "\n");
    adv_exec("git add " . escapeshellarg("{$git_path}/{$ob_path}"));
  }

  foreach($changeset->removed_objects as $ob) {
    $ob_path = get_class($ob) . "/" . $ob->id() . '.json';
    adv_exec("git rm " . escapeshellarg("{$git_path}/{$ob_path}"));
  }

  global $auth;
  if($auth && $auth->current_user()) {
    $user = $auth->current_user()->name();
    $email = $auth->current_user()->email();
  }
  else {
    $user = "Unknown";
    $email = "unknown@unknown";
  }

  if(!$email)
    $email = "unknown@unknown";

  $result = adv_exec("git " .
           "-c user.name=" . escapeshellarg($user) . " " .
           "-c user.email=" . escapeshellarg($email) . " " .
           "commit " .
           "-a -m " . escapeshellarg($message) . " " .
           "--allow-empty-message ".
           "--author=" . escapeshellarg("{$user} <{$email}>")
        );

  if(!in_array($result[0], array(0, 1))) {
    messages_add("<pre>Git commit failed:\n" . htmlspecialchars($result[1]) . "</pre>\n", MSG_ERROR);
  }

  chdir($cwd);
}

function git_log_all($offset=0, $limit=null) {
  $cmd = "git log --stat --stat-width=1024 --stat-name-width=1024" .
    ($offset != 0 ? " --skip " . escapeshellarg($offset) : "") .
    ($limit !== null ? " --max-count ". escapeshellarg($limit) : "");

  return _git_log_exec($cmd);
}

function git_log_object($class, $id, $offset=0, $limit=null) {
  $cmd = "git log --stat --stat-width=1024 --stat-name-width=1024 --follow" .
    ($offset != 0 ? " --skip " . escapeshellarg($offset) : "") .
    ($limit !== null ? " --max-count ". escapeshellarg($limit) : "") .
    " -- " . escapeshellarg("{$class}/{$id}.json");

  return _git_log_exec($cmd);
}

function git_log_commit($commit, $class=null, $id=null) {
  $cmd = "git show -U99999 " . escapeshellarg($commit) .
    ($class ? " --follow -- " . escapeshellarg("{$class}/{$id}.json") : "");

  return _git_log_exec($cmd);
}


function _git_log_exec($cmd) {
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
  $result = adv_exec($cmd);

  $mode = 0;
  foreach(explode("\n", $result[1]) as $r) {
    if(preg_match("/^commit (.*)/", $r, $m)) {
      $mode = 0;
      if($commit)
        $ret[] = $commit;

      $commit = array(
        'commit' => $m[1],
        'message' => '',
        'objects' => array(),
      );
    }
    elseif(($mode == 0) && preg_match("/^Author:\s*(.*) <(.*)>$/", $r, $m)) {
      $commit['author_name'] = $m[1];
      $commit['author_email'] = $m[2];
    }
    elseif(($mode == 0) && preg_match("/^Date:\s*(.*)$/", $r, $m)) {
      $d = new DateTime($m[1]);
      $commit['date'] = $d->format('c');
    }
    elseif(($mode == 0) && preg_match("/^    (.*)$/", $r, $m))
      $commit['message'] .= $m[1];
    elseif(($mode == 0) && preg_match("/^ ([A-Za-z0-9_]*)\/\{(.*)\.json => (.*)\.json\}/", $r, $m)) {

      $commit['objects'][] = array($m[1], $m[3], $m[2]);
    }
    elseif(($mode == 0) && preg_match("/^ ([A-Za-z0-9_]*)\/(.*)\.json/", $r, $m))
      $commit['objects'][] = array($m[1], $m[2]);
    elseif((($mode == 0) || ($mode == 2)) && preg_match("/^diff --git a\/([A-Za-z0-9_]*)\/(.*)\.json b\/([A-Za-z0-9_]*)\/(.*)\.json$/", $r, $m)) {
      $mode = 1;

      if($m[2] != $m[4])
        $commit['objects'][] = array($m[1], $m[2], $m[4], 'diff' => "");
      else
        $commit['objects'][] = array($m[1], $m[2], 'diff' => "");
    }
    elseif(($mode == 1) && (preg_match("/^\@\@/", $r))) {
      $mode = 2;
    }
    elseif($mode == 2) {
      $commit['objects'][sizeof($commit['objects']) - 1]['diff'] .= "$r\n";
    }
  }

  if($commit)
    $ret[] = $commit;

  chdir($cwd);

  return $ret;
}

register_hook("changeset_commit", "git_dump");
