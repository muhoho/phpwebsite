-- @author Matthew McNaney <mcnaney at gmail dot com>
-- @version $Id$

CREATE TABLE calendar_schedule (
  id int NOT NULL default 0,
  key_id int NOT NULL default 0,
  user_id int NOT NULL default 0,
  title varchar(60) NOT NULL,
  summary text,
  public smallint NOT NULL default 0,
  PRIMARY KEY (id)
);

CREATE TABLE calendar_notice (
  id int NOT NULL default 0,
  user_id int NOT NULL default 0,
  email varchar(255) NOT NULL,
  PRIMARY KEY  (id)
);
