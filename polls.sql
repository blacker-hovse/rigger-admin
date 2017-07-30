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
