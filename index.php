<?
include(__DIR__ . '/../../lib/include.php');
include(__DIR__ . '/../../rigger/include.php');

$pdo = rigger_init('../../rigger/rigger.db');

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
    $content = 'Invalid action ' . htmlentities($_POST['action'], NULL, 'UTF-8') . '.';
  }

  die();
} elseif (array_key_exists('title', $_POST)) {
  $candidates = array_filter($_POST['candidates']);

  if ($_POST['title'] and $candidates) {
    $result = $pdo->prepare(<<<EOF
INSERT INTO `elections` (
  `name`,
  `writeins`,
  `created`
)
VALUES (
  :name,
  :writeins,
  DATETIME('now')
)
EOF
      );

    $result->execute(array(
      ':name' => $_POST['title'],
      ':writeins' => (bool) @$_POST['writeins']
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

      function addCandidate() {
        $('<div class="form-control"><label for="candidate' + e++ + '">Candidate ' + e + '</label><div class="input-group input-group-left"><input type="text" id="candidate' + (e - 1) + '" name="candidates[]" maxlength="255" /></div><div class="input-group input-group-right"><a class="btn btn-lg del">&times;</a></div></div>').children('.input-group-right').append(f).end().insertBefore('#writeins-control');
      }

      $(function() {
        f.click(addCandidate);
        addCandidate();
        addCandidate();

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
$subtitle = rigger_subtitle();

echo <<<EOF
      <h2>$subtitle</h2>

EOF;

switch (@$_GET['action']) {
  case 'count':
    $graph = array();
    $result = $pdo->prepare(file_get_contents('tally.sql'));

    $result->execute(array(
      ':id' => $_GET['id']
    ));

    while ($row = $result->fetch(PDO::FETCH_ASSOC)) {
      $c1 = (int) $row['c1'];
      $c2 = (int) $row['c2'];

      if (!array_key_exists($c1, $graph)) {
        $graph[$c1] = array();
      }

      if (!array_key_exists($c2, $graph)) {
        $graph[$c2] = array();
      }

      if (!rigger_dfs($graph, $c2, $c1, array())) {
        $graph[$c1][$c2] = true;
      }
    }

    $candidates = array_keys($graph);

    foreach ($graph as $c1 => $c2) {
      $candidates = array_diff($candidates, array_keys($c2));
    }

    break;
  case 'edit':
    echo <<<EOF
      <form id="poll" action="?action=edit" method="post">
        <div class="form-control">
          <label for="title">Title</label>
          <div class="input-group">
            <input type="text" id="title" name="title" maxlength="255" />
          </div>
        </div>
        <div id="writeins-control" class="form-control">
          <div class="input-group">
            <input type="checkbox" id="writeins" name="writeins" value="writeins" checked="checked" />
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
  `created`,
  `closed`,
  COUNT(DISTINCT `user`) AS `ballots`
FROM (
  SELECT `elections`.`id` AS `id`,
    `elections`.`name` AS `name`,
    `elections`.`created` AS `created`,
    `elections`.`closed` AS `closed`,
    `votes`.`user`
  FROM `elections`
  LEFT JOIN `candidates` ON `elections`.`id` = `candidates`.`election`
  LEFT JOIN `votes` ON `candidates`.`id` = `votes`.`candidate`
  UNION SELECT `elections`.`id` AS `id`,
    `elections`.`name` AS `name`,
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
      $title = htmlentities($row['name'], NULL, 'UTF-8');
      $closed = rigger_closed($row['closed']);
      $active = $row['closed'] ? '' : ' active';

      echo <<<EOF
        <li id="poll$row[id]" class="list-group-item">
          <div class="close toggle$active"></div>
          <h4>$title <small>$row[ballots] cast</small></h4>
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
