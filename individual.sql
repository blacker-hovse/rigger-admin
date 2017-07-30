SELECT DISTINCT `users`.`user`,
  `writeins`.`name`,
  `writeins`.`rank`
FROM (
  SELECT `user`
  FROM `candidates`
  LEFT JOIN `votes` ON `candidates`.`id` = `votes`.`candidate`
  WHERE `candidates`.`election` = :id
  UNION SELECT `user`
  FROM `writeins`
  WHERE `writeins`.`election` = :id
) AS `users`
LEFT JOIN `writeins` ON `users`.`user` = `writeins`.`user`
  AND `writeins`.`election` = :id
