<?
include(__DIR__ . '/../../lib/include.php');
include(__DIR__ . '/../../rigger/include.php');

$pdo = rigger_init('../../rigger/rigger.db');
$title = '';
$winners = 1;
$candidates = array();
$writeins = true;
$error = '';

if (array_key_exists('action', $_POST)) {
  header('HTTP/1.1 400 Bad Request');
  header('Status: 400 Bad Request');

  try {
    $parameters = array(
      ':id' => $_POST['id']
    );

    switch ($_POST['action']) {
      case 'burn':
        $result = $pdo->prepare(<<<EOF
DELETE FROM `votes`
WHERE `candidate` IN (
  SELECT `id`
  FROM `candidates`
  WHERE `election` = :id
)
EOF
          );

        $result->execute($parameters);

        $result = $pdo->prepare(<<<EOF
DELETE FROM `candidates`
WHERE `election` = :id
EOF
          );

        $result->execute($parameters);

        $result = $pdo->prepare(<<<EOF
DELETE FROM `writeins`
WHERE `election` = :id
EOF
          );

        $result->execute($parameters);

        $result = $pdo->prepare(<<<EOF
DELETE FROM `elections`
WHERE `id` = :id
EOF
          );

        $result->execute($parameters);
        break;
      case 'disable':
        $result = $pdo->prepare(<<<EOF
UPDATE `elections`
SET `closed` = DATETIME('now')
WHERE `id` = :id
EOF
          );

        $result->execute($parameters);

        $result = $pdo->prepare(<<<EOF
SELECT `closed`
FROM `elections`
WHERE `id` = :id
EOF
          );

        $result->execute($parameters);
        $row = $result->fetch(PDO::FETCH_COLUMN);
        echo rigger_closed($row);
        break;
      case 'enable':
        $result = $pdo->prepare(<<<EOF
UPDATE `elections`
SET `closed` = NULL
WHERE `id` = :id
EOF
          );

        $result->execute($parameters);
        echo rigger_closed(false);
        break;
      default:
        throw new OutOfBoundsException;
    }

    header('HTTP/1.1 200 OK');
    header('Status: 200 OK');
  } catch (OutOfBoundsException $e) {
    $error = 'Invalid action ' . blacker_encode($_POST['action']) . '.';
  }

  die($error);
} elseif (array_key_exists('title', $_POST)) {
  $title = trim($_POST['title']);
  $winners = (int) $_POST['winners'];
  $candidates = array_filter($_POST['candidates']);
  $writeins = (bool) @$_POST['writeins'];

  if (!$_POST['title']) {
    $error = ' A title is required.';
  }

  if ($winners < 1) {
    $error .= ' Please select a valid number of winners.';
  }

  if (count($candidates) < $winners) {
    $error .= ' At least as many candidates as winners must be provided.';
  }

  if (!$error) {
    $result = $pdo->prepare(<<<EOF
INSERT INTO `elections` (
  `name`,
  `winners`,
  `writeins`,
  `created`
)
VALUES (
  :name,
  :winners,
  :writeins,
  DATETIME('now')
)
EOF
      );

    $result->execute(array(
      ':name' => $title,
      ':winners' => $winners, // TODO
      ':writeins' => $writeins
    ));

    $election = $pdo->lastInsertId();

    foreach ($candidates as $candidate) {
      $result = $pdo->prepare(<<<EOF
INSERT INTO `candidates` (
  `election`,
  `name`
)
VALUES (
  :election,
  :name
)
EOF
        );

      $result->execute(array(
        ':election' => $election,
        ':name' => $candidate
      ));
    }

    header('Location: ./');
    die();
  } else {
    $error = substr($error, 1);
  }
}
?><!DOCTYPE html>
<html xmlns="http://www.w3.org/1999/xhtml">
  <head>
<?
print_head('Vote Rigger');
?>    <script type="text/javascript" src="/lib/js/jquery.min.js"></script>
    <script type="text/javascript">// <![CDATA[
      var e = 0;
      var f = $('<a class="btn btn-lg add">+</a>');

      function addCandidate(g) {
        if (typeof g != 'string') {
          g = '';
        }

        $('<div class="form-control"><label for="candidate' + e++ + '">Candidate ' + e + '</label><div class="input-group input-group-left"><input type="text" id="candidate' + (e - 1) + '" name="candidates[]" maxlength="255" value="' + g + '" /></div><div class="input-group input-group-right"><a class="btn btn-lg del">&times;</a></div></div>').children('.input-group-right').append(f).end().insertBefore('#writeins-control');
      }

      $(function() {
        f.click(addCandidate);
<?
if ($candidates) {
  foreach ($candidates as $candidate) {
    $candidate = blacker_encode($candidate);

    echo <<<EOF
        addCandidate('$candidate');

EOF;
  }
} else {
  echo <<<EOF
        addCandidate();
        addCandidate();

EOF;
}
?>
        $('#poll').on('click', '.del', function() {
          if (e > 1) {
            $(this).closest('.form-control').nextUntil('#writeins-control').addBack().slice(0, -1).each(function() {
              $(this).find('input').val($(this).next().find('input').val());
            }).end().last().prev().children('.input-group-right').append(f).end().next().remove();

            e--;
          }
        });

        $('#polls').on('click', '.del', function() {
          if (confirm('Are you sure you want to destroy this poll?')) {
            var g = $(this).parent().parent();

            $.post('./', {
              action: 'burn',
              id: g[0].id.slice(4)
            }).done(function() {
              g.remove();
              $('.error, .success').remove();
              $('#main h1').after('<div class="success">Successfully destroyed poll.</div>');
            }).fail(function(e) {
              $('.error, .success').remove();
              $('#main h1').after('<div class="success">Action failed: ' + e.responseText + '</div>');
              $(document).scrollTop(0);
            });
          }

          return false;
        }).on('click', '.toggle', function() {
          var g = $(this).parent();
          var f = $(this).hasClass('active');

          $.post('./', {
            action: f ? 'disable' : 'enable',
            id: g[0].id.slice(4)
          }, function(e) {
            console.log(e);
            g.find('.toggle').toggleClass('active', !f);
            g.find('.closed').html(e);
          });
        });
      });
    // ]]></script>
  </head>
  <body>
    <div id="main">
      <h1>Vote Rigger</h1>
<?
if ($error) {
  echo <<<EOF
      <div class="error">$error</div>

EOF;
}

$subtitle = rigger_subtitle();

echo <<<EOF
      <h2>$subtitle</h2>

EOF;

switch (@$_GET['action']) {
  case 'count':
    $set = rigger_count($pdo, $_GET['id']);

    if (!$set) {
      $result = 'No winner could be determined.';
    } elseif (array_key_exists(0, $set)) {
      $result = 'A write-in candidate may have won the election. Please count the ballots manually.';
    } else {
      $winners = rigger_intval($set);

      $result = $pdo->prepare(<<<EOF
SELECT `name`
FROM `candidates`
WHERE `id` IN $winners
EOF
        );

      $result->execute();
      $set = array_map('blacker_encode', $result->fetchAll(PDO::FETCH_COLUMN));

      switch (count($set)) {
        case 0:
          $result = 'There is no winner.';
          break;
        case 1:
          $result = "The winner is $set[0].";
          break;
        case 2:
          $result = "The winners are $set[0] and $set[1].";
          break;
        default:
          $set[count($set) - 1] = 'and ' . $set[count($set) - 1];
          $result = 'The winners are ' . implode(', ', $set) . '.';
      }
    }

    echo <<<EOF
      <p>$result</p>

EOF;

    break;
  case 'edit':
    $title = blacker_encode($title);
    $writeins = $writeins ? ' checked="checked"' : '';

    echo <<<EOF
      <form id="poll" action="?action=edit" method="post">
        <div class="form-control">
          <label for="title">Title</label>
          <div class="input-group">
            <input type="text" id="title" name="title" maxlength="255" value="$title" />
          </div>
        </div>
        <div id="winners-control" class="form-control">
          <label for="winners">Winners</label>
          <div class="input-group">
            <input type="number" id="winners" name="winners" min="1" value="$winners" />
          </div>
        </div>
        <div id="writeins-control" class="form-control">
          <div class="input-group">
            <input type="checkbox" id="writeins" name="writeins" value="writeins"$writeins />
            <label for="writeins">Allow write-ins</label>
          </div>
        </div>
        <div class="form-control">
          <div class="input-group">
            <input type="submit" value="Submit" />
          </div>
        </div>
      </form>

EOF;
    break;
  default:
    echo <<<EOF
      <p class="text-center">
        <a class="btn btn-lg" href="?action=edit">Create Poll</a>
      </p>
      <ul id="polls" class="list-group">

EOF;

    $result = $pdo->prepare(<<<EOF
SELECT `id`,
  `name`,
  `winners`,
  `created`,
  `closed`,
  COUNT(DISTINCT `user`) AS `ballots`
FROM (
  SELECT `elections`.`id` AS `id`,
    `elections`.`name` AS `name`,
    `elections`.`winners` AS `winners`,
    `elections`.`created` AS `created`,
    `elections`.`closed` AS `closed`,
    `votes`.`user`
  FROM `elections`
  LEFT JOIN `candidates` ON `elections`.`id` = `candidates`.`election`
  LEFT JOIN `votes` ON `candidates`.`id` = `votes`.`candidate`
  UNION SELECT `elections`.`id` AS `id`,
    `elections`.`name` AS `name`,
    `elections`.`winners` AS `winners`,
    `elections`.`created` AS `created`,
    `elections`.`closed` AS `closed`,
    `writeins`.`user`
  FROM `elections`
  LEFT JOIN `writeins` ON `elections`.`id` = `writeins`.`election`
)
GROUP BY `id`
EOF
      );

    $result->execute();

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
      $title = blacker_encode($row['name']);
      $closed = rigger_closed($row['closed']);
      $active = $row['closed'] ? '' : ' active';
      $winners = $row['winners'] == 1 ? '1 winner' : $row['winners'] . ' winners';

      echo <<<EOF
        <li id="poll$row[id]" class="list-group-item">
          <div class="close toggle$active"></div>
          <h4>$title ($winners)<small>$row[ballots] cast</small></h4>
          <div class="clearfix pull-right">
            <a class="btn btn-sm" href="?action=count&id=$row[id]">View Results</a>
            <a class="btn btn-sm del">Destroy Poll</a>
          </div>
          <p>
            <small>Created $row[created]</small>
            <small class="closed">$closed</small>
          </p>
          <div class="clearfix"></div>
        </li>

EOF;
    }

    echo <<<EOF
      </ul>

EOF;
}
?>    </div>
<?
print_footer(
  'Copyright &copy; 2017 Will Yu',
  'A service of Blacker House'
);
?>  </body>
</html>
