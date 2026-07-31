INSERT INTO system_settings (`key`, `value`) VALUES ('base_plant_working_hours', '0') ON DUPLICATE KEY UPDATE `value` = `value`;
INSERT INTO system_settings (`key`, `value`) VALUES ('lti_last_reset_date', NOW()) ON DUPLICATE KEY UPDATE `value` = `value`;
