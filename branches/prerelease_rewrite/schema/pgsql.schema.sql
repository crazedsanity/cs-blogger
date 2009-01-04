
--begin;

CREATE TABLE cs_authentication_table (
	uid serial NOT NULL PRIMARY KEY,
	username text NOT NULL UNIQUE,
	passwd varchar(32),
	is_active boolean DEFAULT true NOT NULL,
	date_created date DEFAULT CURRENT_TIMESTAMP NOT NULL,
	last_login timestamp with time zone,
	email text
);
CREATE TABLE cs_blog_location_table (
	blog_location_id serial NOT NULL PRIMARY KEY,
	blog_location text NOT NULL UNIQUE
);
create table cs_blog_table (
	blog_id serial NOT NULL PRIMARY KEY,
	uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	blog_name text NOT NULL,
	blog_display_name text NOT NULL,
	--blog_location text NOT NULL,
	blog_location_id integer NOT NULL REFERENCES cs_blog_location_table(blog_location_id),
	is_active boolean NOT NULL DEFAULT true,
	last_post_timestamp timestamp without time zone
);

CREATE TABLE cs_blog_access_table (
	blog_access_id serial NOT NULL PRIMARY KEY,
	blog_id integer NOT NULL REFERENCES cs_blog_table(blog_id),
	uid integer NOT NULL REFERENCES cs_authentication_table(uid)
);
CREATE TABLE cs_blog_entry_table (
	blog_entry_id serial NOT NULL PRIMARY KEY,
	blog_id integer NOT NULL REFERENCES cs_blog_table(blog_id),
	author_uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	create_date timestamp NOT NULL DEFAULT NOW(),
	content text NOT NULL,
	post_timestamp timestamp NOT NULL DEFAULT NOW(),
	permalink text NOT NULL,
	title text NOT NULL
);
CREATE UNIQUE INDEX cs_blog_entry_table_permalink_blog_id_uidx ON cs_blog_entry_table USING btree (permalink,blog_id);


CREATE TABLE cs_session_table (
	session_id character varying(32) NOT NULL PRIMARY KEY,
	uid integer NOT NULL REFERENCES cs_authentication_table(uid),
	create_date timestamp NOT NULL,
	last_checkin timestamp,
	num_checkins  integer NOT NULL DEFAULT 0,
	ip varchar(15) NOT NULL
);

--
-- Table for storing basic permissions.
--
CREATE TABLE cs_blog_permission_table (
	blog_permission_id serial NOT NULL PRIMARY KEY,
	blog_id integer NOT NULL REFERENCES cs_blog_table(blog_id),
	uid integer NOT NULL REFERENCES cs_authentication_table(uid)
);

-- 
-- Table to hold internal-only information, such as version number & such.
-- 
CREATE TABLE cs_blog_internal_data_table (
	internal_data_id serial NOT NULL PRIMARY KEY,
	internal_name text NOT NULL UNIQUE,
	internal_value text NOT NULL
);


-- Add entries for users.
--NOTE: password for "test" is "test".
INSERT INTO cs_authentication_table (username, passwd) VALUES('test','a0d987ef6826c00ff6e4ac0851ea4744');

-- SET NEXT UID TO BE > 100...
select setval('cs_authentication_table_uid_seq',100);
--abort;
