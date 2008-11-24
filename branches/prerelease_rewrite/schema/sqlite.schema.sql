
--begin;

CREATE TABLE cs_authentication_table (
	uid integer NOT NULL PRIMARY KEY ON CONFLICT FAIL,
	username text NOT NULL,
	passwd varchar(32),
	is_active boolean DEFAULT true NOT NULL,
	date_created date DEFAULT CURRENT_TIMESTAMP NOT NULL,
	last_login timestamp with time zone
);

CREATE TABLE cs_blog_access_table (
	blog_access_id integer NOT NULL PRIMARY KEY ON CONFLICT FAIL,
	blog_id integer NOT NULL REFERENCES cs_blog_table(blog_id),
	uid integer NOT NULL REFERENCES cs_authentication_table(uid)
);
CREATE TABLE cs_blog_entry_table (
	blog_entry_id integer NOT NULL PRIMARY KEY ON CONFLICT FAIL,
	blog_id integer NOT NULL REFERENCES cs_blog_table(blog_id),
	author_uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	create_date integer NOT NULL,
	filename text NOT NULL,
	post_timestamp integer NOT NULL,
	permalink text NOT NULL,
	title text NOT NULL
);
create table cs_blog_table (
	blog_id integer NOT NULL PRIMARY KEY ON CONFLICT FAIL,
	uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	blog_name text NOT NULL,
	location text NOT NULL,
	is_active boolean NOT NULL DEFAULT true,
	last_post_timestamp integer
);
CREATE TABLE cs_session_table (
	session_id character varying(32) NOT NULL PRIMARY KEY ON CONFLICT FAIL,
	uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	create_date timestamp NOT NULL,
	last_checkin timestamp,
	num_checkins  integer NOT NULL DEFAULT 0,
	ip varchar(15) NOT NULL
);


-- Add entries for users.
--NOTE: password for "test" is "test".
INSERT INTO cs_authentication_table VALUES(101,'test','a0d987ef6826c00ff6e4ac0851ea4744','true','2008-06-01',1212601256);

--commit;
