<?php
/**
 * DB migration
 */

/**
 * Tabel where main password save to
 */
const TB_AUTH_SQL = <<< _SQL_
CREATE TABLE "auth" (
  "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  "name" varchar(32) NOT NULL,
  "auth_chars" char(256) NOT NULL
);
_SQL_;

/**
 * Preset default auth string
 */
const AUTH_DEFAULT_SQL = <<< _SQL_
INSERT INTO "auth" (`name`, `auth_chars`) VALUES ('admin', '1pwd_auth_chars');
_SQL_;

/**
 * Table where account and passwords save to
 */
const TB_PASSWORD_SQL = <<< _SQL_
CREATE TABLE "passwords" (
  "id" integer PRIMARY KEY AUTOINCREMENT NOT NULL,
  "account" varchar(256) NOT NULL,
  "passwd" varchar(256) NOT NULL,
  "note" varchar(512)
);
_SQL_;

/**
 * Index for passwords table
 */
const IDX_PASSWORD_SQL = <<< _SQL_
CREATE UNIQUE INDEX "main"."idx_id" ON "passwords" ("id" ASC);
CREATE UNIQUE INDEX "main"."idx_account" ON "passwords" ("account" ASC);
_SQL_;

/**
 * Check and do the migration
 * @param $sqlite
 */
function check_and_migrate(&$sqlite)
{
    $count = $sqlite->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND tbl_name = 'auth'");
    if (!$count) {
        $sqlite->query(TB_AUTH_SQL);
        $sqlite->query(AUTH_DEFAULT_SQL);
    }
    $count = $sqlite->querySingle("SELECT COUNT(*) FROM sqlite_master WHERE type='table' AND tbl_name = 'passwords'");
    if (!$count) {
        $sqlite->query(TB_PASSWORD_SQL);
        $sqlite->query(IDX_PASSWORD_SQL);
    }
}
