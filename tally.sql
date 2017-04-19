WITH `users` AS (
  SELECT DISTINCT `user`
  FROM (
    SELECT `user`
    FROM `candidates`
    LEFT JOIN `votes` ON `candidates`.`id` = `votes`.`candidate`
    WHERE `candidates`.`election` = 1
    UNION SELECT `user`
    FROM `writeins`
    WHERE `writeins`.`election` = 1
  )
)
SELECT `c1`.`id` AS `c1`,
  `c2`.`id` AS `c2`,
  SUM(
    `c1`.`rank` < `c2`.`rank`
      OR `c1`.`rank` IS NOT NULL
      AND `c2`.`rank` IS NULL
  ) AS `a`,
  SUM(
    `c1`.`rank` > `c2`.`rank`
      OR `c1`.`rank` IS NULL
      AND `c2`.`rank` IS NOT NULL
  ) AS `b`
FROM (
  SELECT `candidates`.`id`,
    `users`.`user`,
    `votes`.`rank`
  FROM `candidates`
  CROSS JOIN `users`
  LEFT JOIN `votes` ON `users`.`user` = `votes`.`user`
    AND `candidates`.`id` = `votes`.`candidate`
  WHERE `candidates`.`election` = :id
  UNION SELECT 0,
    `users`.`user`,
    `writeins`.`rank`
  FROM `users`
  LEFT JOIN `writeins` ON `users`.`user` = `writeins`.`user`
) AS `c1`
INNER JOIN (
  SELECT `candidates`.`id`,
    `users`.`user`,
    `votes`.`rank`
  FROM `candidates`
  CROSS JOIN `users`
  LEFT JOIN `votes` ON `users`.`user` = `votes`.`user`
    AND `candidates`.`id` = `votes`.`candidate`
  WHERE `candidates`.`election` = :id
  UNION SELECT 0,
    `users`.`user`,
    `writeins`.`rank`
  FROM `users`
  LEFT JOIN `writeins` ON `users`.`user` = `writeins`.`user`
) AS `c2` ON `c1`.`user` = `c2`.`user`
WHERE `c1`.`id` <> `c2`.`id`
GROUP BY `c1`.`id`,
  `c2`.`id`
ORDER BY `a` DESC,
  `b`
